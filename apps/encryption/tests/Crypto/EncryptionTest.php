<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Encryption\Tests\Crypto;

use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\Crypto\DecryptAll;
use OCA\Encryption\Crypto\EncryptAll;
use OCA\Encryption\Crypto\Encryption;
use OCA\Encryption\Exceptions\PublicKeyMissingException;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Session;
use OCA\Encryption\Util;
use OCP\Files\Storage;
use OCP\IL10N;
use OCP\ILogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class EncryptionTest extends TestCase {

	/** @var Encryption */
	private $instance;

	/** @var \OCA\Encryption\KeyManager|\PHPUnit_Framework_MockObject_MockObject */
	private $keyManagerMock;

	/** @var \OCA\Encryption\Crypto\EncryptAll|\PHPUnit_Framework_MockObject_MockObject */
	private $encryptAllMock;

	/** @var \OCA\Encryption\Crypto\DecryptAll|\PHPUnit_Framework_MockObject_MockObject */
	private $decryptAllMock;

	/** @var \OCA\Encryption\Session|\PHPUnit_Framework_MockObject_MockObject */
	private $sessionMock;

	/** @var \OCA\Encryption\Crypto\Crypt|\PHPUnit_Framework_MockObject_MockObject */
	private $cryptMock;

	/** @var \OCA\Encryption\Util|\PHPUnit_Framework_MockObject_MockObject */
	private $utilMock;

	/** @var \OCP\ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $loggerMock;

	/** @var \OCP\IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10nMock;

	/** @var \OCP\Files\Storage|\PHPUnit_Framework_MockObject_MockObject */
	private $storageMock;

	protected function setUp(): void {
		parent::setUp();

		$this->storageMock = $this->getMockBuilder(Storage::class)
			->disableOriginalConstructor()->getMock();
		$this->cryptMock = $this->getMockBuilder(Crypt::class)
			->disableOriginalConstructor()
			->getMock();
		$this->utilMock = $this->getMockBuilder(Util::class)
			->disableOriginalConstructor()
			->getMock();
		$this->keyManagerMock = $this->getMockBuilder(KeyManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->sessionMock = $this->getMockBuilder(Session::class)
			->disableOriginalConstructor()
			->getMock();
		$this->encryptAllMock = $this->getMockBuilder(EncryptAll::class)
			->disableOriginalConstructor()
			->getMock();
		$this->decryptAllMock = $this->getMockBuilder(DecryptAll::class)
			->disableOriginalConstructor()
			->getMock();
		$this->loggerMock = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10nMock = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10nMock->expects($this->any())
			->method('t')
			->with($this->anything())
			->willReturnArgument(0);

		$this->instance = new Encryption(
			$this->cryptMock,
			$this->keyManagerMock,
			$this->utilMock,
			$this->sessionMock,
			$this->encryptAllMock,
			$this->decryptAllMock,
			$this->loggerMock,
			$this->l10nMock
		);

	}

	/**
	 * test if public key from one of the recipients is missing
	 */
	public function testEndUser1() {
		$this->instance->begin('/foo/bar', 'user1', 'r', [], ['users' => ['user1', 'user2', 'user3']]);
		$this->endTest();
	}

	/**
	 * test if public key from owner is missing
	 *
	 */
	public function testEndUser2() {
		$this->expectException(\OCA\Encryption\Exceptions\PublicKeyMissingException::class);

		$this->instance->begin('/foo/bar', 'user2', 'r', [], ['users' => ['user1', 'user2', 'user3']]);
		$this->endTest();
	}

	/**
	 * common part of testEndUser1 and testEndUser2
	 *
	 * @throws PublicKeyMissingException
	 */
	public function endTest() {
		// prepare internal variables
		self::invokePrivate($this->instance, 'isWriteOperation', [true]);
		self::invokePrivate($this->instance, 'writeCache', ['']);

		$this->keyManagerMock->expects($this->any())
			->method('getPublicKey')
			->willReturnCallback([$this, 'getPublicKeyCallback']);
		$this->keyManagerMock->expects($this->any())
			->method('addSystemKeys')
			->willReturnCallback([$this, 'addSystemKeysCallback']);
		$this->cryptMock->expects($this->any())
			->method('multiKeyEncrypt')
			->willReturn(true);

		$this->instance->end('/foo/bar');
	}


	public function getPublicKeyCallback($uid) {
		if ($uid === 'user2') {
			throw new PublicKeyMissingException($uid);
		}
		return $uid;
	}

	public function addSystemKeysCallback($accessList, $publicKeys) {
		$this->assertSame(2, count($publicKeys));
		$this->assertArrayHasKey('user1', $publicKeys);
		$this->assertArrayHasKey('user3', $publicKeys);
		return $publicKeys;
	}

	/**
	 * @dataProvider dataProviderForTestGetPathToRealFile
	 */
	public function testGetPathToRealFile($path, $expected) {
		$this->assertSame($expected,
			self::invokePrivate($this->instance, 'getPathToRealFile', [$path])
		);
	}

	public function dataProviderForTestGetPathToRealFile() {
		return [
			['/user/files/foo/bar.txt', '/user/files/foo/bar.txt'],
			['/user/files/foo.txt', '/user/files/foo.txt'],
			['/user/files_versions/foo.txt.v543534', '/user/files/foo.txt'],
			['/user/files_versions/foo/bar.txt.v5454', '/user/files/foo/bar.txt'],
		];
	}

	/**
	 * @dataProvider dataTestBegin
	 */
	public function testBegin($mode, $header, $legacyCipher, $defaultCipher, $fileKey, $expected) {

		$this->sessionMock->expects($this->once())
			->method('decryptAllModeActivated')
			->willReturn(false);

		$this->sessionMock->expects($this->never())->method('getDecryptAllUid');
		$this->sessionMock->expects($this->never())->method('getDecryptAllKey');
		$this->keyManagerMock->expects($this->never())->method('getEncryptedFileKey');
		$this->keyManagerMock->expects($this->never())->method('getShareKey');
		$this->cryptMock->expects($this->never())->method('multiKeyDecrypt');

		$this->cryptMock->expects($this->any())
			->method('getCipher')
			->willReturn($defaultCipher);
		$this->cryptMock->expects($this->any())
			->method('getLegacyCipher')
			->willReturn($legacyCipher);
		if (empty($fileKey)) {
			$this->cryptMock->expects($this->once())
				->method('generateFileKey')
				->willReturn('fileKey');
		} else {
			$this->cryptMock->expects($this->never())
				->method('generateFileKey');
		}

		$this->keyManagerMock->expects($this->once())
			->method('getFileKey')
			->willReturn($fileKey);

		$result = $this->instance->begin('/user/files/foo.txt', 'user', $mode, $header, []);

		$this->assertArrayHasKey('cipher', $result);
		$this->assertSame($expected, $result['cipher']);
		if ($mode === 'w') {
			$this->assertTrue(self::invokePrivate($this->instance, 'isWriteOperation'));
		} else {
			$this->assertFalse(self::invokePrivate($this->instance, 'isWriteOperation'));
		}
	}

	public function dataTestBegin() {
		return [
			['w', ['cipher' => 'myCipher'], 'legacyCipher', 'defaultCipher', 'fileKey', 'defaultCipher'],
			['r', ['cipher' => 'myCipher'], 'legacyCipher', 'defaultCipher', 'fileKey', 'myCipher'],
			['w', [], 'legacyCipher', 'defaultCipher', '', 'defaultCipher'],
			['r', [], 'legacyCipher', 'defaultCipher', 'file_key', 'legacyCipher'],
		];
	}


	/**
	 * test begin() if decryptAll mode was activated
	 */
	public function testBeginDecryptAll() {

		$path = '/user/files/foo.txt';
		$recoveryKeyId = 'recoveryKeyId';
		$recoveryShareKey = 'recoveryShareKey';
		$decryptAllKey = 'decryptAllKey';
		$fileKey = 'fileKey';

		$this->sessionMock->expects($this->once())
			->method('decryptAllModeActivated')
			->willReturn(true);
		$this->sessionMock->expects($this->once())
			->method('getDecryptAllUid')
			->willReturn($recoveryKeyId);
		$this->sessionMock->expects($this->once())
			->method('getDecryptAllKey')
			->willReturn($decryptAllKey);

		$this->keyManagerMock->expects($this->once())
			->method('getEncryptedFileKey')
			->willReturn('encryptedFileKey');
		$this->keyManagerMock->expects($this->once())
			->method('getShareKey')
			->with($path, $recoveryKeyId)
			->willReturn($recoveryShareKey);
		$this->cryptMock->expects($this->once())
			->method('multiKeyDecrypt')
			->with('encryptedFileKey', $recoveryShareKey, $decryptAllKey)
			->willReturn($fileKey);

		$this->keyManagerMock->expects($this->never())->method('getFileKey');

		$this->instance->begin($path, 'user', 'r', [], []);

		$this->assertSame($fileKey,
			$this->invokePrivate($this->instance, 'fileKey')
		);
	}

	/**
	 * test begin() if encryption is not initialized but the master key is enabled
	 * in this case we can initialize the encryption without a username/password
	 * and continue
	 */
	public function testBeginInitMasterKey() {

		$this->sessionMock->expects($this->once())->method('isReady')->willReturn(false);
		$this->utilMock->expects($this->once())->method('isMasterKeyEnabled')
			->willReturn(true);
		$this->keyManagerMock->expects($this->once())->method('init')->with('', '');

		$this->instance->begin('/user/files/welcome.txt', 'user', 'r', [], []);
	}

	/**
	 * @dataProvider dataTestUpdate
	 *
	 * @param string $fileKey
	 * @param boolean $expected
	 */
	public function testUpdate($fileKey, $expected) {
		$this->keyManagerMock->expects($this->once())
			->method('getFileKey')->willReturn($fileKey);

		$this->keyManagerMock->expects($this->any())
			->method('getPublicKey')->willReturn('publicKey');

		$this->keyManagerMock->expects($this->any())
			->method('addSystemKeys')
			->willReturnCallback(function ($accessList, $publicKeys) {
				return $publicKeys;
			});

		$this->keyManagerMock->expects($this->never())->method('getVersion');
		$this->keyManagerMock->expects($this->never())->method('setVersion');

		$this->assertSame($expected,
			$this->instance->update('path', 'user1', ['users' => ['user1']])
		);
	}

	public function dataTestUpdate() {
		return [
			['', false],
			['fileKey', true]
		];
	}

	public function testUpdateNoUsers() {

		$this->invokePrivate($this->instance, 'rememberVersion', [['path' => 2]]);

		$this->keyManagerMock->expects($this->never())->method('getFileKey');
		$this->keyManagerMock->expects($this->never())->method('getPublicKey');
		$this->keyManagerMock->expects($this->never())->method('addSystemKeys');
		$this->keyManagerMock->expects($this->once())->method('setVersion')
			->willReturnCallback(function ($path, $version, $view) {
				$this->assertSame('path', $path);
				$this->assertSame(2, $version);
				$this->assertTrue($view instanceof \OC\Files\View);
			});
		$this->instance->update('path', 'user1', []);
	}

	/**
	 * Test case if the public key is missing. Nextcloud should still encrypt
	 * the file for the remaining users
	 */
	public function testUpdateMissingPublicKey() {
		$this->keyManagerMock->expects($this->once())
			->method('getFileKey')->willReturn('fileKey');

		$this->keyManagerMock->expects($this->any())
			->method('getPublicKey')->willReturnCallback(
				function ($user) {
					throw new PublicKeyMissingException($user);
				}
			);

		$this->keyManagerMock->expects($this->any())
			->method('addSystemKeys')
			->willReturnCallback(function ($accessList, $publicKeys) {
				return $publicKeys;
			});

		$this->cryptMock->expects($this->once())->method('multiKeyEncrypt')
			->willReturnCallback(
				function ($fileKey, $publicKeys) {
					$this->assertEmpty($publicKeys);
					$this->assertSame('fileKey', $fileKey);
				}
			);

		$this->keyManagerMock->expects($this->never())->method('getVersion');
		$this->keyManagerMock->expects($this->never())->method('setVersion');

		$this->assertTrue(
			$this->instance->update('path', 'user1', ['users' => ['user1']])
		);
	}

	/**
	 * by default the encryption module should encrypt regular files, files in
	 * files_versions and files in files_trashbin
	 *
	 * @dataProvider dataTestShouldEncrypt
	 */
	public function testShouldEncrypt($path, $shouldEncryptHomeStorage, $isHomeStorage, $expected) {
		$this->utilMock->expects($this->once())->method('shouldEncryptHomeStorage')
			->willReturn($shouldEncryptHomeStorage);

		if ($shouldEncryptHomeStorage === false) {
			$this->storageMock->expects($this->once())->method('instanceOfStorage')
				->with('\OCP\Files\IHomeStorage')->willReturn($isHomeStorage);
			$this->utilMock->expects($this->once())->method('getStorage')->with($path)
				->willReturn($this->storageMock);
		}

		$this->assertSame($expected,
			$this->instance->shouldEncrypt($path)
		);
	}

	public function dataTestShouldEncrypt() {
		return [
			['/user1/files/foo.txt', true, true, true],
			['/user1/files_versions/foo.txt', true, true, true],
			['/user1/files_trashbin/foo.txt', true, true, true],
			['/user1/some_folder/foo.txt', true, true, false],
			['/user1/foo.txt', true, true, false],
			['/user1/files', true, true, false],
			['/user1/files_trashbin', true, true, false],
			['/user1/files_versions', true, true, false],
			// test if shouldEncryptHomeStorage is set to false
			['/user1/files/foo.txt', false, true, false],
			['/user1/files_versions/foo.txt', false, false, true],
		];
	}

	
	public function testDecrypt() {
		$this->expectException(\OC\Encryption\Exceptions\DecryptionFailedException::class);
		$this->expectExceptionMessage('Can not decrypt this file, probably this is a shared file. Please ask the file owner to reshare the file with you.');

		$this->instance->decrypt('abc');
	}

	public function testPrepareDecryptAll() {
		/** @var \Symfony\Component\Console\Input\InputInterface $input */
		$input = $this->createMock(InputInterface::class);
		/** @var \Symfony\Component\Console\Output\OutputInterface $output */
		$output = $this->createMock(OutputInterface::class);

		$this->decryptAllMock->expects($this->once())->method('prepare')
			->with($input, $output, 'user');

		$this->instance->prepareDecryptAll($input, $output, 'user');
	}

}
