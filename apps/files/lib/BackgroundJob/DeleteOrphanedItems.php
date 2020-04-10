<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Delete all share entries that have no matching entries in the file cache table.
 */
class DeleteOrphanedItems extends TimedJob {

	const CHUNK_SIZE = 200;

	/** @var \OCP\IDBConnection */
	protected $connection;

	/** @var \OCP\ILogger */
	protected $logger;

	/**
	 * Default interval in minutes
	 *
	 * @var int $defaultIntervalMin
	 **/
	protected $defaultIntervalMin = 60;

	/**
	 * sets the correct interval for this timed job
	 */
	public function __construct() {
		$this->interval = $this->defaultIntervalMin * 60;
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->logger = \OC::$server->getLogger();
	}

	/**
	 * Makes the background job do its work
	 *
	 * @param array $argument unused argument
	 */
	public function run($argument) {
		$this->cleanSystemTags();
		$this->cleanUserTags();
		$this->cleanComments();
		$this->cleanCommentMarkers();
	}

	/**
	 * Deleting orphaned system tag mappings
	 *
	 * @param string $table
	 * @param string $idCol
	 * @param string $typeCol
	 * @return int Number of deleted entries
	 */
	protected function cleanUp($table, $idCol, $typeCol) {
		$deletedEntries = 0;

		$query = $this->connection->getQueryBuilder();
		$query->select('t1.' . $idCol)
			->from($table, 't1')
			->where($query->expr()->eq($typeCol, $query->expr()->literal('files')))
			->andWhere($query->expr()->isNull('t2.fileid'))
			->leftJoin('t1', 'filecache', 't2', $query->expr()->eq($query->expr()->castColumn('t1.' . $idCol, IQueryBuilder::PARAM_INT), 't2.fileid'))
			->groupBy('t1.' . $idCol)
			->setMaxResults(self::CHUNK_SIZE);

		$deleteQuery = $this->connection->getQueryBuilder();
		$deleteQuery->delete($table)
			->where($deleteQuery->expr()->eq($idCol, $deleteQuery->createParameter('objectid')));

		$deletedInLastChunk = self::CHUNK_SIZE;
		while ($deletedInLastChunk === self::CHUNK_SIZE) {
			$result = $query->execute();
			$deletedInLastChunk = 0;
			while ($row = $result->fetch()) {
				$deletedInLastChunk++;
				$deletedEntries += $deleteQuery->setParameter('objectid', (int) $row[$idCol])
					->execute();
			}
			$result->closeCursor();
		}

		return $deletedEntries;
	}

	/**
	 * Deleting orphaned system tag mappings
	 *
	 * @return int Number of deleted entries
	 */
	protected function cleanSystemTags() {
		$deletedEntries = $this->cleanUp('systemtag_object_mapping', 'objectid', 'objecttype');
		$this->logger->debug("$deletedEntries orphaned system tag relations deleted", ['app' => 'DeleteOrphanedItems']);
		return $deletedEntries;
	}

	/**
	 * Deleting orphaned user tag mappings
	 *
	 * @return int Number of deleted entries
	 */
	protected function cleanUserTags() {
		$deletedEntries = $this->cleanUp('vcategory_to_object', 'objid', 'type');
		$this->logger->debug("$deletedEntries orphaned user tag relations deleted", ['app' => 'DeleteOrphanedItems']);
		return $deletedEntries;
	}

	/**
	 * Deleting orphaned comments
	 *
	 * @return int Number of deleted entries
	 */
	protected function cleanComments() {
		$deletedEntries = $this->cleanUp('comments', 'object_id', 'object_type');
		$this->logger->debug("$deletedEntries orphaned comments deleted", ['app' => 'DeleteOrphanedItems']);
		return $deletedEntries;
	}

	/**
	 * Deleting orphaned comment read markers
	 *
	 * @return int Number of deleted entries
	 */
	protected function cleanCommentMarkers() {
		$deletedEntries = $this->cleanUp('comments_read_markers', 'object_id', 'object_type');
		$this->logger->debug("$deletedEntries orphaned comment read marks deleted", ['app' => 'DeleteOrphanedItems']);
		return $deletedEntries;
	}

}
