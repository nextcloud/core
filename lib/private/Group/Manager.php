<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Knut Ahlers <knut@ahlers.me>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author macjohnny <estebanmarin@gmx.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roman Kreisel <mail@romankreisel.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
 * @author voxsim "Simon Vocella"
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
use OCP\EventDispatcher\IEventDispatcher;
use OCP\GroupInterface;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Manager
 *
 * Hooks available in scope \OC\Group:
 * - preAddUser(\OC\Group\Group $group, \OC\User\User $user)
 * - postAddUser(\OC\Group\Group $group, \OC\User\User $user)
 * - preRemoveUser(\OC\Group\Group $group, \OC\User\User $user)
 * - postRemoveUser(\OC\Group\Group $group, \OC\User\User $user)
 * - preDelete(\OC\Group\Group $group)
 * - postDelete(\OC\Group\Group $group)
 * - preCreate(string $groupId)
 * - postCreate(\OC\Group\Group $group)
 *
 * @package OC\Group
 */
class Manager extends PublicEmitter implements IGroupManager {
	/** @var GroupInterface[] */
	private $backends = [];

	/** @var \OC\User\Manager */
	private $userManager;
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var ILogger */
	private $logger;

	/** @var \OC\Group\Group[] */
	private $cachedGroups = [];

	/** @var (string[])[] */
	private $cachedUserGroups = [];

	/** @var \OC\SubAdmin */
	private $subAdmin = null;

	/**
	 * @param \OC\User\Manager $userManager
	 * @param EventDispatcherInterface $dispatcher
	 * @param ILogger $logger
	 */
	public function __construct(\OC\User\Manager $userManager,
								EventDispatcherInterface $dispatcher,
								ILogger $logger) {
		$this->userManager = $userManager;
		$this->dispatcher = $dispatcher;
		$this->logger = $logger;

		$cachedGroups = &$this->cachedGroups;
		$cachedUserGroups = &$this->cachedUserGroups;
		$this->listen('\OC\Group', 'postDelete', function ($group) use (&$cachedGroups, &$cachedUserGroups) {
			/**
			 * @var \OC\Group\Group $group
			 */
			unset($cachedGroups[$group->getGID()]);
			$cachedUserGroups = [];
		});
		$this->listen('\OC\Group', 'postAddUser', function ($group) use (&$cachedUserGroups) {
			/**
			 * @var \OC\Group\Group $group
			 */
			$cachedUserGroups = [];
		});
		$this->listen('\OC\Group', 'postRemoveUser', function ($group) use (&$cachedUserGroups) {
			/**
			 * @var \OC\Group\Group $group
			 */
			$cachedUserGroups = [];
		});
	}

