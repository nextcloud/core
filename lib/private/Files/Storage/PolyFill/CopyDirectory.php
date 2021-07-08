<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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
namespace OC\Files\Storage\PolyFill;

trait CopyDirectory {
	/**
	 * Check if a path is a directory
	 *
	 * @param string $path
	 * @return bool
	 */
	abstract public function is_dir($path);

	/**
	 * Check if a file or folder exists
	 *
	 * @param string $path
	 * @return bool
	 */
	abstract public function file_exists($path);

	/**
	 * Delete a file or folder
	 *
	 * @param string $path
	 * @return bool
	 */
	abstract public function unlink($path);

	/**
	 * Open a directory handle for a folder
	 *
	 * @param string $path
	 * @return resource | bool
	 */
	abstract public function opendir($path);

	/**
	 * Create a new folder
	 *
	 * @param string $path
	 * @return bool
	 */
	abstract public function mkdir($path);

	public function copy($path1, $path2) {
		if ($this->is_dir($path1)) {
			if ($this->file_exists($path2)) {
				$this->unlink($path2);
			}
			$this->mkdir($path2);
			return $this->copyRecursive($path1, $path2);
		} else {
			return parent::copy($path1, $path2);
		}
	}

	/**
	 * For adapters that don't support copying folders natively
	 *
	 * @param $source
	 * @param $target
	 * @return bool
	 */
	protected function copyRecursive($source, $target) {
		$dh = $this->opendir($source);
		$result = true;
		while ($file = readdir($dh)) {
			if (!\OC\Files\Filesystem::isIgnoredDir($file)) {
				if ($this->is_dir($source . '/' . $file)) {
					$this->mkdir($target . '/' . $file);
					$result = $this->copyRecursive($source . '/' . $file, $target . '/' . $file);
				} else {
					$result = parent::copy($source . '/' . $file, $target . '/' . $file);
				}
				if (!$result) {
					break;
				}
			}
		}
		return $result;
	}
}
