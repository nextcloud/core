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

namespace Tests\Core\Command\Encryption;

use OC\Core\Command\Encryption\ChangeKeyStorageRoot;
use OC\Encryption\Util;
use OC\Files\View;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\UserInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class ChangeKeyStorageRootTest extends TestCase {

	/** @var ChangeKeyStorageRoot */
	protected $changeKeyStorageRoot;

	/** @var View | \PHPUnit_Framework_MockObject_MockObject */
	protected $view;

	/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/**  @var Util | \PHPUnit_Framework_MockObject_MockObject */
	protected $util;

	/** @var QuestionHelper | \PHPUnit_Framework_MockObject_MockObject */
	protected $questionHelper;

	/** @var InputInterface | \PHPUnit_Framework_MockObject_MockObject */
	protected $inputInterface;

	/** @var OutputInterface | \PHPUnit_Framework_MockObject_MockObject */
	protected $outputInterface;

	/** @var \OCP\UserInterface |  \PHPUnit_Framework_MockObject_MockObject */
	protected $userInterface;

	protected function setUp(): void {
		parent::setUp();

		$this->view = $this->getMockBuilder(View::class)->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->util = $this->getMockBuilder('OC\Encryption\Util')->disableOriginalConstructor()->getMock();
		$this->questionHelper = $this->getMockBuilder(QuestionHelper::class)->getMock();
		$this->inputInterface = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->outputInterface = $this->getMockBuilder(OutputInterface::class)->getMock();
		$this->userInterface = $this->getMockBuilder(UserInterface::class)->getMock();

		$outputFormatterInterface = $this->getMockBuilder(OutputFormatterInterface::class)->getMock();
		$this->outputInterface->expects($this->any())->method('getFormatter')
			->willReturn($outputFormatterInterface);

		$this->changeKeyStorageRoot = new ChangeKeyStorageRoot(
			$this->view,
			$this->userManager,
			$this->config,
			$this->util,
			$this->questionHelper
		);

	}

	/**
	 * @dataProvider dataTestExecute
	 */
	public function testExecute($newRoot, $answer, $successMoveKey) {

		$changeKeyStorageRoot = $this->getMockBuilder('OC\Core\Command\Encryption\ChangeKeyStorageRoot')
			->setConstructorArgs(
				[
					$this->view,
					$this->userManager,
					$this->config,
					$this->util,
					$this->questionHelper
				]
			)->setMethods(['moveAllKeys'])->getMock();

		$this->util->expects($this->once())->method('getKeyStorageRoot')
			->willReturn('');
		$this->inputInterface->expects($this->once())->method('getArgument')
			->with('newRoot')->willReturn($newRoot);

		if ($answer === true || $newRoot !== null) {
			$changeKeyStorageRoot->expects($this->once())->method('moveAllKeys')
				->willReturn($successMoveKey);
		} else {
			$changeKeyStorageRoot->expects($this->never())->method('moveAllKeys');
		}

		if ($successMoveKey === true) {
			$this->util->expects($this->once())->method('setKeyStorageRoot');
		} else {
			$this->util->expects($this->never())->method('setKeyStorageRoot');
		}

		if ($newRoot === null) {
			$this->questionHelper->expects($this->once())->method('ask')->willReturn($answer);
		} else {
			$this->questionHelper->expects($this->never())->method('ask');
		}

		$this->invokePrivate(
			$changeKeyStorageRoot,
			'execute',
			[$this->inputInterface, $this->outputInterface]
		);
	}

	public function dataTestExecute() {
		return [
			[null, true, true],
			[null, true, false],
			[null, false, null],
			['/newRoot', null, true],
			['/newRoot', null, false]
		];
	}

	public function testMoveAllKeys() {

		/** @var \OC\Core\Command\Encryption\ChangeKeyStorageRoot $changeKeyStorageRoot */
		$changeKeyStorageRoot = $this->getMockBuilder('OC\Core\Command\Encryption\ChangeKeyStorageRoot')
			->setConstructorArgs(
				[
					$this->view,
					$this->userManager,
					$this->config,
					$this->util,
					$this->questionHelper
				]
			)->setMethods(['prepareNewRoot', 'moveSystemKeys', 'moveUserKeys'])->getMock();

		$changeKeyStorageRoot->expects($this->at(0))->method('prepareNewRoot')->with('newRoot');
		$changeKeyStorageRoot->expects($this->at(1))->method('moveSystemKeys')->with('oldRoot', 'newRoot');
		$changeKeyStorageRoot->expects($this->at(2))->method('moveUserKeys')->with('oldRoot', 'newRoot', $this->outputInterface);

		$this->invokePrivate($changeKeyStorageRoot, 'moveAllKeys', ['oldRoot', 'newRoot', $this->outputInterface]);

	}

	public function testPrepareNewRoot() {
		$this->view->expects($this->once())->method('is_dir')->with('newRoot')
			->willReturn(true);

		$this->view->expects($this->once())->method('file_put_contents')
			->with('newRoot/' . \OC\Encryption\Keys\Storage::KEY_STORAGE_MARKER,
				'Nextcloud will detect this folder as key storage root only if this file exists')->willReturn(true);

		$this->invokePrivate($this->changeKeyStorageRoot, 'prepareNewRoot', ['newRoot']);
	}

	/**
	 * @dataProvider dataTestPrepareNewRootException
	 *
	 * @param bool $dirExists
	 * @param bool $couldCreateFile
	 */
	public function testPrepareNewRootException($dirExists, $couldCreateFile) {
		$this->expectException(\Exception::class);

		$this->view->expects($this->once())->method('is_dir')->with('newRoot')
			->willReturn($dirExists);
		$this->view->expects($this->any())->method('file_put_contents')->willReturn($couldCreateFile);

		$this->invokePrivate($this->changeKeyStorageRoot, 'prepareNewRoot', ['newRoot']);
	}

	public function dataTestPrepareNewRootException() {
		return [
			[true, false],
			[true, null],
			[false, true]
		];
	}

	/**
	 * @dataProvider dataTestMoveSystemKeys
	 *
	 * @param bool $dirExists
	 * @param bool $targetExists
	 * @param bool $executeRename
	 */
	public function testMoveSystemKeys($dirExists, $targetExists, $executeRename) {

		$changeKeyStorageRoot = $this->getMockBuilder('OC\Core\Command\Encryption\ChangeKeyStorageRoot')
			->setConstructorArgs(
				[
					$this->view,
					$this->userManager,
					$this->config,
					$this->util,
					$this->questionHelper
				]
			)->setMethods(['targetExists'])->getMock();

		$this->view->expects($this->once())->method('is_dir')
			->with('oldRoot/files_encryption')->willReturn($dirExists);
		$changeKeyStorageRoot->expects($this->any())->method('targetExists')
			->with('newRoot/files_encryption')->willReturn($targetExists);

		if ($executeRename) {
			$this->view->expects($this->once())->method('rename')
			->with('oldRoot/files_encryption', 'newRoot/files_encryption');
		} else {
			$this->view->expects($this->never())->method('rename');
		}

		$this->invokePrivate($changeKeyStorageRoot, 'moveSystemKeys', ['oldRoot', 'newRoot']);

	}

	public function dataTestMoveSystemKeys() {
		return [
			[true, false, true],
			[false, true, false],
			[true, true, false],
			[false, false, false]
		];
	}


	public function testMoveUserKeys() {

		$changeKeyStorageRoot = $this->getMockBuilder('OC\Core\Command\Encryption\ChangeKeyStorageRoot')
			->setConstructorArgs(
				[
					$this->view,
					$this->userManager,
					$this->config,
					$this->util,
					$this->questionHelper
				]
			)->setMethods(['setupUserFS', 'moveUserEncryptionFolder'])->getMock();

		$this->userManager->expects($this->once())->method('getBackends')
			->willReturn([$this->userInterface]);
		$this->userInterface->expects($this->once())->method('getUsers')
			->willReturn(['user1', 'user2']);
		$changeKeyStorageRoot->expects($this->exactly(2))->method('setupUserFS');
		$changeKeyStorageRoot->expects($this->exactly(2))->method('moveUserEncryptionFolder');

		$this->invokePrivate($changeKeyStorageRoot, 'moveUserKeys', ['oldRoot', 'newRoot', $this->outputInterface]);
	}

	/**
	 * @dataProvider dataTestMoveUserEncryptionFolder
	 *
	 * @param bool $userExists
	 * @param bool $isDir
	 * @param bool $targetExists
	 * @param bool $shouldRename
	 */
	public function testMoveUserEncryptionFolder($userExists, $isDir, $targetExists, $shouldRename) {

		$changeKeyStorageRoot = $this->getMockBuilder('OC\Core\Command\Encryption\ChangeKeyStorageRoot')
			->setConstructorArgs(
				[
					$this->view,
					$this->userManager,
					$this->config,
					$this->util,
					$this->questionHelper
				]
			)->setMethods(['targetExists', 'prepareParentFolder'])->getMock();

		$this->userManager->expects($this->once())->method('userExists')
			->willReturn($userExists);
		$this->view->expects($this->any())->method('is_dir')
			->willReturn($isDir);
		$changeKeyStorageRoot->expects($this->any())->method('targetExists')
			->willReturn($targetExists);

		if ($shouldRename) {
			$changeKeyStorageRoot->expects($this->once())->method('prepareParentFolder')
				->with('newRoot/user1');
			$this->view->expects($this->once())->method('rename')
				->with('oldRoot/user1/files_encryption', 'newRoot/user1/files_encryption');
		} else {
			$changeKeyStorageRoot->expects($this->never())->method('prepareParentFolder');
			$this->view->expects($this->never())->method('rename');
		}

		$this->invokePrivate($changeKeyStorageRoot, 'moveUserEncryptionFolder', ['user1', 'oldRoot', 'newRoot']);

	}

	public function dataTestMoveUserEncryptionFolder() {
		return [
			[true, true, false, true],
			[true, false, true, false],
			[false, true, true, false],
			[false, false, true, false],
			[false, true, false, false],
			[false, true, true, false],
			[false, false, false, false]
		];
	}


	/**
	 * @dataProvider dataTestPrepareParentFolder
	 */
	public function testPrepareParentFolder($path, $pathExists) {
		$this->view->expects($this->any())->method('file_exists')
			->willReturnCallback(
				function ($fileExistsPath) use ($path, $pathExists) {
					if ($path === $fileExistsPath) {
						return $pathExists;
					}
					return false;
				}
			);

		if ($pathExists === false) {
			$subDirs = explode('/', ltrim($path, '/'));
			$this->view->expects($this->exactly(count($subDirs)))->method('mkdir');
		} else {
			$this->view->expects($this->never())->method('mkdir');
		}

		$this->invokePrivate(
			$this->changeKeyStorageRoot,
			'prepareParentFolder',
			[$path]
		);
	}

	public function dataTestPrepareParentFolder() {
		return [
			['/user/folder/sub_folder/keystorage', true],
			['/user/folder/sub_folder/keystorage', false]
		];
	}

	public function testTargetExists() {
		$this->view->expects($this->once())->method('file_exists')->with('path')
			->willReturn(false);

		$this->assertFalse(
			$this->invokePrivate($this->changeKeyStorageRoot, 'targetExists', ['path'])
		);
	}

	
	public function testTargetExistsException() {
		$this->expectException(\Exception::class);

		$this->view->expects($this->once())->method('file_exists')->with('path')
			->willReturn(true);

		$this->invokePrivate($this->changeKeyStorageRoot, 'targetExists', ['path']);
	}

}
