<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OC\Files\Storage;

use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorageFactory;

class StorageFactory implements IStorageFactory {
	/**
	 * @var array[] [$name=>['priority'=>$priority, 'wrapper'=>$callable] $storageWrappers
	 */
	private $storageWrappers = [];

	/**
	 * allow modifier storage behaviour by adding wrappers around storages
	 *
	 * $callback should be a function of type (string $mountPoint, Storage $storage) => Storage
	 *
	 * @param string $wrapperName name of the wrapper
	 * @param callable $callback callback
	 * @param int $priority wrappers with the lower priority are applied last (meaning they get called first)
	 * @param \OCP\Files\Mount\IMountPoint[] $existingMounts existing mount points to apply the wrapper to
	 * @return bool true if the wrapper was added, false if there was already a wrapper with this
	 * name registered
	 */
	public function addStorageWrapper($wrapperName, $callback, $priority = 50, $existingMounts = []) {
		if (isset($this->storageWrappers[$wrapperName])) {
			return false;
		}

		// apply to existing mounts before registering it to prevent applying it double in MountPoint::createStorage
		foreach ($existingMounts as $mount) {
			$mount->wrapStorage($callback);
		}

		$this->storageWrappers[$wrapperName] = ['wrapper' => $callback, 'priority' => $priority];
		return true;
	}

	/**
	 * Remove a storage wrapper by name.
	 * Note: internal method only to be used for cleanup
	 *
	 * @param string $wrapperName name of the wrapper
	 * @internal
	 */
	public function removeStorageWrapper($wrapperName) {
		unset($this->storageWrappers[$wrapperName]);
	}

	/**
	 * Create an instance of a storage and apply the registered storage wrappers
	 *
	 * @param \OCP\Files\Mount\IMountPoint $mountPoint
	 * @param string $class
	 * @param array $arguments
	 * @return \OCP\Files\Storage
	 */
	public function getInstance(IMountPoint $mountPoint, $class, $arguments) {
		return $this->wrap($mountPoint, new $class($arguments));
	}

	/**
	 * @param \OCP\Files\Mount\IMountPoint $mountPoint
	 * @param \OCP\Files\Storage $storage
	 * @return \OCP\Files\Storage
	 */
	public function wrap(IMountPoint $mountPoint, $storage) {
		$wrappers = array_values($this->storageWrappers);
		usort($wrappers, function ($a, $b) {
			return $b['priority'] - $a['priority'];
		});
		/** @var callable[] $wrappers */
		$wrappers = array_map(function ($wrapper) {
			return $wrapper['wrapper'];
		}, $wrappers);
		foreach ($wrappers as $wrapper) {
			$storage = $wrapper($mountPoint->getMountPoint(), $storage, $mountPoint);
			if (!($storage instanceof \OCP\Files\Storage)) {
				throw new \Exception('Invalid result from storage wrapper');
			}
		}
		return $storage;
	}
}
