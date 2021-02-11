<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Ari Selseng <ari@selseng.net>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Jagszent <daniel@jagszent.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Files\Cache\Wrapper;

use OC\Files\Cache\Cache;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchQuery;

class CacheWrapper extends Cache {
	/**
	 * @var \OCP\Files\Cache\ICache
	 */
	protected $cache;

	/**
	 * @param \OCP\Files\Cache\ICache $cache
	 */
	public function __construct($cache) {
		$this->cache = $cache;
	}

	protected function getCache() {
		return $this->cache;
	}

	/**
	 * Make it easy for wrappers to modify every returned cache entry
	 *
	 * @param ICacheEntry $entry
	 * @return ICacheEntry
	 */
	protected function formatCacheEntry($entry) {
		return $entry;
	}

	/**
	 * get the stored metadata of a file or folder
	 *
	 * @param string|int $file
	 * @return ICacheEntry|false
	 */
	public function get($file) {
		$result = $this->getCache()->get($file);
		if ($result) {
			$result = $this->formatCacheEntry($result);
		}
		return $result;
	}

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param string $folder
	 * @return ICacheEntry[]
	 */
	public function getFolderContents($folder) {
		// can't do a simple $this->getCache()->.... call here since getFolderContentsById needs to be called on this
		// and not the wrapped cache
		$fileId = $this->getId($folder);
		return $this->getFolderContentsById($fileId);
	}

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param int $fileId the file id of the folder
	 * @return array
	 */
	public function getFolderContentsById($fileId) {
		$results = $this->getCache()->getFolderContentsById($fileId);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	/**
	 * insert or update meta data for a file or folder
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 * @throws \RuntimeException
	 */
	public function put($file, array $data) {
		if (($id = $this->getId($file)) > -1) {
			$this->update($id, $data);
			return $id;
		} else {
			return $this->insert($file, $data);
		}
	}

	/**
	 * insert meta data for a new file or folder
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 * @throws \RuntimeException
	 */
	public function insert($file, array $data) {
		return $this->getCache()->insert($file, $data);
	}

	/**
	 * update the metadata in the cache
	 *
	 * @param int $id
	 * @param array $data
	 */
	public function update($id, array $data) {
		$this->getCache()->update($id, $data);
	}

	/**
	 * get the file id for a file
	 *
	 * @param string $file
	 * @return int
	 */
	public function getId($file) {
		return $this->getCache()->getId($file);
	}

	/**
	 * get the id of the parent folder of a file
	 *
	 * @param string $file
	 * @return int
	 */
	public function getParentId($file) {
		return $this->getCache()->getParentId($file);
	}

	/**
	 * check if a file is available in the cache
	 *
	 * @param string $file
	 * @return bool
	 */
	public function inCache($file) {
		return $this->getCache()->inCache($file);
	}

	/**
	 * remove a file or folder from the cache
	 *
	 * @param string $file
	 */
	public function remove($file) {
		$this->getCache()->remove($file);
	}

	/**
	 * Move a file or folder in the cache
	 *
	 * @param string $source
	 * @param string $target
	 */
	public function move($source, $target) {
		$this->getCache()->move($source, $target);
	}

	protected function getMoveInfo($path) {
		/** @var Cache $cache */
		$cache = $this->getCache();
		return $cache->getMoveInfo($path);
	}

	public function moveFromCache(ICache $sourceCache, $sourcePath, $targetPath) {
		$this->getCache()->moveFromCache($sourceCache, $sourcePath, $targetPath);
	}

	/**
	 * remove all entries for files that are stored on the storage from the cache
	 */
	public function clear() {
		$this->getCache()->clear();
	}

	/**
	 * @param string $file
	 *
	 * @return int Cache::NOT_FOUND, Cache::PARTIAL, Cache::SHALLOW or Cache::COMPLETE
	 */
	public function getStatus($file) {
		return $this->getCache()->getStatus($file);
	}

	/**
	 * search for files matching $pattern
	 *
	 * @param string $pattern
	 * @return ICacheEntry[] an array of file data
	 */
	public function search($pattern) {
		$results = $this->getCache()->search($pattern);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype
	 * @return ICacheEntry[]
	 */
	public function searchByMime($mimetype) {
		$results = $this->getCache()->searchByMime($mimetype);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	public function searchQuery(ISearchQuery $query) {
		$results = $this->getCache()->searchQuery($query);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	/**
	 * update the folder size and the size of all parent folders
	 *
	 * @param string|boolean $path
	 * @param array $data (optional) meta data of the folder
	 */
	public function correctFolderSize($path, $data = null, $isBackgroundScan = false) {
		if ($this->getCache() instanceof Cache) {
			$this->getCache()->correctFolderSize($path, $data, $isBackgroundScan);
		}
	}

	/**
	 * get the size of a folder and set it in the cache
	 *
	 * @param string $path
	 * @param array $entry (optional) meta data of the folder
	 * @return int
	 */
	public function calculateFolderSize($path, $entry = null) {
		if ($this->getCache() instanceof Cache) {
			return $this->getCache()->calculateFolderSize($path, $entry);
		} else {
			return 0;
		}
	}

	/**
	 * get all file ids on the files on the storage
	 *
	 * @return int[]
	 */
	public function getAll() {
		return $this->getCache()->getAll();
	}

	/**
	 * find a folder in the cache which has not been fully scanned
	 *
	 * If multiple incomplete folders are in the cache, the one with the highest id will be returned,
	 * use the one with the highest id gives the best result with the background scanner, since that is most
	 * likely the folder where we stopped scanning previously
	 *
	 * @return string|bool the path of the folder or false when no folder matched
	 */
	public function getIncomplete() {
		return $this->getCache()->getIncomplete();
	}

	/**
	 * get the path of a file on this storage by it's id
	 *
	 * @param int $id
	 * @return string|null
	 */
	public function getPathById($id) {
		return $this->getCache()->getPathById($id);
	}

	/**
	 * Returns the numeric storage id
	 *
	 * @return int
	 */
	public function getNumericStorageId() {
		return $this->getCache()->getNumericStorageId();
	}

	/**
	 * get the storage id of the storage for a file and the internal path of the file
	 * unlike getPathById this does not limit the search to files on this storage and
	 * instead does a global search in the cache table
	 *
	 * @param int $id
	 * @return array first element holding the storage id, second the path
	 */
	public static function getById($id) {
		return parent::getById($id);
	}
}
