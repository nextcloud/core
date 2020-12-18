<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Avatar;

use OC\AppFramework\Bootstrap\Coordinator;
use OC\User\NoUserException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IAvatar;
use OCP\IAvatarManager;
use OCP\IAvatarProvider;
use OCP\IConfig;
use OCP\IServerContainer;
use Psr\Log\LoggerInterface;

/**
 * This class implements methods to access Avatar functionality
 */
class AvatarManager implements IAvatarManager {

	/** @var IAppData */
	private $appData;

	/** @var LoggerInterface  */
	private $logger;

	/** @var IConfig */
	private $config;

	/** @var IServerContainer */
	private $serverContainer;

	/** @var Coordinator */
	private $bootstrapCoordinator;

	/** @var IAvatarProvider[] */
	private $providers = [];

	/**
	 * AvatarManager constructor.
	 *
	 * @param IAppData $appData
	 * @param LoggerInterface $logger
	 * @param IConfig $config
	 * @param IServerContainer $serverContainer
	 * @param Coordinator $bootstrapCoordinator
	 */
	public function __construct(
			IAppData $appData,
			LoggerInterface $logger,
			IConfig $config,
			IServerContainer $serverContainer,
			Coordinator $bootstrapCoordinator) {
		$this->appData = $appData;
		$this->logger = $logger;
		$this->config = $config;
		$this->serverContainer = $serverContainer;
		$this->bootstrapCoordinator = $bootstrapCoordinator;
	}

	/**
	 * return a user specific instance of \OCP\IAvatar
	 * @see \OCP\IAvatar
	 * @param string $userId the ownCloud user id
	 * @return \OCP\IAvatar
	 * @throws \Exception In case the username is potentially dangerous
	 * @throws NotFoundException In case there is no user folder yet
	 */
	public function getAvatar(string $userId) : IAvatar {
		return $this->getAvatarProvider('user')->getAvatar($userId);
	}

	/**
	 * Clear generated avatars
	 */
	public function clearCachedAvatars() {
		$users = $this->config->getUsersForUserValue('avatar', 'generated', 'true');
		foreach ($users as $userId) {
			try {
				$folder = $this->appData->getFolder($userId);
				$folder->delete();
			} catch (NotFoundException $e) {
				$this->logger->debug("No cache for the user $userId. Ignoring...");
			}
			$this->config->setUserValue($userId, 'avatar', 'generated', 'false');
		}
	}

	public function deleteUserAvatar(string $userId): void {
		try {
			$folder = $this->appData->getFolder($userId);
			$folder->delete();
		} catch (NotFoundException $e) {
			$this->logger->debug("No cache for the user $userId. Ignoring avatar deletion");
		} catch (NotPermittedException $e) {
			$this->logger->error("Unable to delete user avatars for $userId. gnoring avatar deletion");
		} catch (NoUserException $e) {
			$this->logger->debug("User $userId not found. gnoring avatar deletion");
		}
		$this->config->deleteUserValue($userId, 'avatar', 'generated');
	}

	/**
	 * Returns a GuestAvatar.
	 *
	 * @param string $name The guest name, e.g. "Albert".
	 * @return IAvatar
	 */
	public function getGuestAvatar(string $name): IAvatar {
		return $this->getAvatarProvider('guest')->getAvatar($name);
	}

	public function getAvatarProvider(string $type): IAvatarProvider {
		$context = $this->bootstrapCoordinator->getRegistrationContext();

		if ($context === null) {
			throw new \RuntimeException("Avatar provider requested before the apps had been fully registered");
		}

		$providerClasses = $context->getAvatarProviders();

		if (!array_key_exists($type, $providerClasses)) {
			throw new \InvalidArgumentException('Unknown avatar type: ' . $type);
		}

		if (!array_key_exists($type, $this->providers)) {
			$this->providers[$type] = $this->serverContainer->get($providerClasses[$type]);
		}

		return $this->providers[$type];
	}
}
