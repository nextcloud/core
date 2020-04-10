<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\Repair;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Class RepairConfig
 *
 * @package OC\Repair
 */
class CleanTags implements IRepairStep {

	/** @var IDBConnection */
	protected $connection;

	/** @var IUserManager */
	protected $userManager;

	protected $deletedTags = 0;

	/**
	 * @param IDBConnection $connection
	 * @param IUserManager $userManager
	 */
	public function __construct(IDBConnection $connection, IUserManager $userManager) {
		$this->connection = $connection;
		$this->userManager = $userManager;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Clean tags and favorites';
	}

	/**
	 * Updates the configuration after running an update
	 */
	public function run(IOutput $output) {
		$this->deleteOrphanTags($output);
		$this->deleteOrphanFileEntries($output);
		$this->deleteOrphanTagEntries($output);
		$this->deleteOrphanCategoryEntries($output);
	}

	/**
	 * Delete tags for deleted users
	 */
	protected function deleteOrphanTags(IOutput $output) {
		$offset = 0;
		while ($this->checkTags($offset)) {
			$offset += 50;
		}

		$output->info(sprintf('%d tags of deleted users have been removed.', $this->deletedTags));
	}

	protected function checkTags($offset) {
		$query = $this->connection->getQueryBuilder();
		$query->select('uid')
			->from('vcategory')
			->groupBy('uid')
			->orderBy('uid')
			->setMaxResults(50)
			->setFirstResult($offset);
		$result = $query->execute();

		$users = [];
		$hadResults = false;
		while ($row = $result->fetch()) {
			$hadResults = true;
			if (!$this->userManager->userExists($row['uid'])) {
				$users[] = $row['uid'];
			}
		}
		$result->closeCursor();

		if (!$hadResults) {
			// No more tags, stop looping
			return false;
		}

		if (!empty($users)) {
			$query = $this->connection->getQueryBuilder();
			$query->delete('vcategory')
				->where($query->expr()->in('uid', $query->createNamedParameter($users, IQueryBuilder::PARAM_STR_ARRAY)));
			$this->deletedTags += $query->execute();
		}
		return true;
	}

	/**
	 * Delete tag entries for deleted files
	 */
	protected function deleteOrphanFileEntries(IOutput $output) {
		$this->deleteOrphanEntries(
			$output,
			'%d tags for delete files have been removed.',
			'vcategory_to_object', 'objid',
			'filecache', 'fileid', 'path_hash'
		);
	}

	/**
	 * Delete tag entries for deleted tags
	 */
	protected function deleteOrphanTagEntries(IOutput $output) {
		$this->deleteOrphanEntries(
			$output,
			'%d tag entries for deleted tags have been removed.',
			'vcategory_to_object', 'categoryid',
			'vcategory', 'id', 'uid'
		);
	}

	/**
	 * Delete tags that have no entries
	 */
	protected function deleteOrphanCategoryEntries(IOutput $output) {
		$this->deleteOrphanEntries(
			$output,
			'%d tags with no entries have been removed.',
			'vcategory', 'id',
			'vcategory_to_object', 'categoryid', 'type'
		);
	}

	/**
	 * Deletes all entries from $deleteTable that do not have a matching entry in $sourceTable
	 *
	 * A query joins $deleteTable.$deleteId = $sourceTable.$sourceId and checks
	 * whether $sourceNullColumn is null. If it is null, the entry in $deleteTable
	 * is being deleted.
	 *
	 * @param string $repairInfo
	 * @param string $deleteTable
	 * @param string $deleteId
	 * @param string $sourceTable
	 * @param string $sourceId
	 * @param string $sourceNullColumn	If this column is null in the source table,
	 * 								the entry is deleted in the $deleteTable
	 * @suppress SqlInjectionChecker
	 */
	protected function deleteOrphanEntries(IOutput $output, $repairInfo, $deleteTable, $deleteId, $sourceTable, $sourceId, $sourceNullColumn) {
		$qb = $this->connection->getQueryBuilder();

		$qb->select('d.' . $deleteId)
			->from($deleteTable, 'd')
			->leftJoin('d', $sourceTable, 's', $qb->expr()->eq('d.' . $deleteId, 's.' . $sourceId))
			->where(
				$qb->expr()->eq('d.type', $qb->expr()->literal('files'))
			)
			->andWhere(
				$qb->expr()->isNull('s.' . $sourceNullColumn)
			);
		$result = $qb->execute();

		$orphanItems = [];
		while ($row = $result->fetch()) {
			$orphanItems[] = (int) $row[$deleteId];
		}

		if (!empty($orphanItems)) {
			$orphanItemsBatch = array_chunk($orphanItems, 200);
			foreach ($orphanItemsBatch as $items) {
				$qb->delete($deleteTable)
					->where(
						$qb->expr()->eq('type', $qb->expr()->literal('files'))
					)
					->andWhere($qb->expr()->in($deleteId, $qb->createParameter('ids')));
				$qb->setParameter('ids', $items, IQueryBuilder::PARAM_INT_ARRAY);
				$qb->execute();
			}
		}

		if ($repairInfo) {
			$output->info(sprintf($repairInfo, count($orphanItems)));
		}
	}
}
