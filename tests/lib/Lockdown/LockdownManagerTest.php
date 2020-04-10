<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
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

namespace Test\Lockdown;

use OC\Authentication\Token\DefaultToken;
use OC\Lockdown\LockdownManager;
use OCP\ISession;
use Test\TestCase;

class LockdownManagerTest extends TestCase {
	private $sessionCallback;

	protected function setUp(): void {
		parent::setUp();

		$this->sessionCallback = function () {
			return $this->createMock(ISession::class);
		};
	}

	public function testCanAccessFilesystemDisabled() {
		$manager = new LockdownManager($this->sessionCallback);
		$this->assertTrue($manager->canAccessFilesystem());
	}

	public function testCanAccessFilesystemAllowed() {
		$token = new DefaultToken();
		$token->setScope(['filesystem' => true]);
		$manager = new LockdownManager($this->sessionCallback);
		$manager->setToken($token);
		$this->assertTrue($manager->canAccessFilesystem());
	}

	public function testCanAccessFilesystemNotAllowed() {
		$token = new DefaultToken();
		$token->setScope(['filesystem' => false]);
		$manager = new LockdownManager($this->sessionCallback);
		$manager->setToken($token);
		$this->assertFalse($manager->canAccessFilesystem());
	}
}
