<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\MapperEvent;
use OCP\SystemTag\TagNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SystemTagObjectMapper implements ISystemTagObjectMapper {
	public const RELATION_TABLE = 'systemtag_object_mapping';

	/** @var ISystemTagManager */
	protected $tagManager;

	/** @var IDBConnection */
	protected $connection;

	/** @var EventDispatcherInterface */
	protected $dispatcher;

	/**
	 * Constructor.
	 *
	 * @param IDBConnection $connection database connection
	 * @param ISystemTagManager $tagManager system tag manager
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(IDBConnection $connection, ISystemTagManager $tagManager, EventDispatcherInterface $dispatcher) {
		$this->connection = $connection;
		$this->tagManager = $tagManager;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTagIdsForObjects($objIds, string $objectType): array {
		if (!\is_array($objIds)) {
			$objIds = [$objIds];
		} elseif (empty($objIds)) {
			return [];
		}

		$query = $this->connection->getQueryBuilder();
		$query->select(['systemtagid', 'objectid'])
			->from(self::RELATION_TABLE)
			->where($query->expr()->in('objectid', $query->createParameter('objectids')))
			->andWhere($query->expr()->eq('objecttype', $query->createParameter('objecttype')))
			->setParameter('objectids', $objIds, IQueryBuilder::PARAM_STR_ARRAY)
			->setParameter('objecttype', $objectType)
			->addOrderBy('objectid', 'ASC')
			->addOrderBy('systemtagid', 'ASC');

		$mapping = [];
		foreach ($objIds as $objId) {
			$mapping[$objId] = [];
		}

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$objectId = $row['objectid'];
			$mapping[$objectId][] = $row['systemtagid'];
		}

		$result->closeCursor();

		return $mapping;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getObjectIdsForTags($tagIds, string $objectType, int $limit = 0, string $offset = ''): array {
		if (!\is_array($tagIds)) {
			$tagIds = [$tagIds];
		}

		$this->assertTagsExist($tagIds);

		$query = $this->connection->getQueryBuilder();
		$query->selectDistinct('objectid')
			->from(self::RELATION_TABLE)
			->where($query->expr()->in('systemtagid', $query->createNamedParameter($tagIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($query->expr()->eq('objecttype', $query->createNamedParameter($objectType)));

		if ($limit) {
			if (\count($tagIds) !== 1) {
				throw new \InvalidArgumentException('Limit is only allowed with a single tag');
			}

			$query->setMaxResults($limit)
				->orderBy('objectid', 'ASC');

			if ($offset !== '') {
				$query->andWhere($query->expr()->gt('objectid', $query->createNamedParameter($offset)));
			}
		}

		$objectIds = [];

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$objectIds[] = $row['objectid'];
		}

		return $objectIds;
	}

	/**
	 * {@inheritdoc}
	 */
	public function assignTags(string $objId, string $objectType, $tagIds) {
		if (!\is_array($tagIds)) {
			$tagIds = [$tagIds];
		}

		$this->assertTagsExist($tagIds);

		$query = $this->connection->getQueryBuilder();
		$query->insert(self::RELATION_TABLE)
			->values([
				'objectid' => $query->createNamedParameter($objId),
				'objecttype' => $query->createNamedParameter($objectType),
				'systemtagid' => $query->createParameter('tagid'),
			]);

		$tagsAssigned = [];
		foreach ($tagIds as $tagId) {
			try {
				$query->setParameter('tagid', $tagId);
				$query->execute();
				$tagsAssigned[] = $tagId;
			} catch (UniqueConstraintViolationException $e) {
				// ignore existing relations
			}
		}

		if (empty($tagsAssigned)) {
			return;
		}

		$this->dispatcher->dispatch(MapperEvent::EVENT_ASSIGN, new MapperEvent(
			MapperEvent::EVENT_ASSIGN,
			$objectType,
			$objId,
			$tagsAssigned
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function unassignTags(string $objId, string $objectType, $tagIds) {
		if (!\is_array($tagIds)) {
			$tagIds = [$tagIds];
		}

		$this->assertTagsExist($tagIds);

		$query = $this->connection->getQueryBuilder();
		$query->delete(self::RELATION_TABLE)
			->where($query->expr()->eq('objectid', $query->createParameter('objectid')))
			->andWhere($query->expr()->eq('objecttype', $query->createParameter('objecttype')))
			->andWhere($query->expr()->in('systemtagid', $query->createParameter('tagids')))
			->setParameter('objectid', $objId)
			->setParameter('objecttype', $objectType)
			->setParameter('tagids', $tagIds, IQueryBuilder::PARAM_INT_ARRAY)
			->execute();

		$this->dispatcher->dispatch(MapperEvent::EVENT_UNASSIGN, new MapperEvent(
			MapperEvent::EVENT_UNASSIGN,
			$objectType,
			$objId,
			$tagIds
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function haveTag($objIds, string $objectType, string $tagId, bool $all = true): bool {
		$this->assertTagsExist([$tagId]);

		if (!\is_array($objIds)) {
			$objIds = [$objIds];
		}

		$query = $this->connection->getQueryBuilder();

		if (!$all) {
			// If we only need one entry, we make the query lighter, by not
			// counting the elements
			$query->select('*')
				->setMaxResults(1);
		} else {
			$query->select($query->func()->count($query->expr()->literal(1)));
		}

		$query->from(self::RELATION_TABLE)
			->where($query->expr()->in('objectid', $query->createParameter('objectids')))
			->andWhere($query->expr()->eq('objecttype', $query->createParameter('objecttype')))
			->andWhere($query->expr()->eq('systemtagid', $query->createParameter('tagid')))
			->setParameter('objectids', $objIds, IQueryBuilder::PARAM_STR_ARRAY)
			->setParameter('tagid', $tagId)
			->setParameter('objecttype', $objectType);

		$result = $query->execute();
		$row = $result->fetch(\PDO::FETCH_NUM);
		$result->closeCursor();

		if ($all) {
			return ((int)$row[0] === \count($objIds));
		}

		return (bool) $row;
	}

	/**
	 * Asserts that all the given tag ids exist.
	 *
	 * @param string[] $tagIds tag ids to check
	 *
	 * @throws \OCP\SystemTag\TagNotFoundException if at least one tag did not exist
	 */
	private function assertTagsExist($tagIds) {
		$tags = $this->tagManager->getTagsByIds($tagIds);
		if (\count($tags) !== \count($tagIds)) {
			// at least one tag missing, bail out
			$foundTagIds = array_map(
				function (ISystemTag $tag) {
					return $tag->getId();
				},
				$tags
			);
			$missingTagIds = array_diff($tagIds, $foundTagIds);
			throw new TagNotFoundException(
				'Tags not found', 0, null, $missingTagIds
			);
		}
	}
}
