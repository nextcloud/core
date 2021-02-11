<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Files\Tests\BackgroundJob;

use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Temporary;
use OCA\Files\BackgroundJob\ScanFiles;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;
use Test\Traits\MountProviderTrait;
use Test\Traits\UserTrait;

/**
 * Class ScanFilesTest
 *
 * @package OCA\Files\Tests\BackgroundJob
 * @group DB
 */
class ScanFilesTest extends TestCase {
	use UserTrait;
	use MountProviderTrait;

	/** @var ScanFiles */
	private $scanFiles;
	/** @var \OCP\Files\Config\IUserMountCache */
	private $mountCache;

	protected function setUp(): void {
		parent::setUp();

		$config = $this->createMock(IConfig::class);
		$userManager = $this->createMock(IUserManager::class);
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$logger = $this->createMock(ILogger::class);
		$connection = \OC::$server->getDatabaseConnection();
		$this->mountCache = \OC::$server->getUserMountCache();

		$this->scanFiles = $this->getMockBuilder('\OCA\Files\BackgroundJob\ScanFiles')
			->setConstructorArgs([
				$config,
				$userManager,
				$dispatcher,
				$logger,
				$connection,
			])
			->setMethods(['runScanner'])
			->getMock();
	}

	private function runJob() {
		$this->invokePrivate($this->scanFiles, 'run', [[]]);
	}

	private function getUser(string $userId): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn($userId);
		return $user;
	}

	private function setupStorage(string $user, string $mountPoint) {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$storage->getScanner()->scan('');

		$this->createUser($user, '');
		$this->mountCache->registerMounts($this->getUser($user), [
			new MountPoint($storage, $mountPoint)
		]);

		return $storage;
	}

	public function testAllScanned() {
		$this->setupStorage('foouser', '/foousers/files/foo');

		$this->scanFiles->expects($this->never())
			->method('runScanner');
		$this->runJob();
	}

	public function testUnscanned() {
		$storage = $this->setupStorage('foouser', '/foousers/files/foo');
		$storage->getCache()->put('foo', ['size' => -1]);

		$this->scanFiles->expects($this->once())
			->method('runScanner')
			->with('foouser');
		$this->runJob();
	}
}
