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

namespace OCA\Files_External\Config;

use OC\Files\Mount\MountPoint;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Lib\Auth\Password\SessionCredentials;

class ExternalMountPoint extends MountPoint {

	/** @var StorageConfig */
	protected $storageConfig;

	public function __construct(StorageConfig $storageConfig, $storage, $mountpoint, $arguments = null, $loader = null, $mountOptions = null, $mountId = null) {
		$this->storageConfig = $storageConfig;
		parent::__construct($storage, $mountpoint, $arguments, $loader, $mountOptions, $mountId);
	}

	public function getMountType() {
		return ($this->storageConfig->getAuthMechanism() instanceof SessionCredentials) ? 'external-session' : 'external';
	}
}
