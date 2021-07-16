<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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

namespace OCA\Files_External\Service;

use OC\Files\Filesystem;
use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Auth\InvalidAuth;
use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\Backend\InvalidBackend;
use OCA\Files_External\Lib\DefinitionParameter;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\NotFoundException;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\StorageNotAvailableException;
use OCP\ILogger;

/**
 * Service class to manage external storages
 */
abstract class StoragesService {

	/** @var BackendService */
	protected $backendService;

	/**
	 * @var DBConfigService
	 */
	protected $dbConfig;

	/**
	 * @var IUserMountCache
	 */
	protected $userMountCache;

	/**
	 * @param BackendService $backendService
	 * @param DBConfigService $dbConfigService
	 * @param IUserMountCache $userMountCache
	 */
	public function __construct(BackendService $backendService, DBConfigService $dbConfigService, IUserMountCache $userMountCache) {
		$this->backendService = $backendService;
		$this->dbConfig = $dbConfigService;
		$this->userMountCache = $userMountCache;
	}

	protected function readDBConfig() {
		return $this->dbConfig->getAdminMounts();
	}

	protected function getStorageConfigFromDBMount(array $mount) {
		$applicableUsers = array_filter($mount['applicable'], function ($applicable) {
			return $applicable['type'] === DBConfigService::APPLICABLE_TYPE_USER;
		});
		$applicableUsers = array_map(function ($applicable) {
			return $applicable['value'];
		}, $applicableUsers);

		$applicableGroups = array_filter($mount['applicable'], function ($applicable) {
			return $applicable['type'] === DBConfigService::APPLICABLE_TYPE_GROUP;
		});
		$applicableGroups = array_map(function ($applicable) {
			return $applicable['value'];
		}, $applicableGroups);

		try {
			$config = $this->createStorage(
				$mount['mount_point'],
				$mount['storage_backend'],
				$mount['auth_backend'],
				$mount['config'],
				$mount['options'],
				array_values($applicableUsers),
				array_values($applicableGroups),
				$mount['priority']
			);
			$config->setType($mount['type']);
			$config->setId((int)$mount['mount_id']);
			return $config;
		} catch (\UnexpectedValueException $e) {
			// don't die if a storage backend doesn't exist
			\OC::$server->getLogger()->logException($e, [
				'message' => 'Could not load storage.',
				'level' => ILogger::ERROR,
				'app' => 'files_external',
			]);
			return null;
		} catch (\InvalidArgumentException $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => 'Could not load storage.',
				'level' => ILogger::ERROR,
				'app' => 'files_external',
			]);
			return null;
		}
	}

	/**
	 * Read the external storages config
	 *
	 * @return array map of storage id to storage config
	 */
	protected function readConfig() {
		$mounts = $this->readDBConfig();
		$configs = array_map([$this, 'getStorageConfigFromDBMount'], $mounts);
		$configs = array_filter($configs, function ($config) {
			return $config instanceof StorageConfig;
		});

		$keys = array_map(function (StorageConfig $config) {
			return $config->getId();
		}, $configs);

		return array_combine($keys, $configs);
	}

	/**
	 * Get a storage with status
	 *
	 * @param int $id storage id
	 *
	 * @return StorageConfig
	 * @throws NotFoundException if the storage with the given id was not found
	 */
	public function getStorage($id) {
		$mount = $this->dbConfig->getMountById($id);

		if (!is_array($mount)) {
			throw new NotFoundException('Storage with ID "' . $id . '" not found');
		}

		$config = $this->getStorageConfigFromDBMount($mount);
		if ($this->isApplicable($config)) {
			return $config;
		} else {
			throw new NotFoundException('Storage with ID "' . $id . '" not found');
		}
	}

	/**
	 * Check whether this storage service should provide access to a storage
	 *
	 * @param StorageConfig $config
	 * @return bool
	 */
	abstract protected function isApplicable(StorageConfig $config);

	/**
	 * Gets all storages, valid or not
	 *
	 * @return StorageConfig[] array of storage configs
	 */
	public function getAllStorages() {
		return $this->readConfig();
	}

	/**
	 * Gets all valid storages
	 *
	 * @return StorageConfig[]
	 */
	public function getStorages() {
		return array_filter($this->getAllStorages(), [$this, 'validateStorage']);
	}

	/**
	 * Validate storage
	 * FIXME: De-duplicate with StoragesController::validate()
	 *
	 * @param StorageConfig $storage
	 * @return bool
	 */
	protected function validateStorage(StorageConfig $storage) {
		/** @var Backend */
		$backend = $storage->getBackend();
		/** @var AuthMechanism */
		$authMechanism = $storage->getAuthMechanism();

		if (!$backend->isVisibleFor($this->getVisibilityType())) {
			// not permitted to use backend
			return false;
		}
		if (!$authMechanism->isVisibleFor($this->getVisibilityType())) {
			// not permitted to use auth mechanism
			return false;
		}

		return true;
	}

	/**
	 * Get the visibility type for this controller, used in validation
	 *
	 * @return string BackendService::VISIBILITY_* constants
	 */
	abstract public function getVisibilityType();

	/**
	 * @return integer
	 */
	protected function getType() {
		return DBConfigService::MOUNT_TYPE_ADMIN;
	}

	/**
	 * Add new storage to the configuration
	 *
	 * @param StorageConfig $newStorage storage attributes
	 *
	 * @return StorageConfig storage config, with added id
	 */
	public function addStorage(StorageConfig $newStorage) {
		$allStorages = $this->readConfig();

		$configId = $this->dbConfig->addMount(
			$newStorage->getMountPoint(),
			$newStorage->getBackend()->getIdentifier(),
			$newStorage->getAuthMechanism()->getIdentifier(),
			$newStorage->getPriority(),
			$this->getType()
		);

		$newStorage->setId($configId);

		foreach ($newStorage->getApplicableUsers() as $user) {
			$this->dbConfig->addApplicable($configId, DBConfigService::APPLICABLE_TYPE_USER, $user);
		}
		foreach ($newStorage->getApplicableGroups() as $group) {
			$this->dbConfig->addApplicable($configId, DBConfigService::APPLICABLE_TYPE_GROUP, $group);
		}
		foreach ($newStorage->getBackendOptions() as $key => $value) {
			$this->dbConfig->setConfig($configId, $key, $value);
		}
		foreach ($newStorage->getMountOptions() as $key => $value) {
			$this->dbConfig->setOption($configId, $key, $value);
		}

		if (count($newStorage->getApplicableUsers()) === 0 && count($newStorage->getApplicableGroups()) === 0) {
			$this->dbConfig->addApplicable($configId, DBConfigService::APPLICABLE_TYPE_GLOBAL, null);
		}

		// add new storage
		$allStorages[$configId] = $newStorage;

		$this->triggerHooks($newStorage, Filesystem::signal_create_mount);

		$newStorage->setStatus(StorageNotAvailableException::STATUS_SUCCESS);
		return $newStorage;
	}

	/**
	 * Create a storage from its parameters
	 *
	 * @param string $mountPoint storage mount point
	 * @param string $backendIdentifier backend identifier
	 * @param string $authMechanismIdentifier authentication mechanism identifier
	 * @param array $backendOptions backend-specific options
	 * @param array|null $mountOptions mount-specific options
	 * @param array|null $applicableUsers users for which to mount the storage
	 * @param array|null $applicableGroups groups for which to mount the storage
	 * @param int|null $priority priority
	 *
	 * @return StorageConfig
	 */
	public function createStorage(
		$mountPoint,
		$backendIdentifier,
		$authMechanismIdentifier,
		$backendOptions,
		$mountOptions = null,
		$applicableUsers = null,
		$applicableGroups = null,
		$priority = null
	) {
		$backend = $this->backendService->getBackend($backendIdentifier);
		if (!$backend) {
			$backend = new InvalidBackend($backendIdentifier);
		}
		$authMechanism = $this->backendService->getAuthMechanism($authMechanismIdentifier);
		if (!$authMechanism) {
			$authMechanism = new InvalidAuth($authMechanismIdentifier);
		}
		$newStorage = new StorageConfig();
		$newStorage->setMountPoint($mountPoint);
		$newStorage->setBackend($backend);
		$newStorage->setAuthMechanism($authMechanism);
		$newStorage->setBackendOptions($backendOptions);
		if (isset($mountOptions)) {
			$newStorage->setMountOptions($mountOptions);
		}
		if (isset($applicableUsers)) {
			$newStorage->setApplicableUsers($applicableUsers);
		}
		if (isset($applicableGroups)) {
			$newStorage->setApplicableGroups($applicableGroups);
		}
		if (isset($priority)) {
			$newStorage->setPriority($priority);
		}

		return $newStorage;
	}

	/**
	 * Triggers the given hook signal for all the applicables given
	 *
	 * @param string $signal signal
	 * @param string $mountPoint hook mount pount param
	 * @param string $mountType hook mount type param
	 * @param array $applicableArray array of applicable users/groups for which to trigger the hook
	 */
	protected function triggerApplicableHooks($signal, $mountPoint, $mountType, $applicableArray) {
		foreach ($applicableArray as $applicable) {
			\OCP\Util::emitHook(
				Filesystem::CLASSNAME,
				$signal,
				[
					Filesystem::signal_param_path => $mountPoint,
					Filesystem::signal_param_mount_type => $mountType,
					Filesystem::signal_param_users => $applicable,
				]
			);
		}
	}

	/**
	 * Triggers $signal for all applicable users of the given
	 * storage
	 *
	 * @param StorageConfig $storage storage data
	 * @param string $signal signal to trigger
	 */
	abstract protected function triggerHooks(StorageConfig $storage, $signal);

	/**
	 * Triggers signal_create_mount or signal_delete_mount to
	 * accommodate for additions/deletions in applicableUsers
	 * and applicableGroups fields.
	 *
	 * @param StorageConfig $oldStorage old storage data
	 * @param StorageConfig $newStorage new storage data
	 */
	abstract protected function triggerChangeHooks(StorageConfig $oldStorage, StorageConfig $newStorage);

	/**
	 * Update storage to the configuration
	 *
	 * @param StorageConfig $updatedStorage storage attributes
	 *
	 * @return StorageConfig storage config
	 * @throws NotFoundException if the given storage does not exist in the config
	 */
	public function updateStorage(StorageConfig $updatedStorage) {
		$id = $updatedStorage->getId();

		$existingMount = $this->dbConfig->getMountById($id);

		if (!is_array($existingMount)) {
			throw new NotFoundException('Storage with ID "' . $id . '" not found while updating storage');
		}

		$oldStorage = $this->getStorageConfigFromDBMount($existingMount);

		if ($oldStorage->getBackend() instanceof InvalidBackend) {
			throw new NotFoundException('Storage with id "' . $id . '" cannot be edited due to missing backend');
		}

		$removedUsers = array_diff($oldStorage->getApplicableUsers(), $updatedStorage->getApplicableUsers());
		$removedGroups = array_diff($oldStorage->getApplicableGroups(), $updatedStorage->getApplicableGroups());
		$addedUsers = array_diff($updatedStorage->getApplicableUsers(), $oldStorage->getApplicableUsers());
		$addedGroups = array_diff($updatedStorage->getApplicableGroups(), $oldStorage->getApplicableGroups());

		$oldUserCount = count($oldStorage->getApplicableUsers());
		$oldGroupCount = count($oldStorage->getApplicableGroups());
		$newUserCount = count($updatedStorage->getApplicableUsers());
		$newGroupCount = count($updatedStorage->getApplicableGroups());
		$wasGlobal = ($oldUserCount + $oldGroupCount) === 0;
		$isGlobal = ($newUserCount + $newGroupCount) === 0;

		foreach ($removedUsers as $user) {
			$this->dbConfig->removeApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, $user);
		}
		foreach ($removedGroups as $group) {
			$this->dbConfig->removeApplicable($id, DBConfigService::APPLICABLE_TYPE_GROUP, $group);
		}
		foreach ($addedUsers as $user) {
			$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, $user);
		}
		foreach ($addedGroups as $group) {
			$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_GROUP, $group);
		}

		if ($wasGlobal && !$isGlobal) {
			$this->dbConfig->removeApplicable($id, DBConfigService::APPLICABLE_TYPE_GLOBAL, null);
		} elseif (!$wasGlobal && $isGlobal) {
			$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_GLOBAL, null);
		}

		$changedConfig = array_diff_assoc($updatedStorage->getBackendOptions(), $oldStorage->getBackendOptions());
		$changedOptions = array_diff_assoc($updatedStorage->getMountOptions(), $oldStorage->getMountOptions());

		foreach ($changedConfig as $key => $value) {
			if ($value !== DefinitionParameter::UNMODIFIED_PLACEHOLDER) {
				$this->dbConfig->setConfig($id, $key, $value);
			}
		}
		foreach ($changedOptions as $key => $value) {
			$this->dbConfig->setOption($id, $key, $value);
		}

		if ($updatedStorage->getMountPoint() !== $oldStorage->getMountPoint()) {
			$this->dbConfig->setMountPoint($id, $updatedStorage->getMountPoint());
		}

		if ($updatedStorage->getAuthMechanism()->getIdentifier() !== $oldStorage->getAuthMechanism()->getIdentifier()) {
			$this->dbConfig->setAuthBackend($id, $updatedStorage->getAuthMechanism()->getIdentifier());
		}

		$this->triggerChangeHooks($oldStorage, $updatedStorage);

		if (($wasGlobal && !$isGlobal) || count($removedGroups) > 0) { // to expensive to properly handle these on the fly
			$this->userMountCache->remoteStorageMounts($this->getStorageId($updatedStorage));
		} else {
			$storageId = $this->getStorageId($updatedStorage);
			foreach ($removedUsers as $userId) {
				$this->userMountCache->removeUserStorageMount($storageId, $userId);
			}
		}

		return $this->getStorage($id);
	}

	/**
	 * Delete the storage with the given id.
	 *
	 * @param int $id storage id
	 *
	 * @throws NotFoundException if no storage was found with the given id
	 */
	public function removeStorage($id) {
		$existingMount = $this->dbConfig->getMountById($id);

		if (!is_array($existingMount)) {
			throw new NotFoundException('Storage with ID "' . $id . '" not found');
		}

		$this->dbConfig->removeMount($id);

		$deletedStorage = $this->getStorageConfigFromDBMount($existingMount);
		$this->triggerHooks($deletedStorage, Filesystem::signal_delete_mount);

		// delete oc_storages entries and oc_filecache
		\OC\Files\Cache\Storage::cleanByMountId($id);
	}

	/**
	 * Construct the storage implementation
	 *
	 * @param StorageConfig $storageConfig
	 * @return int
	 */
	private function getStorageId(StorageConfig $storageConfig) {
		try {
			$class = $storageConfig->getBackend()->getStorageClass();
			/** @var \OC\Files\Storage\Storage $storage */
			$storage = new $class($storageConfig->getBackendOptions());

			// auth mechanism should fire first
			$storage = $storageConfig->getBackend()->wrapStorage($storage);
			$storage = $storageConfig->getAuthMechanism()->wrapStorage($storage);

			return $storage->getStorageCache()->getNumericId();
		} catch (\Exception $e) {
			return -1;
		}
	}
}
