<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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

namespace OC\Avatar;

use OC\Files\AppData\Factory as AppDataFactory;
use OCP\AvatarProviderException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IAvatar;
use OCP\IAvatarProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as L10NFactory;
use Psr\Log\LoggerInterface;

class UserAvatarProvider implements IAvatarProvider {

	/** @var IUserManager */
	private $userManager;

	/** @var IAppData */
	private $appData;

	/** @var IL10N */
	private $l;

	/** @var LoggerInterface */
	private $logger;

	/** @var IConfig */
	private $config;

	/** @var IUser|null */
	protected $currentUser;

	public function __construct(
			IUserManager $userManager,
			AppDataFactory $appDataFactory,
			L10NFactory $lFactory,
			LoggerInterface $logger,
			IConfig $config,
			IUserSession $userSession) {
		$this->userManager = $userManager;
		$this->appData = $appDataFactory->get('avatar');
		$this->l = $lFactory->get('lib');
		$this->logger = $logger;
		$this->config = $config;
		$this->currentUser = $userSession->getUser();
	}

	/**
	 * Returns a UserAvatar instance for the given user id
	 *
	 * @param string $id the user id
	 * @returns IAvatar
	 * @throws AvatarProviderException if the user name does not exist
	 * @throws NotFoundException if there is no user folder yet
	 */
	public function getAvatar(string $id): IAvatar {
		$user = $this->userManager->get($id);
		if ($user === null) {
			throw new AvatarProviderException('user ' . $id . ' does not exist');
		}

		// sanitize userID - fixes casing issue (needed for the filesystem stuff that is done below)
		$userId = $user->getUID();

		try {
			$folder = $this->appData->getFolder($userId);
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder($userId);
		}

		return new UserAvatar($folder, $this->l, $user, $this->logger, $this->config);
	}

	/**
	 * Returns whether the current user can access the given avatar or not
	 *
	 * @param IAvatar $avatar ignored
	 * @return bool true, as all users can access user avatars
	 */
	public function canBeAccessedByCurrentUser(IAvatar $avatar): bool {
		return true;
	}

	/**
	 * Returns whether the current user can modify the given avatar or not
	 *
	 * @param IAvatar $avatar the avatar to check
	 * @return bool true if the current user is the user of the avatar, false
	 *         otherwise
	 * @throws \InvalidArgumentException if the given avatar is not a UserAvatar
	 */
	public function canBeModifiedByCurrentUser(IAvatar $avatar): bool {
		if (!($avatar instanceof UserAvatar)) {
			throw new \InvalidArgumentException();
		}

		if (!$this->currentUser) {
			return false;
		}

		return $avatar->getUser()->getUID() === $this->currentUser->getUID();
	}

	/**
	 * Returns the latest value of the avatar version
	 *
	 * @param IAvatar $avatar the avatar to check
	 * @return int the latest value of the avatar version
	 * @throws \InvalidArgumentException if the given avatar is not a UserAvatar
	 */
	public function getVersion(IAvatar $avatar): int {
		if (!($avatar instanceof UserAvatar)) {
			throw new \InvalidArgumentException();
		}

		return (int) $this->config->getUserValue($avatar->getUser()->getUID(), 'avatar', 'version', 0);
	}

	/**
	 * Returns the cache duration for user avatars in seconds
	 *
	 * @param IAvatar $avatar ignored, same duration for all user avatars
	 * @return int|null the cache duration
	 */
	public function getCacheTimeToLive(IAvatar $avatar): ?int {
		// Cache for 1 day
		return 60 * 60 * 24;
	}
}
