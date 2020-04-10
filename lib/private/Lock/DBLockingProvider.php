<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Individual IT Services <info@individual-it.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Ole Ostergaard <ole.c.ostergaard@gmail.com>
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

use OC\DB\QueryBuilder\Literal;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * Locking provider that stores the locks in the database
 */
class DBLockingProvider extends AbstractLockingProvider {
	/**
	 * @var \OCP\IDBConnection
	 */
	private $connection;

	/**
	 * @var \OCP\ILogger
	 */
	private $logger;

	/**
	 * @var \OCP\AppFramework\Utility\ITimeFactory
	 */
	private $timeFactory;

	private $sharedLocks = [];

	/**
	 * @var bool
	 */
	private $cacheSharedLocks;

	/**
	 * Check if we have an open shared lock for a path
	 *
	 * @param string $path
	 * @return bool
	 */
	protected function isLocallyLocked(string $path): bool {
		return isset($this->sharedLocks[$path]) && $this->sharedLocks[$path];
	}

	/**
	 * Mark a locally acquired lock
	 *
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 */
	protected function markAcquire(string $path, int $type) {
		parent::markAcquire($path, $type);
		if ($this->cacheSharedLocks) {
			if ($type === self::LOCK_SHARED) {
				$this->sharedLocks[$path] = true;
			}
		}
	}

	/**
	 * Change the type of an existing tracked lock
	 *
	 * @param string $path
	 * @param int $targetType self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 */
	protected function markChange(string $path, int $targetType) {
		parent::markChange($path, $targetType);
		if ($this->cacheSharedLocks) {
			if ($targetType === self::LOCK_SHARED) {
				$this->sharedLocks[$path] = true;
			} else if ($targetType === self::LOCK_EXCLUSIVE) {
				$this->sharedLocks[$path] = false;
			}
		}
	}

	/**
	 * @param \OCP\IDBConnection $connection
	 * @param \OCP\ILogger $logger
	 * @param \OCP\AppFramework\Utility\ITimeFactory $timeFactory
	 * @param int $ttl
	 * @param bool $cacheSharedLocks
	 */
	public function __construct(
		IDBConnection $connection,
		ILogger $logger,
		ITimeFactory $timeFactory,
		int $ttl = 3600,
		$cacheSharedLocks = true
	) {
		$this->connection = $connection;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
		$this->ttl = $ttl;
		$this->cacheSharedLocks = $cacheSharedLocks;
	}

	/**
	 * Insert a file locking row if it does not exists.
	 *
	 * @param string $path
	 * @param int $lock
	 * @return int number of inserted rows
	 */
	protected function initLockField(string $path, int $lock = 0): int {
		$expire = $this->getExpireTime();
		return $this->connection->insertIgnoreConflict('file_locks', [
			'key' => $path,
			'lock' => $lock,
			'ttl' => $expire
		]);
	}

	/**
	 * @return int
	 */
	protected function getExpireTime(): int {
		return $this->timeFactory->getTime() + $this->ttl;
	}

	/**
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 * @return bool
	 */
	public function isLocked(string $path, int $type): bool {
		if ($this->hasAcquiredLock($path, $type)) {
			return true;
		}
		$query = $this->connection->prepare('SELECT `lock` from `*PREFIX*file_locks` WHERE `key` = ?');
		$query->execute([$path]);
		$lockValue = (int)$query->fetchColumn();
		if ($type === self::LOCK_SHARED) {
			if ($this->isLocallyLocked($path)) {
				// if we have a shared lock we kept open locally but it's released we always have at least 1 shared lock in the db
				return $lockValue > 1;
			} else {
				return $lockValue > 0;
			}
		} else if ($type === self::LOCK_EXCLUSIVE) {
			return $lockValue === -1;
		} else {
			return false;
		}
	}

