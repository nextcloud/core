<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Lock;

use OCP\Lock\ILockingProvider;

/**
 * Base locking provider that keeps track of locks acquired during the current request
 * to release any left over locks at the end of the request
 */
abstract class AbstractLockingProvider implements ILockingProvider {
	/** @var int $ttl */
	protected $ttl; // how long until we clear stray locks in seconds

	protected $acquiredLocks = [
		'shared' => [],
		'exclusive' => []
	];

	/**
	 * Check if we've locally acquired a lock
	 *
	 * @param string $path
	 * @param int $type
	 * @return bool
	 */
	protected function hasAcquiredLock(string $path, int $type): bool {
		if ($type === self::LOCK_SHARED) {
			return isset($this->acquiredLocks['shared'][$path]) && $this->acquiredLocks['shared'][$path] > 0;
		} else {
			return isset($this->acquiredLocks['exclusive'][$path]) && $this->acquiredLocks['exclusive'][$path] === true;
		}
	}

	/**
	 * Mark a locally acquired lock
	 *
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 */
	protected function markAcquire(string $path, int $type) {
		if ($type === self::LOCK_SHARED) {
			if (!isset($this->acquiredLocks['shared'][$path])) {
				$this->acquiredLocks['shared'][$path] = 0;
			}
			$this->acquiredLocks['shared'][$path]++;
		} else {
			$this->acquiredLocks['exclusive'][$path] = true;
		}
	}

	/**
	 * Mark a release of a locally acquired lock
	 *
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 */
	protected function markRelease(string $path, int $type) {
		if ($type === self::LOCK_SHARED) {
			if (isset($this->acquiredLocks['shared'][$path]) and $this->acquiredLocks['shared'][$path] > 0) {
				$this->acquiredLocks['shared'][$path]--;
				if ($this->acquiredLocks['shared'][$path] === 0) {
					unset($this->acquiredLocks['shared'][$path]);
				}
			}
		} else if ($type === self::LOCK_EXCLUSIVE) {
			unset($this->acquiredLocks['exclusive'][$path]);
		}
	}

	/**
	 * Change the type of an existing tracked lock
	 *
	 * @param string $path
	 * @param int $targetType self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 */
	protected function markChange(string $path, int $targetType) {
		if ($targetType === self::LOCK_SHARED) {
			unset($this->acquiredLocks['exclusive'][$path]);
			if (!isset($this->acquiredLocks['shared'][$path])) {
				$this->acquiredLocks['shared'][$path] = 0;
			}
			$this->acquiredLocks['shared'][$path]++;
		} else if ($targetType === self::LOCK_EXCLUSIVE) {
			$this->acquiredLocks['exclusive'][$path] = true;
			$this->acquiredLocks['shared'][$path]--;
		}
	}

	/**
	 * release all lock acquired by this instance which were marked using the mark* methods
	 */
	public function releaseAll() {
		foreach ($this->acquiredLocks['shared'] as $path => $count) {
			for ($i = 0; $i < $count; $i++) {
				$this->releaseLock($path, self::LOCK_SHARED);
			}
		}

		foreach ($this->acquiredLocks['exclusive'] as $path => $hasLock) {
			$this->releaseLock($path, self::LOCK_EXCLUSIVE);
		}
	}

	protected function getOwnSharedLockCount(string $path) {
		return isset($this->acquiredLocks['shared'][$path]) ? $this->acquiredLocks['shared'][$path] : 0;
	}
}
