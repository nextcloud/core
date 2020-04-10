<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Repair;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Repairs shares with invalid data
 */
class RepairInvalidShares implements IRepairStep {

	const CHUNK_SIZE = 200;

	/** @var \OCP\IConfig */
	protected $config;

	/** @var \OCP\IDBConnection */
	protected $connection;

	/**
	 * @param \OCP\IConfig $config
	 * @param \OCP\IDBConnection $connection
	 */
	public function __construct($config, $connection) {
		$this->connection = $connection;
		$this->config = $config;
	}

	public function getName() {
		return 'Repair invalid shares';
	}

	/**
	 * Adjust file share permissions
	 * @suppress SqlInjectionChecker
	 */
	private function adjustFileSharePermissions(IOutput $out) {
		$mask = \OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE;
		$builder = $this->connection->getQueryBuilder();

		$permsFunc = $builder->expr()->bitwiseAnd('permissions', $mask);
		$builder
			->update('share')
			->set('permissions', $permsFunc)
			->where($builder->expr()->eq('item_type', $builder->expr()->literal('file')))
			->andWhere($builder->expr()->neq('permissions', $permsFunc));

		$updatedEntries = $builder->execute();
		if ($updatedEntries > 0) {
			$out->info('Fixed file share permissions for ' . $updatedEntries . ' shares');
		}
	}

	/**
	 * Remove shares where the parent share does not exist anymore
	 */
	private function removeSharesNonExistingParent(IOutput $out) {
		$deletedEntries = 0;

		$query = $this->connection->getQueryBuilder();
		$query->select('s1.parent')
			->from('share', 's1')
			->where($query->expr()->isNotNull('s1.parent'))
				->andWhere($query->expr()->isNull('s2.id'))
			->leftJoin('s1', 'share', 's2', $query->expr()->eq('s1.parent', 's2.id'))
			->groupBy('s1.parent')
			->setMaxResults(self::CHUNK_SIZE);

		$deleteQuery = $this->connection->getQueryBuilder();
		$deleteQuery->delete('share')
			->where($deleteQuery->expr()->eq('parent', $deleteQuery->createParameter('parent')));

		$deletedInLastChunk = self::CHUNK_SIZE;
		while ($deletedInLastChunk === self::CHUNK_SIZE) {
			$deletedInLastChunk = 0;
			$result = $query->execute();
			while ($row = $result->fetch()) {
				$deletedInLastChunk++;
				$deletedEntries += $deleteQuery->setParameter('parent', (int) $row['parent'])
					->execute();
			}
			$result->closeCursor();
		}

		if ($deletedEntries) {
			$out->info('Removed ' . $deletedEntries . ' shares where the parent did not exist');
		}
	}

	public function run(IOutput $out) {
		$ocVersionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0');
		if (version_compare($ocVersionFromBeforeUpdate, '12.0.0.11', '<')) {
			$this->adjustFileSharePermissions($out);
		}

		$this->removeSharesNonExistingParent($out);
	}
}
