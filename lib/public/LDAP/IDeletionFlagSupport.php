<?php
/**
 * @copyright Copyright (c) 2016, Roger Szabo (roger.szabo@web.de)
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author root <root@localhost.localdomain>
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

namespace OCP\LDAP;

/**
 * Interface IDeletionFlagSupport
 *
 * @package OCP\LDAP
 * @since 11.0.0
 */
interface IDeletionFlagSupport {
	/**
	 * Flag record for deletion.
	 * @param string $uid user id
	 * @since 11.0.0
	 */
	public function flagRecord($uid);
	
	/**
	 * Unflag record for deletion.
	 * @param string $uid user id
	 * @since 11.0.0
	 */
	public function unflagRecord($uid);
}
