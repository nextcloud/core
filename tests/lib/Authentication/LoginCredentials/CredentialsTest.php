<?php

/**
 * @copyright 2016 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2016 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Authentication\LoginCredentials;

use OC\Authentication\LoginCredentials\Credentials;
use Test\TestCase;

class CredentialsTest extends TestCase {

	/** @var string */
	private $uid;

	/** @var string */
	private $user;

	/** @var string */
	private $password;

	/** @var Credentials */
	private $credentials;

	protected function setUp(): void {
		parent::setUp();

		$this->uid = 'user123';
		$this->user = 'User123';
		$this->password = '123456';

		$this->credentials = new Credentials($this->uid, $this->user, $this->password);
	}

	public function testGetUID() {
		$this->assertEquals($this->uid, $this->credentials->getUID());
	}

	public function testGetUserName() {
		$this->assertEquals($this->user, $this->credentials->getLoginName());
	}

	public function testGetPassword() {
		$this->assertEquals($this->password, $this->credentials->getPassword());
	}

}
