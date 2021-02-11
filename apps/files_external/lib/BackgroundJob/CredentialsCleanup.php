<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\BackgroundJob;

use OCA\Files_External\Lib\Auth\Password\LoginCredentials;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\UserGlobalStoragesService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Security\ICredentialsManager;
use OCP\IUser;
use OCP\IUserManager;

class CredentialsCleanup extends TimedJob {
	private $credentialsManager;
	private $userGlobalStoragesService;
	private $userManager;

	public function __construct(
		ITimeFactory $time,
		ICredentialsManager $credentialsManager,
		UserGlobalStoragesService $userGlobalStoragesService,
		IUserManager $userManager
	) {
		parent::__construct($time);

		$this->credentialsManager = $credentialsManager;
		$this->userGlobalStoragesService = $userGlobalStoragesService;
		$this->userManager = $userManager;

		// run every day
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument) {
		$this->userManager->callForSeenUsers(function (IUser $user) {
			$storages = $this->userGlobalStoragesService->getAllStoragesForUser($user);

			$usesLoginCredentials = array_reduce($storages, function (bool $uses, StorageConfig $storage) {
				return $uses || $storage->getAuthMechanism() instanceof LoginCredentials;
			}, false);

			if (!$usesLoginCredentials) {
				$this->credentialsManager->delete($user->getUID(), LoginCredentials::CREDENTIALS_IDENTIFIER);
			}
		});
	}
}
