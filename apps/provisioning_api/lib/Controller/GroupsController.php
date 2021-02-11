<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tom Needham <tom@owncloud.com>
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

namespace OCA\Provisioning_API\Controller;

use OC\Accounts\AccountManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;

class GroupsController extends AUserData {

	/** @var ILogger */
	private $logger;

	public function __construct(string $appName,
								IRequest $request,
								IUserManager $userManager,
								IConfig $config,
								IGroupManager $groupManager,
								IUserSession $userSession,
								AccountManager $accountManager,
								IFactory $l10nFactory,
								ILogger $logger) {
		parent::__construct($appName,
			$request,
			$userManager,
			$config,
			$groupManager,
			$userSession,
			$accountManager,
			$l10nFactory
		);

		$this->logger = $logger;
	}

	/**
	 * returns a list of groups
	 *
	 * @NoAdminRequired
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return DataResponse
	 */
	public function getGroups(string $search = '', int $limit = null, int $offset = 0): DataResponse {
		$groups = $this->groupManager->search($search, $limit, $offset);
		$groups = array_map(function ($group) {
			/** @var IGroup $group */
			return $group->getGID();
		}, $groups);

		return new DataResponse(['groups' => $groups]);
	}

	/**
	 * returns a list of groups details with ids and displaynames
	 *
	 * @NoAdminRequired
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return DataResponse
	 */
	public function getGroupsDetails(string $search = '', int $limit = null, int $offset = 0): DataResponse {
		$groups = $this->groupManager->search($search, $limit, $offset);
		$groups = array_map(function ($group) {
			/** @var IGroup $group */
			return [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
				'usercount' => $group->count(),
				'disabled' => $group->countDisabled(),
				'canAdd' => $group->canAddUser(),
				'canRemove' => $group->canRemoveUser(),
			];
		}, $groups);

		return new DataResponse(['groups' => $groups]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $groupId
	 * @return DataResponse
	 * @throws OCSException
	 *
	 * @deprecated 14 Use getGroupUsers
	 */
	public function getGroup(string $groupId): DataResponse {
		return $this->getGroupUsers($groupId);
	}

	/**
	 * returns an array of users in the specified group
	 *
	 * @NoAdminRequired
	 *
	 * @param string $groupId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getGroupUsers(string $groupId): DataResponse {
		$groupId = urldecode($groupId);

		$user = $this->userSession->getUser();
		$isSubadminOfGroup = false;

		// Check the group exists
		$group = $this->groupManager->get($groupId);
		if ($group !== null) {
			$isSubadminOfGroup = $this->groupManager->getSubAdmin()->isSubAdminOfGroup($user, $group);
		} else {
			throw new OCSNotFoundException('The requested group could not be found');
		}

		// Check subadmin has access to this group
		if ($this->groupManager->isAdmin($user->getUID())
		   || $isSubadminOfGroup) {
			$users = $this->groupManager->get($groupId)->getUsers();
			$users = array_map(function ($user) {
				/** @var IUser $user */
				return $user->getUID();
			}, $users);
			$users = array_values($users);
			return new DataResponse(['users' => $users]);
		}

		throw new OCSForbiddenException();
	}

	/**
	 * returns an array of users details in the specified group
	 *
	 * @NoAdminRequired
	 *
	 * @param string $groupId
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getGroupUsersDetails(string $groupId, string $search = '', int $limit = null, int $offset = 0): DataResponse {
		$groupId = urldecode($groupId);
		$currentUser = $this->userSession->getUser();

		// Check the group exists
		$group = $this->groupManager->get($groupId);
		if ($group !== null) {
			$isSubadminOfGroup = $this->groupManager->getSubAdmin()->isSubAdminOfGroup($currentUser, $group);
		} else {
			throw new OCSException('The requested group could not be found', \OCP\API::RESPOND_NOT_FOUND);
		}

		// Check subadmin has access to this group
		if ($this->groupManager->isAdmin($currentUser->getUID()) || $isSubadminOfGroup) {
			$users = $group->searchUsers($search, $limit, $offset);

			// Extract required number
			$usersDetails = [];
			foreach ($users as $user) {
				try {
					/** @var IUser $user */
					$userId = (string)$user->getUID();
					$userData = $this->getUserData($userId);
					// Do not insert empty entry
					if (!empty($userData)) {
						$usersDetails[$userId] = $userData;
					} else {
						// Logged user does not have permissions to see this user
						// only showing its id
						$usersDetails[$userId] = ['id' => $userId];
					}
				} catch (OCSNotFoundException $e) {
					// continue if a users ceased to exist.
				}
			}
			return new DataResponse(['users' => $usersDetails]);
		}

		throw new OCSException('User does not have access to specified group', \OCP\API::RESPOND_UNAUTHORISED);
	}

	/**
	 * creates a new group
	 *
	 * @PasswordConfirmationRequired
	 *
	 * @param string $groupid
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function addGroup(string $groupid): DataResponse {
		// Validate name
		if (empty($groupid)) {
			$this->logger->error('Group name not supplied', ['app' => 'provisioning_api']);
			throw new OCSException('Invalid group name', 101);
		}
		// Check if it exists
		if ($this->groupManager->groupExists($groupid)) {
			throw new OCSException('group exists', 102);
		}
		$this->groupManager->createGroup($groupid);
		return new DataResponse();
	}

	/**
	 * @PasswordConfirmationRequired
	 *
	 * @param string $groupId
	 * @param string $key
	 * @param string $value
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function updateGroup(string $groupId, string $key, string $value): DataResponse {
		$groupId = urldecode($groupId);

		if ($key === 'displayname') {
			$group = $this->groupManager->get($groupId);
			if ($group->setDisplayName($value)) {
				return new DataResponse();
			}

			throw new OCSException('Not supported by backend', 101);
		} else {
			throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
		}
	}

	/**
	 * @PasswordConfirmationRequired
	 *
	 * @param string $groupId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function deleteGroup(string $groupId): DataResponse {
		$groupId = urldecode($groupId);

		// Check it exists
		if (!$this->groupManager->groupExists($groupId)) {
			throw new OCSException('', 101);
		} elseif ($groupId === 'admin' || !$this->groupManager->get($groupId)->delete()) {
			// Cannot delete admin group
			throw new OCSException('', 102);
		}

		return new DataResponse();
	}

	/**
	 * @param string $groupId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getSubAdminsOfGroup(string $groupId): DataResponse {
		// Check group exists
		$targetGroup = $this->groupManager->get($groupId);
		if ($targetGroup === null) {
			throw new OCSException('Group does not exist', 101);
		}

		/** @var IUser[] $subadmins */
		$subadmins = $this->groupManager->getSubAdmin()->getGroupsSubAdmins($targetGroup);
		// New class returns IUser[] so convert back
		$uids = [];
		foreach ($subadmins as $user) {
			$uids[] = $user->getUID();
		}

		return new DataResponse($uids);
	}
}
