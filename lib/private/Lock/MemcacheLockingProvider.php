<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jaakko Salo <jaakkos@gmail.com>
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

use OCP\IMemcache;
use OCP\IMemcacheTTL;
use OCP\Lock\LockedException;

class MemcacheLockingProvider extends AbstractLockingProvider {
	/**
	 * @var \OCP\IMemcache
	 */
	private $memcache;

	/**
	 * @param \OCP\IMemcache $memcache
	 * @param int $ttl
	 */
	public function __construct(IMemcache $memcache, int $ttl = 3600) {
		$this->memcache = $memcache;
		$this->ttl = $ttl;
	}

	private function setTTL(string $path) {
		if ($this->memcache instanceof IMemcacheTTL) {
			$this->memcache->setTTL($path, $this->ttl);
		}
	}

	/**
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 * @return bool
	 */
	public function isLocked(string $path, int $type): bool {
		$lockValue = $this->memcache->get($path);
		if ($type === self::LOCK_SHARED) {
			return is_int($lockValue) && $lockValue > 0;
		} elseif ($type === self::LOCK_EXCLUSIVE) {
			return $lockValue === 'exclusive';
		} else {
			return false;
		}
	}

	/**
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 * @param string $readablePath human readable path to use in error messages
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock(string $path, int $type, string $readablePath = null) {
		if ($type === self::LOCK_SHARED) {
			if (!$this->memcache->inc($path)) {
				throw new LockedException($path, null, $this->getExistingLockForException($path), $readablePath);
			}
		} else {
			$this->memcache->add($path, 0);
			if (!$this->memcache->cas($path, 0, 'exclusive')) {
				throw new LockedException($path, null, $this->getExistingLockForException($path), $readablePath);
			}
		}
		$this->setTTL($path);
		$this->markAcquire($path, $type);
	}

	/**
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 */
	public function releaseLock(string $path, int $type) {
		if ($type === self::LOCK_SHARED) {
			$ownSharedLockCount = $this->getOwnSharedLockCount($path);
			$newValue = 0;
			if ($ownSharedLockCount === 0) { // if we are not holding the lock, don't try to release it
				return;
			}
			if ($ownSharedLockCount === 1) {
				$removed = $this->memcache->cad($path, 1); // if we're the only one having a shared lock we can remove it in one go
				if (!$removed) { //someone else also has a shared lock, decrease only
					$newValue = $this->memcache->dec($path);
				}
			} else {
				// if we own more than one lock ourselves just decrease
				$newValue = $this->memcache->dec($path);
			}

			// if we somehow release more locks then exists, reset the lock
			if ($newValue < 0) {
				$this->memcache->cad($path, $newValue);
			}
		} elseif ($type === self::LOCK_EXCLUSIVE) {
			$this->memcache->cad($path, 'exclusive');
		}
		$this->markRelease($path, $type);
	}

	/**
	 * Change the type of an existing lock
	 *
	 * @param string $path
	 * @param int $targetType self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 * @throws \OCP\Lock\LockedException
	 */
	public function changeLock(string $path, int $targetType) {
		if ($targetType === self::LOCK_SHARED) {
			if (!$this->memcache->cas($path, 'exclusive', 1)) {
				throw new LockedException($path, null, $this->getExistingLockForException($path));
			}
		} elseif ($targetType === self::LOCK_EXCLUSIVE) {
			// we can only change a shared lock to an exclusive if there's only a single owner of the shared lock
			if (!$this->memcache->cas($path, 1, 'exclusive')) {
				throw new LockedException($path, null, $this->getExistingLockForException($path));
			}
		}
		$this->setTTL($path);
		$this->markChange($path, $targetType);
	}

	private function getExistingLockForException($path) {
		$existing = $this->memcache->get($path);
		if (!$existing) {
			return 'none';
		} elseif ($existing === 'exclusive') {
			return $existing;
		} else {
			return $existing . ' shared locks';
		}
	}
}
