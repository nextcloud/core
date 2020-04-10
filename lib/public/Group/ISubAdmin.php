<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCP\Group;

use OCP\IGroup;
use OCP\IUser;

/**
 * @since 16.0.0
 */
interface ISubAdmin {

	/**
	 * add a SubAdmin
	 * @param IUser $user user to be SubAdmin
	 * @param IGroup $group group $user becomes subadmin of
	 *
	 * @since 16.0.0
	 */
	public function createSubAdmin(IUser $user, IGroup $group): void;

	/**
	 * delete a SubAdmin
	 * @param IUser $user the user that is the SubAdmin
	 * @param IGroup $group the group
	 *
	 * @since 16.0.0
	 */
	public function deleteSubAdmin(IUser $user, IGroup $group): void;

	/**
	 * get groups of a SubAdmin
	 * @param IUser $user the SubAdmin
	 * @return IGroup[]
	 *
	 * @since 16.0.0
	 */
	public function getSubAdminsGroups(IUser $user): array;

	/**
	 * get SubAdmins of a group
	 * @param IGroup $group the group
	 * @return IUser[]
	 *
	 * @since 16.0.0
	 */
	public function getGroupsSubAdmins(IGroup $group): array;

	/**
	 * checks if a user is a SubAdmin of a group
	 * @param IUser $user
	 * @param IGroup $group
	 * @return bool
	 *
	 * @since 16.0.0
	 */
	public function isSubAdminOfGroup(IUser $user, IGroup $group): bool;

	/**
	 * checks if a user is a SubAdmin
	 * @param IUser $user
	 * @return bool
	 *
	 * @since 16.0.0
	 */
	public function isSubAdmin(IUser $user): bool;

	/**
	 * checks if a user is a accessible by a subadmin
	 * @param IUser $subadmin
	 * @param IUser $user
	 * @return bool
	 *
	 * @since 16.0.0
	 */
	public function isUserAccessible(IUser $subadmin, IUser $user): bool;
}
