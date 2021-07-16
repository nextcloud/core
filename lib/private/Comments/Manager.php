<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Comments;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\CommentsEvent;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsEventHandler;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IInitialStateService;
use OCP\Util;
use Psr\Log\LoggerInterface;

class Manager implements ICommentsManager {

	/** @var  IDBConnection */
	protected $dbConn;

	/** @var  LoggerInterface */
	protected $logger;

	/** @var IConfig */
	protected $config;

	/** @var ITimeFactory */
	protected $timeFactory;

	/** @var IInitialStateService */
	protected $initialStateService;

	/** @var IComment[] */
	protected $commentsCache = [];

	/** @var  \Closure[] */
	protected $eventHandlerClosures = [];

	/** @var  ICommentsEventHandler[] */
	protected $eventHandlers = [];

	/** @var \Closure[] */
	protected $displayNameResolvers = [];

	public function __construct(IDBConnection $dbConn,
								LoggerInterface $logger,
								IConfig $config,
								ITimeFactory $timeFactory,
								IInitialStateService $initialStateService) {
		$this->dbConn = $dbConn;
		$this->logger = $logger;
		$this->config = $config;
		$this->timeFactory = $timeFactory;
		$this->initialStateService = $initialStateService;
	}

	/**
	 * converts data base data into PHP native, proper types as defined by
	 * IComment interface.
	 *
	 * @param array $data
	 * @return array
	 */
	protected function normalizeDatabaseData(array $data) {
		$data['id'] = (string)$data['id'];
		$data['parent_id'] = (string)$data['parent_id'];
		$data['topmost_parent_id'] = (string)$data['topmost_parent_id'];
		$data['creation_timestamp'] = new \DateTime($data['creation_timestamp']);
		if (!is_null($data['latest_child_timestamp'])) {
			$data['latest_child_timestamp'] = new \DateTime($data['latest_child_timestamp']);
		}
		$data['children_count'] = (int)$data['children_count'];
		$data['reference_id'] = $data['reference_id'] ?? null;
		return $data;
	}


	/**
	 * @param array $data
	 * @return IComment
	 */
	public function getCommentFromData(array $data): IComment {
		return new Comment($this->normalizeDatabaseData($data));
	}

	/**
	 * prepares a comment for an insert or update operation after making sure
	 * all necessary fields have a value assigned.
	 *
	 * @param IComment $comment
	 * @return IComment returns the same updated IComment instance as provided
	 *                  by parameter for convenience
	 * @throws \UnexpectedValueException
	 */
	protected function prepareCommentForDatabaseWrite(IComment $comment) {
		if (!$comment->getActorType()
			|| $comment->getActorId() === ''
			|| !$comment->getObjectType()
			|| $comment->getObjectId() === ''
			|| !$comment->getVerb()
		) {
			throw new \UnexpectedValueException('Actor, Object and Verb information must be provided for saving');
		}

		if ($comment->getId() === '') {
			$comment->setChildrenCount(0);
			$comment->setLatestChildDateTime(new \DateTime('0000-00-00 00:00:00', new \DateTimeZone('UTC')));
			$comment->setLatestChildDateTime(null);
		}

		if (is_null($comment->getCreationDateTime())) {
			$comment->setCreationDateTime(new \DateTime());
		}

		if ($comment->getParentId() !== '0') {
			$comment->setTopmostParentId($this->determineTopmostParentId($comment->getParentId()));
		} else {
			$comment->setTopmostParentId('0');
		}

		$this->cache($comment);

		return $comment;
	}

	/**
	 * returns the topmost parent id of a given comment identified by ID
	 *
	 * @param string $id
	 * @return string
	 * @throws NotFoundException
	 */
	protected function determineTopmostParentId($id) {
		$comment = $this->get($id);
		if ($comment->getParentId() === '0') {
			return $comment->getId();
		}

		return $this->determineTopmostParentId($comment->getParentId());
	}

	/**
	 * updates child information of a comment
	 *
	 * @param string $id
	 * @param \DateTime $cDateTime the date time of the most recent child
	 * @throws NotFoundException
	 */
	protected function updateChildrenInformation($id, \DateTime $cDateTime) {
		$qb = $this->dbConn->getQueryBuilder();
		$query = $qb->select($qb->func()->count('id'))
			->from('comments')
			->where($qb->expr()->eq('parent_id', $qb->createParameter('id')))
			->setParameter('id', $id);

		$resultStatement = $query->execute();
		$data = $resultStatement->fetch(\PDO::FETCH_NUM);
		$resultStatement->closeCursor();
		$children = (int)$data[0];

		$comment = $this->get($id);
		$comment->setChildrenCount($children);
		$comment->setLatestChildDateTime($cDateTime);
		$this->save($comment);
	}

	/**
	 * Tests whether actor or object type and id parameters are acceptable.
	 * Throws exception if not.
	 *
	 * @param string $role
	 * @param string $type
	 * @param string $id
	 * @throws \InvalidArgumentException
	 */
	protected function checkRoleParameters($role, $type, $id) {
		if (
			!is_string($type) || empty($type)
			|| !is_string($id) || empty($id)
		) {
			throw new \InvalidArgumentException($role . ' parameters must be string and not empty');
		}
	}

