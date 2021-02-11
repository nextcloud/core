<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OC\Collaboration\Collaborators;

use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IShare;
use OCP\UserStatus\IManager as IUserStatusManager;

class UserPlugin implements ISearchPlugin {
	/* @var bool */
	protected $shareWithGroupOnly;
	protected $shareeEnumeration;
	protected $shareeEnumerationInGroupOnly;

	/** @var IConfig */
	private $config;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IUserSession */
	private $userSession;
	/** @var IUserManager */
	private $userManager;
	/** @var IUserStatusManager */
	private $userStatusManager;

	/**
	 * UserPlugin constructor.
	 *
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IUserSession $userSession
	 * @param IUserStatusManager $userStatusManager
	 */
	public function __construct(IConfig $config,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IUserSession $userSession,
								IUserStatusManager $userStatusManager) {
		$this->config = $config;

		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->userStatusManager = $userStatusManager;

		$this->shareWithGroupOnly = $this->config->getAppValue('core', 'shareapi_only_share_with_group_members', 'no') === 'yes';
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->shareeEnumerationInGroupOnly = $this->shareeEnumeration && $this->config->getAppValue('core', 'shareapi_restrict_user_enumeration_to_group', 'no') === 'yes';
	}

	public function search($search, $limit, $offset, ISearchResult $searchResult) {
		$result = ['wide' => [], 'exact' => []];
		$users = [];
		$hasMoreResults = false;

		$currentUserGroups = $this->groupManager->getUserGroupIds($this->userSession->getUser());
		if ($this->shareWithGroupOnly) {
			// Search in all the groups this user is part of
			foreach ($currentUserGroups as $userGroupId) {
				$usersInGroup = $this->groupManager->displayNamesInGroup($userGroupId, $search, $limit, $offset);
				foreach ($usersInGroup as $userId => $displayName) {
					$userId = (string) $userId;
					$user = $this->userManager->get($userId);
					if (!$user->isEnabled()) {
						// Ignore disabled users
						continue;
					}
					$users[$userId] = $user;
				}
				if (count($usersInGroup) >= $limit) {
					$hasMoreResults = true;
				}
			}
		} else {
			// Search in all users
			$usersTmp = $this->userManager->searchDisplayName($search, $limit, $offset);
			foreach ($usersTmp as $user) {
				if ($user->isEnabled()) { // Don't keep deactivated users
					$users[$user->getUID()] = $user;
				}
			}
		}

		$this->takeOutCurrentUser($users);

		if (!$this->shareeEnumeration || count($users) < $limit) {
			$hasMoreResults = true;
		}

		$foundUserById = false;
		$lowerSearch = strtolower($search);
		$userStatuses = $this->userStatusManager->getUserStatuses(array_keys($users));
		foreach ($users as $uid => $user) {
			$userDisplayName = $user->getDisplayName();
			$userEmail = $user->getEMailAddress();
			$uid = (string) $uid;

			$status = [];
			if (array_key_exists($uid, $userStatuses)) {
				$userStatus = $userStatuses[$uid];
				$status = [
					'status' => $userStatus->getStatus(),
					'message' => $userStatus->getMessage(),
					'icon' => $userStatus->getIcon(),
					'clearAt' => $userStatus->getClearAt()
						? (int)$userStatus->getClearAt()->format('U')
						: null,
				];
			}


			if (
				$lowerSearch !== '' && (strtolower($uid) === $lowerSearch ||
				strtolower($userDisplayName) === $lowerSearch ||
				strtolower($userEmail) === $lowerSearch)
			) {
				if (strtolower($uid) === $lowerSearch) {
					$foundUserById = true;
				}
				$result['exact'][] = [
					'label' => $userDisplayName,
					'subline' => $status['message'] ?? '',
					'icon' => 'icon-user',
					'value' => [
						'shareType' => IShare::TYPE_USER,
						'shareWith' => $uid,
					],
					'shareWithDisplayNameUnique' => !empty($userEmail) ? $userEmail : $uid,
					'status' => $status,
				];
			} else {
				$addToWideResults = false;
				if ($this->shareeEnumeration && !$this->shareeEnumerationInGroupOnly) {
					$addToWideResults = true;
				}

				if ($this->shareeEnumerationInGroupOnly) {
					$commonGroups = array_intersect($currentUserGroups, $this->groupManager->getUserGroupIds($user));
					if (!empty($commonGroups)) {
						$addToWideResults = true;
					}
				}

				if ($addToWideResults) {
					$result['wide'][] = [
						'label' => $userDisplayName,
						'subline' => $status['message'] ?? '',
						'icon' => 'icon-user',
						'value' => [
							'shareType' => IShare::TYPE_USER,
							'shareWith' => $uid,
						],
						'shareWithDisplayNameUnique' => !empty($userEmail) ? $userEmail : $uid,
						'status' => $status,
					];
				}
			}
		}

		if ($offset === 0 && !$foundUserById) {
			// On page one we try if the search result has a direct hit on the
			// user id and if so, we add that to the exact match list
			$user = $this->userManager->get($search);
			if ($user instanceof IUser) {
				$addUser = true;

				if ($this->shareWithGroupOnly) {
					// Only add, if we have a common group
					$commonGroups = array_intersect($currentUserGroups, $this->groupManager->getUserGroupIds($user));
					$addUser = !empty($commonGroups);
				}

				if ($addUser) {
					$status = [];
					$uid = $user->getUID();
					$userEmail = $user->getEMailAddress();
					if (array_key_exists($user->getUID(), $userStatuses)) {
						$userStatus = $userStatuses[$user->getUID()];
						$status = [
							'status' => $userStatus->getStatus(),
							'message' => $userStatus->getMessage(),
							'icon' => $userStatus->getIcon(),
							'clearAt' => $userStatus->getClearAt()
								? (int)$userStatus->getClearAt()->format('U')
								: null,
						];
					}

					$result['exact'][] = [
						'label' => $user->getDisplayName(),
						'icon' => 'icon-user',
						'subline' => $status['message'] ?? '',
						'value' => [
							'shareType' => IShare::TYPE_USER,
							'shareWith' => $user->getUID(),
						],
						'shareWithDisplayNameUnique' => $userEmail !== null && $userEmail !== '' ? $userEmail : $uid,
						'status' => $status,
					];
				}
			}
		}



		$type = new SearchResultType('users');
		$searchResult->addResultSet($type, $result['wide'], $result['exact']);
		if (count($result['exact'])) {
			$searchResult->markExactIdMatch($type);
		}

		return $hasMoreResults;
	}

	public function takeOutCurrentUser(array &$users) {
		$currentUser = $this->userSession->getUser();
		if (!is_null($currentUser)) {
			if (isset($users[$currentUser->getUID()])) {
				unset($users[$currentUser->getUID()]);
			}
		}
	}
}