	/**
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock(string $path, int $type) {
		$expire = $this->getExpireTime();
		if ($type === self::LOCK_SHARED) {
			if (!$this->isLocallyLocked($path)) {
				$result = $this->initLockField($path, 1);
				if ($result <= 0) {
					$result = $this->connection->executeUpdate(
						'UPDATE `*PREFIX*file_locks` SET `lock` = `lock` + 1, `ttl` = ? WHERE `key` = ? AND `lock` >= 0',
						[$expire, $path]
					);
				}
			} else {
				$result = 1;
			}
		} else {
			$existing = 0;
			if ($this->hasAcquiredLock($path, ILockingProvider::LOCK_SHARED) === false && $this->isLocallyLocked($path)) {
				$existing = 1;
			}
			$result = $this->initLockField($path, -1);
			if ($result <= 0) {
				$result = $this->connection->executeUpdate(
					'UPDATE `*PREFIX*file_locks` SET `lock` = -1, `ttl` = ? WHERE `key` = ? AND `lock` = ?',
					[$expire, $path, $existing]
				);
			}
		}
		if ($result !== 1) {
			throw new LockedException($path);
		}
		$this->markAcquire($path, $type);
	}

	/**
	 * @param string $path
	 * @param int $type self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 *
	 * @suppress SqlInjectionChecker
	 */
	public function releaseLock(string $path, int $type) {
		$this->markRelease($path, $type);

		// we keep shared locks till the end of the request so we can re-use them
		if ($type === self::LOCK_EXCLUSIVE) {
			$this->connection->executeUpdate(
				'UPDATE `*PREFIX*file_locks` SET `lock` = 0 WHERE `key` = ? AND `lock` = -1',
				[$path]
			);
		} else if (!$this->cacheSharedLocks) {
			$query = $this->connection->getQueryBuilder();
			$query->update('file_locks')
				->set('lock', $query->func()->subtract('lock', $query->createNamedParameter(1)))
				->where($query->expr()->eq('key', $query->createNamedParameter($path)))
				->andWhere($query->expr()->gt('lock', $query->createNamedParameter(0)));
			$query->execute();
		}
	}

	/**
	 * Change the type of an existing lock
	 *
	 * @param string $path
	 * @param int $targetType self::LOCK_SHARED or self::LOCK_EXCLUSIVE
	 * @throws \OCP\Lock\LockedException
	 */
	public function changeLock(string $path, int $targetType) {
		$expire = $this->getExpireTime();
		if ($targetType === self::LOCK_SHARED) {
			$result = $this->connection->executeUpdate(
				'UPDATE `*PREFIX*file_locks` SET `lock` = 1, `ttl` = ? WHERE `key` = ? AND `lock` = -1',
				[$expire, $path]
			);
		} else {
			// since we only keep one shared lock in the db we need to check if we have more then one shared lock locally manually
			if (isset($this->acquiredLocks['shared'][$path]) && $this->acquiredLocks['shared'][$path] > 1) {
				throw new LockedException($path);
			}
			$result = $this->connection->executeUpdate(
				'UPDATE `*PREFIX*file_locks` SET `lock` = -1, `ttl` = ? WHERE `key` = ? AND `lock` = 1',
				[$expire, $path]
			);
		}
		if ($result !== 1) {
			throw new LockedException($path);
		}
		$this->markChange($path, $targetType);
	}

	/**
	 * cleanup empty locks
	 */
	public function cleanExpiredLocks() {
		$expire = $this->timeFactory->getTime();
		try {
			$this->connection->executeUpdate(
				'DELETE FROM `*PREFIX*file_locks` WHERE `ttl` < ?',
				[$expire]
			);
		} catch (\Exception $e) {
			// If the table is missing, the clean up was successful
			if ($this->connection->tableExists('file_locks')) {
				throw $e;
			}
		}
	}

	/**
	 * release all lock acquired by this instance which were marked using the mark* methods
	 *
	 * @suppress SqlInjectionChecker
	 */
	public function releaseAll() {
		parent::releaseAll();

		if (!$this->cacheSharedLocks) {
			return;
		}
		// since we keep shared locks we need to manually clean those
		$lockedPaths = array_keys($this->sharedLocks);
		$lockedPaths = array_filter($lockedPaths, function ($path) {
			return $this->sharedLocks[$path];
		});

		$chunkedPaths = array_chunk($lockedPaths, 100);

		foreach ($chunkedPaths as $chunk) {
			$builder = $this->connection->getQueryBuilder();

			$query = $builder->update('file_locks')
				->set('lock', $builder->func()->subtract('lock', $builder->expr()->literal(1)))
				->where($builder->expr()->in('key', $builder->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)))
				->andWhere($builder->expr()->gt('lock', new Literal(0)));

			$query->execute();
		}
	}
}
