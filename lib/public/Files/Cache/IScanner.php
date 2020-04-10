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

/**
 * Scan files from the storage and save to the cache
 *
 * @since 9.0.0
 */
interface IScanner {
	const SCAN_RECURSIVE_INCOMPLETE = 2; // only recursive into not fully scanned folders
	const SCAN_RECURSIVE = true;
	const SCAN_SHALLOW = false;

	const REUSE_NONE = 0;
	const REUSE_ETAG = 1;
	const REUSE_SIZE = 2;

	/**
	 * scan a single file and store it in the cache
	 *
	 * @param string $file
	 * @param int $reuseExisting
	 * @param int $parentId
	 * @param array | null $cacheData existing data in the cache for the file to be scanned
	 * @param bool $lock set to false to disable getting an additional read lock during scanning
	 * @return array an array of metadata of the scanned file
	 * @throws \OC\ServerNotAvailableException
	 * @throws \OCP\Lock\LockedException
	 * @since 9.0.0
	 */
	public function scanFile($file, $reuseExisting = 0, $parentId = -1, $cacheData = null, $lock = true);

	/**
	 * scan a folder and all its children
	 *
	 * @param string $path
	 * @param bool $recursive
	 * @param int $reuse
	 * @param bool $lock set to false to disable getting an additional read lock during scanning
	 * @return array an array of the meta data of the scanned file or folder
	 * @since 9.0.0
	 */
	public function scan($path, $recursive = self::SCAN_RECURSIVE, $reuse = -1, $lock = true);

	/**
	 * check if the file should be ignored when scanning
	 * NOTE: files with a '.part' extension are ignored as well!
	 *       prevents unfinished put requests to be scanned
	 *
	 * @param string $file
	 * @return boolean
	 * @since 9.0.0
	 */
	public static function isPartialFile($file);

	/**
	 * walk over any folders that are not fully scanned yet and scan them
	 *
	 * @since 9.0.0
	 */
	public function backgroundScan();
}
