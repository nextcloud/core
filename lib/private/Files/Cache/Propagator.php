<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\Files\Cache;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\IPropagator;
use OCP\IDBConnection;

/**
 * Propagate etags and mtimes within the storage
 */
class Propagator implements IPropagator {
	private $inBatch = false;

	private $batch = [];

	/**
	 * @var \OC\Files\Storage\Storage
	 */
	protected $storage;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * @var array
	 */
	private $ignore = [];

	public function __construct(\OC\Files\Storage\Storage $storage, IDBConnection $connection, array $ignore = []) {
		$this->storage = $storage;
		$this->connection = $connection;
		$this->ignore = $ignore;
	}


	/**
	 * @param string $internalPath
	 * @param int $time
	 * @param int $sizeDifference number of bytes the file has grown
	 * @suppress SqlInjectionChecker
	 */
	public function propagateChange($internalPath, $time, $sizeDifference = 0) {
		// Do not propogate changes in ignored paths
		foreach ($this->ignore as $ignore) {
			if (strpos($internalPath, $ignore) === 0) {
				return;
			}
		}

		$storageId = (int)$this->storage->getStorageCache()->getNumericId();

		$parents = $this->getParents($internalPath);

		if ($this->inBatch) {
			foreach ($parents as $parent) {
				$this->addToBatch($parent, $time, $sizeDifference);
			}
			return;
		}

		$parentHashes = array_map('md5', $parents);
		$etag = uniqid(); // since we give all folders the same etag we don't ask the storage for the etag

		$builder = $this->connection->getQueryBuilder();
		$hashParams = array_map(function ($hash) use ($builder) {
			return $builder->expr()->literal($hash);
		}, $parentHashes);

		$builder->update('filecache')
			->set('mtime', $builder->func()->greatest('mtime', $builder->createNamedParameter((int)$time, IQueryBuilder::PARAM_INT)))
			->set('etag', $builder->createNamedParameter($etag, IQueryBuilder::PARAM_STR))
			->where($builder->expr()->eq('storage', $builder->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($builder->expr()->in('path_hash', $hashParams));

		$builder->execute();

		if ($sizeDifference !== 0) {
			// we need to do size separably so we can ignore entries with uncalculated size
			$builder = $this->connection->getQueryBuilder();
			$builder->update('filecache')
				->set('size', $builder->func()->greatest(
					$builder->createNamedParameter(-1, IQueryBuilder::PARAM_INT),
					$builder->func()->add('size', $builder->createNamedParameter($sizeDifference)))
				)
				->where($builder->expr()->eq('storage', $builder->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
				->andWhere($builder->expr()->in('path_hash', $hashParams))
				->andWhere($builder->expr()->gt('size', $builder->expr()->literal(-1, IQueryBuilder::PARAM_INT)));

			$builder->execute();
		}
	}

	protected function getParents($path) {
		$parts = explode('/', $path);
		$parent = '';
		$parents = [];
		foreach ($parts as $part) {
			$parents[] = $parent;
			$parent = trim($parent . '/' . $part, '/');
		}
		return $parents;
	}

	/**
	 * Mark the beginning of a propagation batch
	 *
	 * Note that not all cache setups support propagation in which case this will be a noop
	 *
	 * Batching for cache setups that do support it has to be explicit since the cache state is not fully consistent
	 * before the batch is committed.
	 */
	public function beginBatch() {
		$this->inBatch = true;
	}

	private function addToBatch($internalPath, $time, $sizeDifference) {
		if (!isset($this->batch[$internalPath])) {
			$this->batch[$internalPath] = [
				'hash' => md5($internalPath),
				'time' => $time,
				'size' => $sizeDifference
			];
		} else {
			$this->batch[$internalPath]['size'] += $sizeDifference;
			if ($time > $this->batch[$internalPath]['time']) {
				$this->batch[$internalPath]['time'] = $time;
			}
		}
	}

	/**
	 * Commit the active propagation batch
	 * @suppress SqlInjectionChecker
	 */
	public function commitBatch() {
		if (!$this->inBatch) {
			throw new \BadMethodCallException('Not in batch');
		}
		$this->inBatch = false;

		$this->connection->beginTransaction();

		$query = $this->connection->getQueryBuilder();
		$storageId = (int)$this->storage->getStorageCache()->getNumericId();

		$query->update('filecache')
			->set('mtime', $query->createFunction('GREATEST(' . $query->getColumnName('mtime') . ', ' . $query->createParameter('time') . ')'))
			->set('etag', $query->expr()->literal(uniqid()))
			->where($query->expr()->eq('storage', $query->expr()->literal($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('path_hash', $query->createParameter('hash')));

		$sizeQuery = $this->connection->getQueryBuilder();
		$sizeQuery->update('filecache')
			->set('size', $sizeQuery->func()->add('size', $sizeQuery->createParameter('size')))
			->where($query->expr()->eq('storage', $query->expr()->literal($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('path_hash', $query->createParameter('hash')))
			->andWhere($sizeQuery->expr()->gt('size', $sizeQuery->expr()->literal(-1, IQueryBuilder::PARAM_INT)));

		foreach ($this->batch as $item) {
			$query->setParameter('time', $item['time'], IQueryBuilder::PARAM_INT);
			$query->setParameter('hash', $item['hash']);

			$query->execute();

			if ($item['size']) {
				$sizeQuery->setParameter('size', $item['size'], IQueryBuilder::PARAM_INT);
				$sizeQuery->setParameter('hash', $item['hash']);

				$sizeQuery->execute();
			}
		}

		$this->batch = [];

		$this->connection->commit();
	}


}
