<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCP;

/**
 * Interface IGroup
 *
 * @package OCP
 * @since 8.0.0
 */
interface IGroup {
	/**
	 * @return string
	 * @since 8.0.0
	 */
	public function getGID();

	/**
	 * Returns the group display name
	 *
	 * @return string
	 * @since 12.0.0
	 */
	public function getDisplayName();

	/**
	 * Set the group display name
	 *
	 * @param string $displayName
	 * @return bool
	 * @since 18.0.0
	 */
	public function setDisplayName(string $displayName): bool;

	/**
	 * get all users in the group
	 *
	 * @return \OCP\IUser[]
	 * @since 8.0.0
	 */
	public function getUsers();

	/**
	 * check if a user is in the group
	 *
	 * @param \OCP\IUser $user
	 * @return bool
	 * @since 8.0.0
	 */
	public function inGroup(IUser $user);

	/**
	 * add a user to the group
	 *
	 * @param \OCP\IUser $user
	 * @since 8.0.0
	 */
	public function addUser(IUser $user);

	/**
	 * remove a user from the group
	 *
	 * @param \OCP\IUser $user
	 * @since 8.0.0
	 */
	public function removeUser($user);

	/**
	 * search for users in the group by userid
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return \OCP\IUser[]
	 * @since 8.0.0
	 */
	public function searchUsers($search, $limit = null, $offset = null);

	/**
	 * returns the number of users matching the search string
	 *
	 * @param string $search
	 * @return int|bool
	 * @since 8.0.0
	 */
	public function count($search = '');

	/**
	 * returns the number of disabled users
	 *
	 * @return int|bool
	 * @since 14.0.0
	 */
	public function countDisabled();

	/**
	 * search for users in the group by displayname
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return \OCP\IUser[]
	 * @since 8.0.0
	 */
	public function searchDisplayName($search, $limit = null, $offset = null);

	/**
	 * delete the group
	 *
	 * @return bool
	 * @since 8.0.0
	 */
	public function delete();

	/**
	 * @return bool
	 * @since 14.0.0
	 */
	public function canRemoveUser();

	/**
	 * @return bool
	 * @since 14.0.0
	 */
	public function canAddUser();

	/**
	 * @return bool
	 * @since 16.0.0
	 */
	public function hideFromCollaboration(): bool;
}
