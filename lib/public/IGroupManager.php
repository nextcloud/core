<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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
 * @since 8.0.0
 */
interface IGroupManager {
	/**
	 * Checks whether a given backend is used
	 *
	 * @param string $backendClass Full classname including complete namespace
	 * @return bool
	 * @since 8.1.0
	 */
	public function isBackendUsed($backendClass);

	/**
	 * @param \OCP\GroupInterface $backend
	 * @since 8.0.0
	 */
	public function addBackend($backend);

	/**
	 * @since 8.0.0
	 */
	public function clearBackends();

	/**
	 * Get the active backends
	 * @return \OCP\GroupInterface[]
	 * @since 13.0.0
	 */
	public function getBackends();

	/**
	 * @param string $gid
	 * @return \OCP\IGroup|null
	 * @since 8.0.0
	 */
	public function get($gid);

	/**
	 * @param string $gid
	 * @return bool
	 * @since 8.0.0
	 */
	public function groupExists($gid);

	/**
	 * @param string $gid
	 * @return \OCP\IGroup|null
	 * @since 8.0.0
	 */
	public function createGroup($gid);

	/**
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return \OCP\IGroup[]
	 * @since 8.0.0
	 */
	public function search($search, $limit = null, $offset = null);

	/**
	 * @param \OCP\IUser|null $user
	 * @return \OCP\IGroup[]
	 * @since 8.0.0
	 */
	public function getUserGroups(IUser $user = null);

	/**
	 * @param \OCP\IUser $user
	 * @return array with group names
	 * @since 8.0.0
	 */
	public function getUserGroupIds(IUser $user);

	/**
	 * get a list of all display names in a group
	 *
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of display names (value) and user ids (key)
	 * @since 8.0.0
	 */
	public function displayNamesInGroup($gid, $search = '', $limit = -1, $offset = 0);

	/**
	 * Checks if a userId is in the admin group
	 * @param string $userId
	 * @return bool if admin
	 * @since 8.0.0
	 */
	public function isAdmin($userId);

	/**
	 * Checks if a userId is in a group
	 * @param string $userId
	 * @param string $group
	 * @return bool if in group
	 * @since 8.0.0
	 */
	public function isInGroup($userId, $group);
}