	/**
	 * run-time caches a comment
	 *
	 * @param IComment $comment
	 */
	protected function cache(IComment $comment) {
		$id = $comment->getId();
		if (empty($id)) {
			return;
		}
		$this->commentsCache[(string)$id] = $comment;
	}

	/**
	 * removes an entry from the comments run time cache
	 *
	 * @param mixed $id the comment's id
	 */
	protected function uncache($id) {
		$id = (string)$id;
		if (isset($this->commentsCache[$id])) {
			unset($this->commentsCache[$id]);
		}
	}

	/**
	 * returns a comment instance
	 *
	 * @param string $id the ID of the comment
	 * @return IComment
	 * @throws NotFoundException
	 * @throws \InvalidArgumentException
	 * @since 9.0.0
	 */
	public function get($id) {
		if ((int)$id === 0) {
			throw new \InvalidArgumentException('IDs must be translatable to a number in this implementation.');
		}

		if (isset($this->commentsCache[$id])) {
			return $this->commentsCache[$id];
		}

		$qb = $this->dbConn->getQueryBuilder();
		$resultStatement = $qb->select('*')
			->from('comments')
			->where($qb->expr()->eq('id', $qb->createParameter('id')))
			->setParameter('id', $id, IQueryBuilder::PARAM_INT)
			->execute();

		$data = $resultStatement->fetch();
		$resultStatement->closeCursor();
		if (!$data) {
			throw new NotFoundException();
		}


		$comment = $this->getCommentFromData($data);
		$this->cache($comment);
		return $comment;
	}

	/**
	 * returns the comment specified by the id and all it's child comments.
	 * At this point of time, we do only support one level depth.
	 *
	 * @param string $id
	 * @param int $limit max number of entries to return, 0 returns all
	 * @param int $offset the start entry
	 * @return array
	 * @since 9.0.0
	 *
	 * The return array looks like this
	 * [
	 *   'comment' => IComment, // root comment
	 *   'replies' =>
	 *   [
	 *     0 =>
	 *     [
	 *       'comment' => IComment,
	 *       'replies' => []
	 *     ]
	 *     1 =>
	 *     [
	 *       'comment' => IComment,
	 *       'replies'=> []
	 *     ],
	 *     …
	 *   ]
	 * ]
	 */
	public function getTree($id, $limit = 0, $offset = 0) {
		$tree = [];
		$tree['comment'] = $this->get($id);
		$tree['replies'] = [];

		$qb = $this->dbConn->getQueryBuilder();
		$query = $qb->select('*')
			->from('comments')
			->where($qb->expr()->eq('topmost_parent_id', $qb->createParameter('id')))
			->orderBy('creation_timestamp', 'DESC')
			->setParameter('id', $id);

		if ($limit > 0) {
			$query->setMaxResults($limit);
		}
		if ($offset > 0) {
			$query->setFirstResult($offset);
		}

		$resultStatement = $query->execute();
		while ($data = $resultStatement->fetch()) {
			$comment = $this->getCommentFromData($data);
			$this->cache($comment);
			$tree['replies'][] = [
				'comment' => $comment,
				'replies' => []
			];
		}
		$resultStatement->closeCursor();

		return $tree;
	}

	/**
	 * returns comments for a specific object (e.g. a file).
	 *
	 * The sort order is always newest to oldest.
	 *
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param int $limit optional, number of maximum comments to be returned. if
	 * not specified, all comments are returned.
	 * @param int $offset optional, starting point
	 * @param \DateTime $notOlderThan optional, timestamp of the oldest comments
	 * that may be returned
	 * @return IComment[]
	 * @since 9.0.0
	 */
	public function getForObject(
		$objectType,
		$objectId,
		$limit = 0,
		$offset = 0,
		\DateTime $notOlderThan = null
	) {
		$comments = [];

		$qb = $this->dbConn->getQueryBuilder();
		$query = $qb->select('*')
			->from('comments')
			->where($qb->expr()->eq('object_type', $qb->createParameter('type')))
			->andWhere($qb->expr()->eq('object_id', $qb->createParameter('id')))
			->orderBy('creation_timestamp', 'DESC')
			->setParameter('type', $objectType)
			->setParameter('id', $objectId);

		if ($limit > 0) {
			$query->setMaxResults($limit);
		}
		if ($offset > 0) {
			$query->setFirstResult($offset);
		}
		if (!is_null($notOlderThan)) {
			$query
				->andWhere($qb->expr()->gt('creation_timestamp', $qb->createParameter('notOlderThan')))
				->setParameter('notOlderThan', $notOlderThan, 'datetime');
		}

		$resultStatement = $query->execute();
		while ($data = $resultStatement->fetch()) {
			$comment = $this->getCommentFromData($data);
			$this->cache($comment);
			$comments[] = $comment;
		}
		$resultStatement->closeCursor();

		return $comments;
	}

