<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use OC\Files\FileInfo;
use OC\Files\View;
use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Session;
use OCA\Encryption\Util;
use OCP\Encryption\Keys\IStorage;
use OCP\Files\Cache\ICache;
use OCP\Files\Storage;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class KeyManagerTest extends TestCase {
	/**
	 * @var KeyManager
	 */
	private $instance;
	/**
	 * @var string
	 */
	private $userId;

	/** @var string */
	private $systemKeyId;

	/** @var \OCP\Encryption\Keys\IStorage|\PHPUnit\Framework\MockObject\MockObject */
	private $keyStorageMock;

	/** @var \OCA\Encryption\Crypto\Crypt|\PHPUnit\Framework\MockObject\MockObject */
	private $cryptMock;

	/** @var \OCP\IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userMock;

	/** @var \OCA\Encryption\Session|\PHPUnit\Framework\MockObject\MockObject */
	private $sessionMock;

	/** @var \OCP\ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logMock;

	/** @var \OCA\Encryption\Util|\PHPUnit\Framework\MockObject\MockObject */
	private $utilMock;

	/** @var \OCP\IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $configMock;

	/** @var ILockingProvider|MockObject */
	private $lockingProviderMock;

	protected function setUp(): void {
		parent::setUp();
		$this->userId = 'user1';
		$this->systemKeyId = 'systemKeyId';
		$this->keyStorageMock = $this->createMock(IStorage::class);
		$this->cryptMock = $this->getMockBuilder(Crypt::class)
			->disableOriginalConstructor()
			->getMock();
		$this->configMock = $this->createMock(IConfig::class);
		$this->configMock->expects($this->any())
			->method('getAppValue')
			->willReturn($this->systemKeyId);
		$this->userMock = $this->createMock(IUserSession::class);
		$this->sessionMock = $this->getMockBuilder(Session::class)
			->disableOriginalConstructor()
			->getMock();
		$this->logMock = $this->createMock(ILogger::class);
		$this->utilMock = $this->getMockBuilder(Util::class)
			->disableOriginalConstructor()
			->getMock();
		$this->lockingProviderMock = $this->createMock(ILockingProvider::class);

		$this->instance = new KeyManager(
			$this->keyStorageMock,
			$this->cryptMock,
			$this->configMock,
			$this->userMock,
			$this->sessionMock,
			$this->logMock,
			$this->utilMock,
			$this->lockingProviderMock
		);
	}

	public function testDeleteShareKey() {
		$this->keyStorageMock->expects($this->any())
			->method('deleteFileKey')
			->with($this->equalTo('/path'), $this->equalTo('keyId.shareKey'))
			->willReturn(true);

		$this->assertTrue(
			$this->instance->deleteShareKey('/path', 'keyId')
		);
	}

	public function testGetPrivateKey() {
		$this->keyStorageMock->expects($this->any())
			->method('getUserKey')
			->with($this->equalTo($this->userId), $this->equalTo('privateKey'))
			->willReturn('privateKey');


		$this->assertSame('privateKey',
			$this->instance->getPrivateKey($this->userId)
		);
	}

	public function testGetPublicKey() {
		$this->keyStorageMock->expects($this->any())
			->method('getUserKey')
			->with($this->equalTo($this->userId), $this->equalTo('publicKey'))
			->willReturn('publicKey');


		$this->assertSame('publicKey',
			$this->instance->getPublicKey($this->userId)
		);
	}

	public function testRecoveryKeyExists() {
		$this->keyStorageMock->expects($this->any())
			->method('getSystemUserKey')
			->with($this->equalTo($this->systemKeyId . '.publicKey'))
			->willReturn('recoveryKey');


		$this->assertTrue($this->instance->recoveryKeyExists());
	}

	public function testCheckRecoveryKeyPassword() {
		$this->keyStorageMock->expects($this->any())
			->method('getSystemUserKey')
			->with($this->equalTo($this->systemKeyId . '.privateKey'))
			->willReturn('recoveryKey');
		$this->cryptMock->expects($this->any())
			->method('decryptPrivateKey')
			->with($this->equalTo('recoveryKey'), $this->equalTo('pass'))
			->willReturn('decryptedRecoveryKey');

		$this->assertTrue($this->instance->checkRecoveryPassword('pass'));
	}

	public function testSetPublicKey() {
		$this->keyStorageMock->expects($this->any())
			->method('setUserKey')
			->with(
				$this->equalTo($this->userId),
				$this->equalTo('publicKey'),
				$this->equalTo('key'))
			->willReturn(true);


		$this->assertTrue(
			$this->instance->setPublicKey($this->userId, 'key')
		);
	}

	public function testSetPrivateKey() {
		$this->keyStorageMock->expects($this->any())
			->method('setUserKey')
			->with(
				$this->equalTo($this->userId),
				$this->equalTo('privateKey'),
				$this->equalTo('key'))
			->willReturn(true);


		$this->assertTrue(
			$this->instance->setPrivateKey($this->userId, 'key')
		);
	}

	/**
	 * @dataProvider dataTestUserHasKeys
	 */
	public function testUserHasKeys($key, $expected) {
		$this->keyStorageMock->expects($this->exactly(2))
			->method('getUserKey')
			->with($this->equalTo($this->userId), $this->anything())
			->willReturn($key);


		$this->assertSame($expected,
			$this->instance->userHasKeys($this->userId)
		);
	}

	public function dataTestUserHasKeys() {
		return [
			['key', true],
			['', false]
		];
	}


	public function testUserHasKeysMissingPrivateKey() {
		$this->expectException(\OCA\Encryption\Exceptions\PrivateKeyMissingException::class);

		$this->keyStorageMock->expects($this->exactly(2))
			->method('getUserKey')
			->willReturnCallback(function ($uid, $keyID, $encryptionModuleId) {
				if ($keyID === 'privateKey') {
					return '';
				}
				return 'key';
			});

		$this->instance->userHasKeys($this->userId);
	}


	public function testUserHasKeysMissingPublicKey() {
		$this->expectException(\OCA\Encryption\Exceptions\PublicKeyMissingException::class);

		$this->keyStorageMock->expects($this->exactly(2))
			->method('getUserKey')
			->willReturnCallback(function ($uid, $keyID, $encryptionModuleId) {
				if ($keyID === 'publicKey') {
					return '';
				}
				return 'key';
			});

		$this->instance->userHasKeys($this->userId);
	}

	/**
	 * @dataProvider dataTestInit
	 *
	 * @param bool $useMasterKey
	 */
	public function testInit($useMasterKey) {

		/** @var \OCA\Encryption\KeyManager|\PHPUnit\Framework\MockObject\MockObject $instance */
		$instance = $this->getMockBuilder(KeyManager::class)
			->setConstructorArgs(
				[
					$this->keyStorageMock,
					$this->cryptMock,
					$this->configMock,
					$this->userMock,
					$this->sessionMock,
					$this->logMock,
					$this->utilMock,
					$this->lockingProviderMock
				]
			)->setMethods(['getMasterKeyId', 'getMasterKeyPassword', 'getSystemPrivateKey', 'getPrivateKey'])
			->getMock();

		$this->utilMock->expects($this->once())->method('isMasterKeyEnabled')
			->willReturn($useMasterKey);

		$this->sessionMock->expects($this->at(0))->method('setStatus')
			->with(Session::INIT_EXECUTED);

		$instance->expects($this->any())->method('getMasterKeyId')->willReturn('masterKeyId');
		$instance->expects($this->any())->method('getMasterKeyPassword')->willReturn('masterKeyPassword');
		$instance->expects($this->any())->method('getSystemPrivateKey')->with('masterKeyId')->willReturn('privateMasterKey');
		$instance->expects($this->any())->method('getPrivateKey')->with($this->userId)->willReturn('privateUserKey');

		if ($useMasterKey) {
			$this->cryptMock->expects($this->once())->method('decryptPrivateKey')
				->with('privateMasterKey', 'masterKeyPassword', 'masterKeyId')
				->willReturn('key');
		} else {
			$this->cryptMock->expects($this->once())->method('decryptPrivateKey')
				->with('privateUserKey', 'pass', $this->userId)
				->willReturn('key');
		}

		$this->sessionMock->expects($this->once())->method('setPrivateKey')
			->with('key');

		$this->assertTrue($instance->init($this->userId, 'pass'));
	}

	public function dataTestInit() {
		return [
			[true],
			[false]
		];
	}


	public function testSetRecoveryKey() {
		$this->keyStorageMock->expects($this->exactly(2))
			->method('setSystemUserKey')
			->willReturn(true);
		$this->cryptMock->expects($this->any())
			->method('encryptPrivateKey')
			->with($this->equalTo('privateKey'), $this->equalTo('pass'))
			->willReturn('decryptedPrivateKey');


		$this->assertTrue(
			$this->instance->setRecoveryKey('pass',
				['publicKey' => 'publicKey', 'privateKey' => 'privateKey'])
		);
	}

	public function testSetSystemPrivateKey() {
		$this->keyStorageMock->expects($this->exactly(1))
			->method('setSystemUserKey')
			->with($this->equalTo('keyId.privateKey'), $this->equalTo('key'))
			->willReturn(true);


		$this->assertTrue(
			$this->instance->setSystemPrivateKey('keyId', 'key')
		);
	}

	public function testGetSystemPrivateKey() {
		$this->keyStorageMock->expects($this->exactly(1))
			->method('getSystemUserKey')
			->with($this->equalTo('keyId.privateKey'))
			->willReturn('systemPrivateKey');


		$this->assertSame('systemPrivateKey',
			$this->instance->getSystemPrivateKey('keyId')
		);
	}

	public function testGetEncryptedFileKey() {
		$this->keyStorageMock->expects($this->once())
			->method('getFileKey')
			->with('/', 'fileKey')
			->willReturn(true);

		$this->assertTrue($this->instance->getEncryptedFileKey('/'));
	}

	public function dataTestGetFileKey() {
		return [
			['user1', false, 'privateKey', true],
			['user1', false, false, ''],
			['user1', true, 'privateKey', true],
			['user1', true, false, ''],
			[null, false, 'privateKey', true],
			[null, false, false, ''],
			[null, true, 'privateKey', true],
			[null, true, false, '']
		];
	}

	/**
	 * @dataProvider dataTestGetFileKey
	 *
	 * @param $uid
	 * @param $isMasterKeyEnabled
	 * @param $privateKey
	 * @param $expected
	 */
	public function testGetFileKey($uid, $isMasterKeyEnabled, $privateKey, $expected) {
		$path = '/foo.txt';

		if ($isMasterKeyEnabled) {
			$expectedUid = 'masterKeyId';
			$this->configMock->expects($this->any())->method('getSystemValue')->with('secret')
				->willReturn('password');
		} elseif (!$uid) {
			$expectedUid = 'systemKeyId';
		} else {
			$expectedUid = $uid;
		}

		$this->invokePrivate($this->instance, 'masterKeyId', ['masterKeyId']);

		$this->keyStorageMock->expects($this->at(0))
			->method('getFileKey')
			->with($path, 'fileKey', 'OC_DEFAULT_MODULE')
			->willReturn(true);

		$this->keyStorageMock->expects($this->at(1))
			->method('getFileKey')
			->with($path, $expectedUid . '.shareKey', 'OC_DEFAULT_MODULE')
			->willReturn(true);

		$this->utilMock->expects($this->any())->method('isMasterKeyEnabled')
			->willReturn($isMasterKeyEnabled);

		if (is_null($uid)) {
			$this->keyStorageMock->expects($this->once())
				->method('getSystemUserKey')
				->willReturn(true);
			$this->cryptMock->expects($this->once())
				->method('decryptPrivateKey')
				->willReturn($privateKey);
		} else {
			$this->keyStorageMock->expects($this->never())
				->method('getSystemUserKey');
			$this->sessionMock->expects($this->once())->method('getPrivateKey')->willReturn($privateKey);
		}

		if ($privateKey) {
			$this->cryptMock->expects($this->once())
				->method('multiKeyDecrypt')
				->willReturn(true);
		} else {
			$this->cryptMock->expects($this->never())
				->method('multiKeyDecrypt');
		}

		$this->assertSame($expected,
			$this->instance->getFileKey($path, $uid)
		);
	}

	public function testDeletePrivateKey() {
		$this->keyStorageMock->expects($this->once())
			->method('deleteUserKey')
			->with('user1', 'privateKey')
			->willReturn(true);

		$this->assertTrue(self::invokePrivate($this->instance,
			'deletePrivateKey',
			[$this->userId]));
	}

	public function testDeleteAllFileKeys() {
		$this->keyStorageMock->expects($this->once())
			->method('deleteAllFileKeys')
			->willReturn(true);

		$this->assertTrue($this->instance->deleteAllFileKeys('/'));
	}

	/**
	 * test add public share key and or recovery key to the list of public keys
	 *
	 * @dataProvider dataTestAddSystemKeys
	 *
	 * @param array $accessList
	 * @param array $publicKeys
	 * @param string $uid
	 * @param array $expectedKeys
	 */
	public function testAddSystemKeys($accessList, $publicKeys, $uid, $expectedKeys) {
		$publicShareKeyId = 'publicShareKey';
		$recoveryKeyId = 'recoveryKey';

		$this->keyStorageMock->expects($this->any())
			->method('getSystemUserKey')
			->willReturnCallback(function ($keyId, $encryptionModuleId) {
				return $keyId;
			});

		$this->utilMock->expects($this->any())
			->method('isRecoveryEnabledForUser')
			->willReturnCallback(function ($uid) {
				if ($uid === 'user1') {
					return true;
				}
				return false;
			});

		// set key IDs
		self::invokePrivate($this->instance, 'publicShareKeyId', [$publicShareKeyId]);
		self::invokePrivate($this->instance, 'recoveryKeyId', [$recoveryKeyId]);

		$result = $this->instance->addSystemKeys($accessList, $publicKeys, $uid);

		foreach ($expectedKeys as $expected) {
			$this->assertArrayHasKey($expected, $result);
		}

		$this->assertSameSize($expectedKeys, $result);
	}

	/**
	 * data provider for testAddSystemKeys()
	 *
	 * @return array
	 */
	public function dataTestAddSystemKeys() {
		return [
			[['public' => true],[], 'user1', ['publicShareKey', 'recoveryKey']],
			[['public' => false], [], 'user1', ['recoveryKey']],
			[['public' => true],[], 'user2', ['publicShareKey']],
			[['public' => false], [], 'user2', []],
		];
	}

	public function testGetMasterKeyId() {
		$this->assertSame('systemKeyId', $this->instance->getMasterKeyId());
	}

	public function testGetPublicMasterKey() {
		$this->keyStorageMock->expects($this->once())->method('getSystemUserKey')
			->with('systemKeyId.publicKey', \OCA\Encryption\Crypto\Encryption::ID)
			->willReturn(true);

		$this->assertTrue(
			$this->instance->getPublicMasterKey()
		);
	}

	public function testGetMasterKeyPassword() {
		$this->configMock->expects($this->once())->method('getSystemValue')->with('secret')
			->willReturn('password');

		$this->assertSame('password',
			$this->invokePrivate($this->instance, 'getMasterKeyPassword', [])
		);
	}


	public function testGetMasterKeyPasswordException() {
		$this->expectException(\Exception::class);

		$this->configMock->expects($this->once())->method('getSystemValue')->with('secret')
			->willReturn('');

		$this->invokePrivate($this->instance, 'getMasterKeyPassword', []);
	}

	/**
	 * @dataProvider dataTestValidateMasterKey
	 *
	 * @param $masterKey
	 */
	public function testValidateMasterKey($masterKey) {

		/** @var \OCA\Encryption\KeyManager | \PHPUnit\Framework\MockObject\MockObject $instance */
		$instance = $this->getMockBuilder(KeyManager::class)
			->setConstructorArgs(
				[
					$this->keyStorageMock,
					$this->cryptMock,
					$this->configMock,
					$this->userMock,
					$this->sessionMock,
					$this->logMock,
					$this->utilMock,
					$this->lockingProviderMock
				]
			)->setMethods(['getPublicMasterKey', 'setSystemPrivateKey', 'getMasterKeyPassword'])
			->getMock();

		$instance->expects($this->once())->method('getPublicMasterKey')
			->willReturn($masterKey);

		$instance->expects($this->any())->method('getMasterKeyPassword')->willReturn('masterKeyPassword');
		$this->cryptMock->expects($this->any())->method('generateHeader')->willReturn('header');

		if (empty($masterKey)) {
			$this->cryptMock->expects($this->once())->method('createKeyPair')
				->willReturn(['publicKey' => 'public', 'privateKey' => 'private']);
			$this->keyStorageMock->expects($this->once())->method('setSystemUserKey')
				->with('systemKeyId.publicKey', 'public', \OCA\Encryption\Crypto\Encryption::ID);
			$this->cryptMock->expects($this->once())->method('encryptPrivateKey')
				->with('private', 'masterKeyPassword', 'systemKeyId')
				->willReturn('EncryptedKey');
			$this->lockingProviderMock->expects($this->once())
				->method('acquireLock');
			$instance->expects($this->once())->method('setSystemPrivateKey')
				->with('systemKeyId', 'headerEncryptedKey');
		} else {
			$this->cryptMock->expects($this->never())->method('createKeyPair');
			$this->keyStorageMock->expects($this->never())->method('setSystemUserKey');
			$this->cryptMock->expects($this->never())->method('encryptPrivateKey');
			$instance->expects($this->never())->method('setSystemPrivateKey');
		}

		$instance->validateMasterKey();
	}

	public function testValidateMasterKeyLocked() {
		/** @var \OCA\Encryption\KeyManager | \PHPUnit_Framework_MockObject_MockObject $instance */
		$instance = $this->getMockBuilder(KeyManager::class)
			->setConstructorArgs(
				[
					$this->keyStorageMock,
					$this->cryptMock,
					$this->configMock,
					$this->userMock,
					$this->sessionMock,
					$this->logMock,
					$this->utilMock,
					$this->lockingProviderMock
				]
			)->setMethods(['getPublicMasterKey', 'getPrivateMasterKey', 'setSystemPrivateKey', 'getMasterKeyPassword'])
			->getMock();

		$instance->expects($this->once())->method('getPublicMasterKey')
			->willReturn('');
		$instance->expects($this->once())->method('getPrivateMasterKey')
			->willReturn('');

		$instance->expects($this->any())->method('getMasterKeyPassword')->willReturn('masterKeyPassword');
		$this->cryptMock->expects($this->any())->method('generateHeader')->willReturn('header');

		$this->lockingProviderMock->expects($this->once())
			->method('acquireLock')
			->willThrowException(new LockedException('encryption-generateMasterKey'));

		$this->expectException(LockedException::class);
		$instance->validateMasterKey();
	}

	public function dataTestValidateMasterKey() {
		return [
			['masterKey'],
			['']
		];
	}

	public function testGetVersionWithoutFileInfo() {
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()->getMock();
		$view->expects($this->once())
			->method('getFileInfo')
			->with('/admin/files/myfile.txt')
			->willReturn(false);

		/** @var \OC\Files\View $view */
		$this->assertSame(0, $this->instance->getVersion('/admin/files/myfile.txt', $view));
	}

	public function testGetVersionWithFileInfo() {
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()->getMock();
		$fileInfo = $this->getMockBuilder(FileInfo::class)
			->disableOriginalConstructor()->getMock();
		$fileInfo->expects($this->once())
			->method('getEncryptedVersion')
			->willReturn(1337);
		$view->expects($this->once())
			->method('getFileInfo')
			->with('/admin/files/myfile.txt')
			->willReturn($fileInfo);

		/** @var \OC\Files\View $view */
		$this->assertSame(1337, $this->instance->getVersion('/admin/files/myfile.txt', $view));
	}

	public function testSetVersionWithFileInfo() {
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()->getMock();
		$cache = $this->getMockBuilder(ICache::class)
			->disableOriginalConstructor()->getMock();
		$cache->expects($this->once())
			->method('update')
			->with(123, ['encrypted' => 5, 'encryptedVersion' => 5]);
		$storage = $this->getMockBuilder(Storage::class)
			->disableOriginalConstructor()->getMock();
		$storage->expects($this->once())
			->method('getCache')
			->willReturn($cache);
		$fileInfo = $this->getMockBuilder(FileInfo::class)
			->disableOriginalConstructor()->getMock();
		$fileInfo->expects($this->once())
			->method('getStorage')
			->willReturn($storage);
		$fileInfo->expects($this->once())
			->method('getId')
			->willReturn(123);
		$view->expects($this->once())
			->method('getFileInfo')
			->with('/admin/files/myfile.txt')
			->willReturn($fileInfo);

		/** @var \OC\Files\View $view */
		$this->instance->setVersion('/admin/files/myfile.txt', 5, $view);
	}

	public function testSetVersionWithoutFileInfo() {
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()->getMock();
		$view->expects($this->once())
			->method('getFileInfo')
			->with('/admin/files/myfile.txt')
			->willReturn(false);

		/** @var \OC\Files\View $view */
		$this->instance->setVersion('/admin/files/myfile.txt', 5, $view);
	}

	public function testBackupUserKeys() {
		$this->keyStorageMock->expects($this->once())->method('backupUserKeys')
			->with('OC_DEFAULT_MODULE', 'test', 'user1');
		$this->instance->backupUserKeys('test', 'user1');
	}
}
