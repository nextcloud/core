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

use OC\Encryption\Update;
use OC\Files\Mount\Manager;
use OC\Files\View;
use Test\TestCase;

class UpdateTest extends TestCase {

	/** @var \OC\Encryption\Update */
	private $update;

	/** @var string */
	private $uid;

	/** @var \OC\Files\View | \PHPUnit_Framework_MockObject_MockObject */
	private $view;

	/** @var \OC\Encryption\Util | \PHPUnit_Framework_MockObject_MockObject */
	private $util;

	/** @var \OC\Files\Mount\Manager | \PHPUnit_Framework_MockObject_MockObject */
	private $mountManager;

	/** @var \OC\Encryption\Manager | \PHPUnit_Framework_MockObject_MockObject */
	private $encryptionManager;

	/** @var \OCP\Encryption\IEncryptionModule | \PHPUnit_Framework_MockObject_MockObject */
	private $encryptionModule;

	/** @var \OC\Encryption\File | \PHPUnit_Framework_MockObject_MockObject */
	private $fileHelper;

	protected function setUp(): void {
		parent::setUp();

		$this->view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()->getMock();
		$this->util = $this->getMockBuilder('\OC\Encryption\Util')
			->disableOriginalConstructor()->getMock();
		$this->mountManager = $this->getMockBuilder(Manager::class)
			->disableOriginalConstructor()->getMock();
		$this->encryptionManager = $this->getMockBuilder('\OC\Encryption\Manager')
			->disableOriginalConstructor()->getMock();
		$this->fileHelper = $this->getMockBuilder('\OC\Encryption\File')
			->disableOriginalConstructor()->getMock();
		$this->encryptionModule = $this->getMockBuilder('\OCP\Encryption\IEncryptionModule')
			->disableOriginalConstructor()->getMock();

		$this->uid = 'testUser1';

		$this->update = new Update(
			$this->view,
			$this->util,
			$this->mountManager,
			$this->encryptionManager,
			$this->fileHelper,
			$this->uid);
	}

	/**
	 * @dataProvider dataTestUpdate
	 *
	 * @param string $path
	 * @param boolean $isDir
	 * @param array $allFiles
	 * @param integer $numberOfFiles
	 */
	public function testUpdate($path, $isDir, $allFiles, $numberOfFiles) {

		$this->encryptionManager->expects($this->once())
			->method('getEncryptionModule')
			->willReturn($this->encryptionModule);

		$this->view->expects($this->once())
			->method('is_dir')
			->willReturn($isDir);

		if($isDir) {
			$this->util->expects($this->once())
				->method('getAllFiles')
				->willReturn($allFiles);
		}

		$this->fileHelper->expects($this->exactly($numberOfFiles))
			->method('getAccessList')
			->willReturn(['users' => [], 'public' => false]);

		$this->encryptionModule->expects($this->exactly($numberOfFiles))
			->method('update')
			->willReturn(true);

		$this->update->update($path);
	}

	/**
	 * data provider for testUpdate()
	 *
	 * @return array
	 */
	public function dataTestUpdate() {
		return [
			['/user/files/foo', true, ['/user/files/foo/file1.txt', '/user/files/foo/file1.txt'], 2],
			['/user/files/test.txt', false, [], 1],
		];
	}

	/**
	 * @dataProvider dataTestPostRename
	 *
	 * @param string $source
	 * @param string $target
	 * @param boolean $encryptionEnabled
	 */
	public function testPostRename($source, $target, $encryptionEnabled) {

		$updateMock = $this->getUpdateMock(['update', 'getOwnerPath']);

		$this->encryptionManager->expects($this->once())
			->method('isEnabled')
			->willReturn($encryptionEnabled);

		if (dirname($source) === dirname($target) || $encryptionEnabled === false) {
			$updateMock->expects($this->never())->method('getOwnerPath');
			$updateMock->expects($this->never())->method('update');
		} else {
			$updateMock->expects($this->once())
				->method('getOwnerPath')
				->willReturnCallback(function ($path) use ($target) {
					$this->assertSame(
						$target,
						$path,
						'update needs to be executed for the target destination');
					return ['owner', $path];

				});
			$updateMock->expects($this->once())->method('update');
		}

		$updateMock->postRename(['oldpath' => $source, 'newpath' => $target]);
	}

	/**
	 * test data for testPostRename()
	 *
	 * @return array
	 */
	public function dataTestPostRename() {
		return [
			['/test.txt', '/testNew.txt', true],
			['/test.txt', '/testNew.txt', false],
			['/folder/test.txt', '/testNew.txt', true],
			['/folder/test.txt', '/testNew.txt', false],
			['/folder/test.txt', '/testNew.txt', true],
			['/test.txt', '/folder/testNew.txt', false],
		];
	}


	/**
	 * @dataProvider dataTestPostRestore
	 *
	 * @param boolean $encryptionEnabled
	 */
	public function testPostRestore($encryptionEnabled) {

		$updateMock = $this->getUpdateMock(['update']);

		$this->encryptionManager->expects($this->once())
			->method('isEnabled')
			->willReturn($encryptionEnabled);

		if ($encryptionEnabled) {
			$updateMock->expects($this->once())->method('update');

		} else {
			$updateMock->expects($this->never())->method('update');
		}

		$updateMock->postRestore(['filePath' => '/folder/test.txt']);
	}

	/**
	 * test data for testPostRestore()
	 *
	 * @return array
	 */
	public function dataTestPostRestore() {
		return [
			[true],
			[false],
		];
	}

	/**
	 * create mock of the update method
	 *
	 * @param array$methods methods which should be set
	 * @return \OC\Encryption\Update | \PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getUpdateMock($methods) {
		return  $this->getMockBuilder('\OC\Encryption\Update')
			->setConstructorArgs(
				[
					$this->view,
					$this->util,
					$this->mountManager,
					$this->encryptionManager,
					$this->fileHelper,
					$this->uid
				]
			)->setMethods($methods)->getMock();
	}

}
