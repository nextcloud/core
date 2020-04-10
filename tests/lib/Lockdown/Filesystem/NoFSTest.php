<?php
/**
 * @copyright 2016, Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace Test\Lockdown\Filesystem;

use OC\Authentication\Token\DefaultToken;
use OC\Files\Filesystem;
use OC\Lockdown\Filesystem\NullStorage;
use Test\Traits\UserTrait;

/**
 * @group DB
 */
class NoFSTest extends \Test\TestCase {
	use UserTrait;

	protected function tearDown(): void {
		$token = new DefaultToken();
		$token->setScope([
			'filesystem' => true
		]);
		\OC::$server->getLockdownManager()->setToken($token);
		parent::tearDown();
	}

	protected function setUp(): void {
		parent::setUp();
		$token = new DefaultToken();
		$token->setScope([
			'filesystem' => false
		]);

		\OC::$server->getLockdownManager()->setToken($token);
		$this->createUser('foo', 'var');
	}

	public function testSetupFS() {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS('foo');

		$this->assertInstanceOf(NullStorage::class, Filesystem::getStorage('/foo/files'));
	}
}
