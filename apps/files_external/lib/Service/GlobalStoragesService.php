<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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
use OCA\Files_External\Lib\StorageConfig;

/**
 * Service class to manage global external storages
 */
class GlobalStoragesService extends StoragesService {
	/**
	 * Triggers $signal for all applicable users of the given
	 * storage
	 *
	 * @param StorageConfig $storage storage data
	 * @param string $signal signal to trigger
	 */
	protected function triggerHooks(StorageConfig $storage, $signal) {
		// FIXME: Use as expression in empty once PHP 5.4 support is dropped
		$applicableUsers = $storage->getApplicableUsers();
		$applicableGroups = $storage->getApplicableGroups();
		if (empty($applicableUsers) && empty($applicableGroups)) {
			// raise for user "all"
			$this->triggerApplicableHooks(
				$signal,
				$storage->getMountPoint(),
				\OC_Mount_Config::MOUNT_TYPE_USER,
				['all']
			);
			return;
		}

		$this->triggerApplicableHooks(
			$signal,
			$storage->getMountPoint(),
			\OC_Mount_Config::MOUNT_TYPE_USER,
			$applicableUsers
		);
		$this->triggerApplicableHooks(
			$signal,
			$storage->getMountPoint(),
			\OC_Mount_Config::MOUNT_TYPE_GROUP,
			$applicableGroups
		);
	}

	/**
	 * Triggers signal_create_mount or signal_delete_mount to
	 * accommodate for additions/deletions in applicableUsers
	 * and applicableGroups fields.
	 *
	 * @param StorageConfig $oldStorage old storage config
	 * @param StorageConfig $newStorage new storage config
	 */
	protected function triggerChangeHooks(StorageConfig $oldStorage, StorageConfig $newStorage) {
		// if mount point changed, it's like a deletion + creation
		if ($oldStorage->getMountPoint() !== $newStorage->getMountPoint()) {
			$this->triggerHooks($oldStorage, Filesystem::signal_delete_mount);
			$this->triggerHooks($newStorage, Filesystem::signal_create_mount);
			return;
		}

		$userAdditions = array_diff($newStorage->getApplicableUsers(), $oldStorage->getApplicableUsers());
		$userDeletions = array_diff($oldStorage->getApplicableUsers(), $newStorage->getApplicableUsers());
		$groupAdditions = array_diff($newStorage->getApplicableGroups(), $oldStorage->getApplicableGroups());
		$groupDeletions = array_diff($oldStorage->getApplicableGroups(), $newStorage->getApplicableGroups());

		// FIXME: Use as expression in empty once PHP 5.4 support is dropped
		// if no applicable were set, raise a signal for "all"
		$oldApplicableUsers = $oldStorage->getApplicableUsers();
		$oldApplicableGroups = $oldStorage->getApplicableGroups();
		if (empty($oldApplicableUsers) && empty($oldApplicableGroups)) {
			$this->triggerApplicableHooks(
				Filesystem::signal_delete_mount,
				$oldStorage->getMountPoint(),
				\OC_Mount_Config::MOUNT_TYPE_USER,
				['all']
			);
		}

		// trigger delete for removed users
		$this->triggerApplicableHooks(
			Filesystem::signal_delete_mount,
			$oldStorage->getMountPoint(),
			\OC_Mount_Config::MOUNT_TYPE_USER,
			$userDeletions
		);

		// trigger delete for removed groups
		$this->triggerApplicableHooks(
			Filesystem::signal_delete_mount,
			$oldStorage->getMountPoint(),
			\OC_Mount_Config::MOUNT_TYPE_GROUP,
			$groupDeletions
		);

		// and now add the new users
		$this->triggerApplicableHooks(
			Filesystem::signal_create_mount,
			$newStorage->getMountPoint(),
			\OC_Mount_Config::MOUNT_TYPE_USER,
			$userAdditions
		);

		// and now add the new groups
		$this->triggerApplicableHooks(
			Filesystem::signal_create_mount,
			$newStorage->getMountPoint(),
			\OC_Mount_Config::MOUNT_TYPE_GROUP,
			$groupAdditions
		);

		// FIXME: Use as expression in empty once PHP 5.4 support is dropped
		// if no applicable, raise a signal for "all"
		$newApplicableUsers = $newStorage->getApplicableUsers();
		$newApplicableGroups = $newStorage->getApplicableGroups();
		if (empty($newApplicableUsers) && empty($newApplicableGroups)) {
			$this->triggerApplicableHooks(
				Filesystem::signal_create_mount,
				$newStorage->getMountPoint(),
				\OC_Mount_Config::MOUNT_TYPE_USER,
				['all']
			);
		}
	}

	/**
	 * Get the visibility type for this controller, used in validation
	 *
	 * @return string BackendService::VISIBILITY_* constants
	 */
	public function getVisibilityType() {
		return BackendService::VISIBILITY_ADMIN;
	}

	protected function isApplicable(StorageConfig $config) {
		return true;
	}

	/**
	 * Get all configured admin and personal mounts
	 *
	 * @return array map of storage id to storage config
	 */
	public function getStorageForAllUsers() {
		$mounts = $this->dbConfig->getAllMounts();
		$configs = array_map([$this, 'getStorageConfigFromDBMount'], $mounts);
		$configs = array_filter($configs, function ($config) {
			return $config instanceof StorageConfig;
		});

		$keys = array_map(function (StorageConfig $config) {
			return $config->getId();
		}, $configs);

		return array_combine($keys, $configs);
	}
}
