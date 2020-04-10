<?php
/**
 * @copyright Copyright (c) 2017, Christoph Seitz <christoph.seitz@posteo.de>
 *
 * @author Christoph Seitz <christoph.seitz@posteo.de>
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

namespace OCA\DAV\CalDAV\Principal;

/**
 * Class User
 *
 * @package OCA\DAV\CalDAV\Principal
 */
class User extends \Sabre\CalDAV\Principal\User {

	/**
	 * Returns a list of ACE's for this node.
	 *
	 * Each ACE has the following properties:
	 *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
	 *     currently the only supported privileges
	 *   * 'principal', a url to the principal who owns the node
	 *   * 'protected' (optional), indicating that this ACE is not allowed to
	 *      be updated.
	 *
	 * @return array
	 */
	function getACL() {
		$acl = parent::getACL();
		$acl[] = [
			'privilege' => '{DAV:}read',
			'principal' => '{DAV:}authenticated',
			'protected' => true,
		];
		return $acl;
	}

}
