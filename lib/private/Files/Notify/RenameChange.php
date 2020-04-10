<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\Notify;

use OCP\Files\Notify\IRenameChange;

class RenameChange extends Change implements IRenameChange {
	/** @var string */
	private $targetPath;

	/**
	 * Change constructor.
	 *
	 * @param int $type
	 * @param string $path
	 * @param string $targetPath
	 */
	public function __construct($type, $path, $targetPath) {
		parent::__construct($type, $path);
		$this->targetPath = $targetPath;
	}

	/**
	 * Get the new path of the renamed file relative to the storage root
	 *
	 * @return string
	 */
	public function getTargetPath() {
		return $this->targetPath;
	}
}
