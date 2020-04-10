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

namespace OCA\User_LDAP\Tests;

use OCA\User_LDAP\ILDAPGroupPlugin;

class LDAPGroupPluginDummy implements ILDAPGroupPlugin {


	public function respondToActions() {
		return null;
	}

	public function createGroup($gid) {
		return null;
	}

	public function deleteGroup($gid) {
		return null;
	}

	public function addToGroup($uid, $gid) {
		return null;
	}

	public function removeFromGroup($uid, $gid) {
		return null;
	}

	public function countUsersInGroup($gid, $search = '') {
		return null;
	}

	public function getGroupDetails($gid) {
		return null;
	}
}
