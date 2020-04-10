<?php
/**
 * @copyright Copyright (c) 2017 EITA Cooperative (eita.org.br)
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OCP\GroupInterface;

class GroupPluginManager {

	private $respondToActions = 0;

	private $which = [
		GroupInterface::CREATE_GROUP => null,
		GroupInterface::DELETE_GROUP => null,
		GroupInterface::ADD_TO_GROUP => null,
		GroupInterface::REMOVE_FROM_GROUP => null,
		GroupInterface::COUNT_USERS => null,
		GroupInterface::GROUP_DETAILS => null
	];

	/**
	 * @return int All implemented actions
	 */
	public function getImplementedActions() {
		return $this->respondToActions;
	}

	/**
	 * Registers a group plugin that may implement some actions, overriding User_LDAP's group actions.
	 * @param ILDAPGroupPlugin $plugin
	 */
	public function register(ILDAPGroupPlugin $plugin) {
		$respondToActions = $plugin->respondToActions();
		$this->respondToActions |= $respondToActions;

		foreach($this->which as $action => $v) {
			if ((bool)($respondToActions & $action)) {
				$this->which[$action] = $plugin;
				\OC::$server->getLogger()->debug("Registered action ".$action." to plugin ".get_class($plugin), ['app' => 'user_ldap']);
			}
		}
	}

	/**
	 * Signal if there is a registered plugin that implements some given actions
	 * @param int $actions Actions defined in \OCP\GroupInterface, like GroupInterface::REMOVE_FROM_GROUP
	 * @return bool
	 */
	public function implementsActions($actions) {
		return ($actions & $this->respondToActions) == $actions;
	}

	/**
	 * Create a group
	 * @param string $gid Group Id
	 * @return string | null The group DN if group creation was successful.
	 * @throws \Exception
	 */
	public function createGroup($gid) {
		$plugin = $this->which[GroupInterface::CREATE_GROUP];

		if ($plugin) {
			return $plugin->createGroup($gid);
		}
		throw new \Exception('No plugin implements createGroup in this LDAP Backend.');
	}

	/**
	 * Delete a group
	 * @param string $gid Group Id of the group to delete
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteGroup($gid) {
		$plugin = $this->which[GroupInterface::DELETE_GROUP];

		if ($plugin) {
			return $plugin->deleteGroup($gid);
		}
		throw new \Exception('No plugin implements deleteGroup in this LDAP Backend.');
	}

	/**
	 * Add a user to a group
	 * @param string $uid ID of the user to add to group
	 * @param string $gid ID of the group in which add the user
	 * @return bool
	 * @throws \Exception
	 *
	 * Adds a user to a group.
	 */
	public function addToGroup($uid, $gid) {
		$plugin = $this->which[GroupInterface::ADD_TO_GROUP];

		if ($plugin) {
			return $plugin->addToGroup($uid, $gid);
		}
		throw new \Exception('No plugin implements addToGroup in this LDAP Backend.');
	}

	/**
	 * Removes a user from a group
	 * @param string $uid ID of the user to remove from group
	 * @param string $gid ID of the group from which remove the user
	 * @return bool
	 * @throws \Exception
	 *
	 * removes the user from a group.
	 */
	public function removeFromGroup($uid, $gid) {
		$plugin = $this->which[GroupInterface::REMOVE_FROM_GROUP];

		if ($plugin) {
			return $plugin->removeFromGroup($uid, $gid);
		}
		throw new \Exception('No plugin implements removeFromGroup in this LDAP Backend.');
	}

	/**
	 * get the number of all users matching the search string in a group
	 * @param string $gid ID of the group
	 * @param string $search query string
	 * @return int|false
	 * @throws \Exception
	 */
	public function countUsersInGroup($gid, $search = '') {
		$plugin = $this->which[GroupInterface::COUNT_USERS];

		if ($plugin) {
			return $plugin->countUsersInGroup($gid,$search);
		}
		throw new \Exception('No plugin implements countUsersInGroup in this LDAP Backend.');
	}

	/**
	 * get an array with group details
	 * @param string $gid
	 * @return array|false
	 * @throws \Exception
	 */
	public function getGroupDetails($gid) {
		$plugin = $this->which[GroupInterface::GROUP_DETAILS];

		if ($plugin) {
			return $plugin->getGroupDetails($gid);
		}
		throw new \Exception('No plugin implements getGroupDetails in this LDAP Backend.');
	}
}
