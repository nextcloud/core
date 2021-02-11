<?php
/**
 * @author Björn Schießle <schiessle@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Encryption;

use OC\Encryption\DecryptAll;
use OC\Encryption\Exceptions\DecryptionFailedException;
use OC\Encryption\Manager;
use OC\Files\FileInfo;
use OC\Files\View;
use OCP\Files\Storage;
use OCP\IUserManager;
use OCP\UserInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

/**
 * Class DecryptAllTest
 *
 * @group DB
 *
 * @package Test\Encryption
 */
class DecryptAllTest extends TestCase {

	/** @var \PHPUnit\Framework\MockObject\MockObject | IUserManager */
	protected $userManager;

	/** @var \PHPUnit\Framework\MockObject\MockObject | Manager */
	protected $encryptionManager;

	/** @var \PHPUnit\Framework\MockObject\MockObject | View */
	protected $view;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | \Symfony\Component\Console\Input\InputInterface */
	protected $inputInterface;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | \Symfony\Component\Console\Output\OutputInterface */
	protected $outputInterface;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | \OCP\UserInterface */
	protected $userInterface;

	/** @var  DecryptAll */
	protected $instance;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()->getMock();
		$this->encryptionManager = $this->getMockBuilder('OC\Encryption\Manager')
			->disableOriginalConstructor()->getMock();
		$this->view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()->getMock();
		$this->inputInterface = $this->getMockBuilder(InputInterface::class)
			->disableOriginalConstructor()->getMock();
		$this->outputInterface = $this->getMockBuilder(OutputInterface::class)
			->disableOriginalConstructor()->getMock();
		$this->userInterface = $this->getMockBuilder(UserInterface::class)
			->disableOriginalConstructor()->getMock();

		$this->outputInterface->expects($this->any())->method('getFormatter')
			->willReturn($this->createMock(OutputFormatterInterface::class));

		$this->instance = new DecryptAll($this->encryptionManager, $this->userManager, $this->view);

