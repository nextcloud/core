<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Greta Doci <gretadoci@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Migration;

use OCP\IUser;
use OCP\IUserSession;

class DummyUserSession implements IUserSession {

	/**
	 * @var IUser
	 */
	private $user;

	public function login($uid, $password) {
	}

	public function logout() {
	}

	public function setUser($user) {
		$this->user = $user;
	}

	public function getUser() {
		return $this->user;
	}

	public function isLoggedIn() {
		return !is_null($this->user);
	}

	/**
	 * get getImpersonatingUserID
	 *
	 * @return string|null
	 * @since 17.0.0
	 */
	public function getImpersonatingUserID() : ?string {
		return null;
	}

	/**
	 * set setImpersonatingUserID
	 *
	 * @since 17.0.0
	 */
	public function setImpersonatingUserID(bool $useCurrentUser = true): void {
		//no OP
	}
}
