<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\TwoFactorBackupCodes\Tests\Service;

use OCA\TwoFactorBackupCodes\Service\BackupCodeStorage;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Test\TestCase;

/**
 * @group DB
 */
class BackupCodeStorageTest extends TestCase {

	/** @var BackupCodeStorage */
	private $storage;

	/** @var string */
	private $testUID = 'test123456789';

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $notificationManager;

	protected function setUp(): void {
		parent::setUp();

		$this->storage = \OC::$server->query(BackupCodeStorage::class);

		$this->notificationManager = $this->createMock(IManager::class);
		$this->notificationManager->method('createNotification')
			->willReturn(\OC::$server->query(IManager::class)->createNotification());
		$this->overwriteService(IManager::class, $this->notificationManager);
	}

	public function testSimpleWorkFlow() {
		$user = $this->getMockBuilder(\OCP\IUser::class)->getMock();
		$user->expects($this->any())
			->method('getUID')
			->willReturn($this->testUID);

		$this->notificationManager->expects($this->once())
			->method('markProcessed')
			->with($this->callback(function (INotification $notification) {
				return $notification->getUser() === $this->testUID &&
					$notification->getObjectType() === 'create' &&
					$notification->getObjectId() === 'codes' &&
					$notification->getApp() === 'twofactor_backupcodes';
			}));

		// Create codes
		$codes = $this->storage->createCodes($user, 5);
		$this->assertCount(5, $codes);
		$this->assertTrue($this->storage->hasBackupCodes($user));
		$initialState = [
			'enabled' => true,
			'total' => 5,
			'used' => 0,
		];
		$this->assertEquals($initialState, $this->storage->getBackupCodesState($user));

		// Use codes
		$code = $codes[2];
		$this->assertTrue($this->storage->validateCode($user, $code));
		// Code must not be used twice
		$this->assertFalse($this->storage->validateCode($user, $code));
		// Invalid codes are invalid
		$this->assertFalse($this->storage->validateCode($user, 'I DO NOT EXIST'));
		$stateAfter = [
			'enabled' => true,
			'total' => 5,
			'used' => 1,
		];
		$this->assertEquals($stateAfter, $this->storage->getBackupCodesState($user));

		// Deplete codes
		$this->assertTrue($this->storage->validateCode($user, $codes[0]));
		$this->assertTrue($this->storage->validateCode($user, $codes[1]));
		$this->assertTrue($this->storage->validateCode($user, $codes[3]));
		$this->assertTrue($this->storage->validateCode($user, $codes[4]));
		$stateAllUsed = [
			'enabled' => true,
			'total' => 5,
			'used' => 5,
		];
		$this->assertEquals($stateAllUsed, $this->storage->getBackupCodesState($user));
	}

}
