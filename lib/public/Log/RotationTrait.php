<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCP\Log;

/**
 * Trait RotationTrait
 *
 *
 * @since 14.0.0
 */
trait RotationTrait {

	/**
	 * @var string
	 * @since 14.0.0
	 */
	protected $filePath;

	/**
	 * @var int
	 * @since 14.0.0
	 */
	protected $maxSize;

	/**
	 * @return string the resulting new filepath
	 * @since 14.0.0
	 */
	protected function rotate():string {
		$rotatedFile = $this->filePath.'.1';
		rename($this->filePath, $rotatedFile);
		return $rotatedFile;
	}

	/**
	 * @return bool
	 * @since 14.0.0
	 */
	protected function shouldRotateBySize():bool {
		if ((int)$this->maxSize > 0) {
			$filesize = @filesize($this->filePath);
			if ($filesize >= (int)$this->maxSize) {
				return true;
			}
		}
		return false;
	}
}
