<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Provisioning_API\Controller;

use OC\Accounts\AccountManager;
use OC\User\Backend;
use OC\User\NoUserException;
use OC_Helper;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\User\Backend\ISetDisplayNameBackend;
use OCP\User\Backend\ISetPasswordBackend;

abstract class AUserData extends OCSController {

	/** @var IUserManager */
	protected $userManager;
	/** @var IConfig */
	protected $config;
	/** @var IGroupManager|\OC\Group\Manager */ // FIXME Requires a method that is not on the interface
	protected $groupManager;
	/** @var IUserSession */
	protected $userSession;
	/** @var AccountManager */
	protected $accountManager;
	/** @var IFactory */
	protected $l10nFactory;

	public function __construct(string $appName,
								IRequest $request,
								IUserManager $userManager,
								IConfig $config,
								IGroupManager $groupManager,
								IUserSession $userSession,
								AccountManager $accountManager,
								IFactory $l10nFactory) {
		parent::__construct($appName, $request);

		$this->userManager = $userManager;
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
		$this->accountManager = $accountManager;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * creates a array with all user data
	 *
	 * @param string $userId
	 * @return array
	 * @throws NotFoundException
	 * @throws OCSException
	 * @throws OCSNotFoundException
	 */
	protected function getUserData(string $userId): array {
		$currentLoggedInUser = $this->userSession->getUser();

		$data = [];

		// Check if the target user exists
		$targetUserObject = $this->userManager->get($userId);
		if ($targetUserObject === null) {
			throw new OCSNotFoundException('User does not exist');
		}

		// Should be at least Admin Or SubAdmin!
		if ($this->groupManager->isAdmin($currentLoggedInUser->getUID())
			|| $this->groupManager->getSubAdmin()->isUserAccessible($currentLoggedInUser, $targetUserObject)) {
			$data['enabled'] = $this->config->getUserValue($targetUserObject->getUID(), 'core', 'enabled', 'true') === 'true';
		} else {
			// Check they are looking up themselves
			if ($currentLoggedInUser->getUID() !== $targetUserObject->getUID()) {
				return $data;
			}
		}

		// Get groups data
		$userAccount = $this->accountManager->getUser($targetUserObject);
		$groups = $this->groupManager->getUserGroups($targetUserObject);
		$gids = [];
		foreach ($groups as $group) {
			$gids[] = $group->getGID();
		}

		try {
			# might be thrown by LDAP due to handling of users disappears
			# from the external source (reasons unknown to us)
			# cf. https://github.com/nextcloud/server/issues/12991
			$data['storageLocation'] = $targetUserObject->getHome();
		} catch (NoUserException $e) {
			throw new OCSNotFoundException($e->getMessage(), $e);
		}

		// Find the data
		$data['id'] = $targetUserObject->getUID();
		$data['lastLogin'] = $targetUserObject->getLastLogin() * 1000;
		$data['backend'] = $targetUserObject->getBackendClassName();
		$data['subadmin'] = $this->getUserSubAdminGroupsData($targetUserObject->getUID());
		$data['quota'] = $this->fillStorageInfo($targetUserObject->getUID());
		$data[IAccountManager::PROPERTY_EMAIL] = $targetUserObject->getEMailAddress();
		$data[IAccountManager::PROPERTY_DISPLAYNAME] = $targetUserObject->getDisplayName();
		$data[IAccountManager::PROPERTY_PHONE] = $userAccount[IAccountManager::PROPERTY_PHONE]['value'];
		$data[IAccountManager::PROPERTY_ADDRESS] = $userAccount[IAccountManager::PROPERTY_ADDRESS]['value'];
		$data[IAccountManager::PROPERTY_WEBSITE] = $userAccount[IAccountManager::PROPERTY_WEBSITE]['value'];
		$data[IAccountManager::PROPERTY_TWITTER] = $userAccount[IAccountManager::PROPERTY_TWITTER]['value'];
		$data['groups'] = $gids;
		$data['language'] = $this->l10nFactory->getUserLanguage($targetUserObject);
		$data['locale'] = $this->config->getUserValue($targetUserObject->getUID(), 'core', 'locale');

		$backend = $targetUserObject->getBackend();
		$data['backendCapabilities'] = [
			'setDisplayName' => $backend instanceof ISetDisplayNameBackend || $backend->implementsActions(Backend::SET_DISPLAYNAME),
			'setPassword' => $backend instanceof ISetPasswordBackend || $backend->implementsActions(Backend::SET_PASSWORD),
		];

		return $data;
	}

	/**
	 * Get the groups a user is a subadmin of
	 *
	 * @param string $userId
	 * @return array
	 * @throws OCSException
	 */
	protected function getUserSubAdminGroupsData(string $userId): array {
		$user = $this->userManager->get($userId);
		// Check if the user exists
		if ($user === null) {
			throw new OCSNotFoundException('User does not exist');
		}

		// Get the subadmin groups
		$subAdminGroups = $this->groupManager->getSubAdmin()->getSubAdminsGroups($user);
		$groups = [];
		foreach ($subAdminGroups as $key => $group) {
			$groups[] = $group->getGID();
		}

		return $groups;
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 */
	protected function fillStorageInfo(string $userId): array {
		try {
			\OC_Util::tearDownFS();
			\OC_Util::setupFS($userId);
			$storage = OC_Helper::getStorageInfo('/');
			$data = [
				'free' => $storage['free'],
				'used' => $storage['used'],
				'total' => $storage['total'],
				'relative' => $storage['relative'],
				'quota' => $storage['quota'],
			];
		} catch (NotFoundException $ex) {
			// User fs is not setup yet
			$user = $this->userManager->get($userId);
			if ($user === null) {
				throw new OCSException('User does not exist', 101);
			}
			$quota = $user->getQuota();
			if ($quota !== 'none') {
				$quota = OC_Helper::computerFileSize($quota);
			}
			$data = [
				'quota' => $quota !== false ? $quota : 'none',
				'used' => 0
			];
		}
		return $data;
	}
}
