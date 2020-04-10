<?php
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

namespace OC\Files\Cache;

use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;

/**
 * Fallback implementation for moveFromCache
 */
trait MoveFromCacheTrait {
	/**
	 * store meta data for a file or folder
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 * @throws \RuntimeException
	 */
	abstract public function put($file, array $data);

	/**
	 * Move a file or folder in the cache
	 *
	 * @param \OCP\Files\Cache\ICache $sourceCache
	 * @param string $sourcePath
	 * @param string $targetPath
	 */
	public function moveFromCache(ICache $sourceCache, $sourcePath, $targetPath) {
		$sourceEntry = $sourceCache->get($sourcePath);

		$this->copyFromCache($sourceCache, $sourceEntry, $targetPath);

		$sourceCache->remove($sourcePath);
	}

	/**
	 * Copy a file or folder in the cache
	 *
	 * @param \OCP\Files\Cache\ICache $sourceCache
	 * @param ICacheEntry $sourceEntry
	 * @param string $targetPath
	 */
	public function copyFromCache(ICache $sourceCache, ICacheEntry $sourceEntry, $targetPath) {
		$this->put($targetPath, $this->cacheEntryToArray($sourceEntry));
		if ($sourceEntry->getMimeType() === ICacheEntry::DIRECTORY_MIMETYPE) {
			$folderContent = $sourceCache->getFolderContentsById($sourceEntry->getId());
			foreach ($folderContent as $subEntry) {
				$subTargetPath = $targetPath . '/' . $subEntry->getName();
				$this->copyFromCache($sourceCache, $subEntry, $subTargetPath);
			}
		}
	}

	private function cacheEntryToArray(ICacheEntry $entry) {
		return [
			'size' => $entry->getSize(),
			'mtime' => $entry->getMTime(),
			'storage_mtime' => $entry->getStorageMTime(),
			'mimetype' => $entry->getMimeType(),
			'mimepart' => $entry->getMimePart(),
			'etag' => $entry->getEtag(),
			'permissions' => $entry->getPermissions(),
			'encrypted' => $entry->isEncrypted(),
			'creation_time' => $entry->getCreationTime(),
			'upload_time' => $entry->getUploadTime(),
			'metadata_etag' => $entry->getMetadataEtag(),
		];
	}
}
