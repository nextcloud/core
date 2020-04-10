<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Files\Config;

use OCP\Files\Config\ICachedMountFileInfo;
use OCP\IUser;

class CachedMountFileInfo extends CachedMountInfo implements ICachedMountFileInfo {
	/** @var string */
	private $internalPath;

	public function __construct(IUser $user, $storageId, $rootId, $mountPoint, $mountId = null, $rootInternalPath = '', $internalPath) {
		parent::__construct($user, $storageId, $rootId, $mountPoint, $mountId, $rootInternalPath);
		$this->internalPath = $internalPath;
	}

	public function getInternalPath() {
		if ($this->getRootInternalPath()) {
			return substr($this->internalPath, strlen($this->getRootInternalPath()) + 1);
		} else {
			return $this->internalPath;
		}
	}

	public function getPath() {
		return $this->getMountPoint() . $this->getInternalPath();
	}
}
