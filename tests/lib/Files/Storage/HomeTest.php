<?php
/**
 * ownCloud
 *
 * @author Robin Appelman
 * @copyright 2012 Robin Appelman icewind@owncloud.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Files\Storage;

use OC\User\User;

class DummyUser extends User {
	private $home;

	private $uid;

	/**
	 * @param string $uid
	 * @param string $home
	 */
	public function __construct($uid, $home) {
		$this->uid = $uid;
		$this->home = $home;
	}

	public function getHome() {
		return $this->home;
	}

	public function getUID() {
		return $this->uid;
	}
}

/**
 * Class Home
 *
 * @group DB
 *
 * @package Test\Files\Storage
 */
class HomeTest extends Storage {
	/**
	 * @var string tmpDir
	 */
	private $tmpDir;

	private $userId;

	/**
	 * @var \OC\User\User $user
	 */
	private $user;

	protected function setUp(): void {
		parent::setUp();

		$this->tmpDir = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->userId = $this->getUniqueID('user_');
		$this->user = new DummyUser($this->userId, $this->tmpDir);
		$this->instance = new \OC\Files\Storage\Home(['user' => $this->user]);
	}

	protected function tearDown(): void {
		\OC_Helper::rmdirr($this->tmpDir);
		parent::tearDown();
	}

	/**
	 * Tests that the home id is in the format home::user1
	 */
	public function testId() {
		$this->assertEquals('home::' . $this->userId, $this->instance->getId());
	}

	/**
	 * Tests that getCache() returns an instance of HomeCache
	 */
	public function testGetCacheReturnsHomeCache() {
		$this->assertInstanceOf('\OC\Files\Cache\HomeCache', $this->instance->getCache());
	}

	public function testGetOwner() {
		$this->assertEquals($this->userId, $this->instance->getOwner(''));
	}
}
