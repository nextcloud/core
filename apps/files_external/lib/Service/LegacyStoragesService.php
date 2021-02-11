<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files_External\Service;

use OCA\Files_External\Lib\StorageConfig;
use OCP\ILogger;

/**
 * Read mount config from legacy mount.json
 */
abstract class LegacyStoragesService {
	/** @var BackendService */
	protected $backendService;

	/**
	 * Read legacy config data
	 *
	 * @return array list of mount configs
	 */
	abstract protected function readLegacyConfig();

	/**
	 * Copy legacy storage options into the given storage config object.
	 *
	 * @param StorageConfig $storageConfig storage config to populate
	 * @param string $mountType mount type
	 * @param string $applicable applicable user or group
	 * @param array $storageOptions legacy storage options
	 *
	 * @return StorageConfig populated storage config
	 */
	protected function populateStorageConfigWithLegacyOptions(
		&$storageConfig,
		$mountType,
		$applicable,
		$storageOptions
	) {
		$backend = $this->backendService->getBackend($storageOptions['backend']);
		if (!$backend) {
			throw new \UnexpectedValueException('Invalid backend ' . $storageOptions['backend']);
		}
		$storageConfig->setBackend($backend);
		if (isset($storageOptions['authMechanism']) && $storageOptions['authMechanism'] !== 'builtin::builtin') {
			$authMechanism = $this->backendService->getAuthMechanism($storageOptions['authMechanism']);
		} else {
			$authMechanism = $backend->getLegacyAuthMechanism($storageOptions);
			$storageOptions['authMechanism'] = 'null'; // to make error handling easier
		}
		if (!$authMechanism) {
			throw new \UnexpectedValueException('Invalid authentication mechanism ' . $storageOptions['authMechanism']);
		}
		$storageConfig->setAuthMechanism($authMechanism);
		$storageConfig->setBackendOptions($storageOptions['options']);
		if (isset($storageOptions['mountOptions'])) {
			$storageConfig->setMountOptions($storageOptions['mountOptions']);
		}
		if (!isset($storageOptions['priority'])) {
			$storageOptions['priority'] = $backend->getPriority();
		}
		$storageConfig->setPriority($storageOptions['priority']);
		if ($mountType === \OCA\Files_External\MountConfig::MOUNT_TYPE_USER) {
			$applicableUsers = $storageConfig->getApplicableUsers();
			if ($applicable !== 'all') {
				$applicableUsers[] = $applicable;
				$storageConfig->setApplicableUsers($applicableUsers);
			}
		} elseif ($mountType === \OCA\Files_External\MountConfig::MOUNT_TYPE_GROUP) {
			$applicableGroups = $storageConfig->getApplicableGroups();
			$applicableGroups[] = $applicable;
			$storageConfig->setApplicableGroups($applicableGroups);
		}
		return $storageConfig;
	}

	/**
	 * Read the external storages config
	 *
	 * @return StorageConfig[] map of storage id to storage config
	 */
	public function getAllStorages() {
		$mountPoints = $this->readLegacyConfig();
		/**
		 * Here is the how the horribly messy mount point array looks like
		 * from the mount.json file:
		 *
		 * $storageOptions = $mountPoints[$mountType][$applicable][$mountPath]
		 *
		 * - $mountType is either "user" or "group"
		 * - $applicable is the name of a user or group (or the current user for personal mounts)
		 * - $mountPath is the mount point path (where the storage must be mounted)
		 * - $storageOptions is a map of storage options:
		 *     - "priority": storage priority
		 *     - "backend": backend identifier
		 *     - "class": LEGACY backend class name
		 *     - "options": backend-specific options
		 *     - "authMechanism": authentication mechanism identifier
		 *     - "mountOptions": mount-specific options (ex: disable previews, scanner, etc)
		 */
		// group by storage id
		/** @var StorageConfig[] $storages */
		$storages = [];
		// for storages without id (legacy), group by config hash for
		// later processing
		$storagesWithConfigHash = [];
		foreach ($mountPoints as $mountType => $applicables) {
			foreach ($applicables as $applicable => $mountPaths) {
				foreach ($mountPaths as $rootMountPath => $storageOptions) {
					$currentStorage = null;
					/**
					 * Flag whether the config that was read already has an id.
					 * If not, it will use a config hash instead and generate
					 * a proper id later
					 *
					 * @var boolean
					 */
					$hasId = false;
					// the root mount point is in the format "/$user/files/the/mount/point"
					// we remove the "/$user/files" prefix
					$parts = explode('/', ltrim($rootMountPath, '/'), 3);
					if (count($parts) < 3) {
						// something went wrong, skip
						\OC::$server->getLogger()->error('Could not parse mount point "' . $rootMountPath . '"', ['app' => 'files_external']);
						continue;
					}
					$relativeMountPath = rtrim($parts[2], '/');
					// note: we cannot do this after the loop because the decrypted config
					// options might be needed for the config hash
					$storageOptions['options'] = \OCA\Files_External\MountConfig::decryptPasswords($storageOptions['options']);
					if (!isset($storageOptions['backend'])) {
						$storageOptions['backend'] = $storageOptions['class']; // legacy compat
					}
					if (!isset($storageOptions['authMechanism'])) {
						$storageOptions['authMechanism'] = null; // ensure config hash works
					}
					if (isset($storageOptions['id'])) {
						$configId = (int)$storageOptions['id'];
						if (isset($storages[$configId])) {
							$currentStorage = $storages[$configId];
						}
						$hasId = true;
					} else {
						// missing id in legacy config, need to generate
						// but at this point we don't know the max-id, so use
						// first group it by config hash
						$storageOptions['mountpoint'] = $rootMountPath;
						$configId = \OCA\Files_External\MountConfig::makeConfigHash($storageOptions);
						if (isset($storagesWithConfigHash[$configId])) {
							$currentStorage = $storagesWithConfigHash[$configId];
						}
					}
					if (is_null($currentStorage)) {
						// create new
						$currentStorage = new StorageConfig($configId);
						$currentStorage->setMountPoint($relativeMountPath);
					}
					try {
						$this->populateStorageConfigWithLegacyOptions(
							$currentStorage,
							$mountType,
							$applicable,
							$storageOptions
						);
						if ($hasId) {
							$storages[$configId] = $currentStorage;
						} else {
							$storagesWithConfigHash[$configId] = $currentStorage;
						}
					} catch (\UnexpectedValueException $e) {
						// don't die if a storage backend doesn't exist
						\OC::$server->getLogger()->logException($e, [
							'message' => 'Could not load storage.',
							'level' => ILogger::ERROR,
							'app' => 'files_external',
						]);
					}
				}
			}
		}

		// convert parameter values
		foreach ($storages as $storage) {
			$storage->getBackend()->validateStorageDefinition($storage);
			$storage->getAuthMechanism()->validateStorageDefinition($storage);
		}
		return $storages;
	}
}
