<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Files_External\Config;

use OC\Files\Storage\FailedStorage;
use OC\Files\Storage\Wrapper\Availability;
use OCA\Files_External\Lib\PersonalMount;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Migration\StorageMigrator;
use OCA\Files_External\Service\UserGlobalStoragesService;
use OCA\Files_External\Service\UserStoragesService;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Storage;
use OCP\Files\Storage\IStorageFactory;
use OCP\Files\StorageNotAvailableException;
use OCP\IUser;

/**
 * Make the old files_external config work with the new public mount config api
 */
class ConfigAdapter implements IMountProvider {

	/** @var UserStoragesService */
	private $userStoragesService;

	/** @var UserGlobalStoragesService */
	private $userGlobalStoragesService;
	/** @var StorageMigrator  */
	private $migrator;

	/**
	 * @param UserStoragesService $userStoragesService
	 * @param UserGlobalStoragesService $userGlobalStoragesService
	 * @param StorageMigrator $migrator
	 */
	public function __construct(
		UserStoragesService $userStoragesService,
		UserGlobalStoragesService $userGlobalStoragesService,
		StorageMigrator $migrator
	) {
		$this->userStoragesService = $userStoragesService;
		$this->userGlobalStoragesService = $userGlobalStoragesService;
		$this->migrator = $migrator;
	}

	/**
	 * Process storage ready for mounting
	 *
	 * @param StorageConfig $storage
	 * @param IUser $user
	 * @throws \OCP\AppFramework\QueryException
	 */
	private function prepareStorageConfig(StorageConfig &$storage, IUser $user) {
		foreach ($storage->getBackendOptions() as $option => $value) {
			$storage->setBackendOption($option, \OCA\Files_External\MountConfig::substitutePlaceholdersInConfig($value, $user->getUID()));
		}

		$objectStore = $storage->getBackendOption('objectstore');
		if ($objectStore) {
			$objectClass = $objectStore['class'];
			if (!is_subclass_of($objectClass, '\OCP\Files\ObjectStore\IObjectStore')) {
				throw new \InvalidArgumentException('Invalid object store');
			}
			$storage->setBackendOption('objectstore', new $objectClass($objectStore));
		}

		$storage->getAuthMechanism()->manipulateStorageConfig($storage, $user);
		$storage->getBackend()->manipulateStorageConfig($storage, $user);
	}

	/**
	 * Construct the storage implementation
	 *
	 * @param StorageConfig $storageConfig
	 * @return Storage
	 */
	private function constructStorage(StorageConfig $storageConfig) {
		$class = $storageConfig->getBackend()->getStorageClass();
		$storage = new $class($storageConfig->getBackendOptions());

		// auth mechanism should fire first
		$storage = $storageConfig->getBackend()->wrapStorage($storage);
		$storage = $storageConfig->getAuthMechanism()->wrapStorage($storage);

		return $storage;
	}

	/**
	 * Get all mountpoints applicable for the user
	 *
	 * @param \OCP\IUser $user
	 * @param \OCP\Files\Storage\IStorageFactory $loader
	 * @return \OCP\Files\Mount\IMountPoint[]
	 */
	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$this->migrator->migrateUser($user);

		$this->userStoragesService->setUser($user);
		$this->userGlobalStoragesService->setUser($user);

		$storageConfigs = $this->userGlobalStoragesService->getAllStoragesForUser();

		$storages = array_map(function (StorageConfig $storageConfig) use ($user) {
			try {
				$this->prepareStorageConfig($storageConfig, $user);
				return $this->constructStorage($storageConfig);
			} catch (\Exception $e) {
				// propagate exception into filesystem
				return new FailedStorage(['exception' => $e]);
			}
		}, $storageConfigs);


		\OC\Files\Cache\Storage::getGlobalCache()->loadForStorageIds(array_map(function (Storage\IStorage $storage) {
			return $storage->getId();
		}, $storages));

		$availableStorages = array_map(function (Storage\IStorage $storage, StorageConfig $storageConfig) {
			try {
				$availability = $storage->getAvailability();
				if (!$availability['available'] && !Availability::shouldRecheck($availability)) {
					$storage = new FailedStorage([
						'exception' => new StorageNotAvailableException('Storage with mount id ' . $storageConfig->getId() . ' is not available')
					]);
				}
			} catch (\Exception $e) {
				// propagate exception into filesystem
				$storage = new FailedStorage(['exception' => $e]);
			}
			return $storage;
		}, $storages, $storageConfigs);

		$mounts = array_map(function (StorageConfig $storageConfig, Storage\IStorage $storage) use ($user, $loader) {
			if ($storageConfig->getType() === StorageConfig::MOUNT_TYPE_PERSONAl) {
				return new PersonalMount(
					$this->userStoragesService,
					$storageConfig,
					$storageConfig->getId(),
					$storage,
					'/' . $user->getUID() . '/files' . $storageConfig->getMountPoint(),
					null,
					$loader,
					$storageConfig->getMountOptions(),
					$storageConfig->getId()
				);
			} else {
				return new ExternalMountPoint(
					$storageConfig,
					$storage,
					'/' . $user->getUID() . '/files' . $storageConfig->getMountPoint(),
					null,
					$loader,
					$storageConfig->getMountOptions(),
					$storageConfig->getId()
				);
			}
		}, $storageConfigs, $availableStorages);

		$this->userStoragesService->resetUser();
		$this->userGlobalStoragesService->resetUser();

		return $mounts;
	}
}
