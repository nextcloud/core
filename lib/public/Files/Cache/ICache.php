<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\Files\Cache;

use OCP\Files\Search\ISearchQuery;

/**
 * Metadata cache for a storage
 *
 * The cache stores the metadata for all files and folders in a storage and is kept up to date trough the following mechanisms:
 *
 * - Scanner: scans the storage and updates the cache where needed
 * - Watcher: checks for changes made to the filesystem outside of the ownCloud instance and rescans files and folder when a change is detected
 * - Updater: listens to changes made to the filesystem inside of the ownCloud instance and updates the cache where needed
 * - ChangePropagator: updates the mtime and etags of parent folders whenever a change to the cache is made to the cache by the updater
 *
 * @since 9.0.0
 */
interface ICache {
	const NOT_FOUND = 0;
	const PARTIAL = 1; //only partial data available, file not cached in the database
	const SHALLOW = 2; //folder in cache, but not all child files are completely scanned
	const COMPLETE = 3;

	/**
	 * Get the numeric storage id for this cache's storage
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getNumericStorageId();

	/**
	 * get the stored metadata of a file or folder
	 *
	 * @param string | int $file either the path of a file or folder or the file id for a file or folder
	 * @return ICacheEntry|false the cache entry or false if the file is not found in the cache
	 * @since 9.0.0
	 */
	public function get($file);

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * Only returns files one level deep, no recursion
	 *
	 * @param string $folder
	 * @return ICacheEntry[]
	 * @since 9.0.0
	 */
	public function getFolderContents($folder);

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * Only returns files one level deep, no recursion
	 *
	 * @param int $fileId the file id of the folder
	 * @return ICacheEntry[]
	 * @since 9.0.0
	 */
	public function getFolderContentsById($fileId);

	/**
	 * store meta data for a file or folder
	 * This will automatically call either insert or update depending on if the file exists
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 * @throws \RuntimeException
	 * @since 9.0.0
	 */
	public function put($file, array $data);

	/**
	 * insert meta data for a new file or folder
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 * @throws \RuntimeException
	 * @since 9.0.0
	 */
	public function insert($file, array $data);

	/**
	 * update the metadata of an existing file or folder in the cache
	 *
	 * @param int $id the fileid of the existing file or folder
	 * @param array $data [$key => $value] the metadata to update, only the fields provided in the array will be updated, non-provided values will remain unchanged
	 * @since 9.0.0
	 */
	public function update($id, array $data);

	/**
	 * get the file id for a file
	 *
	 * A file id is a numeric id for a file or folder that's unique within an owncloud instance which stays the same for the lifetime of a file
	 *
	 * File ids are easiest way for apps to store references to a file since unlike paths they are not affected by renames or sharing
	 *
	 * @param string $file
	 * @return int
	 * @since 9.0.0
	 */
	public function getId($file);

	/**
	 * get the id of the parent folder of a file
	 *
	 * @param string $file
	 * @return int
	 * @since 9.0.0
	 */
	public function getParentId($file);

	/**
	 * check if a file is available in the cache
	 *
	 * @param string $file
	 * @return bool
	 * @since 9.0.0
	 */
	public function inCache($file);

	/**
	 * remove a file or folder from the cache
	 *
	 * when removing a folder from the cache all files and folders inside the folder will be removed as well
	 *
	 * @param string $file
	 * @since 9.0.0
	 */
	public function remove($file);

	/**
	 * Move a file or folder in the cache
	 *
	 * @param string $source
	 * @param string $target
	 * @since 9.0.0
	 */
	public function move($source, $target);

	/**
	 * Move a file or folder in the cache
	 *
	 * Note that this should make sure the entries are removed from the source cache
	 *
	 * @param \OCP\Files\Cache\ICache $sourceCache
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @throws \OC\DatabaseException
	 * @since 9.0.0
	 */
	public function moveFromCache(ICache $sourceCache, $sourcePath, $targetPath);

	/**
	 * Get the scan status of a file
	 *
	 * - ICache::NOT_FOUND: File is not in the cache
	 * - ICache::PARTIAL: File is not stored in the cache but some incomplete data is known
	 * - ICache::SHALLOW: The folder and it's direct children are in the cache but not all sub folders are fully scanned
	 * - ICache::COMPLETE: The file or folder, with all it's children) are fully scanned
	 *
	 * @param string $file
	 *
	 * @return int ICache::NOT_FOUND, ICache::PARTIAL, ICache::SHALLOW or ICache::COMPLETE
	 * @since 9.0.0
	 */
	public function getStatus($file);

	/**
	 * search for files matching $pattern, files are matched if their filename matches the search pattern
	 *
	 * @param string $pattern the search pattern using SQL search syntax (e.g. '%searchstring%')
	 * @return ICacheEntry[] an array of cache entries where the name matches the search pattern
	 * @since 9.0.0
	 * @deprecated 9.0.0 due to lack of pagination, not all backends might implement this
	 */
	public function search($pattern);

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype either a full mimetype to search ('text/plain') or only the first part of a mimetype ('image')
	 *        where it will search for all mimetypes in the group ('image/*')
	 * @return ICacheEntry[] an array of cache entries where the mimetype matches the search
	 * @since 9.0.0
	 * @deprecated 9.0.0 due to lack of pagination, not all backends might implement this
	 */
	public function searchByMime($mimetype);

	/**
	 * Search for files with a flexible query
	 *
	 * @param ISearchQuery $query
	 * @return ICacheEntry[]
	 * @throw \InvalidArgumentException if the cache is unable to perform the query
	 * @since 12.0.0
	 */
	public function searchQuery(ISearchQuery $query);

	/**
	 * find a folder in the cache which has not been fully scanned
	 *
	 * If multiple incomplete folders are in the cache, the one with the highest id will be returned,
	 * use the one with the highest id gives the best result with the background scanner, since that is most
	 * likely the folder where we stopped scanning previously
	 *
	 * @return string|bool the path of the folder or false when no folder matched
	 * @since 9.0.0
	 */
	public function getIncomplete();

	/**
	 * get the path of a file on this storage by it's file id
	 *
	 * @param int $id the file id of the file or folder to search
	 * @return string|null the path of the file (relative to the storage) or null if a file with the given id does not exists within this cache
	 * @since 9.0.0
	 */
	public function getPathById($id);

	/**
	 * normalize the given path for usage in the cache
	 *
	 * @param string $path
	 * @return string
	 * @since 9.0.0
	 */
	public function normalize($path);
}