		$this->invokePrivate($this->instance, 'input', [$this->inputInterface]);
		$this->invokePrivate($this->instance, 'output', [$this->outputInterface]);
	}

	public function dataDecryptAll() {
		return [
			[true, 'user1', true],
			[false, 'user1', true],
			[true, '0', true],
			[false, '0', true],
			[true, '', false],
		];
	}

	/**
	 * @dataProvider dataDecryptAll
	 * @param bool $prepareResult
	 * @param string $user
	 * @param bool $userExistsChecked
	 */
	public function testDecryptAll($prepareResult, $user, $userExistsChecked) {
		if ($userExistsChecked) {
			$this->userManager->expects($this->once())->method('userExists')->willReturn(true);
		} else {
			$this->userManager->expects($this->never())->method('userExists');
		}
		/** @var DecryptAll | \PHPUnit\Framework\MockObject\MockObject |  $instance */
		$instance = $this->getMockBuilder('OC\Encryption\DecryptAll')
			->setConstructorArgs(
				[
					$this->encryptionManager,
					$this->userManager,
					$this->view
				]
			)
			->setMethods(['prepareEncryptionModules', 'decryptAllUsersFiles'])
			->getMock();

		$instance->expects($this->once())
			->method('prepareEncryptionModules')
			->with($user)
			->willReturn($prepareResult);

		if ($prepareResult) {
			$instance->expects($this->once())
				->method('decryptAllUsersFiles')
				->with($user);
		} else {
			$instance->expects($this->never())->method('decryptAllUsersFiles');
		}

		$instance->decryptAll($this->inputInterface, $this->outputInterface, $user);
	}

	/**
	 * test decrypt all call with a user who doesn't exists
	 */
	public function testDecryptAllWrongUser() {
		$this->userManager->expects($this->once())->method('userExists')->willReturn(false);
		$this->outputInterface->expects($this->once())->method('writeln')
			->with('User "user1" does not exist. Please check the username and try again');

		$this->assertFalse(
			$this->instance->decryptAll($this->inputInterface, $this->outputInterface, 'user1')
		);
	}

	public function dataTrueFalse() {
		return [
			[true],
			[false],
		];
	}

	/**
	 * @dataProvider dataTrueFalse
	 * @param bool $success
	 */
	public function testPrepareEncryptionModules($success) {
		$user = 'user1';

		$dummyEncryptionModule = $this->getMockBuilder('OCP\Encryption\IEncryptionModule')
			->disableOriginalConstructor()->getMock();

		$dummyEncryptionModule->expects($this->once())
			->method('prepareDecryptAll')
			->with($this->inputInterface, $this->outputInterface, $user)
			->willReturn($success);

		$callback = function () use ($dummyEncryptionModule) {
			return $dummyEncryptionModule;
		};
		$moduleDescription = [
			'id' => 'id',
			'displayName' => 'displayName',
			'callback' => $callback
		];

		$this->encryptionManager->expects($this->once())
			->method('getEncryptionModules')
			->willReturn([$moduleDescription]);

		$this->assertSame($success,
			$this->invokePrivate($this->instance, 'prepareEncryptionModules', [$user])
		);
	}

	/**
	 * @dataProvider dataTestDecryptAllUsersFiles
	 */
	public function testDecryptAllUsersFiles($user) {

		/** @var DecryptAll | \PHPUnit\Framework\MockObject\MockObject |  $instance */
		$instance = $this->getMockBuilder('OC\Encryption\DecryptAll')
			->setConstructorArgs(
				[
					$this->encryptionManager,
					$this->userManager,
					$this->view
				]
			)
			->setMethods(['decryptUsersFiles'])
			->getMock();

		$this->invokePrivate($instance, 'input', [$this->inputInterface]);
		$this->invokePrivate($instance, 'output', [$this->outputInterface]);

		if (empty($user)) {
			$this->userManager->expects($this->once())
				->method('getBackends')
				->willReturn([$this->userInterface]);
			$this->userInterface->expects($this->any())
				->method('getUsers')
				->willReturn(['user1', 'user2']);
			$instance->expects($this->at(0))
				->method('decryptUsersFiles')
				->with('user1');
			$instance->expects($this->at(1))
				->method('decryptUsersFiles')
				->with('user2');
		} else {
			$instance->expects($this->once())
				->method('decryptUsersFiles')
				->with($user);
		}

		$this->invokePrivate($instance, 'decryptAllUsersFiles', [$user]);
	}

	public function dataTestDecryptAllUsersFiles() {
		return [
			['user1'],
			['']
		];
	}

	public function testDecryptUsersFiles() {
		/** @var DecryptAll | \PHPUnit\Framework\MockObject\MockObject  $instance */
		$instance = $this->getMockBuilder('OC\Encryption\DecryptAll')
			->setConstructorArgs(
				[
					$this->encryptionManager,
					$this->userManager,
					$this->view
				]
			)
			->setMethods(['decryptFile'])
			->getMock();

		$storage = $this->getMockBuilder(Storage::class)
			->disableOriginalConstructor()->getMock();


		$sharedStorage = $this->getMockBuilder(Storage::class)
			->disableOriginalConstructor()->getMock();

		$sharedStorage->expects($this->once())->method('instanceOfStorage')
			->with('OCA\Files_Sharing\SharedStorage')->willReturn(true);

		$this->view->expects($this->at(0))->method('getDirectoryContent')
			->with('/user1/files')->willReturn(
				[
					new FileInfo('path', $storage, 'intPath', ['name' => 'foo', 'type' => 'dir'], null),
					new FileInfo('path', $storage, 'intPath', ['name' => 'bar', 'type' => 'file', 'encrypted' => true], null),
					new FileInfo('path', $sharedStorage, 'intPath', ['name' => 'shared', 'type' => 'file', 'encrypted' => true], null),
				]
			);

		$this->view->expects($this->at(3))->method('getDirectoryContent')
			->with('/user1/files/foo')->willReturn(
				[
					new FileInfo('path', $storage, 'intPath', ['name' => 'subfile', 'type' => 'file', 'encrypted' => true], null)
				]
			);

		$this->view->expects($this->any())->method('is_dir')
			->willReturnCallback(
				function ($path) {
					if ($path === '/user1/files/foo') {
						return true;
					}
					return false;
				}
			);

		$instance->expects($this->at(0))
			->method('decryptFile')
			->with('/user1/files/bar');
		$instance->expects($this->at(1))
			->method('decryptFile')
			->with('/user1/files/foo/subfile');

		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->any())
			->method('getFormatter')
			->willReturn($this->createMock(OutputFormatterInterface::class));
		$progressBar = new ProgressBar($output);

		$this->invokePrivate($instance, 'decryptUsersFiles', ['user1', $progressBar, '']);
	}

	/**
	 * @dataProvider dataTrueFalse
	 */
	public function testDecryptFile($isEncrypted) {
		$path = 'test.txt';

		/** @var DecryptAll | \PHPUnit\Framework\MockObject\MockObject  $instance */
		$instance = $this->getMockBuilder('OC\Encryption\DecryptAll')
			->setConstructorArgs(
				[
					$this->encryptionManager,
					$this->userManager,
					$this->view
				]
			)
			->setMethods(['getTimestamp'])
			->getMock();

		$fileInfo = $this->createMock(FileInfo::class);
		$fileInfo->expects($this->any())->method('isEncrypted')
			->willReturn($isEncrypted);
		$this->view->expects($this->any())->method('getFileInfo')
			->willReturn($fileInfo);

		if ($isEncrypted) {
			$instance->expects($this->any())->method('getTimestamp')->willReturn(42);

			$this->view->expects($this->once())
				->method('copy')
				->with($path, $path . '.decrypted.42');
			$this->view->expects($this->once())
				->method('rename')
				->with($path . '.decrypted.42', $path);
		} else {
			$instance->expects($this->never())->method('getTimestamp');
			$this->view->expects($this->never())->method('copy');
			$this->view->expects($this->never())->method('rename');
		}
		$this->assertTrue(
			$this->invokePrivate($instance, 'decryptFile', [$path])
		);
	}

	public function testDecryptFileFailure() {
		$path = 'test.txt';

		/** @var DecryptAll | \PHPUnit\Framework\MockObject\MockObject  $instance */
		$instance = $this->getMockBuilder('OC\Encryption\DecryptAll')
			->setConstructorArgs(
				[
					$this->encryptionManager,
					$this->userManager,
					$this->view
				]
			)
			->setMethods(['getTimestamp'])
			->getMock();


		$fileInfo = $this->createMock(FileInfo::class);
		$fileInfo->expects($this->any())->method('isEncrypted')
			->willReturn(true);
		$this->view->expects($this->any())->method('getFileInfo')
			->willReturn($fileInfo);

		$instance->expects($this->any())->method('getTimestamp')->willReturn(42);

		$this->view->expects($this->once())
			->method('copy')
			->with($path, $path . '.decrypted.42')
			->willReturnCallback(function () {
				throw new DecryptionFailedException();
			});

		$this->view->expects($this->never())->method('rename');
		$this->view->expects($this->once())
			->method('file_exists')
			->with($path . '.decrypted.42')
			->willReturn(true);
		$this->view->expects($this->once())
			->method('unlink')
			->with($path . '.decrypted.42');

		$this->assertFalse(
			$this->invokePrivate($instance, 'decryptFile', [$path])
		);
	}
}
