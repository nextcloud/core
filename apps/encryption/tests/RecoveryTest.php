<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Encryption\Tests;

use OC\Files\View;
use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Recovery;
use OCP\Encryption\IFile;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RecoveryTest extends TestCase {
	private static $tempStorage = [];
	/**
	 * @var \OCP\Encryption\IFile|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $fileMock;
	/**
	 * @var \OC\Files\View|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $viewMock;
	/**
	 * @var \OCP\IUserSession|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $userSessionMock;
	/**
	 * @var MockObject|IUser
	 */
	private $user;
	/**
	 * @var \OCA\Encryption\KeyManager|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $keyManagerMock;
	/**
	 * @var \OCP\IConfig|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $configMock;
	/**
	 * @var \OCA\Encryption\Crypto\Crypt|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $cryptMock;
	/**
	 * @var Recovery
	 */
	private $instance;

	public function testEnableAdminRecoverySuccessful() {
		$this->keyManagerMock->expects($this->exactly(2))
			->method('recoveryKeyExists')
			->willReturnOnConsecutiveCalls(false, true);

		$this->cryptMock->expects($this->once())
			->method('createKeyPair')
			->willReturn([
				'publicKey' => 'privateKey',
				'privateKey' => 'publicKey',
			]);

		$this->keyManagerMock->expects($this->once())
			->method('setRecoveryKey')
			->willReturn(false);

		$this->keyManagerMock->expects($this->exactly(2))
			->method('checkRecoveryPassword')
			->willReturnOnConsecutiveCalls(true, true);

		$this->assertTrue($this->instance->enableAdminRecovery('password'));
		$this->assertArrayHasKey('recoveryAdminEnabled', self::$tempStorage);
		$this->assertEquals(1, self::$tempStorage['recoveryAdminEnabled']);

		$this->assertTrue($this->instance->enableAdminRecovery('password'));
	}

	public function testEnableAdminRecoveryCouldNotCheckPassword() {
		$this->keyManagerMock->expects($this->exactly(2))
			->method('recoveryKeyExists')
			->willReturnOnConsecutiveCalls(false, true);

		$this->cryptMock->expects($this->once())
			->method('createKeyPair')
			->willReturn([
				'publicKey' => 'privateKey',
				'privateKey' => 'publicKey',
			]);

		$this->keyManagerMock->expects($this->once())
			->method('setRecoveryKey')
			->willReturn(false);

		$this->keyManagerMock->expects($this->exactly(2))
			->method('checkRecoveryPassword')
			->willReturnOnConsecutiveCalls(true, false);

		$this->assertTrue($this->instance->enableAdminRecovery('password'));
		$this->assertArrayHasKey('recoveryAdminEnabled', self::$tempStorage);
		$this->assertEquals(1, self::$tempStorage['recoveryAdminEnabled']);

		$this->assertFalse($this->instance->enableAdminRecovery('password'));
	}

	public function testEnableAdminRecoveryCouldNotCreateKey() {
		$this->keyManagerMock->expects($this->once())
			->method('recoveryKeyExists')
			->willReturn(false);

		$this->cryptMock->expects($this->once())
			->method('createKeyPair')
			->willReturn(false);

		$this->assertFalse($this->instance->enableAdminRecovery('password'));
	}

	public function testChangeRecoveryKeyPasswordSuccessful() {
		$this->assertFalse($this->instance->changeRecoveryKeyPassword('password',
			'passwordOld'));

		$this->keyManagerMock->expects($this->once())
			->method('getSystemPrivateKey');

		$this->cryptMock->expects($this->once())
			->method('decryptPrivateKey');

		$this->cryptMock->expects($this->once())
			->method('encryptPrivateKey')
			->willReturn(true);

		$this->assertTrue($this->instance->changeRecoveryKeyPassword('password',
			'passwordOld'));
	}

	public function testChangeRecoveryKeyPasswordCouldNotDecryptPrivateRecoveryKey() {
		$this->assertFalse($this->instance->changeRecoveryKeyPassword('password', 'passwordOld'));

		$this->keyManagerMock->expects($this->once())
			->method('getSystemPrivateKey');

		$this->cryptMock->expects($this->once())
			->method('decryptPrivateKey')
			->willReturn(false);

		$this->assertFalse($this->instance->changeRecoveryKeyPassword('password', 'passwordOld'));
	}

	public function testDisableAdminRecovery() {

		$this->keyManagerMock->expects($this->exactly(2))
			->method('checkRecoveryPassword')
			->willReturnOnConsecutiveCalls(true, false);

		$this->assertArrayHasKey('recoveryAdminEnabled', self::$tempStorage);
		$this->assertTrue($this->instance->disableAdminRecovery('password'));
		$this->assertEquals(0, self::$tempStorage['recoveryAdminEnabled']);

		$this->assertFalse($this->instance->disableAdminRecovery('password'));
	}

	public function testIsRecoveryEnabledForUser() {

		$this->configMock->expects($this->exactly(2))
			->method('getUserValue')
			->willReturnOnConsecutiveCalls('1', '0');

		$this->assertTrue($this->instance->isRecoveryEnabledForUser());
		$this->assertFalse($this->instance->isRecoveryEnabledForUser('admin'));
	}

	public function testIsRecoveryKeyEnabled() {
		$this->assertFalse($this->instance->isRecoveryKeyEnabled());
		self::$tempStorage['recoveryAdminEnabled'] = '1';
		$this->assertTrue($this->instance->isRecoveryKeyEnabled());
	}

	public function testSetRecoveryFolderForUser() {
		$this->viewMock->expects($this->exactly(2))
			->method('getDirectoryContent')
			->willReturn([]);
		$this->assertTrue($this->instance->setRecoveryForUser(0));
		$this->assertTrue($this->instance->setRecoveryForUser('1'));
	}

	public function testRecoverUserFiles() {
		$this->viewMock->expects($this->once())
			->method('getDirectoryContent')
			->willReturn([]);

		$this->cryptMock->expects($this->once())
			->method('decryptPrivateKey');
		$this->instance->recoverUsersFiles('password', 'admin');
		$this->addToAssertionCount(1);
	}

	public function testRecoverFile() {
		$this->keyManagerMock->expects($this->once())
			->method('getEncryptedFileKey')
			->willReturn(true);

		$this->keyManagerMock->expects($this->once())
			->method('getShareKey')
			->willReturn(true);

		$this->cryptMock->expects($this->once())
			->method('multiKeyDecrypt')
			->willReturn(true);

		$this->fileMock->expects($this->once())
			->method('getAccessList')
			->willReturn(['users' => ['admin']]);

		$this->keyManagerMock->expects($this->once())
			->method('getPublicKey')
			->willReturn('publicKey');

		$this->keyManagerMock->expects($this->once())
			->method('addSystemKeys')
			->with($this->anything(), $this->anything(), $this->equalTo('admin'))
			->willReturn(['admin' => 'publicKey']);


		$this->cryptMock->expects($this->once())
			->method('multiKeyEncrypt');

		$this->keyManagerMock->expects($this->once())
			->method('setAllFileKeys');

		$this->assertNull(self::invokePrivate($this->instance,
			'recoverFile',
			['/', 'testkey', 'admin']));
	}

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('admin');

		$this->userSessionMock = $this->createMock(IUserSession::class);
		$this->userSessionMock->expects($this->any())
			->method('getUser')
			->willReturn($this->user);
		$this->userSessionMock->expects($this->any())
			->method('isLoggedIn')
			->willReturn(true);

		$this->cryptMock = $this->getMockBuilder(Crypt::class)->disableOriginalConstructor()->getMock();
		$this->keyManagerMock = $this->getMockBuilder(KeyManager::class)->disableOriginalConstructor()->getMock();
		$this->configMock = $this->createMock(IConfig::class);
		$this->fileMock = $this->createMock(IFile::class);
		$this->viewMock = $this->createMock(View::class);

		$this->configMock->expects($this->any())
			->method('setAppValue')
			->willReturnCallback([$this, 'setValueTester']);

		$this->configMock->expects($this->any())
			->method('getAppValue')
			->willReturnCallback([$this, 'getValueTester']);

		$this->instance = new Recovery($this->userSessionMock,
			$this->cryptMock,
			$this->keyManagerMock,
			$this->configMock,
			$this->fileMock,
			$this->viewMock);
	}


	/**
	 * @param $app
	 * @param $key
	 * @param $value
	 */
	public function setValueTester($app, $key, $value) {
		self::$tempStorage[$key] = $value;
	}

	/**
	 * @param $key
	 */
	public function removeValueTester($key) {
		unset(self::$tempStorage[$key]);
	}

	/**
	 * @param $app
	 * @param $key
	 * @return mixed
	 */
	public function getValueTester($app, $key) {
		if (!empty(self::$tempStorage[$key])) {
			return self::$tempStorage[$key];
		}
		return null;
	}


}
