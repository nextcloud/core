<?php
/**
 * @copyright Copyright (c) 2017 EITA Cooperative (eita.org.br)
 *
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

namespace OCA\User_LDAP;

interface ILDAPGroupPlugin {

	/**
	 * Check if plugin implements actions
	 * @return int
	 *
	 * Returns the supported actions as int to be
	 * compared with OC_GROUP_BACKEND_CREATE_GROUP etc.
	 */
	public function respondToActions();

	/**
	 * @param string $gid
	 * @return string|null The group DN if group creation was successful.
	 */
	public function createGroup($gid);

	/**
	 * delete a group
	 * @param string $gid gid of the group to delete
	 * @return bool
	 */
	public function deleteGroup($gid);

	/**
	 * Add a user to a group
	 * @param string $uid Name of the user to add to group
	 * @param string $gid Name of the group in which add the user
	 * @return bool
	 *
	 * Adds a user to a group.
	 */
	public function addToGroup($uid, $gid);

	/**
	 * Removes a user from a group
	 * @param string $uid Name of the user to remove from group
	 * @param string $gid Name of the group from which remove the user
	 * @return bool
	 *
	 * removes the user from a group.
	 */
	public function removeFromGroup($uid, $gid);

	/**
	 * get the number of all users matching the search string in a group
	 * @param string $gid
	 * @param string $search
	 * @return int|false
	 */
	public function countUsersInGroup($gid, $search = '');

	/**
	 * get an array with group details
	 * @param string $gid
	 * @return array|false
	 */
	public function getGroupDetails($gid);

}
