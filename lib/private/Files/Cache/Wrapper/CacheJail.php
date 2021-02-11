<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Jagszent <daniel@jagszent.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
use OC\Files\Search\SearchQuery;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchQuery;

/**
 * Jail to a subdirectory of the wrapped cache
 */
class CacheJail extends CacheWrapper {
	/**
	 * @var string
	 */
	protected $root;

	/**
	 * @param \OCP\Files\Cache\ICache $cache
	 * @param string $root
	 */
	public function __construct($cache, $root) {
		parent::__construct($cache);
		$this->root = $root;
	}

	protected function getRoot() {
		return $this->root;
	}

	protected function getSourcePath($path) {
		if ($path === '') {
			return $this->getRoot();
		} else {
			return $this->getRoot() . '/' . ltrim($path, '/');
		}
	}

	/**
	 * @param string $path
	 * @return null|string the jailed path or null if the path is outside the jail
	 */
	protected function getJailedPath($path) {
		if ($this->getRoot() === '') {
			return $path;
		}
		$rootLength = strlen($this->getRoot()) + 1;
		if ($path === $this->getRoot()) {
			return '';
		} elseif (substr($path, 0, $rootLength) === $this->getRoot() . '/') {
			return substr($path, $rootLength);
		} else {
			return null;
		}
	}

	/**
	 * @param ICacheEntry|array $entry
	 * @return array
	 */
	protected function formatCacheEntry($entry) {
		if (isset($entry['path'])) {
			$entry['path'] = $this->getJailedPath($entry['path']);
		}
		return $entry;
	}

	protected function filterCacheEntry($entry) {
		$rootLength = strlen($this->getRoot()) + 1;
		return $rootLength === 1 || ($entry['path'] === $this->getRoot()) || (substr($entry['path'], 0, $rootLength) === $this->getRoot() . '/');
	}

	/**
	 * get the stored metadata of a file or folder
	 *
	 * @param string /int $file
	 * @return ICacheEntry|false
	 */
	public function get($file) {
		if (is_string($file) or $file == '') {
			$file = $this->getSourcePath($file);
		}
		return parent::get($file);
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
		return $this->getCache()->insert($this->getSourcePath($file), $data);
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
		return $this->getCache()->getId($this->getSourcePath($file));
	}

	/**
	 * get the id of the parent folder of a file
	 *
	 * @param string $file
	 * @return int
	 */
	public function getParentId($file) {
		return $this->getCache()->getParentId($this->getSourcePath($file));
	}

	/**
	 * check if a file is available in the cache
	 *
	 * @param string $file
	 * @return bool
	 */
	public function inCache($file) {
		return $this->getCache()->inCache($this->getSourcePath($file));
	}

	/**
	 * remove a file or folder from the cache
	 *
	 * @param string $file
	 */
	public function remove($file) {
		$this->getCache()->remove($this->getSourcePath($file));
	}

	/**
	 * Move a file or folder in the cache
	 *
	 * @param string $source
	 * @param string $target
	 */
	public function move($source, $target) {
		$this->getCache()->move($this->getSourcePath($source), $this->getSourcePath($target));
	}

	/**
	 * Get the storage id and path needed for a move
	 *
	 * @param string $path
	 * @return array [$storageId, $internalPath]
	 */
	protected function getMoveInfo($path) {
		return [$this->getNumericStorageId(), $this->getSourcePath($path)];
	}

	/**
	 * remove all entries for files that are stored on the storage from the cache
	 */
	public function clear() {
		$this->getCache()->remove($this->getRoot());
	}

	/**
	 * @param string $file
	 *
	 * @return int Cache::NOT_FOUND, Cache::PARTIAL, Cache::SHALLOW or Cache::COMPLETE
	 */
	public function getStatus($file) {
		return $this->getCache()->getStatus($this->getSourcePath($file));
	}

	private function formatSearchResults($results) {
		$results = array_filter($results, [$this, 'filterCacheEntry']);
		$results = array_values($results);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	/**
	 * search for files matching $pattern
	 *
	 * @param string $pattern
	 * @return array an array of file data
	 */
	public function search($pattern) {
		$results = $this->getCache()->search($pattern);
		return $this->formatSearchResults($results);
	}

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype
	 * @return array
	 */
	public function searchByMime($mimetype) {
		$results = $this->getCache()->searchByMime($mimetype);
		return $this->formatSearchResults($results);
	}

	public function searchQuery(ISearchQuery $query) {
		$simpleQuery = new SearchQuery($query->getSearchOperation(), 0, 0, $query->getOrder(), $query->getUser());
		$results = $this->getCache()->searchQuery($simpleQuery);
		$results = $this->formatSearchResults($results);

		$limit = $query->getLimit() === 0 ? null : $query->getLimit();
		$results = array_slice($results, $query->getOffset(), $limit);

		return $results;
	}

	/**
	 * update the folder size and the size of all parent folders
	 *
	 * @param string|boolean $path
	 * @param array $data (optional) meta data of the folder
	 */
	public function correctFolderSize($path, $data = null, $isBackgroundScan = false) {
		if ($this->getCache() instanceof Cache) {
			$this->getCache()->correctFolderSize($this->getSourcePath($path), $data, $isBackgroundScan);
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
			return $this->getCache()->calculateFolderSize($this->getSourcePath($path), $entry);
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
		// not supported
		return [];
	}

	/**
	 * find a folder in the cache which has not been fully scanned
	 *
	 * If multiply incomplete folders are in the cache, the one with the highest id will be returned,
	 * use the one with the highest id gives the best result with the background scanner, since that is most
	 * likely the folder where we stopped scanning previously
	 *
	 * @return string|bool the path of the folder or false when no folder matched
	 */
	public function getIncomplete() {
		// not supported
		return false;
	}

	/**
	 * get the path of a file on this storage by it's id
	 *
	 * @param int $id
	 * @return string|null
	 */
	public function getPathById($id) {
		$path = $this->getCache()->getPathById($id);
		if ($path === null) {
			return null;
		}

		return $this->getJailedPath($path);
	}

	/**
	 * Move a file or folder in the cache
	 *
	 * Note that this should make sure the entries are removed from the source cache
	 *
	 * @param \OCP\Files\Cache\ICache $sourceCache
	 * @param string $sourcePath
	 * @param string $targetPath
	 */
	public function moveFromCache(\OCP\Files\Cache\ICache $sourceCache, $sourcePath, $targetPath) {
		if ($sourceCache === $this) {
			return $this->move($sourcePath, $targetPath);
		}
		return $this->getCache()->moveFromCache($sourceCache, $sourcePath, $this->getSourcePath($targetPath));
	}
}
