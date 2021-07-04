<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
namespace OC\User;

use OCP\UserInterface;

/**
 * Abstract base class for user management. Provides methods for querying backend
 * capabilities.
 * @deprecated 21.0.0 use \OCP\Public\User\Backend\AbstractUserBackEnd instead
 */
abstract class Backend implements UserInterface {
	/**
	 * error code for functions not provided by the user backend
	 */
	public const NOT_IMPLEMENTED = -501;

	/**
	 * actions that user backends can define
	 */
	public const CREATE_USER = 1;			// 1 << 0
	public const SET_PASSWORD = 16;			// 1 << 4
	public const CHECK_PASSWORD = 256;			// 1 << 8
	public const GET_HOME = 4096;			// 1 << 12
	public const GET_DISPLAYNAME = 65536;		// 1 << 16
	public const SET_DISPLAYNAME = 1048576;		// 1 << 20
	public const PROVIDE_AVATAR = 16777216;		// 1 << 24
	public const COUNT_USERS = 268435456;	// 1 << 28

	protected $possibleActions = [
		self::CREATE_USER => 'createUser',
		self::SET_PASSWORD => 'setPassword',
		self::CHECK_PASSWORD => 'checkPassword',
		self::GET_HOME => 'getHome',
		self::GET_DISPLAYNAME => 'getDisplayName',
		self::SET_DISPLAYNAME => 'setDisplayName',
		self::PROVIDE_AVATAR => 'canChangeAvatar',
		self::COUNT_USERS => 'countUsers',
	];

	/**
	 * Get all supported actions
	 * @return int bitwise-or'ed actions
	 *
	 * Returns the supported actions as int to be
	 * compared with self::CREATE_USER etc.
	 */
	public function getSupportedActions() {
		$actions = 0;
		foreach ($this->possibleActions as $action => $methodName) {
			if (method_exists($this, $methodName)) {
				$actions |= $action;
			}
		}

		return $actions;
	}

	/**
	 * Check if backend implements actions
	 * @param int $actions bitwise-or'ed actions
	 * @return boolean
	 *
	 * Returns the supported actions as int to be
	 * compared with self::CREATE_USER etc.
	 */
	public function implementsActions($actions) {
		return (bool)($this->getSupportedActions() & $actions);
	}

	/**
	 * delete a user
	 * @param string $uid The username of the user to delete
	 * @return bool
	 *
	 * Deletes a user
	 */
	public function deleteUser($uid) {
		return false;
	}

	/**
	 * Get a list of all users
	 *
	 * @param string $search
	 * @param null|int $limit
	 * @param null|int $offset
	 * @return string[] an array of all uids
	 */
	public function getUsers($search = '', $limit = null, $offset = null) {
		return [];
	}

	/**
	 * check if a user exists
	 * @param string $uid the username
	 * @return boolean
	 */
	public function userExists($uid) {
		return false;
	}

	/**
	 * get the user's home directory
	 * @param string $uid the username
	 * @return boolean
	 */
	public function getHome($uid) {
		return false;
	}

	/**
	 * get display name of the user
	 * @param string $uid user ID of the user
	 * @return string display name
	 */
	public function getDisplayName($uid) {
		return $uid;
	}

	/**
	 * Get a list of all display names and user ids.
	 *
	 * @param string $search
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array an array of all displayNames (value) and the corresponding uids (key)
	 */
	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		$displayNames = [];
		$users = $this->getUsers($search, $limit, $offset);
		foreach ($users as $user) {
			$displayNames[$user] = $user;
		}
		return $displayNames;
	}

	/**
	 * Check if a user list is available or not
	 * @return boolean if users can be listed or not
	 */
	public function hasUserListings() {
		return false;
	}
}
