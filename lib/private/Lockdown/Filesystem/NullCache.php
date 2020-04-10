<?php
/**
 * @copyright Copyright (c) 2016, Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Lockdown\Filesystem;

use OC\Files\Cache\CacheEntry;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\Files\FileInfo;
use OCP\Files\Search\ISearchQuery;

class NullCache implements ICache {
	public function getNumericStorageId() {
		return -1;
	}

	public function get($file) {
		return $file !== '' ? null :
			new CacheEntry([
				'fileid' => -1,
				'parent' => -1,
				'name' => '',
				'path' => '',
				'size' => '0',
				'mtime' => time(),
				'storage_mtime' => time(),
				'etag' => '',
				'mimetype' => FileInfo::MIMETYPE_FOLDER,
				'mimepart' => 'httpd',
				'permissions' => Constants::PERMISSION_READ
			]);
	}

	public function getFolderContents($folder) {
		return [];
	}

	public function getFolderContentsById($fileId) {
		return [];
	}

	public function put($file, array $data) {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function insert($file, array $data) {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function update($id, array $data) {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function getId($file) {
		return -1;
	}

	public function getParentId($file) {
		return -1;
	}

	public function inCache($file) {
		return $file === '';
	}

	public function remove($file) {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function move($source, $target) {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function moveFromCache(ICache $sourceCache, $sourcePath, $targetPath) {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function getStatus($file) {
		return ICache::COMPLETE;
	}

	public function search($pattern) {
		return [];
	}

	public function searchByMime($mimetype) {
		return [];
	}

	public function searchQuery(ISearchQuery $query) {
		return [];
	}

	public function getIncomplete() {
		return [];
	}

	public function getPathById($id) {
		return '';
	}

	public function normalize($path) {
		return $path;
	}

}
