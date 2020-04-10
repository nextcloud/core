<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Lock;

use OCP\Lock\ILockingProvider;

/**
 * @group DB
 *
 * @package Test\Lock
 */
class NonCachingDBLockingProviderTest extends DBLockingProviderTest {
	/**
	 * @return \OCP\Lock\ILockingProvider
	 */
	protected function getInstance() {
		$this->connection = \OC::$server->getDatabaseConnection();
		return new \OC\Lock\DBLockingProvider($this->connection, \OC::$server->getLogger(), $this->timeFactory, 3600, false);
	}

	public function testDoubleShared() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);

		$this->assertEquals(2, $this->getLockValue('foo'));

		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);

		$this->assertEquals(1, $this->getLockValue('foo'));

		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);

		$this->assertEquals(0, $this->getLockValue('foo'));
	}
}
