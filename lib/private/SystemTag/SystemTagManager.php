<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OC\SystemTag;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ManagerEvent;
use OCP\SystemTag\TagAlreadyExistsException;
use OCP\SystemTag\TagNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manager class for system tags
 */
class SystemTagManager implements ISystemTagManager {

	const TAG_TABLE = 'systemtag';
	const TAG_GROUP_TABLE = 'systemtag_group';

	/** @var IDBConnection */
	protected $connection;

	/** @var EventDispatcherInterface */
	protected $dispatcher;

	/** @var IGroupManager */
	protected $groupManager;

	/**
	 * Prepared query for selecting tags directly
	 *
	 * @var \OCP\DB\QueryBuilder\IQueryBuilder
	 */
	private $selectTagQuery;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $connection database connection
	 * @param IGroupManager $groupManager
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(
		IDBConnection $connection,
		IGroupManager $groupManager,
		EventDispatcherInterface $dispatcher
	) {
		$this->connection = $connection;
		$this->groupManager = $groupManager;
		$this->dispatcher = $dispatcher;

		$query = $this->connection->getQueryBuilder();
		$this->selectTagQuery = $query->select('*')
			->from(self::TAG_TABLE)
			->where($query->expr()->eq('name', $query->createParameter('name')))
			->andWhere($query->expr()->eq('visibility', $query->createParameter('visibility')))
			->andWhere($query->expr()->eq('editable', $query->createParameter('editable')));
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTagsByIds($tagIds): array {
		if (!\is_array($tagIds)) {
			$tagIds = [$tagIds];
		}

		$tags = [];

		// note: not all databases will fail if it's a string or starts with a number
		foreach ($tagIds as $tagId) {
			if (!is_numeric($tagId)) {
				throw new \InvalidArgumentException('Tag id must be integer');
			}
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from(self::TAG_TABLE)
			->where($query->expr()->in('id', $query->createParameter('tagids')))
			->addOrderBy('name', 'ASC')
			->addOrderBy('visibility', 'ASC')
			->addOrderBy('editable', 'ASC')
			->setParameter('tagids', $tagIds, IQueryBuilder::PARAM_INT_ARRAY);

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$tags[$row['id']] = $this->createSystemTagFromRow($row);
		}

		$result->closeCursor();

		if (\count($tags) !== \count($tagIds)) {
			throw new TagNotFoundException(
				'Tag id(s) not found', 0, null, array_diff($tagIds, array_keys($tags))
			);
		}

		return $tags;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAllTags($visibilityFilter = null, $nameSearchPattern = null): array {
		$tags = [];

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from(self::TAG_TABLE);

		if (!\is_null($visibilityFilter)) {
			$query->andWhere($query->expr()->eq('visibility', $query->createNamedParameter((int)$visibilityFilter)));
		}

		if (!empty($nameSearchPattern)) {
			$query->andWhere(
				$query->expr()->like(
					'name',
					$query->createNamedParameter('%' . $this->connection->escapeLikeParameter($nameSearchPattern). '%')
				)
			);
		}

		$query
			->addOrderBy('name', 'ASC')
			->addOrderBy('visibility', 'ASC')
			->addOrderBy('editable', 'ASC');

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$tags[$row['id']] = $this->createSystemTagFromRow($row);
		}

		$result->closeCursor();

		return $tags;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTag(string $tagName, bool $userVisible, bool $userAssignable): ISystemTag {
		$result = $this->selectTagQuery
			->setParameter('name', $tagName)
			->setParameter('visibility', $userVisible ? 1 : 0)
			->setParameter('editable', $userAssignable ? 1 : 0)
			->execute();

		$row = $result->fetch();
		$result->closeCursor();
		if (!$row) {
			throw new TagNotFoundException(
				'Tag ("' . $tagName . '", '. $userVisible . ', ' . $userAssignable . ') does not exist'
			);
		}

		return $this->createSystemTagFromRow($row);
	}

	/**
	 * {@inheritdoc}
	 */
	public function createTag(string $tagName, bool $userVisible, bool $userAssignable): ISystemTag {
		$query = $this->connection->getQueryBuilder();
		$query->insert(self::TAG_TABLE)
			->values([
				'name' => $query->createNamedParameter($tagName),
				'visibility' => $query->createNamedParameter($userVisible ? 1 : 0),
				'editable' => $query->createNamedParameter($userAssignable ? 1 : 0),
			]);

		try {
			$query->execute();
		} catch (UniqueConstraintViolationException $e) {
			throw new TagAlreadyExistsException(
				'Tag ("' . $tagName . '", '. $userVisible . ', ' . $userAssignable . ') already exists',
				0,
				$e
			);
		}

		$tagId = $query->getLastInsertId();

		$tag = new SystemTag(
			(string)$tagId,
			$tagName,
			$userVisible,
			$userAssignable
		);

		$this->dispatcher->dispatch(ManagerEvent::EVENT_CREATE, new ManagerEvent(
			ManagerEvent::EVENT_CREATE, $tag
		));

		return $tag;
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateTag(string $tagId, string $tagName, bool $userVisible, bool $userAssignable) {
		try {
			$tags = $this->getTagsByIds($tagId);
		} catch (TagNotFoundException $e) {
			throw new TagNotFoundException(
				'Tag does not exist', 0, null, [$tagId]
			);
		}

		$beforeUpdate = array_shift($tags);
		$afterUpdate = new SystemTag(
			$tagId,
			$tagName,
			$userVisible,
			$userAssignable
		);

		$query = $this->connection->getQueryBuilder();
		$query->update(self::TAG_TABLE)
			->set('name', $query->createParameter('name'))
			->set('visibility', $query->createParameter('visibility'))
			->set('editable', $query->createParameter('editable'))
			->where($query->expr()->eq('id', $query->createParameter('tagid')))
			->setParameter('name', $tagName)
			->setParameter('visibility', $userVisible ? 1 : 0)
			->setParameter('editable', $userAssignable ? 1 : 0)
			->setParameter('tagid', $tagId);

		try {
			if ($query->execute() === 0) {
				throw new TagNotFoundException(
					'Tag does not exist', 0, null, [$tagId]
				);
			}
		} catch (UniqueConstraintViolationException $e) {
			throw new TagAlreadyExistsException(
				'Tag ("' . $tagName . '", '. $userVisible . ', ' . $userAssignable . ') already exists',
				0,
				$e
			);
		}

		$this->dispatcher->dispatch(ManagerEvent::EVENT_UPDATE, new ManagerEvent(
			ManagerEvent::EVENT_UPDATE, $afterUpdate, $beforeUpdate
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteTags($tagIds) {
		if (!\is_array($tagIds)) {
			$tagIds = [$tagIds];
		}

		$tagNotFoundException = null;
		$tags = [];
		try {
			$tags = $this->getTagsByIds($tagIds);
		} catch (TagNotFoundException $e) {
			$tagNotFoundException = $e;

			// Get existing tag objects for the hooks later
			$existingTags = array_diff($tagIds, $tagNotFoundException->getMissingTags());
			if (!empty($existingTags)) {
				try {
					$tags = $this->getTagsByIds($existingTags);
				} catch (TagNotFoundException $e) {
					// Ignore further errors...
				}
			}
		}

		// delete relationships first
		$query = $this->connection->getQueryBuilder();
		$query->delete(SystemTagObjectMapper::RELATION_TABLE)
			->where($query->expr()->in('systemtagid', $query->createParameter('tagids')))
			->setParameter('tagids', $tagIds, IQueryBuilder::PARAM_INT_ARRAY)
			->execute();

		$query = $this->connection->getQueryBuilder();
		$query->delete(self::TAG_TABLE)
			->where($query->expr()->in('id', $query->createParameter('tagids')))
			->setParameter('tagids', $tagIds, IQueryBuilder::PARAM_INT_ARRAY)
			->execute();

		foreach ($tags as $tag) {
			$this->dispatcher->dispatch(ManagerEvent::EVENT_DELETE, new ManagerEvent(
				ManagerEvent::EVENT_DELETE, $tag
			));
		}

		if ($tagNotFoundException !== null) {
			throw new TagNotFoundException(
				'Tag id(s) not found', 0, $tagNotFoundException, $tagNotFoundException->getMissingTags()
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function canUserAssignTag(ISystemTag $tag, IUser $user): bool {
		// early check to avoid unneeded group lookups
		if ($tag->isUserAssignable() && $tag->isUserVisible()) {
			return true;
		}

		if ($this->groupManager->isAdmin($user->getUID())) {
			return true;
		}

		if (!$tag->isUserVisible()) {
			return false;
		}

		$groupIds = $this->groupManager->getUserGroupIds($user);
		if (!empty($groupIds)) {
			$matchingGroups = array_intersect($groupIds, $this->getTagGroups($tag));
			if (!empty($matchingGroups)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function canUserSeeTag(ISystemTag $tag, IUser $user): bool {
		if ($tag->isUserVisible()) {
			return true;
		}

		if ($this->groupManager->isAdmin($user->getUID())) {
			return true;
		}

		return false;
	}

	private function createSystemTagFromRow($row) {
		return new SystemTag((string)$row['id'], $row['name'], (bool)$row['visibility'], (bool)$row['editable']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function setTagGroups(ISystemTag $tag, array $groupIds) {
		// delete relationships first
		$this->connection->beginTransaction();
		try {
			$query = $this->connection->getQueryBuilder();
			$query->delete(self::TAG_GROUP_TABLE)
				->where($query->expr()->eq('systemtagid', $query->createNamedParameter($tag->getId())))
				->execute();

			// add each group id
			$query = $this->connection->getQueryBuilder();
			$query->insert(self::TAG_GROUP_TABLE)
				->values([
					'systemtagid' => $query->createNamedParameter($tag->getId()),
					'gid' => $query->createParameter('gid'),
				]);
			foreach ($groupIds as $groupId) {
				if ($groupId === '') {
					continue;
				}
				$query->setParameter('gid', $groupId);
				$query->execute();
			}

			$this->connection->commit();
		} catch (\Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTagGroups(ISystemTag $tag): array {
		$groupIds = [];
		$query = $this->connection->getQueryBuilder();
		$query->select('gid')
			->from(self::TAG_GROUP_TABLE)
			->where($query->expr()->eq('systemtagid', $query->createNamedParameter($tag->getId())))
			->orderBy('gid');

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$groupIds[] = $row['gid'];
		}

		$result->closeCursor();

		return $groupIds;
	}
}
