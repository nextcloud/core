<?php
/**
 * @copyright Copyright (c) 2017, EITA Cooperative (eita.org.br)
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

interface IGroupLDAP {

	//Used by LDAPProvider

	/**
	 * Return access for LDAP interaction.
	 * @param string $gid
	 * @return Access instance of Access for LDAP interaction
	 */
	public function getLDAPAccess($gid);

	/**
	 * Return a new LDAP connection for the specified group.
	 * @param string $gid
	 * @return resource of the LDAP connection
	 */
	public function getNewLDAPConnection($gid);

}
