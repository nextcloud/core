<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\NotFoundException;

use OCP\Files\Config\IUserMountCache;
use OCP\IUserSession;

/**
 * Service class to manage user external storages
 * (aka personal storages)
 */
class UserStoragesService extends StoragesService {
	use UserTrait;

	/**
	 * Create a user storages service
	 *
	 * @param BackendService $backendService
	 * @param DBConfigService $dbConfig
	 * @param IUserSession $userSession user session
	 * @param IUserMountCache $userMountCache
	 */
	public function __construct(
		BackendService $backendService,
		DBConfigService $dbConfig,
		IUserSession $userSession,
		IUserMountCache $userMountCache
	) {
		$this->userSession = $userSession;
		parent::__construct($backendService, $dbConfig, $userMountCache);
	}

	protected function readDBConfig() {
		return $this->dbConfig->getUserMountsFor(DBConfigService::APPLICABLE_TYPE_USER, $this->getUser()->getUID());
	}

	/**
	 * Triggers $signal for all applicable users of the given
	 * storage
	 *
	 * @param StorageConfig $storage storage data
	 * @param string $signal signal to trigger
	 */
	protected function triggerHooks(StorageConfig $storage, $signal) {
		$user = $this->getUser()->getUID();

		// trigger hook for the current user
		$this->triggerApplicableHooks(
			$signal,
			$storage->getMountPoint(),
			\OC_Mount_Config::MOUNT_TYPE_USER,
			[$user]
		);
	}

	/**
	 * Triggers signal_create_mount or signal_delete_mount to
	 * accommodate for additions/deletions in applicableUsers
	 * and applicableGroups fields.
	 *
	 * @param StorageConfig $oldStorage old storage data
	 * @param StorageConfig $newStorage new storage data
	 */
	protected function triggerChangeHooks(StorageConfig $oldStorage, StorageConfig $newStorage) {
		// if mount point changed, it's like a deletion + creation
		if ($oldStorage->getMountPoint() !== $newStorage->getMountPoint()) {
			$this->triggerHooks($oldStorage, Filesystem::signal_delete_mount);
			$this->triggerHooks($newStorage, Filesystem::signal_create_mount);
		}
	}

	protected function getType() {
		return DBConfigService::MOUNT_TYPE_PERSONAl;
	}

	/**
	 * Add new storage to the configuration
	 *
	 * @param StorageConfig $newStorage storage attributes
	 *
	 * @return StorageConfig storage config, with added id
	 */
	public function addStorage(StorageConfig $newStorage) {
		$newStorage->setApplicableUsers([$this->getUser()->getUID()]);
		return parent::addStorage($newStorage);
	}

	/**
	 * Update storage to the configuration
	 *
	 * @param StorageConfig $updatedStorage storage attributes
	 *
	 * @return StorageConfig storage config
	 * @throws NotFoundException if the given storage does not exist in the config
	 */
	public function updateStorage(StorageConfig $updatedStorage) {
		$updatedStorage->setApplicableUsers([$this->getUser()->getUID()]);
		return parent::updateStorage($updatedStorage);
	}

	/**
	 * Get the visibility type for this controller, used in validation
	 *
	 * @return string BackendService::VISIBILITY_* constants
	 */
	public function getVisibilityType() {
		return BackendService::VISIBILITY_PERSONAL;
	}

	protected function isApplicable(StorageConfig $config) {
		return ($config->getApplicableUsers() === [$this->getUser()->getUID()]) && $config->getType() === StorageConfig::MOUNT_TYPE_PERSONAl;
	}
}
