<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Files_Sharing\External;

use OC\ForbiddenException;
use OCP\Files\NotFoundException;
use OCP\Files\StorageInvalidException;
use OCP\Files\StorageNotAvailableException;

class Scanner extends \OC\Files\Cache\Scanner {
	/** @var \OCA\Files_Sharing\External\Storage */
	protected $storage;

	/** {@inheritDoc} */
	public function scan($path, $recursive = self::SCAN_RECURSIVE, $reuse = -1, $lock = true) {
		if (!$this->storage->remoteIsOwnCloud()) {
			return parent::scan($path, $recursive, $recursive, $lock);
		}

		$this->scanAll();
	}

	/**
	 * Scan a single file and store it in the cache.
	 * If an exception happened while accessing the external storage,
	 * the storage will be checked for availability and removed
	 * if it is not available any more.
	 *
	 * @param string $file file to scan
	 * @param int $reuseExisting
	 * @param int $parentId
	 * @param array | null $cacheData existing data in the cache for the file to be scanned
	 * @param bool $lock set to false to disable getting an additional read lock during scanning
	 * @return array an array of metadata of the scanned file
	 */
	public function scanFile($file, $reuseExisting = 0, $parentId = -1, $cacheData = null, $lock = true, $data = null) {
		try {
			return parent::scanFile($file, $reuseExisting);
		} catch (ForbiddenException $e) {
			$this->storage->checkStorageAvailability();
		} catch (NotFoundException $e) {
			// if the storage isn't found, the call to
			// checkStorageAvailable() will verify it and remove it
			// if appropriate
			$this->storage->checkStorageAvailability();
		} catch (StorageInvalidException $e) {
			$this->storage->checkStorageAvailability();
		} catch (StorageNotAvailableException $e) {
			$this->storage->checkStorageAvailability();
		}
	}

	/**
	 * Checks the remote share for changes.
	 * If changes are available, scan them and update
	 * the cache.
	 * @throws NotFoundException
	 * @throws StorageInvalidException
	 * @throws \Exception
	 */
	public function scanAll() {
		try {
			$data = $this->storage->getShareInfo();
		} catch (\Exception $e) {
			$this->storage->checkStorageAvailability();
			throw new \Exception(
				'Error while scanning remote share: "' .
				$this->storage->getRemote() . '" ' .
				$e->getMessage()
			);
		}
		if ($data['status'] === 'success') {
			$this->addResult($data['data'], '');
		} else {
			throw new \Exception(
				'Error while scanning remote share: "' .
				$this->storage->getRemote() . '"'
			);
		}
	}

	/**
	 * @param array $data
	 * @param string $path
	 */
	private function addResult($data, $path) {
		$id = $this->cache->put($path, $data);
		if (isset($data['children'])) {
			$children = [];
			foreach ($data['children'] as $child) {
				$children[$child['name']] = true;
				$this->addResult($child, ltrim($path . '/' . $child['name'], '/'));
			}

			$existingCache = $this->cache->getFolderContentsById($id);
			foreach ($existingCache as $existingChild) {
				// if an existing child is not in the new data, remove it
				if (!isset($children[$existingChild['name']])) {
					$this->cache->remove(ltrim($path . '/' . $existingChild['name'], '/'));
				}
			}
		}
	}
}
