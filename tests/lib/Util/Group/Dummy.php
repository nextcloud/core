<?php
/**
 * @author Arthur Schiwon <blizzz@owncloud.com>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Util\Group;

use OC\Group\Backend;

/**
 * dummy group backend, does not keep state, only for testing use
 */
class Dummy extends Backend {
	private $groups = [];
	/**
	 * Try to create a new group
	 * @param string $gid The name of the group to create
	 * @return bool
	 *
	 * Tries to create a new group. If the group name already exists, false will
	 * be returned.
	 */
	public function createGroup($gid) {
		if (!isset($this->groups[$gid])) {
			$this->groups[$gid] = [];
			return true;
		} else {
			return false;
		}
	}

	/**
	 * delete a group
	 * @param string $gid gid of the group to delete
	 * @return bool
	 *
	 * Deletes a group and removes it from the group_user-table
	 */
	public function deleteGroup($gid) {
		if (isset($this->groups[$gid])) {
			unset($this->groups[$gid]);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * is user in group?
	 * @param string $uid uid of the user
	 * @param string $gid gid of the group
	 * @return bool
	 *
	 * Checks whether the user is member of a group or not.
	 */
	public function inGroup($uid, $gid) {
		if (isset($this->groups[$gid])) {
			return (array_search($uid, $this->groups[$gid]) !== false);
		} else {
			return false;
		}
	}

	/**
	 * Add a user to a group
	 * @param string $uid Name of the user to add to group
	 * @param string $gid Name of the group in which add the user
	 * @return bool
	 *
	 * Adds a user to a group.
	 */
	public function addToGroup($uid, $gid) {
		if (isset($this->groups[$gid])) {
			if (array_search($uid, $this->groups[$gid]) === false) {
				$this->groups[$gid][] = $uid;
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Removes a user from a group
	 * @param string $uid Name of the user to remove from group
	 * @param string $gid Name of the group from which remove the user
	 * @return bool
	 *
	 * removes the user from a group.
	 */
	public function removeFromGroup($uid, $gid) {
		if (isset($this->groups[$gid])) {
			if (($index = array_search($uid, $this->groups[$gid])) !== false) {
				unset($this->groups[$gid][$index]);
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Get all groups a user belongs to
	 * @param string $uid Name of the user
	 * @return array an array of group names
	 *
	 * This function fetches all groups a user belongs to. It does not check
	 * if the user exists at all.
	 */
	public function getUserGroups($uid) {
		$groups = [];
		$allGroups = array_keys($this->groups);
		foreach ($allGroups as $group) {
			if ($this->inGroup($uid, $group)) {
				$groups[] = $group;
			}
		}
		return $groups;
	}

	/**
	 * Get a list of all groups
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of group names
	 */
	public function getGroups($search = '', $limit = -1, $offset = 0) {
		if (empty($search)) {
			return array_keys($this->groups);
		}
		$result = [];
		foreach (array_keys($this->groups) as $group) {
			if (stripos($group, $search) !== false) {
				$result[] = $group;
			}
		}
		return $result;
	}

	/**
	 * Get a list of all users in a group
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of user IDs
	 */
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		if (isset($this->groups[$gid])) {
			if (empty($search)) {
				$length = $limit < 0 ? null : $limit;
				return array_slice($this->groups[$gid], $offset, $length);
			}
			$result = [];
			foreach ($this->groups[$gid] as $user) {
				if (stripos($user, $search) !== false) {
					$result[] = $user;
				}
			}
			return $result;
		} else {
			return [];
		}
	}

	/**
	 * get the number of all users in a group
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return int
	 */
	public function countUsersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		if (isset($this->groups[$gid])) {
			if (empty($search)) {
				return count($this->groups[$gid]);
			}
			$count = 0;
			foreach ($this->groups[$gid] as $user) {
				if (stripos($user, $search) !== false) {
					$count++;
				}
			}
			return $count;
		}
	}
}
