<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Greta Doci <gretadoci@gmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

/**
 * Public interface of ownCloud for apps to use.
 * User session interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * User session
 * @since 6.0.0
 */
interface IUserSession {
	/**
	 * Do a user login
	 *
	 * @param string $uid the username
	 * @param string $password the password
	 * @return bool true if successful
	 * @since 6.0.0
	 */
	public function login($uid, $password);

	/**
	 * Logs the user out including all the session data
	 * Logout, destroys session
	 *
	 * @return void
	 * @since 6.0.0
	 */
	public function logout();

	/**
	 * set the currently active user
	 *
	 * @param \OCP\IUser|null $user
	 * @since 8.0.0
	 */
	public function setUser($user);

	/**
	 * get the current active user
	 *
	 * @return \OCP\IUser|null Current user, otherwise null
	 * @since 8.0.0
	 */
	public function getUser();

	/**
	 * Checks whether the user is logged in
	 *
	 * @return bool if logged in
	 * @since 8.0.0
	 */
	public function isLoggedIn();

	/**
	 * get getImpersonatingUserID
	 *
	 * @return string|null
	 * @since 18.0.0
	 */
	public function getImpersonatingUserID(): ?string;

	/**
	 * set setImpersonatingUserID
	 *
	 * @since 18.0.0
	 */
	public function setImpersonatingUserID(bool $useCurrentUser = true): void;
}
