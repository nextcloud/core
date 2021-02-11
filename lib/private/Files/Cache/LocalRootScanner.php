<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\Cache;

class LocalRootScanner extends Scanner {
	public function scanFile($file, $reuseExisting = 0, $parentId = -1, $cacheData = null, $lock = true, $data = null) {
		if ($this->shouldScanPath($file)) {
			return parent::scanFile($file, $reuseExisting, $parentId, $cacheData, $lock, $data);
		} else {
			return null;
		}
	}

	public function scan($path, $recursive = self::SCAN_RECURSIVE, $reuse = -1, $lock = true) {
		if ($this->shouldScanPath($path)) {
			return parent::scan($path, $recursive, $reuse, $lock);
		} else {
			return null;
		}
	}

	private function shouldScanPath(string $path): bool {
		$path = trim($path, '/');
		return $path === '' || strpos($path, 'appdata_') === 0 || strpos($path, '__groupfolders') === 0;
	}
}
