<?php
/**
 * @copyright Copyright (c) 2017 EITA Cooperative (eita.org.br)
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Filis Futsarov <filisko@users.noreply.github.com>
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

use OC\User\Backend;

class UserPluginManager {

	public $test = false;

	private $respondToActions = 0;

	private $which = [
		Backend::CREATE_USER => null,
		Backend::SET_PASSWORD => null,
		Backend::GET_HOME => null,
		Backend::GET_DISPLAYNAME => null,
		Backend::SET_DISPLAYNAME => null,
		Backend::PROVIDE_AVATAR => null,
		Backend::COUNT_USERS => null,
		'deleteUser' => null
	];

	/**
	 * @return int All implemented actions, except for 'deleteUser'
	 */
	public function getImplementedActions() {
		return $this->respondToActions;
	}

	/**
	 * Registers a user plugin that may implement some actions, overriding User_LDAP's user actions.
	 *
	 * @param ILDAPUserPlugin $plugin
	 */
	public function register(ILDAPUserPlugin $plugin) {
		$respondToActions = $plugin->respondToActions();
		$this->respondToActions |= $respondToActions;

		foreach($this->which as $action => $v) {
			if (is_int($action) && (bool)($respondToActions & $action)) {
				$this->which[$action] = $plugin;
				\OC::$server->getLogger()->debug("Registered action ".$action." to plugin ".get_class($plugin), ['app' => 'user_ldap']);
			}
		}
		if (method_exists($plugin,'deleteUser')) {
			$this->which['deleteUser'] = $plugin;
			\OC::$server->getLogger()->debug("Registered action deleteUser to plugin ".get_class($plugin), ['app' => 'user_ldap']);
		}
	}

	/**
	 * Signal if there is a registered plugin that implements some given actions
	 * @param int $actions Actions defined in \OC\User\Backend, like Backend::CREATE_USER
	 * @return bool
	 */
	public function implementsActions($actions) {
		return ($actions & $this->respondToActions) == $actions;
	}

	/**
	 * Create a new user in LDAP Backend
	 *
	 * @param string $username The username of the user to create
	 * @param string $password The password of the new user
	 * @return string | false The user DN if user creation was successful.
	 * @throws \Exception
	 */
	public function createUser($username, $password) {
		$plugin = $this->which[Backend::CREATE_USER];

		if ($plugin) {
			return $plugin->createUser($username,$password);
		}
		throw new \Exception('No plugin implements createUser in this LDAP Backend.');
	}

	/**
	 * Change the password of a user*
	 * @param string $uid The username
	 * @param string $password The new password
	 * @return bool
	 * @throws \Exception
	 */
	public function setPassword($uid, $password) {
		$plugin = $this->which[Backend::SET_PASSWORD];

		if ($plugin) {
			return $plugin->setPassword($uid,$password);
		}
		throw new \Exception('No plugin implements setPassword in this LDAP Backend.');
	}

	/**
	 * checks whether the user is allowed to change his avatar in Nextcloud
	 * @param string $uid the Nextcloud user name
	 * @return boolean either the user can or cannot
	 * @throws \Exception
	 */
	public function canChangeAvatar($uid) {
		$plugin = $this->which[Backend::PROVIDE_AVATAR];

		if ($plugin) {
			return $plugin->canChangeAvatar($uid);
		}
		throw new \Exception('No plugin implements canChangeAvatar in this LDAP Backend.');
	}

	/**
	 * Get the user's home directory
	 * @param string $uid the username
	 * @return boolean
	 * @throws \Exception
	 */
	public function getHome($uid) {
		$plugin = $this->which[Backend::GET_HOME];

		if ($plugin) {
			return $plugin->getHome($uid);
		}
		throw new \Exception('No plugin implements getHome in this LDAP Backend.');
	}

	/**
	 * Get display name of the user
	 * @param string $uid user ID of the user
	 * @return string display name
	 * @throws \Exception
	 */
	public function getDisplayName($uid) {
		$plugin = $this->which[Backend::GET_DISPLAYNAME];

		if ($plugin) {
			return $plugin->getDisplayName($uid);
		}
		throw new \Exception('No plugin implements getDisplayName in this LDAP Backend.');
	}

	/**
	 * Set display name of the user
	 * @param string $uid user ID of the user
	 * @param string $displayName new user's display name
	 * @return string display name
	 * @throws \Exception
	 */
	public function setDisplayName($uid, $displayName) {
		$plugin = $this->which[Backend::SET_DISPLAYNAME];

		if ($plugin) {
			return $plugin->setDisplayName($uid, $displayName);
		}
		throw new \Exception('No plugin implements setDisplayName in this LDAP Backend.');
	}

	/**
	 * Count the number of users
	 * @return int|bool
	 * @throws \Exception
	 */
	public function countUsers() {
		$plugin = $this->which[Backend::COUNT_USERS];

		if ($plugin) {
			return $plugin->countUsers();
		}
		throw new \Exception('No plugin implements countUsers in this LDAP Backend.');
	}

	/**
	 * @return bool
	 */
	public function canDeleteUser() {
		return $this->which['deleteUser'] !== null;
	}

	/**
	 * @param $uid
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteUser($uid) {
		$plugin = $this->which['deleteUser'];
		if ($plugin) {
			return $plugin->deleteUser($uid);
		}
		throw new \Exception('No plugin implements deleteUser in this LDAP Backend.');
	}
}
