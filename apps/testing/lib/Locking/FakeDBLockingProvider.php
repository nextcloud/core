<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Testing\Locking;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\ILogger;

class FakeDBLockingProvider extends \OC\Lock\DBLockingProvider {
	// Lock for 10 hours just to be sure
	const TTL = 36000;

	/**
	 * Need a new child, because parent::connection is private instead of protected...
	 * @var IDBConnection
	 */
	protected $db;

	/**
	 * @param \OCP\IDBConnection $connection
	 * @param \OCP\ILogger $logger
	 * @param \OCP\AppFramework\Utility\ITimeFactory $timeFactory
	 */
	public function __construct(IDBConnection $connection, ILogger $logger, ITimeFactory $timeFactory) {
		parent::__construct($connection, $logger, $timeFactory);
		$this->db = $connection;
	}


	/**
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 */
	public function releaseLock(string $path, int $type) {
		// we DONT keep shared locks till the end of the request
		if ($type === self::LOCK_SHARED) {
			$this->db->executeUpdate(
				'UPDATE `*PREFIX*file_locks` SET `lock` = 0 WHERE `key` = ? AND `lock` = 1',
				[$path]
			);
		}

		parent::releaseLock($path, $type);
	}

	public function __destruct() {
		// Prevent cleaning up at the end of the live time.
		// parent::__destruct();
	}
}
