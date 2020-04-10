<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Lock;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Lock\ILockingProvider;

/**
 * Class DBLockingProvider
 *
 * @group DB
 *
 * @package Test\Lock
 */
class DBLockingProviderTest extends LockingProvider {
	/**
	 * @var \OC\Lock\DBLockingProvider
	 */
	protected $instance;

	/**
	 * @var \OCP\IDBConnection
	 */
	protected $connection;

	/**
	 * @var \OCP\AppFramework\Utility\ITimeFactory
	 */
	protected $timeFactory;

	protected $currentTime;

	protected function setUp(): void {
		$this->currentTime = time();
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->expects($this->any())
			->method('getTime')
			->willReturnCallback(function () {
				return $this->currentTime;
			});
		parent::setUp();
	}

	/**
	 * @return \OCP\Lock\ILockingProvider
	 */
	protected function getInstance() {
		$this->connection = \OC::$server->getDatabaseConnection();
		return new \OC\Lock\DBLockingProvider($this->connection, \OC::$server->getLogger(), $this->timeFactory, 3600);
	}

	protected function tearDown(): void {
		$this->connection->executeQuery('DELETE FROM `*PREFIX*file_locks`');
		parent::tearDown();
	}

	public function testCleanEmptyLocks() {
		$this->currentTime = 100;
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->instance->acquireLock('asd', ILockingProvider::LOCK_EXCLUSIVE);

		$this->currentTime = 200;
		$this->instance->acquireLock('bar', ILockingProvider::LOCK_EXCLUSIVE);
		$this->instance->changeLock('asd', ILockingProvider::LOCK_SHARED);

		$this->currentTime = 150 + 3600;

		$this->assertEquals(3, $this->getLockEntryCount());

		$this->instance->cleanExpiredLocks();

		$this->assertEquals(2, $this->getLockEntryCount());
	}

	private function getLockEntryCount() {
		$query = $this->connection->prepare('SELECT count(*) FROM `*PREFIX*file_locks`');
		$query->execute();
		return $query->fetchColumn();
	}

	protected function getLockValue($key) {
		$query = $this->connection->getQueryBuilder();
		$query->select('lock')
			->from('file_locks')
			->where($query->expr()->eq('key', $query->createNamedParameter($key)));
		return $query->execute()->fetchColumn();
	}

	public function testDoubleShared() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);

		$this->assertEquals(1, $this->getLockValue('foo'));

		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);

		$this->assertEquals(1, $this->getLockValue('foo'));

		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);

		$this->assertEquals(1, $this->getLockValue('foo'));

		$this->instance->releaseAll();

		$this->assertEquals(0, $this->getLockValue('foo'));
	}
}
