<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
use OCP\Files\Config\IUserMountCache;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Service class to read global storages applicable to the user
 * Read-only access available, attempting to write will throw DomainException
 */
class UserGlobalStoragesService extends GlobalStoragesService {

	use UserTrait;

	/** @var IGroupManager */
	protected $groupManager;

	/**
	 * @param BackendService $backendService
	 * @param DBConfigService $dbConfig
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 * @param IUserMountCache $userMountCache
	 */
	public function __construct(
		BackendService $backendService,
		DBConfigService $dbConfig,
		IUserSession $userSession,
		IGroupManager $groupManager,
		IUserMountCache $userMountCache
	) {
		parent::__construct($backendService, $dbConfig, $userMountCache);
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
	}

	/**
	 * Replace config hash ID with real IDs, for migrating legacy storages
	 *
	 * @param StorageConfig[] $storages Storages with real IDs
	 * @param StorageConfig[] $storagesWithConfigHash Storages with config hash IDs
	 */
	protected function setRealStorageIds(array &$storages, array $storagesWithConfigHash) {
		// as a read-only view, storage IDs don't need to be real
		foreach ($storagesWithConfigHash as $storage) {
			$storages[$storage->getId()] = $storage;
		}
	}

	protected function readDBConfig() {
		$userMounts = $this->dbConfig->getAdminMountsFor(DBConfigService::APPLICABLE_TYPE_USER, $this->getUser()->getUID());
		$globalMounts = $this->dbConfig->getAdminMountsFor(DBConfigService::APPLICABLE_TYPE_GLOBAL, null);
		$groups = $this->groupManager->getUserGroupIds($this->getUser());
		if (is_array($groups) && count($groups) !== 0) {
			$groupMounts = $this->dbConfig->getAdminMountsForMultiple(DBConfigService::APPLICABLE_TYPE_GROUP, $groups);
		} else {
			$groupMounts = [];
		}
		return array_merge($userMounts, $groupMounts, $globalMounts);
	}

	public function addStorage(StorageConfig $newStorage) {
		throw new \DomainException('UserGlobalStoragesService writing disallowed');
	}

	public function updateStorage(StorageConfig $updatedStorage) {
		throw new \DomainException('UserGlobalStoragesService writing disallowed');
	}

	/**
	 * @param integer $id
	 */
	public function removeStorage($id) {
		throw new \DomainException('UserGlobalStoragesService writing disallowed');
	}

	/**
	 * Get unique storages, in case two are defined with the same mountpoint
	 * Higher priority storages take precedence
	 *
	 * @return StorageConfig[]
	 */
	public function getUniqueStorages() {
		$storages = $this->getStorages();

		$storagesByMountpoint = [];
		foreach ($storages as $storage) {
			$storagesByMountpoint[$storage->getMountPoint()][] = $storage;
		}

		$result = [];
		foreach ($storagesByMountpoint as $storageList) {
			$storage = array_reduce($storageList, function ($carry, $item) {
				if (isset($carry)) {
					$carryPriorityType = $this->getPriorityType($carry);
					$itemPriorityType = $this->getPriorityType($item);
					if ($carryPriorityType > $itemPriorityType) {
						return $carry;
					} elseif ($carryPriorityType === $itemPriorityType) {
						if ($carry->getPriority() > $item->getPriority()) {
							return $carry;
						}
					}
				}
				return $item;
			});
			$result[$storage->getID()] = $storage;
		}

		return $result;
	}

	/**
	 * Get a priority 'type', where a bigger number means higher priority
	 * user applicable > group applicable > 'all'
	 *
	 * @param StorageConfig $storage
	 * @return int
	 */
	protected function getPriorityType(StorageConfig $storage) {
		$applicableUsers = $storage->getApplicableUsers();
		$applicableGroups = $storage->getApplicableGroups();

		if ($applicableUsers && $applicableUsers[0] !== 'all') {
			return 2;
		}
		if ($applicableGroups) {
			return 1;
		}
		return 0;
	}

	protected function isApplicable(StorageConfig $config) {
		$applicableUsers = $config->getApplicableUsers();
		$applicableGroups = $config->getApplicableGroups();

		if (count($applicableUsers) === 0 && count($applicableGroups) === 0) {
			return true;
		}
		if (in_array($this->getUser()->getUID(), $applicableUsers, true)) {
			return true;
		}
		$groupIds = $this->groupManager->getUserGroupIds($this->getUser());
		foreach ($groupIds as $groupId) {
			if (in_array($groupId, $applicableGroups, true)) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Gets all storages for the user, admin, personal, global, etc
	 *
	 * @return StorageConfig[] array of storage configs
	 */
	public function getAllStoragesForUser() {
		if (is_null($this->getUser())) {
			return [];
		}
		$groupIds = $this->groupManager->getUserGroupIds($this->getUser());
		$mounts = $this->dbConfig->getMountsForUser($this->getUser()->getUID(), $groupIds);
		$configs = array_map([$this, 'getStorageConfigFromDBMount'], $mounts);
		$configs = array_filter($configs, function ($config) {
			return $config instanceof StorageConfig;
		});

		$keys = array_map(function (StorageConfig $config) {
			return $config->getId();
		}, $configs);

		$storages = array_combine($keys, $configs);
		return array_filter($storages, [$this, 'validateStorage']);
	}
}
