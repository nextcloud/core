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

use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Test\TestCase;

abstract class LockingProvider extends TestCase {
	/**
	 * @var \OCP\Lock\ILockingProvider
	 */
	protected $instance;

	/**
	 * @return \OCP\Lock\ILockingProvider
	 */
	abstract protected function getInstance();

	protected function setUp(): void {
		parent::setUp();
		$this->instance = $this->getInstance();
	}

	public function testExclusiveLock() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
	}

	public function testSharedLock() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
	}

	public function testDoubleSharedLock() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
	}

	public function testReleaseSharedLock() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
	}

	
	public function testDoubleExclusiveLock() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
	}

	public function testReleaseExclusiveLock() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->instance->releaseLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
	}

	
	public function testExclusiveLockAfterShared() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
	}

	public function testExclusiveLockAfterSharedReleased() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
	}

	public function testReleaseAll() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('bar', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('asd', ILockingProvider::LOCK_EXCLUSIVE);
		$this->instance->acquireLock('fizz#A=23', ILockingProvider::LOCK_EXCLUSIVE);

		$this->instance->releaseAll();

		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->assertFalse($this->instance->isLocked('bar', ILockingProvider::LOCK_SHARED));
		$this->assertFalse($this->instance->isLocked('asd', ILockingProvider::LOCK_EXCLUSIVE));
		$this->assertFalse($this->instance->isLocked('fizz#A=23', ILockingProvider::LOCK_EXCLUSIVE));
	}

	public function testReleaseAllAfterChange() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('bar', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('asd', ILockingProvider::LOCK_EXCLUSIVE);

		$this->instance->changeLock('bar', ILockingProvider::LOCK_EXCLUSIVE);

		$this->instance->releaseAll();

		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->assertFalse($this->instance->isLocked('bar', ILockingProvider::LOCK_SHARED));
		$this->assertFalse($this->instance->isLocked('bar', ILockingProvider::LOCK_EXCLUSIVE));
		$this->assertFalse($this->instance->isLocked('asd', ILockingProvider::LOCK_EXCLUSIVE));
	}

	public function testReleaseAllAfterUnlock() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('bar', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('asd', ILockingProvider::LOCK_EXCLUSIVE);

		$this->instance->releaseLock('bar', ILockingProvider::LOCK_SHARED);

		$this->instance->releaseAll();

		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->assertFalse($this->instance->isLocked('asd', ILockingProvider::LOCK_EXCLUSIVE));
	}

	public function testReleaseAfterReleaseAll() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);

		$this->instance->releaseAll();

		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));

		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);
	}


	
	public function testSharedLockAfterExclusive() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
	}

	public function testLockedExceptionHasPathForShared() {
		try {
			$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
			$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
			$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);

			$this->fail('Expected locked exception');
		} catch (LockedException $e) {
			$this->assertEquals('foo', $e->getPath());
		}
	}

	public function testLockedExceptionHasPathForExclusive() {
		try {
			$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
			$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
			$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);

			$this->fail('Expected locked exception');
		} catch (LockedException $e) {
			$this->assertEquals('foo', $e->getPath());
		}
	}

	public function testChangeLockToExclusive() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->changeLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
	}

	public function testChangeLockToShared() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->instance->changeLock('foo', ILockingProvider::LOCK_SHARED);
		$this->assertFalse($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_SHARED));
	}

	
	public function testChangeLockToExclusiveDoubleShared() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->changeLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
	}

	
	public function testChangeLockToExclusiveNoShared() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->instance->changeLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
	}

	
	public function testChangeLockToExclusiveFromExclusive() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->instance->changeLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
	}

	
	public function testChangeLockToSharedNoExclusive() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->instance->changeLock('foo', ILockingProvider::LOCK_SHARED);
	}

	
	public function testChangeLockToSharedFromShared() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->changeLock('foo', ILockingProvider::LOCK_SHARED);
	}

	public function testReleaseNonExistingShared() {
		$this->instance->acquireLock('foo', ILockingProvider::LOCK_SHARED);
		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);

		// releasing a lock once to many should not result in a locked state
		$this->instance->releaseLock('foo', ILockingProvider::LOCK_SHARED);

		$this->instance->acquireLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertTrue($this->instance->isLocked('foo', ILockingProvider::LOCK_EXCLUSIVE));
		$this->instance->releaseLock('foo', ILockingProvider::LOCK_EXCLUSIVE);
	}
}
