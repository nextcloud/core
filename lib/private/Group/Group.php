<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OC\Group;

use OC\Hooks\PublicEmitter;
use OCP\Group\Backend\ICountDisabledInGroup;
use OCP\Group\Backend\IGetDisplayNameBackend;
use OCP\Group\Backend\IHideFromCollaborationBackend;
use OCP\Group\Backend\ISetDisplayNameBackend;
use OCP\GroupInterface;
use OCP\IGroup;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Group implements IGroup {
	/** @var null|string  */
	protected $displayName;

	/** @var string */
	private $gid;

	/** @var \OC\User\User[] */
	private $users = [];

	/** @var bool */
	private $usersLoaded;

	/** @var Backend[] */
	private $backends;
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var \OC\User\Manager|IUserManager  */
	private $userManager;
	/** @var PublicEmitter */
	private $emitter;


	/**
	 * @param string $gid
	 * @param Backend[] $backends
	 * @param EventDispatcherInterface $dispatcher
	 * @param IUserManager $userManager
	 * @param PublicEmitter $emitter
	 * @param string $displayName
	 */
	public function __construct(string $gid, array $backends, EventDispatcherInterface $dispatcher, IUserManager $userManager, PublicEmitter $emitter = null, ?string $displayName = null) {
		$this->gid = $gid;
		$this->backends = $backends;
		$this->dispatcher = $dispatcher;
		$this->userManager = $userManager;
		$this->emitter = $emitter;
		$this->displayName = $displayName;
	}

	public function getGID() {
		return $this->gid;
	}

	public function getDisplayName() {
		if (is_null($this->displayName)) {
			foreach ($this->backends as $backend) {
				if ($backend instanceof IGetDisplayNameBackend) {
					$displayName = $backend->getDisplayName($this->gid);
					if (trim($displayName) !== '') {
						$this->displayName = $displayName;
						return $this->displayName;
					}
				}
			}
			return $this->gid;
		}
		return $this->displayName;
	}

	public function setDisplayName(string $displayName): bool {
		$displayName = trim($displayName);
		if ($displayName !== '') {
			foreach ($this->backends as $backend) {
				if (($backend instanceof ISetDisplayNameBackend)
					&& $backend->setDisplayName($this->gid, $displayName)) {
					$this->displayName = $displayName;
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * get all users in the group
	 *
	 * @return \OC\User\User[]
	 */
	public function getUsers() {
		if ($this->usersLoaded) {
			return $this->users;
		}

		$userIds = [];
		foreach ($this->backends as $backend) {
			$diff = array_diff(
				$backend->usersInGroup($this->gid),
				$userIds
			);
			if ($diff) {
				$userIds = array_merge($userIds, $diff);
			}
		}

		$this->users = $this->getVerifiedUsers($userIds);
		$this->usersLoaded = true;
		return $this->users;
	}

	/**
	 * check if a user is in the group
	 *
	 * @param IUser $user
	 * @return bool
	 */
	public function inGroup(IUser $user) {
		if (isset($this->users[$user->getUID()])) {
			return true;
		}
		foreach ($this->backends as $backend) {
			if ($backend->inGroup($user->getUID(), $this->gid)) {
				$this->users[$user->getUID()] = $user;
				return true;
			}
		}
		return false;
	}

	/**
	 * add a user to the group
	 *
	 * @param IUser $user
	 */
	public function addUser(IUser $user) {
		if ($this->inGroup($user)) {
			return;
		}

		$this->dispatcher->dispatch(IGroup::class . '::preAddUser', new GenericEvent($this, [
			'user' => $user,
		]));

		if ($this->emitter) {
			$this->emitter->emit('\OC\Group', 'preAddUser', [$this, $user]);
		}
		foreach ($this->backends as $backend) {
			if ($backend->implementsActions(\OC\Group\Backend::ADD_TO_GROUP)) {
				$backend->addToGroup($user->getUID(), $this->gid);
				if ($this->users) {
					$this->users[$user->getUID()] = $user;
				}

				$this->dispatcher->dispatch(IGroup::class . '::postAddUser', new GenericEvent($this, [
					'user' => $user,
				]));

				if ($this->emitter) {
					$this->emitter->emit('\OC\Group', 'postAddUser', [$this, $user]);
				}
				return;
			}
		}
	}

	/**
	 * remove a user from the group
	 *
	 * @param \OC\User\User $user
	 */
	public function removeUser($user) {
		$result = false;
		$this->dispatcher->dispatch(IGroup::class . '::preRemoveUser', new GenericEvent($this, [
			'user' => $user,
		]));
		if ($this->emitter) {
			$this->emitter->emit('\OC\Group', 'preRemoveUser', [$this, $user]);
		}
		foreach ($this->backends as $backend) {
			if ($backend->implementsActions(\OC\Group\Backend::REMOVE_FROM_GOUP) and $backend->inGroup($user->getUID(), $this->gid)) {
				$backend->removeFromGroup($user->getUID(), $this->gid);
				$result = true;
			}
		}
		if ($result) {
			$this->dispatcher->dispatch(IGroup::class . '::postRemoveUser', new GenericEvent($this, [
				'user' => $user,
			]));
			if ($this->emitter) {
				$this->emitter->emit('\OC\Group', 'postRemoveUser', [$this, $user]);
			}
			if ($this->users) {
				foreach ($this->users as $index => $groupUser) {
					if ($groupUser->getUID() === $user->getUID()) {
						unset($this->users[$index]);
						return;
					}
				}
			}
		}
	}

	/**
	 * search for users in the group by userid
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return \OC\User\User[]
	 */
	public function searchUsers($search, $limit = null, $offset = null) {
		$users = [];
		foreach ($this->backends as $backend) {
			$userIds = $backend->usersInGroup($this->gid, $search, $limit, $offset);
			$users += $this->getVerifiedUsers($userIds);
			if (!is_null($limit) and $limit <= 0) {
				return $users;
			}
		}
		return $users;
	}

	/**
	 * returns the number of users matching the search string
	 *
	 * @param string $search
	 * @return int|bool
	 */
	public function count($search = '') {
		$users = false;
		foreach ($this->backends as $backend) {
			if ($backend->implementsActions(\OC\Group\Backend::COUNT_USERS)) {
				if ($users === false) {
					//we could directly add to a bool variable, but this would
					//be ugly
					$users = 0;
				}
				$users += $backend->countUsersInGroup($this->gid, $search);
			}
		}
		return $users;
	}

	/**
	 * returns the number of disabled users
	 *
	 * @return int|bool
	 */
	public function countDisabled() {
		$users = false;
		foreach ($this->backends as $backend) {
			if ($backend instanceof ICountDisabledInGroup) {
				if ($users === false) {
					//we could directly add to a bool variable, but this would
					//be ugly
					$users = 0;
				}
				$users += $backend->countDisabledInGroup($this->gid);
			}
		}
		return $users;
	}

	/**
	 * search for users in the group by displayname
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return \OC\User\User[]
	 */
	public function searchDisplayName($search, $limit = null, $offset = null) {
		$users = [];
		foreach ($this->backends as $backend) {
			$userIds = $backend->usersInGroup($this->gid, $search, $limit, $offset);
			$users = $this->getVerifiedUsers($userIds);
			if (!is_null($limit) and $limit <= 0) {
				return array_values($users);
			}
		}
		return array_values($users);
	}

	/**
	 * delete the group
	 *
	 * @return bool
	 */
	public function delete() {
		// Prevent users from deleting group admin
		if ($this->getGID() === 'admin') {
			return false;
		}

		$result = false;
		$this->dispatcher->dispatch(IGroup::class . '::preDelete', new GenericEvent($this));
		if ($this->emitter) {
			$this->emitter->emit('\OC\Group', 'preDelete', [$this]);
		}
		foreach ($this->backends as $backend) {
			if ($backend->implementsActions(\OC\Group\Backend::DELETE_GROUP)) {
				$result = true;
				$backend->deleteGroup($this->gid);
			}
		}
		if ($result) {
			$this->dispatcher->dispatch(IGroup::class . '::postDelete', new GenericEvent($this));
			if ($this->emitter) {
				$this->emitter->emit('\OC\Group', 'postDelete', [$this]);
			}
		}
		return $result;
	}

	/**
	 * returns all the Users from an array that really exists
	 * @param string[] $userIds an array containing user IDs
	 * @return \OC\User\User[] an Array with the userId as Key and \OC\User\User as value
	 */
	private function getVerifiedUsers($userIds) {
		if (!is_array($userIds)) {
			return [];
		}
		$users = [];
		foreach ($userIds as $userId) {
			$user = $this->userManager->get($userId);
			if (!is_null($user)) {
				$users[$userId] = $user;
			}
		}
		return $users;
	}

	/**
	 * @return bool
	 * @since 14.0.0
	 */
	public function canRemoveUser() {
		foreach ($this->backends as $backend) {
			if ($backend->implementsActions(GroupInterface::REMOVE_FROM_GOUP)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return bool
	 * @since 14.0.0
	 */
	public function canAddUser() {
		foreach ($this->backends as $backend) {
			if ($backend->implementsActions(GroupInterface::ADD_TO_GROUP)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return bool
	 * @since 16.0.0
	 */
	public function hideFromCollaboration(): bool {
		return array_reduce($this->backends, function (bool $hide, GroupInterface $backend) {
			return $hide | ($backend instanceof IHideFromCollaborationBackend && $backend->hideGroup($this->gid));
		}, false);
	}
}