	/**
	 * Checks whether a given backend is used
	 *
	 * @param string $backendClass Full classname including complete namespace
	 * @return bool
	 */
	public function isBackendUsed($backendClass) {
		$backendClass = strtolower(ltrim($backendClass, '\\'));

		foreach ($this->backends as $backend) {
			if (strtolower(get_class($backend)) === $backendClass) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param \OCP\GroupInterface $backend
	 */
	public function addBackend($backend) {
		$this->backends[] = $backend;
		$this->clearCaches();
	}

	public function clearBackends() {
		$this->backends = [];
		$this->clearCaches();
	}

	/**
	 * Get the active backends
	 *
	 * @return \OCP\GroupInterface[]
	 */
	public function getBackends() {
		return $this->backends;
	}


	protected function clearCaches() {
		$this->cachedGroups = [];
		$this->cachedUserGroups = [];
	}

	/**
	 * @param string $gid
	 * @return IGroup|null
	 */
	public function get($gid) {
		if (isset($this->cachedGroups[$gid])) {
			return $this->cachedGroups[$gid];
		}
		return $this->getGroupObject($gid);
	}

	/**
	 * @param string $gid
	 * @param string $displayName
	 * @return \OCP\IGroup|null
	 */
	protected function getGroupObject($gid, $displayName = null) {
		$backends = [];
		foreach ($this->backends as $backend) {
			if ($backend->implementsActions(Backend::GROUP_DETAILS)) {
				$groupData = $backend->getGroupDetails($gid);
				if (is_array($groupData) && !empty($groupData)) {
					// take the display name from the first backend that has a non-null one
					if (is_null($displayName) && isset($groupData['displayName'])) {
						$displayName = $groupData['displayName'];
					}
					$backends[] = $backend;
				}
			} elseif ($backend->groupExists($gid)) {
				$backends[] = $backend;
			}
		}
		if (count($backends) === 0) {
			return null;
		}
		$this->cachedGroups[$gid] = new Group($gid, $backends, $this->dispatcher, $this->userManager, $this, $displayName);
		return $this->cachedGroups[$gid];
	}

	/**
	 * @param string $gid
	 * @return bool
	 */
	public function groupExists($gid) {
		return $this->get($gid) instanceof IGroup;
	}

	/**
	 * @param string $gid
	 * @return IGroup|null
	 */
	public function createGroup($gid) {
		if ($gid === '' || $gid === null) {
			return null;
		} elseif ($group = $this->get($gid)) {
			return $group;
		} else {
			$this->emit('\OC\Group', 'preCreate', [$gid]);
			foreach ($this->backends as $backend) {
				if ($backend->implementsActions(Backend::CREATE_GROUP)) {
					if ($backend->createGroup($gid)) {
						$group = $this->getGroupObject($gid);
						$this->emit('\OC\Group', 'postCreate', [$group]);
						return $group;
					}
				}
			}
			return null;
		}
	}

	/**
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return \OC\Group\Group[]
	 */
	public function search($search, $limit = null, $offset = null) {
		$groups = [];
		foreach ($this->backends as $backend) {
			$groupIds = $backend->getGroups($search, $limit, $offset);
			foreach ($groupIds as $groupId) {
				$aGroup = $this->get($groupId);
				if ($aGroup instanceof IGroup) {
					$groups[$groupId] = $aGroup;
				} else {
					$this->logger->debug('Group "' . $groupId . '" was returned by search but not found through direct access', ['app' => 'core']);
				}
			}
			if (!is_null($limit) and $limit <= 0) {
				return array_values($groups);
			}
		}
		return array_values($groups);
	}

	/**
	 * @param IUser|null $user
	 * @return \OC\Group\Group[]
	 */
	public function getUserGroups(IUser $user = null) {
		if (!$user instanceof IUser) {
			return [];
		}
		return $this->getUserIdGroups($user->getUID());
	}

	/**
	 * @param string $uid the user id
	 * @return \OC\Group\Group[]
	 */
	public function getUserIdGroups(string $uid): array {
		$groups = [];

		foreach ($this->getUserIdGroupIds($uid) as $groupId) {
			$aGroup = $this->get($groupId);
			if ($aGroup instanceof IGroup) {
				$groups[$groupId] = $aGroup;
			} else {
				$this->logger->debug('User "' . $uid . '" belongs to deleted group: "' . $groupId . '"', ['app' => 'core']);
			}
		}

		return $groups;
	}

	/**
	 * Checks if a userId is in the admin group
	 *
	 * @param string $userId
	 * @return bool if admin
	 */
	public function isAdmin($userId) {
		foreach ($this->backends as $backend) {
			if ($backend->implementsActions(Backend::IS_ADMIN) && $backend->isAdmin($userId)) {
				return true;
			}
		}
		return $this->isInGroup($userId, 'admin');
	}

	/**
	 * Checks if a userId is in a group
	 *
	 * @param string $userId
	 * @param string $group
	 * @return bool if in group
	 */
	public function isInGroup($userId, $group) {
		return array_search($group, $this->getUserIdGroupIds($userId)) !== false;
	}

	/**
	 * get a list of group ids for a user
	 *
	 * @param IUser $user
	 * @return string[] with group ids
	 */
	public function getUserGroupIds(IUser $user): array {
		return $this->getUserIdGroupIds($user->getUID());
	}

	/**
	 * @param string $uid the user id
	 * @return string[]
	 */
	private function getUserIdGroupIds(string $uid): array {
		if (!isset($this->cachedUserGroups[$uid])) {
			$groups = [];
			foreach ($this->backends as $backend) {
				if ($groupIds = $backend->getUserGroups($uid)) {
					$groups = array_merge($groups, $groupIds);
				}
			}
			$this->cachedUserGroups[$uid] = $groups;
		}

		return $this->cachedUserGroups[$uid];
	}

	/**
	 * get an array of groupid and displayName for a user
	 *
	 * @param IUser $user
	 * @return array ['displayName' => displayname]
	 */
	public function getUserGroupNames(IUser $user) {
		return array_map(function ($group) {
			return ['displayName' => $group->getDisplayName()];
		}, $this->getUserGroups($user));
	}

	/**
	 * get a list of all display names in a group
	 *
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of display names (value) and user ids (key)
	 */
	public function displayNamesInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		$group = $this->get($gid);
		if (is_null($group)) {
			return [];
		}

		$search = trim($search);
		$groupUsers = [];

		if (!empty($search)) {
			// only user backends have the capability to do a complex search for users
			$chunkOffset = 0;
			$chunkLimit = 500;
			do {
				$userChunk = $group->searchUsers('', $chunkLimit, $chunkOffset);
				foreach ($userChunk as $user) {
					if (mb_stripos($user->getDisplayName(), $search) > -1) {
						$groupUsers[] = $user;
					}
				}
				$chunkOffset += $chunkLimit;
			} while (count($userChunk) !== 0);

			if ($limit === -1) {
				$groupUsers = array_slice($groupUsers, $offset);
			} else {
				$groupUsers = array_slice($groupUsers, $offset, $limit);
			}
		} else {
			$groupUsers = $group->searchUsers('', $limit, $offset);
		}

		$matchingUsers = [];
		foreach ($groupUsers as $groupUser) {
			$matchingUsers[(string) $groupUser->getUID()] = $groupUser->getDisplayName();
		}
		return $matchingUsers;
	}

	/**
	 * @return \OC\SubAdmin
	 */
	public function getSubAdmin() {
		if (!$this->subAdmin) {
			$this->subAdmin = new \OC\SubAdmin(
				$this->userManager,
				$this,
				\OC::$server->getDatabaseConnection(),
				\OC::$server->get(IEventDispatcher::class)
			);
		}

		return $this->subAdmin;
	}
}
