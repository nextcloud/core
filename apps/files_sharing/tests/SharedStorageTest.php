<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Sharing\Tests;

use OC\Files\View;
use OCA\Files_Sharing\SharedStorage;
use OCA\Files_Trashbin\AppInfo\Application;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\Files\NotFoundException;
use OCP\Share\IShare;

/**
 * Class SharedStorageTest
 *
 * @group DB
 */
class SharedStorageTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		// register trashbin hooks
		$trashbinApp = new Application();
		$trashbinApp->boot($this->createMock(IBootContext::class));
		$this->folder = '/folder_share_storage_test';

		$this->filename = '/share-api-storage.txt';


		$this->view->mkdir($this->folder);

		// save file with content
		$this->view->file_put_contents($this->filename, "root file");
		$this->view->file_put_contents($this->folder . $this->filename, "file in subfolder");
	}

	protected function tearDown(): void {
		if ($this->view) {
			if ($this->view->file_exists($this->folder)) {
				$this->view->unlink($this->folder);
			}
			if ($this->view->file_exists($this->filename)) {
				$this->view->unlink($this->filename);
			}
		}

		\OC\Files\Filesystem::getLoader()->removeStorageWrapper('oc_trashbin');

		\OC\Share\Share::unregisterBackend('file');
		\OC\Share\Share::unregisterBackend('folder');

		parent::tearDown();
	}

	/**
	 * if the parent of the mount point is gone then the mount point should move up
	 *
	 * @medium
	 */
	public function testParentOfMountPointIsGone() {

		// share to user
		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$user2View = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');
		$this->assertTrue($user2View->file_exists($this->folder));

		// create a local folder
		$result = $user2View->mkdir('localfolder');
		$this->assertTrue($result);

		// move mount point to local folder
		$result = $user2View->rename($this->folder, '/localfolder/' . $this->folder);
		$this->assertTrue($result);

		// mount point in the root folder should no longer exist
		$this->assertFalse($user2View->is_dir($this->folder));

		// delete the local folder
		/** @var \OC\Files\Storage\Storage $storage */
		[$storage, $internalPath] = \OC\Files\Filesystem::resolvePath('/' . self::TEST_FILES_SHARING_API_USER2 . '/files/localfolder');
		$storage->rmdir($internalPath);

		//enforce reload of the mount points
		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		//mount point should be back at the root
		$this->assertTrue($user2View->is_dir($this->folder));

		//cleanup
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		$this->view->unlink($this->folder);
	}

	/**
	 * @medium
	 */
	public function testRenamePartFile() {

		// share to user
		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);


		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$user2View = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');

		$this->assertTrue($user2View->file_exists($this->folder));

		// create part file
		$result = $user2View->file_put_contents($this->folder . '/foo.txt.part', 'some test data');

		$this->assertTrue(is_int($result));
		// rename part file to real file
		$result = $user2View->rename($this->folder . '/foo.txt.part', $this->folder . '/foo.txt');

		$this->assertTrue($result);

		// check if the new file really exists
		$this->assertTrue($user2View->file_exists($this->folder . '/foo.txt'));

		// check if the rename also affected the owner
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$this->assertTrue($this->view->file_exists($this->folder . '/foo.txt'));

		//cleanup
		$this->shareManager->deleteShare($share);
	}

	public function testFilesize() {
		$folderSize = $this->view->filesize($this->folder);
		$file1Size = $this->view->filesize($this->folder . $this->filename);
		$file2Size = $this->view->filesize($this->filename);

		$share1 = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);

		$share2 = $this->share(
			IShare::TYPE_USER,
			$this->filename,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE
		);


		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		// compare file size between user1 and user2, should always be the same
		$this->assertSame($folderSize, \OC\Files\Filesystem::filesize($this->folder));
		$this->assertSame($file1Size, \OC\Files\Filesystem::filesize($this->folder . $this->filename));
		$this->assertSame($file2Size, \OC\Files\Filesystem::filesize($this->filename));

		//cleanup
		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
	}

	public function testGetPermissions() {
		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_READ
		);


		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		$this->assertTrue(\OC\Files\Filesystem::is_dir($this->folder));

		// for the share root we expect:
		// the read permissions (1)
		// the delete permission (8), to enable unshare
		$rootInfo = \OC\Files\Filesystem::getFileInfo($this->folder);
		$this->assertSame(9, $rootInfo->getPermissions());

		// for the file within the shared folder we expect:
		// the read permissions (1)
		$subfileInfo = \OC\Files\Filesystem::getFileInfo($this->folder . $this->filename);
		$this->assertSame(1, $subfileInfo->getPermissions());


		//cleanup
		$this->shareManager->deleteShare($share);
	}

	public function testFopenWithReadOnlyPermission() {
		$this->view->file_put_contents($this->folder . '/existing.txt', 'foo');

		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_READ
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$user2View = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');

		// part file should be forbidden
		$handle = $user2View->fopen($this->folder . '/test.txt.part', 'w');
		$this->assertFalse($handle);

		// regular file forbidden
		$handle = $user2View->fopen($this->folder . '/test.txt', 'w');
		$this->assertFalse($handle);

		// rename forbidden
		$this->assertFalse($user2View->rename($this->folder . '/existing.txt', $this->folder . '/existing2.txt'));

		// delete forbidden
		$this->assertFalse($user2View->unlink($this->folder . '/existing.txt'));

		//cleanup
		$this->shareManager->deleteShare($share);
	}

	public function testFopenWithCreateOnlyPermission() {
		$this->view->file_put_contents($this->folder . '/existing.txt', 'foo');
		$fileinfoFolder = $this->view->getFileInfo($this->folder);

		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$user2View = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');

		// create part file allowed
		$handle = $user2View->fopen($this->folder . '/test.txt.part', 'w');
		$this->assertNotFalse($handle);
		fclose($handle);

		// create regular file allowed
		$handle = $user2View->fopen($this->folder . '/test-create.txt', 'w');
		$this->assertNotFalse($handle);
		fclose($handle);

		// rename file never allowed
		$this->assertFalse($user2View->rename($this->folder . '/test-create.txt', $this->folder . '/newtarget.txt'));
		$this->assertFalse($user2View->file_exists($this->folder . '/newtarget.txt'));

		// rename file not allowed if target exists
		$this->assertFalse($user2View->rename($this->folder . '/newtarget.txt', $this->folder . '/existing.txt'));

		// overwriting file not allowed
		$handle = $user2View->fopen($this->folder . '/existing.txt', 'w');
		$this->assertFalse($handle);

		// overwrite forbidden (no update permission)
		$this->assertFalse($user2View->rename($this->folder . '/test.txt.part', $this->folder . '/existing.txt'));

		// delete forbidden
		$this->assertFalse($user2View->unlink($this->folder . '/existing.txt'));

		//cleanup
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		$this->shareManager->deleteShare($share);
	}

	public function testFopenWithUpdateOnlyPermission() {
		$this->view->file_put_contents($this->folder . '/existing.txt', 'foo');

		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$user2View = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');

		// create part file allowed
		$handle = $user2View->fopen($this->folder . '/test.txt.part', 'w');
		$this->assertNotFalse($handle);
		fclose($handle);

		// create regular file not allowed
		$handle = $user2View->fopen($this->folder . '/test-create.txt', 'w');
		$this->assertFalse($handle);

		// rename part file not allowed to non-existing file
		$this->assertFalse($user2View->rename($this->folder . '/test.txt.part', $this->folder . '/nonexist.txt'));

		// rename part file allowed to target existing file
		$this->assertTrue($user2View->rename($this->folder . '/test.txt.part', $this->folder . '/existing.txt'));
		$this->assertTrue($user2View->file_exists($this->folder . '/existing.txt'));

		// rename regular file allowed
		$this->assertTrue($user2View->rename($this->folder . '/existing.txt', $this->folder . '/existing-renamed.txt'));
		$this->assertTrue($user2View->file_exists($this->folder . '/existing-renamed.txt'));

		// overwriting file directly is allowed
		$handle = $user2View->fopen($this->folder . '/existing-renamed.txt', 'w');
		$this->assertNotFalse($handle);
		fclose($handle);

		// delete forbidden
		$this->assertFalse($user2View->unlink($this->folder . '/existing-renamed.txt'));

		//cleanup
		$this->shareManager->deleteShare($share);
	}

	public function testFopenWithDeleteOnlyPermission() {
		$this->view->file_put_contents($this->folder . '/existing.txt', 'foo');

		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_DELETE
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$user2View = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');

		// part file should be forbidden
		$handle = $user2View->fopen($this->folder . '/test.txt.part', 'w');
		$this->assertFalse($handle);

		// regular file forbidden
		$handle = $user2View->fopen($this->folder . '/test.txt', 'w');
		$this->assertFalse($handle);

		// rename forbidden
		$this->assertFalse($user2View->rename($this->folder . '/existing.txt', $this->folder . '/existing2.txt'));

		// delete allowed
		$this->assertTrue($user2View->unlink($this->folder . '/existing.txt'));

		//cleanup
		$this->shareManager->deleteShare($share);
	}

	public function testMountSharesOtherUser() {
		$rootView = new \OC\Files\View('');
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		// share 2 different files with 2 different users
		$share1 = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);
		$share2 = $this->share(
			IShare::TYPE_USER,
			$this->filename,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER3,
			\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$this->assertTrue($rootView->file_exists('/' . self::TEST_FILES_SHARING_API_USER2 . '/files/' . $this->folder));

		$mountConfigManager = \OC::$server->getMountProviderCollection();
		$mounts = $mountConfigManager->getMountsForUser(\OC::$server->getUserManager()->get(self::TEST_FILES_SHARING_API_USER3));
		array_walk($mounts, [\OC\Files\Filesystem::getMountManager(), 'addMount']);

		$this->assertTrue($rootView->file_exists('/' . self::TEST_FILES_SHARING_API_USER3 . '/files/' . $this->filename));

		// make sure we didn't double setup shares for user 2 or mounted the shares for user 3 in user's 2 home
		$this->assertFalse($rootView->file_exists('/' . self::TEST_FILES_SHARING_API_USER2 . '/files/' . $this->folder . ' (2)'));
		$this->assertFalse($rootView->file_exists('/' . self::TEST_FILES_SHARING_API_USER2 . '/files/' . $this->filename));

		//cleanup
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		$this->view->unlink($this->folder);

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
	}

	public function testCopyFromStorage() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$view = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');
		$this->assertTrue($view->file_exists($this->folder));

		/**
		 * @var \OCP\Files\Storage $sharedStorage
		 */
		[$sharedStorage,] = $view->resolvePath($this->folder);
		$this->assertTrue($sharedStorage->instanceOfStorage('OCA\Files_Sharing\ISharedStorage'));

		$sourceStorage = new \OC\Files\Storage\Temporary([]);
		$sourceStorage->file_put_contents('foo.txt', 'asd');

		$sharedStorage->copyFromStorage($sourceStorage, 'foo.txt', 'bar.txt');
		$this->assertTrue($sharedStorage->file_exists('bar.txt'));
		$this->assertEquals('asd', $sharedStorage->file_get_contents('bar.txt'));

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		$this->view->unlink($this->folder);
		$this->shareManager->deleteShare($share);
	}

	public function testMoveFromStorage() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$view = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');
		$this->assertTrue($view->file_exists($this->folder));

		/**
		 * @var \OCP\Files\Storage $sharedStorage
		 */
		[$sharedStorage,] = $view->resolvePath($this->folder);
		$this->assertTrue($sharedStorage->instanceOfStorage('OCA\Files_Sharing\ISharedStorage'));

		$sourceStorage = new \OC\Files\Storage\Temporary([]);
		$sourceStorage->file_put_contents('foo.txt', 'asd');

		$sharedStorage->moveFromStorage($sourceStorage, 'foo.txt', 'bar.txt');
		$this->assertTrue($sharedStorage->file_exists('bar.txt'));
		$this->assertEquals('asd', $sharedStorage->file_get_contents('bar.txt'));

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		$this->view->unlink($this->folder);
		$this->shareManager->deleteShare($share);
	}

	public function testNameConflict() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		$view1 = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER1 . '/files');
		$view1->mkdir('foo');

		self::loginHelper(self::TEST_FILES_SHARING_API_USER3);
		$view3 = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER3 . '/files');
		$view3->mkdir('foo');

		// share a folder with the same name from two different users to the same user
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$share1 = $this->share(
			IShare::TYPE_GROUP,
			'foo',
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_GROUP1,
			\OCP\Constants::PERMISSION_ALL
		);
		$this->shareManager->acceptShare($share1, self::TEST_FILES_SHARING_API_USER2);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER3);

		$share2 = $this->share(
			IShare::TYPE_GROUP,
			'foo',
			self::TEST_FILES_SHARING_API_USER3,
			self::TEST_FILES_SHARING_API_GROUP1,
			\OCP\Constants::PERMISSION_ALL
		);
		$this->shareManager->acceptShare($share2, self::TEST_FILES_SHARING_API_USER2);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$view2 = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');

		$this->assertTrue($view2->file_exists('/foo'));
		$this->assertTrue($view2->file_exists('/foo (2)'));

		$mount = $view2->getMount('/foo');
		$this->assertInstanceOf('\OCA\Files_Sharing\SharedMount', $mount);
		/** @var \OCA\Files_Sharing\SharedStorage $storage */
		$storage = $mount->getStorage();

		$this->assertEquals(self::TEST_FILES_SHARING_API_USER1, $storage->getOwner(''));

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
	}

	public function testOwnerPermissions() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_DELETE
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$view = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');
		$this->assertTrue($view->file_exists($this->folder));

		$view->file_put_contents($this->folder . '/newfile.txt', 'asd');

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$this->assertTrue($this->view->file_exists($this->folder . '/newfile.txt'));
		$this->assertEquals(\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_CREATE,
			$this->view->getFileInfo($this->folder . '/newfile.txt')->getPermissions());

		$this->view->unlink($this->folder);
		$this->shareManager->deleteShare($share);
	}

	public function testInitWithNonExistingUser() {
		$share = $this->createMock(IShare::class);
		$share->method('getShareOwner')->willReturn('unexist');
		$ownerView = $this->createMock(View::class);
		$storage = new SharedStorage([
			'ownerView' => $ownerView,
			'superShare' => $share,
			'groupedShares' => [$share],
			'user' => 'user1',
		]);

		// trigger init
		$this->assertInstanceOf(\OC\Files\Storage\FailedStorage::class, $storage->getSourceStorage());
		$this->assertInstanceOf(\OC\Files\Cache\FailedCache::class, $storage->getCache());
	}

	public function testInitWithNotFoundSource() {
		$share = $this->createMock(IShare::class);
		$share->method('getShareOwner')->willReturn(self::TEST_FILES_SHARING_API_USER1);
		$ownerView = $this->createMock(View::class);
		$ownerView->method('getPath')->will($this->throwException(new NotFoundException()));
		$storage = new SharedStorage([
			'ownerView' => $ownerView,
			'superShare' => $share,
			'groupedShares' => [$share],
			'user' => 'user1',
		]);

		// trigger init
		$this->assertInstanceOf(\OC\Files\Storage\FailedStorage::class, $storage->getSourceStorage());
		$this->assertInstanceOf(\OC\Files\Cache\FailedCache::class, $storage->getCache());
	}
}
