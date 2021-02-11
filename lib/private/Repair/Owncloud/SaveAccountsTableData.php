<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Repair\Owncloud;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\PreConditionNotMetException;

/**
 * Copies the email address from the accounts table to the preference table,
 * before the data structure is changed and the information is gone
 */
class SaveAccountsTableData implements IRepairStep {
	public const BATCH_SIZE = 75;

	/** @var IDBConnection */
	protected $db;

	/** @var IConfig */
	protected $config;

	protected $hasForeignKeyOnPersistentLocks = false;

	/**
	 * @param IDBConnection $db
	 * @param IConfig $config
	 */
	public function __construct(IDBConnection $db, IConfig $config) {
		$this->db = $db;
		$this->config = $config;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Copy data from accounts table when migrating from ownCloud';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		if (!$this->shouldRun()) {
			return;
		}

		$offset = 0;
		$numUsers = $this->runStep($offset);

		while ($numUsers === self::BATCH_SIZE) {
			$offset += $numUsers;
			$numUsers = $this->runStep($offset);
		}

		// oc_persistent_locks will be removed later on anyways so we can just drop and ignore any foreign key constraints here
		$tableName = $this->config->getSystemValue('dbtableprefix', 'oc_') . 'persistent_locks';
		$schema = $this->db->createSchema();
		$table = $schema->getTable($tableName);
		foreach ($table->getForeignKeys() as $foreignKey) {
			$table->removeForeignKey($foreignKey->getName());
		}
		$this->db->migrateToSchema($schema);

		// Remove the table
		if ($this->hasForeignKeyOnPersistentLocks) {
			$this->db->dropTable('persistent_locks');
		}
		$this->db->dropTable('accounts');
	}

	/**
	 * @return bool
	 */
	protected function shouldRun() {
		$schema = $this->db->createSchema();
		$prefix = $this->config->getSystemValue('dbtableprefix', 'oc_');

		$tableName = $prefix . 'accounts';
		if (!$schema->hasTable($tableName)) {
			return false;
		}

		$table = $schema->getTable($tableName);
		if (!$table->hasColumn('user_id')) {
			return false;
		}

		if ($schema->hasTable($prefix . 'persistent_locks')) {
			$locksTable = $schema->getTable($prefix . 'persistent_locks');
			$foreignKeys = $locksTable->getForeignKeys();
			foreach ($foreignKeys as $foreignKey) {
				if ($tableName === $foreignKey->getForeignTableName()) {
					$this->hasForeignKeyOnPersistentLocks = true;
				}
			}
		}

		return true;
	}

	/**
	 * @param int $offset
	 * @return int Number of copied users
	 */
	protected function runStep($offset) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('accounts')
			->orderBy('id')
			->setMaxResults(self::BATCH_SIZE);

		if ($offset > 0) {
			$query->setFirstResult($offset);
		}

		$result = $query->execute();

		$update = $this->db->getQueryBuilder();
		$update->update('users')
			->set('displayname', $update->createParameter('displayname'))
			->where($update->expr()->eq('uid', $update->createParameter('userid')));

		$updatedUsers = 0;
		while ($row = $result->fetch()) {
			try {
				$this->migrateUserInfo($update, $row);
			} catch (PreConditionNotMetException $e) {
				// Ignore and continue
			} catch (\UnexpectedValueException $e) {
				// Ignore and continue
			}
			$updatedUsers++;
		}
		$result->closeCursor();

		return $updatedUsers;
	}

	/**
	 * @param IQueryBuilder $update
	 * @param array $userdata
	 * @throws PreConditionNotMetException
	 * @throws \UnexpectedValueException
	 */
	protected function migrateUserInfo(IQueryBuilder $update, $userdata) {
		$state = (int) $userdata['state'];
		if ($state === 3) {
			// Deleted user, ignore
			return;
		}

		if ($userdata['email'] !== null) {
			$this->config->setUserValue($userdata['user_id'], 'settings', 'email', $userdata['email']);
		}
		if ($userdata['quota'] !== null) {
			$this->config->setUserValue($userdata['user_id'], 'files', 'quota', $userdata['quota']);
		}
		if ($userdata['last_login'] !== null) {
			$this->config->setUserValue($userdata['user_id'], 'login', 'lastLogin', $userdata['last_login']);
		}
		if ($state === 1) {
			$this->config->setUserValue($userdata['user_id'], 'core', 'enabled', 'true');
		} elseif ($state === 2) {
			$this->config->setUserValue($userdata['user_id'], 'core', 'enabled', 'false');
		}

		if ($userdata['display_name'] !== null) {
			$update->setParameter('displayname', $userdata['display_name'])
				->setParameter('userid', $userdata['user_id']);
			$update->execute();
		}
	}
}
