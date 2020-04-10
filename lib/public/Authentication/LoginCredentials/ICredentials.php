<?php
/**
 * @copyright 2016 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\Authentication\LoginCredentials;

use OCP\Authentication\Exceptions\PasswordUnavailableException;

/**
 * @since 12
 */
interface ICredentials {

	/**
	 * Get the user UID
	 *
	 * @since 12
	 *
	 * @return string
	 */
	public function getUID();

	/**
	 * Get the login name the users used to login
	 *
	 * @since 12
	 *
	 * @return string
	 */
	public function getLoginName();

	/**
	 * Get the password
	 *
	 * @since 12
	 *
	 * @return string
	 * @throws PasswordUnavailableException
	 */
	public function getPassword();
}
