<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Jagszent <daniel@jagszent.de>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Files\Cache;

use OC\Files\FileInfo;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IUpdater;
use OCP\Files\Storage\IStorage;

/**
 * Update the cache and propagate changes
 *
 */
class Updater implements IUpdater {
	/**
	 * @var bool
	 */
	protected $enabled = true;

	/**
	 * @var \OC\Files\Storage\Storage
	 */
	protected $storage;

	/**
	 * @var \OC\Files\Cache\Propagator
	 */
	protected $propagator;

	/**
	 * @var Scanner
	 */
	protected $scanner;

	/**
	 * @var Cache
	 */
	protected $cache;

	/**
	 * @param \OC\Files\Storage\Storage $storage
	 */
	public function __construct(\OC\Files\Storage\Storage $storage) {
		$this->storage = $storage;
		$this->propagator = $storage->getPropagator();
		$this->scanner = $storage->getScanner();
		$this->cache = $storage->getCache();
	}

	/**
	 * Disable updating the cache trough this updater
	 */
	public function disable() {
		$this->enabled = false;
	}

	/**
	 * Re-enable the updating of the cache trough this updater
	 */
	public function enable() {
		$this->enabled = true;
	}

	/**
	 * Get the propagator for etags and mtime for the view the updater works on
	 *
	 * @return Propagator
	 */
	public function getPropagator() {
		return $this->propagator;
	}

	/**
	 * Propagate etag and mtime changes for the parent folders of $path up to the root of the filesystem
	 *
	 * @param string $path the path of the file to propagate the changes for
	 * @param int|null $time the timestamp to set as mtime for the parent folders, if left out the current time is used
	 */
	public function propagate($path, $time = null) {
		if (Scanner::isPartialFile($path)) {
			return;
		}
		$this->propagator->propagateChange($path, $time);
	}

	/**
	 * Update the cache for $path and update the size, etag and mtime of the parent folders
	 *
	 * @param string $path
	 * @param int $time
	 */
	public function update($path, $time = null) {
		if (!$this->enabled or Scanner::isPartialFile($path)) {
			return;
		}
		if (is_null($time)) {
			$time = time();
		}

		$data = $this->scanner->scan($path, Scanner::SCAN_SHALLOW, -1, false);
		if (
			isset($data['oldSize']) && isset($data['size']) &&
			!$data['encrypted'] // encryption is a pita and touches the cache itself
		) {
			$sizeDifference = $data['size'] - $data['oldSize'];
		} else {
			// scanner didn't provide size info, fallback to full size calculation
			$sizeDifference = 0;
			if ($this->cache instanceof Cache) {
				$this->cache->correctFolderSize($path, $data);
			}
		}
		$this->correctParentStorageMtime($path);
		$this->propagator->propagateChange($path, $time, $sizeDifference);
	}

	/**
	 * Remove $path from the cache and update the size, etag and mtime of the parent folders
	 *
	 * @param string $path
	 */
	public function remove($path) {
		if (!$this->enabled or Scanner::isPartialFile($path)) {
			return;
		}

		$parent = dirname($path);
		if ($parent === '.') {
			$parent = '';
		}

		$entry = $this->cache->get($path);

		$this->cache->remove($path);

		$this->correctParentStorageMtime($path);
		if ($entry instanceof ICacheEntry) {
			$this->propagator->propagateChange($path, time(), -$entry->getSize());
		} else {
			$this->propagator->propagateChange($path, time());
			if ($this->cache instanceof Cache) {
				$this->cache->correctFolderSize($parent);
			}
		}
	}

	/**
	 * Rename a file or folder in the cache and update the size, etag and mtime of the parent folders
	 *
	 * @param IStorage $sourceStorage
	 * @param string $source
	 * @param string $target
	 */
	public function renameFromStorage(IStorage $sourceStorage, $source, $target) {
		if (!$this->enabled or Scanner::isPartialFile($source) or Scanner::isPartialFile($target)) {
			return;
		}

		$time = time();

		$sourceCache = $sourceStorage->getCache();
		$sourceUpdater = $sourceStorage->getUpdater();
		$sourcePropagator = $sourceStorage->getPropagator();

		$sourceInfo = $sourceCache->get($source);

		if ($sourceInfo !== false) {
			if ($this->cache->inCache($target)) {
				$this->cache->remove($target);
			}

			if ($sourceStorage === $this->storage) {
				$this->cache->move($source, $target);
			} else {
				$this->cache->moveFromCache($sourceCache, $source, $target);
			}

			$sourceExtension = pathinfo($source, PATHINFO_EXTENSION);
			$targetExtension = pathinfo($target, PATHINFO_EXTENSION);
			$targetIsTrash = preg_match("/d\d+/", $targetExtension);

			if ($sourceExtension !== $targetExtension && $sourceInfo->getMimeType() !== FileInfo::MIMETYPE_FOLDER && !$targetIsTrash) {
				// handle mime type change
				$mimeType = $this->storage->getMimeType($target);
				$fileId = $this->cache->getId($target);
				$this->cache->update($fileId, ['mimetype' => $mimeType]);
			}
		}

		if ($sourceCache instanceof Cache) {
			$sourceCache->correctFolderSize($source);
		}
		if ($this->cache instanceof Cache) {
			$this->cache->correctFolderSize($target);
		}
		if ($sourceUpdater instanceof Updater) {
			$sourceUpdater->correctParentStorageMtime($source);
		}
		$this->correctParentStorageMtime($target);
		$this->updateStorageMTimeOnly($target);
		$sourcePropagator->propagateChange($source, $time);
		$this->propagator->propagateChange($target, $time);
	}

	private function updateStorageMTimeOnly($internalPath) {
		$fileId = $this->cache->getId($internalPath);
		if ($fileId !== -1) {
			$mtime = $this->storage->filemtime($internalPath);
			if ($mtime !== false) {
				$this->cache->update(
					$fileId, [
						'mtime' => null, // this magic tells it to not overwrite mtime
						'storage_mtime' => $mtime
					]
				);
			}
		}
	}

	/**
	 * update the storage_mtime of the direct parent in the cache to the mtime from the storage
	 *
	 * @param string $internalPath
	 */
	private function correctParentStorageMtime($internalPath) {
		$parentId = $this->cache->getParentId($internalPath);
		$parent = dirname($internalPath);
		if ($parentId != -1) {
			$mtime = $this->storage->filemtime($parent);
			if ($mtime !== false) {
				$this->cache->update($parentId, ['storage_mtime' => $mtime]);
			}
		}
	}
}
