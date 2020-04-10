<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files_External\Migration;

use OCA\Files_External\Service\BackendService;
use OCA\Files_External\Service\DBConfigService;
use OCA\Files_External\Service\LegacyStoragesService;
use OCA\Files_External\Service\StoragesService;
use OCA\Files_External\Service\UserLegacyStoragesService;
use OCA\Files_External\Service\UserStoragesService;
use OCP\Files\Config\IUserMountCache;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IUser;

/**
 * Migrate mount config from mount.json to the database
 */
class StorageMigrator {
	/**
	 * @var BackendService
	 */
	private $backendService;

	/**
	 * @var DBConfigService
	 */
	private $dbConfig;

	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * @var ILogger
	 */
	private $logger;

	/** @var IUserMountCache  */
	private $userMountCache;

	/**
	 * StorageMigrator constructor.
	 *
	 * @param BackendService $backendService
	 * @param DBConfigService $dbConfig
	 * @param IConfig $config
	 * @param IDBConnection $connection
	 * @param ILogger $logger
	 * @param IUserMountCache $userMountCache
	 */
	public function __construct(
		BackendService $backendService,
		DBConfigService $dbConfig,
		IConfig $config,
		IDBConnection $connection,
		ILogger $logger,
		IUserMountCache $userMountCache
	) {
		$this->backendService = $backendService;
		$this->dbConfig = $dbConfig;
		$this->config = $config;
		$this->connection = $connection;
		$this->logger = $logger;
		$this->userMountCache = $userMountCache;
	}

	private function migrate(LegacyStoragesService $legacyService, StoragesService $storageService) {
		$existingStorage = $legacyService->getAllStorages();

		$this->connection->beginTransaction();
		try {
			foreach ($existingStorage as $storage) {
				$mountOptions = $storage->getMountOptions();
				if (!empty($mountOptions) && !isset($mountOptions['enable_sharing'])) {
					// existing mounts must have sharing enabled by default to avoid surprises
					$mountOptions['enable_sharing'] = true;
					$storage->setMountOptions($mountOptions);
				}
				$storageService->addStorage($storage);
			}
			$this->connection->commit();
		} catch (\Exception $e) {
			$this->logger->logException($e);
			$this->connection->rollBack();
		}
	}

	/**
	 * Migrate personal storages configured by the current user
	 *
	 * @param IUser $user
	 */
	public function migrateUser(IUser $user) {
		$dummySession = new DummyUserSession();
		$dummySession->setUser($user);
		$userId = $user->getUID();
		$userVersion = $this->config->getUserValue($userId, 'files_external', 'config_version', '0.0.0');
		if (version_compare($userVersion, '0.5.0', '<')) {
			$this->config->setUserValue($userId, 'files_external', 'config_version', '0.5.0');
			$legacyService = new UserLegacyStoragesService($this->backendService, $dummySession);
			$storageService = new UserStoragesService($this->backendService, $this->dbConfig, $dummySession, $this->userMountCache);

			$this->migrate($legacyService, $storageService);
		}
	}
}
