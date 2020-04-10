<?php
/**
 * @copyright 2016 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Authentication\LoginCredentials;

use OCP\Authentication\LoginCredentials\ICredentials;

class Credentials implements ICredentials {

	/** @var string */
	private $uid;

	/** @var string */
	private $loginName;

	/** @var string */
	private $password;

	/**
	 * @param string $uid
	 * @param string $loginName
	 * @param string $password
	 */
	public function __construct($uid, $loginName, $password) {
		$this->uid = $uid;
		$this->loginName = $loginName;
		$this->password = $password;
	}

	/**
	 * @return string
	 */
	public function getUID() {
		return $this->uid;
	}

	/**
	 * @return string
	 */
	public function getLoginName() {
		return $this->loginName;
	}

	/**
	 * @return string
	 */
	public function getPassword() {
		return $this->password;
	}

}