	/**
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param int $lastKnownCommentId the last known comment (will be used as offset)
	 * @param string $sortDirection direction of the comments (`asc` or `desc`)
	 * @param int $limit optional, number of maximum comments to be returned. if
	 * set to 0, all comments are returned.
	 * @param bool $includeLastKnown
	 * @return IComment[]
	 * @return array
	 */
	public function getForObjectSince(
		string $objectType,
		string $objectId,
		int $lastKnownCommentId,
		string $sortDirection = 'asc',
		int $limit = 30,
		bool $includeLastKnown = false
	): array {
		$comments = [];

		$query = $this->dbConn->getQueryBuilder();
		$query->select('*')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->orderBy('creation_timestamp', $sortDirection === 'desc' ? 'DESC' : 'ASC')
			->addOrderBy('id', $sortDirection === 'desc' ? 'DESC' : 'ASC');

		if ($limit > 0) {
			$query->setMaxResults($limit);
		}

		$lastKnownComment = $lastKnownCommentId > 0 ? $this->getLastKnownComment(
			$objectType,
			$objectId,
			$lastKnownCommentId
		) : null;
		if ($lastKnownComment instanceof IComment) {
			$lastKnownCommentDateTime = $lastKnownComment->getCreationDateTime();
			if ($sortDirection === 'desc') {
				if ($includeLastKnown) {
					$idComparison = $query->expr()->lte('id', $query->createNamedParameter($lastKnownCommentId));
				} else {
					$idComparison = $query->expr()->lt('id', $query->createNamedParameter($lastKnownCommentId));
				}
				$query->andWhere(
					$query->expr()->orX(
						$query->expr()->lt(
							'creation_timestamp',
							$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATE),
							IQueryBuilder::PARAM_DATE
						),
						$query->expr()->andX(
							$query->expr()->eq(
								'creation_timestamp',
								$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATE),
								IQueryBuilder::PARAM_DATE
							),
							$idComparison
						)
					)
				);
			} else {
				if ($includeLastKnown) {
					$idComparison = $query->expr()->gte('id', $query->createNamedParameter($lastKnownCommentId));
				} else {
					$idComparison = $query->expr()->gt('id', $query->createNamedParameter($lastKnownCommentId));
				}
				$query->andWhere(
					$query->expr()->orX(
						$query->expr()->gt(
							'creation_timestamp',
							$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATE),
							IQueryBuilder::PARAM_DATE
						),
						$query->expr()->andX(
							$query->expr()->eq(
								'creation_timestamp',
								$query->createNamedParameter($lastKnownCommentDateTime, IQueryBuilder::PARAM_DATE),
								IQueryBuilder::PARAM_DATE
							),
							$idComparison
						)
					)
				);
			}
		}

		$resultStatement = $query->execute();
		while ($data = $resultStatement->fetch()) {
			$comment = $this->getCommentFromData($data);
			$this->cache($comment);
			$comments[] = $comment;
		}
		$resultStatement->closeCursor();

		return $comments;
	}

	/**
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param int $id the comment to look for
	 * @return Comment|null
	 */
	protected function getLastKnownComment(string $objectType,
										   string $objectId,
										   int $id) {
		$query = $this->dbConn->getQueryBuilder();
		$query->select('*')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row) {
			$comment = $this->getCommentFromData($row);
			$this->cache($comment);
			return $comment;
		}

		return null;
	}

	/**
	 * Search for comments with a given content
	 *
	 * @param string $search content to search for
	 * @param string $objectType Limit the search by object type
	 * @param string $objectId Limit the search by object id
	 * @param string $verb Limit the verb of the comment
	 * @param int $offset
	 * @param int $limit
	 * @return IComment[]
	 */
	public function search(string $search, string $objectType, string $objectId, string $verb, int $offset, int $limit = 50): array {
		$objectIds = [];
		if ($objectId) {
			$objectIds[] = $objectIds;
		}
		return $this->searchForObjects($search, $objectType, $objectIds, $verb, $offset, $limit);
	}

	/**
	 * Search for comments on one or more objects with a given content
	 *
	 * @param string $search content to search for
	 * @param string $objectType Limit the search by object type
	 * @param array $objectIds Limit the search by object ids
	 * @param string $verb Limit the verb of the comment
	 * @param int $offset
	 * @param int $limit
	 * @return IComment[]
	 */
	public function searchForObjects(string $search, string $objectType, array $objectIds, string $verb, int $offset, int $limit = 50): array {
		$query = $this->dbConn->getQueryBuilder();

		$query->select('*')
			->from('comments')
			->where($query->expr()->iLike('message', $query->createNamedParameter(
				'%' . $this->dbConn->escapeLikeParameter($search). '%'
			)))
			->orderBy('creation_timestamp', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults($limit);

		if ($objectType !== '') {
			$query->andWhere($query->expr()->eq('object_type', $query->createNamedParameter($objectType)));
		}
		if (!empty($objectIds)) {
			$query->andWhere($query->expr()->in('object_id', $query->createNamedParameter($objectIds, IQueryBuilder::PARAM_STR_ARRAY)));
		}
		if ($verb !== '') {
			$query->andWhere($query->expr()->eq('verb', $query->createNamedParameter($verb)));
		}
		if ($offset !== 0) {
			$query->setFirstResult($offset);
		}

		$comments = [];
		$result = $query->execute();
		while ($data = $result->fetch()) {
			$comment = $this->getCommentFromData($data);
			$this->cache($comment);
			$comments[] = $comment;
		}
		$result->closeCursor();

		return $comments;
	}

	/**
	 * @param $objectType string the object type, e.g. 'files'
	 * @param $objectId string the id of the object
	 * @param \DateTime $notOlderThan optional, timestamp of the oldest comments
	 * that may be returned
	 * @param string $verb Limit the verb of the comment - Added in 14.0.0
	 * @return Int
	 * @since 9.0.0
	 */
	public function getNumberOfCommentsForObject($objectType, $objectId, \DateTime $notOlderThan = null, $verb = '') {
		$qb = $this->dbConn->getQueryBuilder();
		$query = $qb->select($qb->func()->count('id'))
			->from('comments')
			->where($qb->expr()->eq('object_type', $qb->createParameter('type')))
			->andWhere($qb->expr()->eq('object_id', $qb->createParameter('id')))
			->setParameter('type', $objectType)
			->setParameter('id', $objectId);

		if (!is_null($notOlderThan)) {
			$query
				->andWhere($qb->expr()->gt('creation_timestamp', $qb->createParameter('notOlderThan')))
				->setParameter('notOlderThan', $notOlderThan, 'datetime');
		}

		if ($verb !== '') {
			$query->andWhere($qb->expr()->eq('verb', $qb->createNamedParameter($verb)));
		}

		$resultStatement = $query->execute();
		$data = $resultStatement->fetch(\PDO::FETCH_NUM);
		$resultStatement->closeCursor();
		return (int)$data[0];
	}

	/**
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string[] $objectIds the id of the object
	 * @param IUser $user
	 * @param string $verb Limit the verb of the comment - Added in 14.0.0
	 * @return array Map with object id => # of unread comments
	 * @psalm-return array<string, int>
	 * @since 21.0.0
	 */
	public function getNumberOfUnreadCommentsForObjects(string $objectType, array $objectIds, IUser $user, $verb = ''): array {
		$unreadComments = [];
		$query = $this->dbConn->getQueryBuilder();
		$query->select('c.object_id', $query->func()->count('c.id', 'num_comments'))
			->from('comments', 'c')
			->leftJoin('c', 'comments_read_markers', 'm', $query->expr()->andX(
				$query->expr()->eq('m.user_id', $query->createNamedParameter($user->getUID())),
				$query->expr()->eq('c.object_type', 'm.object_type'),
				$query->expr()->eq('c.object_id', 'm.object_id')
			))
			->where($query->expr()->eq('c.object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->in('c.object_id', $query->createParameter('ids')))
			->andWhere($query->expr()->orX(
				$query->expr()->gt('c.creation_timestamp', 'm.marker_datetime'),
				$query->expr()->isNull('m.marker_datetime')
			))
			->groupBy('c.object_id');

		if ($verb !== '') {
			$query->andWhere($query->expr()->eq('c.verb', $query->createNamedParameter($verb)));
		}

		$unreadComments = array_fill_keys($objectIds, 0);
		foreach (array_chunk($objectIds, 1000) as $chunk) {
			$query->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);

			$result = $query->executeQuery();
			while ($row = $result->fetch()) {
				$unreadComments[$row['object_id']] = (int) $row['num_comments'];
			}
			$result->closeCursor();
		}

		return $unreadComments;
	}

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param int $lastRead
	 * @param string $verb
	 * @return int
	 * @since 21.0.0
	 */
	public function getNumberOfCommentsForObjectSinceComment(string $objectType, string $objectId, int $lastRead, string $verb = ''): int {
		$query = $this->dbConn->getQueryBuilder();
		$query->select($query->func()->count('id', 'num_messages'))
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->gt('id', $query->createNamedParameter($lastRead)));

		if ($verb !== '') {
			$query->andWhere($query->expr()->eq('verb', $query->createNamedParameter($verb)));
		}

		$result = $query->execute();
		$data = $result->fetch();
		$result->closeCursor();

		return (int) ($data['num_messages'] ?? 0);
	}

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param \DateTime $beforeDate
	 * @param string $verb
	 * @return int
	 * @since 21.0.0
	 */
	public function getLastCommentBeforeDate(string $objectType, string $objectId, \DateTime $beforeDate, string $verb = ''): int {
		$query = $this->dbConn->getQueryBuilder();
		$query->select('id')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->lt('creation_timestamp', $query->createNamedParameter($beforeDate, IQueryBuilder::PARAM_DATE)))
			->orderBy('creation_timestamp', 'desc');

		if ($verb !== '') {
			$query->andWhere($query->expr()->eq('verb', $query->createNamedParameter($verb)));
		}

		$result = $query->execute();
		$data = $result->fetch();
		$result->closeCursor();

		return (int) ($data['id'] ?? 0);
	}

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param string $verb
	 * @param string $actorType
	 * @param string[] $actors
	 * @return \DateTime[] Map of "string actor" => "\DateTime most recent comment date"
	 * @psalm-return array<string, \DateTime>
	 * @since 21.0.0
	 */
	public function getLastCommentDateByActor(
		string $objectType,
		string $objectId,
		string $verb,
		string $actorType,
		array $actors
	): array {
		$lastComments = [];

		$query = $this->dbConn->getQueryBuilder();
		$query->select('actor_id')
			->selectAlias($query->createFunction('MAX(' . $query->getColumnName('creation_timestamp') . ')'), 'last_comment')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->eq('verb', $query->createNamedParameter($verb)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->in('actor_id', $query->createNamedParameter($actors, IQueryBuilder::PARAM_STR_ARRAY)))
			->groupBy('actor_id');

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$lastComments[$row['actor_id']] = $this->timeFactory->getDateTime($row['last_comment']);
		}
		$result->closeCursor();

		return $lastComments;
	}

	/**
	 * Get the number of unread comments for all files in a folder
	 *
	 * @param int $folderId
	 * @param IUser $user
	 * @return array [$fileId => $unreadCount]
	 */
	public function getNumberOfUnreadCommentsForFolder($folderId, IUser $user) {
		$qb = $this->dbConn->getQueryBuilder();

		$query = $qb->select('f.fileid')
			->addSelect($qb->func()->count('c.id', 'num_ids'))
			->from('filecache', 'f')
			->leftJoin('f', 'comments', 'c', $qb->expr()->andX(
				$qb->expr()->eq('f.fileid', $qb->expr()->castColumn('c.object_id', IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('c.object_type', $qb->createNamedParameter('files'))
			))
			->leftJoin('c', 'comments_read_markers', 'm', $qb->expr()->andX(
				$qb->expr()->eq('c.object_id', 'm.object_id'),
				$qb->expr()->eq('m.object_type', $qb->createNamedParameter('files'))
			))
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('f.parent', $qb->createNamedParameter($folderId)),
					$qb->expr()->orX(
						$qb->expr()->eq('c.object_type', $qb->createNamedParameter('files')),
						$qb->expr()->isNull('c.object_type')
					),
					$qb->expr()->orX(
						$qb->expr()->eq('m.object_type', $qb->createNamedParameter('files')),
						$qb->expr()->isNull('m.object_type')
					),
					$qb->expr()->orX(
						$qb->expr()->eq('m.user_id', $qb->createNamedParameter($user->getUID())),
						$qb->expr()->isNull('m.user_id')
					),
					$qb->expr()->orX(
						$qb->expr()->gt('c.creation_timestamp', 'm.marker_datetime'),
						$qb->expr()->isNull('m.marker_datetime')
					)
				)
			)->groupBy('f.fileid');

		$resultStatement = $query->execute();

		$results = [];
		while ($row = $resultStatement->fetch()) {
			$results[$row['fileid']] = (int) $row['num_ids'];
		}
		$resultStatement->closeCursor();
		return $results;
	}

	/**
	 * creates a new comment and returns it. At this point of time, it is not
	 * saved in the used data storage. Use save() after setting other fields
	 * of the comment (e.g. message or verb).
	 *
	 * @param string $actorType the actor type (e.g. 'users')
	 * @param string $actorId a user id
	 * @param string $objectType the object type the comment is attached to
	 * @param string $objectId the object id the comment is attached to
	 * @return IComment
	 * @since 9.0.0
	 */
	public function create($actorType, $actorId, $objectType, $objectId) {
		$comment = new Comment();
		$comment
			->setActor($actorType, $actorId)
			->setObject($objectType, $objectId);
		return $comment;
	}

	/**
	 * permanently deletes the comment specified by the ID
	 *
	 * When the comment has child comments, their parent ID will be changed to
	 * the parent ID of the item that is to be deleted.
	 *
	 * @param string $id
	 * @return bool
	 * @throws \InvalidArgumentException
	 * @since 9.0.0
	 */
	public function delete($id) {
		if (!is_string($id)) {
			throw new \InvalidArgumentException('Parameter must be string');
		}

		try {
			$comment = $this->get($id);
		} catch (\Exception $e) {
			// Ignore exceptions, we just don't fire a hook then
			$comment = null;
		}

		$qb = $this->dbConn->getQueryBuilder();
		$query = $qb->delete('comments')
			->where($qb->expr()->eq('id', $qb->createParameter('id')))
			->setParameter('id', $id);

		try {
			$affectedRows = $query->execute();
			$this->uncache($id);
		} catch (DriverException $e) {
			$this->logger->error($e->getMessage(), [
				'exception' => $e,
				'app' => 'core_comments',
			]);
			return false;
		}

		if ($affectedRows > 0 && $comment instanceof IComment) {
			$this->sendEvent(CommentsEvent::EVENT_DELETE, $comment);
		}

		return ($affectedRows > 0);
	}

	/**
	 * saves the comment permanently
	 *
	 * if the supplied comment has an empty ID, a new entry comment will be
	 * saved and the instance updated with the new ID.
	 *
	 * Otherwise, an existing comment will be updated.
	 *
	 * Throws NotFoundException when a comment that is to be updated does not
	 * exist anymore at this point of time.
	 *
	 * @param IComment $comment
	 * @return bool
	 * @throws NotFoundException
	 * @since 9.0.0
	 */
	public function save(IComment $comment) {
		if ($this->prepareCommentForDatabaseWrite($comment)->getId() === '') {
			$result = $this->insert($comment);
		} else {
			$result = $this->update($comment);
		}

		if ($result && !!$comment->getParentId()) {
			$this->updateChildrenInformation(
				$comment->getParentId(),
				$comment->getCreationDateTime()
			);
			$this->cache($comment);
		}

		return $result;
	}

	/**
	 * inserts the provided comment in the database
	 *
	 * @param IComment $comment
	 * @return bool
	 */
	protected function insert(IComment $comment): bool {
		try {
			$result = $this->insertQuery($comment, true);
		} catch (InvalidFieldNameException $e) {
			// The reference id field was only added in Nextcloud 19.
			// In order to not cause too long waiting times on the update,
			// it was decided to only add it lazy, as it is also not a critical
			// feature, but only helps to have a better experience while commenting.
			// So in case the reference_id field is missing,
			// we simply save the comment without that field.
			$result = $this->insertQuery($comment, false);
		}

		return $result;
	}

	protected function insertQuery(IComment $comment, bool $tryWritingReferenceId): bool {
		$qb = $this->dbConn->getQueryBuilder();

		$values = [
			'parent_id' => $qb->createNamedParameter($comment->getParentId()),
			'topmost_parent_id' => $qb->createNamedParameter($comment->getTopmostParentId()),
			'children_count' => $qb->createNamedParameter($comment->getChildrenCount()),
			'actor_type' => $qb->createNamedParameter($comment->getActorType()),
			'actor_id' => $qb->createNamedParameter($comment->getActorId()),
			'message' => $qb->createNamedParameter($comment->getMessage()),
			'verb' => $qb->createNamedParameter($comment->getVerb()),
			'creation_timestamp' => $qb->createNamedParameter($comment->getCreationDateTime(), 'datetime'),
			'latest_child_timestamp' => $qb->createNamedParameter($comment->getLatestChildDateTime(), 'datetime'),
			'object_type' => $qb->createNamedParameter($comment->getObjectType()),
			'object_id' => $qb->createNamedParameter($comment->getObjectId()),
		];

		if ($tryWritingReferenceId) {
			$values['reference_id'] = $qb->createNamedParameter($comment->getReferenceId());
		}

		$affectedRows = $qb->insert('comments')
			->values($values)
			->execute();

		if ($affectedRows > 0) {
			$comment->setId((string)$qb->getLastInsertId());
			$this->sendEvent(CommentsEvent::EVENT_ADD, $comment);
		}

		return $affectedRows > 0;
	}

	/**
	 * updates a Comment data row
	 *
	 * @param IComment $comment
	 * @return bool
	 * @throws NotFoundException
	 */
	protected function update(IComment $comment) {
		// for properly working preUpdate Events we need the old comments as is
		// in the DB and overcome caching. Also avoid that outdated information stays.
		$this->uncache($comment->getId());
		$this->sendEvent(CommentsEvent::EVENT_PRE_UPDATE, $this->get($comment->getId()));
		$this->uncache($comment->getId());

		try {
			$result = $this->updateQuery($comment, true);
		} catch (InvalidFieldNameException $e) {
			// See function insert() for explanation
			$result = $this->updateQuery($comment, false);
		}

		$this->sendEvent(CommentsEvent::EVENT_UPDATE, $comment);

		return $result;
	}

	protected function updateQuery(IComment $comment, bool $tryWritingReferenceId): bool {
		$qb = $this->dbConn->getQueryBuilder();
		$qb
			->update('comments')
			->set('parent_id', $qb->createNamedParameter($comment->getParentId()))
			->set('topmost_parent_id', $qb->createNamedParameter($comment->getTopmostParentId()))
			->set('children_count', $qb->createNamedParameter($comment->getChildrenCount()))
			->set('actor_type', $qb->createNamedParameter($comment->getActorType()))
			->set('actor_id', $qb->createNamedParameter($comment->getActorId()))
			->set('message', $qb->createNamedParameter($comment->getMessage()))
			->set('verb', $qb->createNamedParameter($comment->getVerb()))
			->set('creation_timestamp', $qb->createNamedParameter($comment->getCreationDateTime(), 'datetime'))
			->set('latest_child_timestamp', $qb->createNamedParameter($comment->getLatestChildDateTime(), 'datetime'))
			->set('object_type', $qb->createNamedParameter($comment->getObjectType()))
			->set('object_id', $qb->createNamedParameter($comment->getObjectId()));

		if ($tryWritingReferenceId) {
			$qb->set('reference_id', $qb->createNamedParameter($comment->getReferenceId()));
		}

		$affectedRows = $qb->where($qb->expr()->eq('id', $qb->createNamedParameter($comment->getId())))
			->execute();

		if ($affectedRows === 0) {
			throw new NotFoundException('Comment to update does ceased to exist');
		}

		return $affectedRows > 0;
	}

	/**
	 * removes references to specific actor (e.g. on user delete) of a comment.
	 * The comment itself must not get lost/deleted.
	 *
	 * @param string $actorType the actor type (e.g. 'users')
	 * @param string $actorId a user id
	 * @return boolean
	 * @since 9.0.0
	 */
	public function deleteReferencesOfActor($actorType, $actorId) {
		$this->checkRoleParameters('Actor', $actorType, $actorId);

		$qb = $this->dbConn->getQueryBuilder();
		$affectedRows = $qb
			->update('comments')
			->set('actor_type', $qb->createNamedParameter(ICommentsManager::DELETED_USER))
			->set('actor_id', $qb->createNamedParameter(ICommentsManager::DELETED_USER))
			->where($qb->expr()->eq('actor_type', $qb->createParameter('type')))
			->andWhere($qb->expr()->eq('actor_id', $qb->createParameter('id')))
			->setParameter('type', $actorType)
			->setParameter('id', $actorId)
			->execute();

		$this->commentsCache = [];

		return is_int($affectedRows);
	}

	/**
	 * deletes all comments made of a specific object (e.g. on file delete)
	 *
	 * @param string $objectType the object type (e.g. 'files')
	 * @param string $objectId e.g. the file id
	 * @return boolean
	 * @since 9.0.0
	 */
	public function deleteCommentsAtObject($objectType, $objectId) {
		$this->checkRoleParameters('Object', $objectType, $objectId);

		$qb = $this->dbConn->getQueryBuilder();
		$affectedRows = $qb
			->delete('comments')
			->where($qb->expr()->eq('object_type', $qb->createParameter('type')))
			->andWhere($qb->expr()->eq('object_id', $qb->createParameter('id')))
			->setParameter('type', $objectType)
			->setParameter('id', $objectId)
			->execute();

		$this->commentsCache = [];

		return is_int($affectedRows);
	}

	/**
	 * deletes the read markers for the specified user
	 *
	 * @param \OCP\IUser $user
	 * @return bool
	 * @since 9.0.0
	 */
	public function deleteReadMarksFromUser(IUser $user) {
		$qb = $this->dbConn->getQueryBuilder();
		$query = $qb->delete('comments_read_markers')
			->where($qb->expr()->eq('user_id', $qb->createParameter('user_id')))
			->setParameter('user_id', $user->getUID());

		try {
			$affectedRows = $query->execute();
		} catch (DriverException $e) {
			$this->logger->error($e->getMessage(), [
				'exception' => $e,
				'app' => 'core_comments',
			]);
			return false;
		}
		return ($affectedRows > 0);
	}

	/**
	 * sets the read marker for a given file to the specified date for the
	 * provided user
	 *
	 * @param string $objectType
	 * @param string $objectId
	 * @param \DateTime $dateTime
	 * @param IUser $user
	 * @since 9.0.0
	 */
	public function setReadMark($objectType, $objectId, \DateTime $dateTime, IUser $user) {
		$this->checkRoleParameters('Object', $objectType, $objectId);

		$qb = $this->dbConn->getQueryBuilder();
		$values = [
			'user_id' => $qb->createNamedParameter($user->getUID()),
			'marker_datetime' => $qb->createNamedParameter($dateTime, 'datetime'),
			'object_type' => $qb->createNamedParameter($objectType),
			'object_id' => $qb->createNamedParameter($objectId),
		];

		// Strategy: try to update, if this does not return affected rows, do an insert.
		$affectedRows = $qb
			->update('comments_read_markers')
			->set('user_id', $values['user_id'])
			->set('marker_datetime', $values['marker_datetime'])
			->set('object_type', $values['object_type'])
			->set('object_id', $values['object_id'])
			->where($qb->expr()->eq('user_id', $qb->createParameter('user_id')))
			->andWhere($qb->expr()->eq('object_type', $qb->createParameter('object_type')))
			->andWhere($qb->expr()->eq('object_id', $qb->createParameter('object_id')))
			->setParameter('user_id', $user->getUID(), IQueryBuilder::PARAM_STR)
			->setParameter('object_type', $objectType, IQueryBuilder::PARAM_STR)
			->setParameter('object_id', $objectId, IQueryBuilder::PARAM_STR)
			->execute();

		if ($affectedRows > 0) {
			return;
		}

		$qb->insert('comments_read_markers')
			->values($values)
			->execute();
	}

	/**
	 * returns the read marker for a given file to the specified date for the
	 * provided user. It returns null, when the marker is not present, i.e.
	 * no comments were marked as read.
	 *
	 * @param string $objectType
	 * @param string $objectId
	 * @param IUser $user
	 * @return \DateTime|null
	 * @since 9.0.0
	 */
	public function getReadMark($objectType, $objectId, IUser $user) {
		$qb = $this->dbConn->getQueryBuilder();
		$resultStatement = $qb->select('marker_datetime')
			->from('comments_read_markers')
			->where($qb->expr()->eq('user_id', $qb->createParameter('user_id')))
			->andWhere($qb->expr()->eq('object_type', $qb->createParameter('object_type')))
			->andWhere($qb->expr()->eq('object_id', $qb->createParameter('object_id')))
			->setParameter('user_id', $user->getUID(), IQueryBuilder::PARAM_STR)
			->setParameter('object_type', $objectType, IQueryBuilder::PARAM_STR)
			->setParameter('object_id', $objectId, IQueryBuilder::PARAM_STR)
			->execute();

		$data = $resultStatement->fetch();
		$resultStatement->closeCursor();
		if (!$data || is_null($data['marker_datetime'])) {
			return null;
		}

		return new \DateTime($data['marker_datetime']);
	}

	/**
	 * deletes the read markers on the specified object
	 *
	 * @param string $objectType
	 * @param string $objectId
	 * @return bool
	 * @since 9.0.0
	 */
	public function deleteReadMarksOnObject($objectType, $objectId) {
		$this->checkRoleParameters('Object', $objectType, $objectId);

		$qb = $this->dbConn->getQueryBuilder();
		$query = $qb->delete('comments_read_markers')
			->where($qb->expr()->eq('object_type', $qb->createParameter('object_type')))
			->andWhere($qb->expr()->eq('object_id', $qb->createParameter('object_id')))
			->setParameter('object_type', $objectType)
			->setParameter('object_id', $objectId);

		try {
			$affectedRows = $query->execute();
		} catch (DriverException $e) {
			$this->logger->error($e->getMessage(), [
				'exception' => $e,
				'app' => 'core_comments',
			]);
			return false;
		}
		return ($affectedRows > 0);
	}

	/**
	 * registers an Entity to the manager, so event notifications can be send
	 * to consumers of the comments infrastructure
	 *
	 * @param \Closure $closure
	 */
	public function registerEventHandler(\Closure $closure) {
		$this->eventHandlerClosures[] = $closure;
		$this->eventHandlers = [];
	}

	/**
	 * registers a method that resolves an ID to a display name for a given type
	 *
	 * @param string $type
	 * @param \Closure $closure
	 * @throws \OutOfBoundsException
	 * @since 11.0.0
	 *
	 * Only one resolver shall be registered per type. Otherwise a
	 * \OutOfBoundsException has to thrown.
	 */
	public function registerDisplayNameResolver($type, \Closure $closure) {
		if (!is_string($type)) {
			throw new \InvalidArgumentException('String expected.');
		}
		if (isset($this->displayNameResolvers[$type])) {
			throw new \OutOfBoundsException('Displayname resolver for this type already registered');
		}
		$this->displayNameResolvers[$type] = $closure;
	}

	/**
	 * resolves a given ID of a given Type to a display name.
	 *
	 * @param string $type
	 * @param string $id
	 * @return string
	 * @throws \OutOfBoundsException
	 * @since 11.0.0
	 *
	 * If a provided type was not registered, an \OutOfBoundsException shall
	 * be thrown. It is upon the resolver discretion what to return of the
	 * provided ID is unknown. It must be ensured that a string is returned.
	 */
	public function resolveDisplayName($type, $id) {
		if (!is_string($type)) {
			throw new \InvalidArgumentException('String expected.');
		}
		if (!isset($this->displayNameResolvers[$type])) {
			throw new \OutOfBoundsException('No Displayname resolver for this type registered');
		}
		return (string)$this->displayNameResolvers[$type]($id);
	}

	/**
	 * returns valid, registered entities
	 *
	 * @return \OCP\Comments\ICommentsEventHandler[]
	 */
	private function getEventHandlers() {
		if (!empty($this->eventHandlers)) {
			return $this->eventHandlers;
		}

		$this->eventHandlers = [];
		foreach ($this->eventHandlerClosures as $name => $closure) {
			$entity = $closure();
			if (!($entity instanceof ICommentsEventHandler)) {
				throw new \InvalidArgumentException('The given entity does not implement the ICommentsEntity interface');
			}
			$this->eventHandlers[$name] = $entity;
		}

		return $this->eventHandlers;
	}

	/**
	 * sends notifications to the registered entities
	 *
	 * @param $eventType
	 * @param IComment $comment
	 */
	private function sendEvent($eventType, IComment $comment) {
		$entities = $this->getEventHandlers();
		$event = new CommentsEvent($eventType, $comment);
		foreach ($entities as $entity) {
			$entity->handle($event);
		}
	}

	/**
	 * Load the Comments app into the page
	 *
	 * @since 21.0.0
	 */
	public function load(): void {
		$this->initialStateService->provideInitialState('comments', 'max-message-length', IComment::MAX_MESSAGE_LENGTH);
		Util::addScript('comments', 'comments-app');
	}
}
