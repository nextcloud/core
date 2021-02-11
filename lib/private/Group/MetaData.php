<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stephan Peijnik <speijnik@anexia-it.com>
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

namespace OC\Group;

use OC\Group\Manager as GroupManager;
use OCP\IGroupManager;
use OCP\IUserSession;

class MetaData {
	public const SORT_NONE = 0;
	public const SORT_USERCOUNT = 1; // May have performance issues on LDAP backends
	public const SORT_GROUPNAME = 2;

	/** @var string */
	protected $user;
	/** @var bool */
	protected $isAdmin;
	/** @var array */
	protected $metaData = [];
	/** @var GroupManager */
	protected $groupManager;
	/** @var bool */
	protected $sorting = false;
	/** @var IUserSession */
	protected $userSession;

	/**
	 * @param string $user the uid of the current user
	 * @param bool $isAdmin whether the current users is an admin
	 * @param IGroupManager $groupManager
	 * @param IUserSession $userSession
	 */
	public function __construct(
			$user,
			$isAdmin,
			IGroupManager $groupManager,
			IUserSession $userSession
			) {
		$this->user = $user;
		$this->isAdmin = (bool)$isAdmin;
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
	}

	/**
	 * returns an array with meta data about all available groups
	 * the array is structured as follows:
	 * [0] array containing meta data about admin groups
	 * [1] array containing meta data about unprivileged groups
	 * @param string $groupSearch only effective when instance was created with
	 * isAdmin being true
	 * @param string $userSearch the pattern users are search for
	 * @return array
	 */
	public function get($groupSearch = '', $userSearch = '') {
		$key = $groupSearch . '::' . $userSearch;
		if (isset($this->metaData[$key])) {
			return $this->metaData[$key];
		}

		$adminGroups = [];
		$groups = [];
		$sortGroupsIndex = 0;
		$sortGroupsKeys = [];
		$sortAdminGroupsIndex = 0;
		$sortAdminGroupsKeys = [];

		foreach ($this->getGroups($groupSearch) as $group) {
			$groupMetaData = $this->generateGroupMetaData($group, $userSearch);
			if (strtolower($group->getGID()) !== 'admin') {
				$this->addEntry(
					$groups,
					$sortGroupsKeys,
					$sortGroupsIndex,
					$groupMetaData);
			} else {
				//admin group is hard coded to 'admin' for now. In future,
				//backends may define admin groups too. Then the if statement
				//has to be adjusted accordingly.
				$this->addEntry(
					$adminGroups,
					$sortAdminGroupsKeys,
					$sortAdminGroupsIndex,
					$groupMetaData);
			}
		}

		//whether sorting is necessary is will be checked in sort()
		$this->sort($groups, $sortGroupsKeys);
		$this->sort($adminGroups, $sortAdminGroupsKeys);

		$this->metaData[$key] = [$adminGroups, $groups];
		return $this->metaData[$key];
	}

	/**
	 * sets the sort mode, see SORT_* constants for supported modes
	 *
	 * @param int $sortMode
	 */
	public function setSorting($sortMode) {
		switch ($sortMode) {
			case self::SORT_USERCOUNT:
			case self::SORT_GROUPNAME:
				$this->sorting = $sortMode;
				break;

			default:
				$this->sorting = self::SORT_NONE;
		}
	}

	/**
	 * adds an group entry to the resulting array
	 * @param array $entries the resulting array, by reference
	 * @param array $sortKeys the sort key array, by reference
	 * @param int $sortIndex the sort key index, by reference
	 * @param array $data the group's meta data as returned by generateGroupMetaData()
	 */
	private function addEntry(&$entries, &$sortKeys, &$sortIndex, $data) {
		$entries[] = $data;
		if ($this->sorting === self::SORT_USERCOUNT) {
			$sortKeys[$sortIndex] = $data['usercount'];
			$sortIndex++;
		} elseif ($this->sorting === self::SORT_GROUPNAME) {
			$sortKeys[$sortIndex] = $data['name'];
			$sortIndex++;
		}
	}

	/**
	 * creates an array containing the group meta data
	 * @param \OCP\IGroup $group
	 * @param string $userSearch
	 * @return array with the keys 'id', 'name', 'usercount' and 'disabled'
	 */
	private function generateGroupMetaData(\OCP\IGroup $group, $userSearch) {
		return [
			'id' => $group->getGID(),
			'name' => $group->getDisplayName(),
			'usercount' => $this->sorting === self::SORT_USERCOUNT ? $group->count($userSearch) : 0,
			'disabled' => $group->countDisabled(),
			'canAdd' => $group->canAddUser(),
			'canRemove' => $group->canRemoveUser(),
		];
	}

	/**
	 * sorts the result array, if applicable
	 * @param array $entries the result array, by reference
	 * @param array $sortKeys the array containing the sort keys
	 * @param return null
	 */
	private function sort(&$entries, $sortKeys) {
		if ($this->sorting === self::SORT_USERCOUNT) {
			array_multisort($sortKeys, SORT_DESC, $entries);
		} elseif ($this->sorting === self::SORT_GROUPNAME) {
			array_multisort($sortKeys, SORT_ASC, $entries);
		}
	}

	/**
	 * returns the available groups
	 * @param string $search a search string
	 * @return \OCP\IGroup[]
	 */
	public function getGroups($search = '') {
		if ($this->isAdmin) {
			return $this->groupManager->search($search);
		} else {
			$userObject = $this->userSession->getUser();
			if ($userObject !== null) {
				$groups = $this->groupManager->getSubAdmin()->getSubAdminsGroups($userObject);
			} else {
				$groups = [];
			}

			return $groups;
		}
	}
}
