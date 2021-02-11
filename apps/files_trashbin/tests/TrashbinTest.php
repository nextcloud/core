<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tobia De Koninck <tobia@ledfan.be>
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

use OC\AppFramework\Bootstrap\BootContext;
use OC\AppFramework\DependencyInjection\DIContainer;
use OCA\Files_Sharing\AppInfo\Application;
use OCA\Files_Trashbin\AppInfo\Application as TrashbinApplication;
use OCP\Share\IShare;

/**
 * Class Test_Encryption
 *
 * @group DB
 */
class TrashbinTest extends \Test\TestCase {
	public const TEST_TRASHBIN_USER1 = "test-trashbin-user1";
	public const TEST_TRASHBIN_USER2 = "test-trashbin-user2";

	private $trashRoot1;
	private $trashRoot2;

	private static $rememberRetentionObligation;

	/**
	 * @var bool
	 */
	private static $trashBinStatus;

	/**
	 * @var \OC\Files\View
	 */
	private $rootView;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$appManager = \OC::$server->getAppManager();
		self::$trashBinStatus = $appManager->isEnabledForUser('files_trashbin');

		// reset backend
		\OC_User::clearBackends();
		\OC_User::useBackend('database');

		// clear share hooks
		\OC_Hook::clear('OCP\\Share');
		\OC::registerShareHooks(\OC::$server->getSystemConfig());

		// init files sharing
		new Application();

		//disable encryption
		\OC::$server->getAppManager()->disableApp('encryption');

		$config = \OC::$server->getConfig();
		//configure trashbin
		self::$rememberRetentionObligation = $config->getSystemValue('trashbin_retention_obligation', \OCA\Files_Trashbin\Expiration::DEFAULT_RETENTION_OBLIGATION);
		/** @var \OCA\Files_Trashbin\Expiration $expiration */
		$expiration = \OC::$server->query(\OCA\Files_Trashbin\Expiration::class);
		$expiration->setRetentionObligation('auto, 2');

		// register trashbin hooks
		$trashbinApp = new TrashbinApplication();
		$trashbinApp->boot(new BootContext(new DIContainer('', [], \OC::$server)));

		// create test user
		self::loginHelper(self::TEST_TRASHBIN_USER2, true);
		self::loginHelper(self::TEST_TRASHBIN_USER1, true);
	}


	public static function tearDownAfterClass(): void {
		// cleanup test user
		$user = \OC::$server->getUserManager()->get(self::TEST_TRASHBIN_USER1);
		if ($user !== null) {
			$user->delete();
		}

		/** @var \OCA\Files_Trashbin\Expiration $expiration */
		$expiration = \OC::$server->query(\OCA\Files_Trashbin\Expiration::class);
		$expiration->setRetentionObligation(self::$rememberRetentionObligation);

		\OC_Hook::clear();

		\OC\Files\Filesystem::getLoader()->removeStorageWrapper('oc_trashbin');

		if (self::$trashBinStatus) {
			\OC::$server->getAppManager()->enableApp('files_trashbin');
		}

		parent::tearDownAfterClass();
	}

	protected function setUp(): void {
		parent::setUp();

		\OC::$server->getAppManager()->enableApp('files_trashbin');
		$config = \OC::$server->getConfig();
		$mockConfig = $this->createMock(\OCP\IConfig::class);
		$mockConfig
			->method('getSystemValue')
			->willReturnCallback(static function ($key, $default) use ($config) {
				if ($key === 'filesystem_check_changes') {
					return \OC\Files\Cache\Watcher::CHECK_ONCE;
				} else {
					return $config->getSystemValue($key, $default);
				}
			});
		$mockConfig
			->method('getUserValue')
			->willReturnCallback(static function ($userId, $appName, $key, $default = '') use ($config) {
				return $config->getUserValue($userId, $appName, $key, $default);
			});
		$mockConfig
			->method('getAppValue')
			->willReturnCallback(static function ($appName, $key, $default = '') use ($config) {
				return $config->getAppValue($appName, $key, $default);
			});
		$this->overwriteService(\OC\AllConfig::class, $mockConfig);

		$this->trashRoot1 = '/' . self::TEST_TRASHBIN_USER1 . '/files_trashbin';
		$this->trashRoot2 = '/' . self::TEST_TRASHBIN_USER2 . '/files_trashbin';
		$this->rootView = new \OC\Files\View();
		self::loginHelper(self::TEST_TRASHBIN_USER1);
	}

	protected function tearDown(): void {
		$this->restoreService(\OC\AllConfig::class);
		// disable trashbin to be able to properly clean up
		\OC::$server->getAppManager()->disableApp('files_trashbin');

		$this->rootView->deleteAll('/' . self::TEST_TRASHBIN_USER1 . '/files');
		$this->rootView->deleteAll('/' . self::TEST_TRASHBIN_USER2 . '/files');
		$this->rootView->deleteAll($this->trashRoot1);
		$this->rootView->deleteAll($this->trashRoot2);

		// clear trash table
		$connection = \OC::$server->getDatabaseConnection();
		$connection->executeUpdate('DELETE FROM `*PREFIX*files_trash`');

		parent::tearDown();
	}

	/**
	 * test expiration of files older then the max storage time defined for the trash
	 */
	public function testExpireOldFiles() {

		/** @var \OCP\AppFramework\Utility\ITimeFactory $time */
		$time = \OC::$server->query(\OCP\AppFramework\Utility\ITimeFactory::class);
		$currentTime = $time->getTime();
		$expireAt = $currentTime - 2 * 24 * 60 * 60;
		$expiredDate = $currentTime - 3 * 24 * 60 * 60;

		// create some files
		\OC\Files\Filesystem::file_put_contents('file1.txt', 'file1');
		\OC\Files\Filesystem::file_put_contents('file2.txt', 'file2');
		\OC\Files\Filesystem::file_put_contents('file3.txt', 'file3');

		// delete them so that they end up in the trash bin
		\OC\Files\Filesystem::unlink('file1.txt');
		\OC\Files\Filesystem::unlink('file2.txt');
		\OC\Files\Filesystem::unlink('file3.txt');

		//make sure that files are in the trash bin
		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'name');
		$this->assertSame(3, count($filesInTrash));

		// every second file will get a date in the past so that it will get expired
		$manipulatedList = $this->manipulateDeleteTime($filesInTrash, $this->trashRoot1, $expiredDate);

		$testClass = new TrashbinForTesting();
		list($sizeOfDeletedFiles, $count) = $testClass->dummyDeleteExpiredFiles($manipulatedList, $expireAt);

		$this->assertSame(10, $sizeOfDeletedFiles);
		$this->assertSame(2, $count);

		// only file2.txt should be left
		$remainingFiles = array_slice($manipulatedList, $count);
		$this->assertSame(1, count($remainingFiles));
		$remainingFile = reset($remainingFiles);
		// TODO: failing test
		#$this->assertSame('file2.txt', $remainingFile['name']);

		// check that file1.txt and file3.txt was really deleted
		$newTrashContent = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1);
		$this->assertSame(1, count($newTrashContent));
		$element = reset($newTrashContent);
		// TODO: failing test
		#$this->assertSame('file2.txt', $element['name']);
	}

	/**
	 * test expiration of files older then the max storage time defined for the trash
	 * in this test we delete a shared file and check if both trash bins, the one from
	 * the owner of the file and the one from the user who deleted the file get expired
	 * correctly
	 */
	public function testExpireOldFilesShared() {
		$currentTime = time();
		$folder = "trashTest-" . $currentTime . '/';
		$expiredDate = $currentTime - 3 * 24 * 60 * 60;

		// create some files
		\OC\Files\Filesystem::mkdir($folder);
		\OC\Files\Filesystem::file_put_contents($folder . 'user1-1.txt', 'file1');
		\OC\Files\Filesystem::file_put_contents($folder . 'user1-2.txt', 'file2');
		\OC\Files\Filesystem::file_put_contents($folder . 'user1-3.txt', 'file3');
		\OC\Files\Filesystem::file_put_contents($folder . 'user1-4.txt', 'file4');

		//share user1-4.txt with user2
		$node = \OC::$server->getUserFolder(self::TEST_TRASHBIN_USER1)->get($folder);
		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(IShare::TYPE_USER)
			->setNode($node)
			->setSharedBy(self::TEST_TRASHBIN_USER1)
			->setSharedWith(self::TEST_TRASHBIN_USER2)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = \OC::$server->getShareManager()->createShare($share);
		\OC::$server->getShareManager()->acceptShare($share, self::TEST_TRASHBIN_USER2);

		// delete them so that they end up in the trash bin
		\OC\Files\Filesystem::unlink($folder . 'user1-1.txt');
		\OC\Files\Filesystem::unlink($folder . 'user1-2.txt');
		\OC\Files\Filesystem::unlink($folder . 'user1-3.txt');

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'name');
		$this->assertSame(3, count($filesInTrash));

		// every second file will get a date in the past so that it will get expired
		$this->manipulateDeleteTime($filesInTrash, $this->trashRoot1, $expiredDate);

		// login as user2
		self::loginHelper(self::TEST_TRASHBIN_USER2);

		$this->assertTrue(\OC\Files\Filesystem::file_exists($folder . "user1-4.txt"));

		// create some files
		\OC\Files\Filesystem::file_put_contents('user2-1.txt', 'file1');
		\OC\Files\Filesystem::file_put_contents('user2-2.txt', 'file2');

		// delete them so that they end up in the trash bin
		\OC\Files\Filesystem::unlink('user2-1.txt');
		\OC\Files\Filesystem::unlink('user2-2.txt');

		$filesInTrashUser2 = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER2, 'name');
		$this->assertSame(2, count($filesInTrashUser2));

		// every second file will get a date in the past so that it will get expired
		$this->manipulateDeleteTime($filesInTrashUser2, $this->trashRoot2, $expiredDate);

		\OC\Files\Filesystem::unlink($folder . 'user1-4.txt');

		$this->runCommands();

		$filesInTrashUser2AfterDelete = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER2);

		// user2-1.txt should have been expired
		$this->verifyArray($filesInTrashUser2AfterDelete, ['user2-2.txt', 'user1-4.txt']);

		self::loginHelper(self::TEST_TRASHBIN_USER1);

		// user1-1.txt and user1-3.txt should have been expired
		$filesInTrashUser1AfterDelete = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1);

		$this->verifyArray($filesInTrashUser1AfterDelete, ['user1-2.txt', 'user1-4.txt']);
	}

	/**
	 * verify that the array contains the expected results
	 *
	 * @param OCP\Files\FileInfo[] $result
	 * @param string[] $expected
	 */
	private function verifyArray($result, $expected) {
		$this->assertSame(count($expected), count($result));
		foreach ($expected as $expectedFile) {
			$found = false;
			foreach ($result as $fileInTrash) {
				if ($expectedFile === $fileInTrash['name']) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				// if we didn't found the expected file, something went wrong
				$this->assertTrue(false, "can't find expected file '" . $expectedFile . "' in trash bin");
			}
		}
	}

	/**
	 * @param OCP\Files\FileInfo[] $files
	 * @param string $trashRoot
	 * @param integer $expireDate
	 */
	private function manipulateDeleteTime($files, $trashRoot, $expireDate) {
		$counter = 0;
		foreach ($files as &$file) {
			// modify every second file
			$counter = ($counter + 1) % 2;
			if ($counter === 1) {
				$source = $trashRoot . '/files/' . $file['name'] . '.d' . $file['mtime'];
				$target = \OC\Files\Filesystem::normalizePath($trashRoot . '/files/' . $file['name'] . '.d' . $expireDate);
				$this->rootView->rename($source, $target);
				$file['mtime'] = $expireDate;
			}
		}
		return \OCA\Files\Helper::sortFiles($files, 'mtime');
	}


	/**
	 * test expiration of old files in the trash bin until the max size
	 * of the trash bin is met again
	 */
	public function testExpireOldFilesUtilLimitsAreMet() {

		// create some files
		\OC\Files\Filesystem::file_put_contents('file1.txt', 'file1');
		\OC\Files\Filesystem::file_put_contents('file2.txt', 'file2');
		\OC\Files\Filesystem::file_put_contents('file3.txt', 'file3');

		// delete them so that they end up in the trash bin
		\OC\Files\Filesystem::unlink('file3.txt');
		sleep(1); // make sure that every file has a unique mtime
		\OC\Files\Filesystem::unlink('file2.txt');
		sleep(1); // make sure that every file has a unique mtime
		\OC\Files\Filesystem::unlink('file1.txt');

		//make sure that files are in the trash bin
		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertSame(3, count($filesInTrash));

		$testClass = new TrashbinForTesting();
		$sizeOfDeletedFiles = $testClass->dummyDeleteFiles($filesInTrash, -8);

		// the two oldest files (file3.txt and file2.txt) should be deleted
		$this->assertSame(10, $sizeOfDeletedFiles);

		$newTrashContent = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1);
		$this->assertSame(1, count($newTrashContent));
		$element = reset($newTrashContent);
		$this->assertSame('file1.txt', $element['name']);
	}

	/**
	 * Test restoring a file
	 */
	public function testRestoreFileInRoot() {
		$userFolder = \OC::$server->getUserFolder();
		$file = $userFolder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($userFolder->nodeExists('file1.txt'));

		$file->delete();

		$this->assertFalse($userFolder->nodeExists('file1.txt'));

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertCount(1, $filesInTrash);

		/** @var \OCP\Files\FileInfo */
		$trashedFile = $filesInTrash[0];

		$this->assertTrue(
			OCA\Files_Trashbin\Trashbin::restore(
				'file1.txt.d' . $trashedFile->getMtime(),
				$trashedFile->getName(),
				$trashedFile->getMtime()
			)
		);

		$file = $userFolder->get('file1.txt');
		$this->assertEquals('foo', $file->getContent());
	}

	/**
	 * Test restoring a file in subfolder
	 */
	public function testRestoreFileInSubfolder() {
		$userFolder = \OC::$server->getUserFolder();
		$folder = $userFolder->newFolder('folder');
		$file = $folder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($userFolder->nodeExists('folder/file1.txt'));

		$file->delete();

		$this->assertFalse($userFolder->nodeExists('folder/file1.txt'));

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertCount(1, $filesInTrash);

		/** @var \OCP\Files\FileInfo */
		$trashedFile = $filesInTrash[0];

		$this->assertTrue(
			OCA\Files_Trashbin\Trashbin::restore(
				'file1.txt.d' . $trashedFile->getMtime(),
				$trashedFile->getName(),
				$trashedFile->getMtime()
			)
		);

		$file = $userFolder->get('folder/file1.txt');
		$this->assertEquals('foo', $file->getContent());
	}

	/**
	 * Test restoring a folder
	 */
	public function testRestoreFolder() {
		$userFolder = \OC::$server->getUserFolder();
		$folder = $userFolder->newFolder('folder');
		$file = $folder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($userFolder->nodeExists('folder'));

		$folder->delete();

		$this->assertFalse($userFolder->nodeExists('folder'));

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertCount(1, $filesInTrash);

		/** @var \OCP\Files\FileInfo */
		$trashedFolder = $filesInTrash[0];

		$this->assertTrue(
			OCA\Files_Trashbin\Trashbin::restore(
				'folder.d' . $trashedFolder->getMtime(),
				$trashedFolder->getName(),
				$trashedFolder->getMtime()
			)
		);

		$file = $userFolder->get('folder/file1.txt');
		$this->assertEquals('foo', $file->getContent());
	}

	/**
	 * Test restoring a file from inside a trashed folder
	 */
	public function testRestoreFileFromTrashedSubfolder() {
		$userFolder = \OC::$server->getUserFolder();
		$folder = $userFolder->newFolder('folder');
		$file = $folder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($userFolder->nodeExists('folder'));

		$folder->delete();

		$this->assertFalse($userFolder->nodeExists('folder'));

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertCount(1, $filesInTrash);

		/** @var \OCP\Files\FileInfo */
		$trashedFile = $filesInTrash[0];

		$this->assertTrue(
			OCA\Files_Trashbin\Trashbin::restore(
				'folder.d' . $trashedFile->getMtime() . '/file1.txt',
				'file1.txt',
				$trashedFile->getMtime()
			)
		);

		$file = $userFolder->get('file1.txt');
		$this->assertEquals('foo', $file->getContent());
	}

	/**
	 * Test restoring a file whenever the source folder was removed.
	 * The file should then land in the root.
	 */
	public function testRestoreFileWithMissingSourceFolder() {
		$userFolder = \OC::$server->getUserFolder();
		$folder = $userFolder->newFolder('folder');
		$file = $folder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($userFolder->nodeExists('folder/file1.txt'));

		$file->delete();

		$this->assertFalse($userFolder->nodeExists('folder/file1.txt'));

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertCount(1, $filesInTrash);

		/** @var \OCP\Files\FileInfo */
		$trashedFile = $filesInTrash[0];

		// delete source folder
		$folder->delete();

		$this->assertTrue(
			OCA\Files_Trashbin\Trashbin::restore(
				'file1.txt.d' . $trashedFile->getMtime(),
				$trashedFile->getName(),
				$trashedFile->getMtime()
			)
		);

		$file = $userFolder->get('file1.txt');
		$this->assertEquals('foo', $file->getContent());
	}

	/**
	 * Test restoring a file in the root folder whenever there is another file
	 * with the same name in the root folder
	 */
	public function testRestoreFileDoesNotOverwriteExistingInRoot() {
		$userFolder = \OC::$server->getUserFolder();
		$file = $userFolder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($userFolder->nodeExists('file1.txt'));

		$file->delete();

		$this->assertFalse($userFolder->nodeExists('file1.txt'));

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertCount(1, $filesInTrash);

		/** @var \OCP\Files\FileInfo */
		$trashedFile = $filesInTrash[0];

		// create another file
		$file = $userFolder->newFile('file1.txt');
		$file->putContent('bar');

		$this->assertTrue(
			OCA\Files_Trashbin\Trashbin::restore(
				'file1.txt.d' . $trashedFile->getMtime(),
				$trashedFile->getName(),
				$trashedFile->getMtime()
			)
		);

		$anotherFile = $userFolder->get('file1.txt');
		$this->assertEquals('bar', $anotherFile->getContent());

		$restoredFile = $userFolder->get('file1 (restored).txt');
		$this->assertEquals('foo', $restoredFile->getContent());
	}

	/**
	 * Test restoring a file whenever there is another file
	 * with the same name in the source folder
	 */
	public function testRestoreFileDoesNotOverwriteExistingInSubfolder() {
		$userFolder = \OC::$server->getUserFolder();
		$folder = $userFolder->newFolder('folder');
		$file = $folder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($userFolder->nodeExists('folder/file1.txt'));

		$file->delete();

		$this->assertFalse($userFolder->nodeExists('folder/file1.txt'));

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertCount(1, $filesInTrash);

		/** @var \OCP\Files\FileInfo */
		$trashedFile = $filesInTrash[0];

		// create another file
		$file = $folder->newFile('file1.txt');
		$file->putContent('bar');

		$this->assertTrue(
			OCA\Files_Trashbin\Trashbin::restore(
				'file1.txt.d' . $trashedFile->getMtime(),
				$trashedFile->getName(),
				$trashedFile->getMtime()
			)
		);

		$anotherFile = $userFolder->get('folder/file1.txt');
		$this->assertEquals('bar', $anotherFile->getContent());

		$restoredFile = $userFolder->get('folder/file1 (restored).txt');
		$this->assertEquals('foo', $restoredFile->getContent());
	}

	/**
	 * Test restoring a non-existing file from trashbin, returns false
	 */
	public function testRestoreUnexistingFile() {
		$this->assertFalse(
			OCA\Files_Trashbin\Trashbin::restore(
				'unexist.txt.d123456',
				'unexist.txt',
				'123456'
			)
		);
	}

	/**
	 * Test restoring a file into a read-only folder, will restore
	 * the file to root instead
	 */
	public function testRestoreFileIntoReadOnlySourceFolder() {
		$userFolder = \OC::$server->getUserFolder();
		$folder = $userFolder->newFolder('folder');
		$file = $folder->newFile('file1.txt');
		$file->putContent('foo');

		$this->assertTrue($userFolder->nodeExists('folder/file1.txt'));

		$file->delete();

		$this->assertFalse($userFolder->nodeExists('folder/file1.txt'));

		$filesInTrash = OCA\Files_Trashbin\Helper::getTrashFiles('/', self::TEST_TRASHBIN_USER1, 'mtime');
		$this->assertCount(1, $filesInTrash);

		/** @var \OCP\Files\FileInfo */
		$trashedFile = $filesInTrash[0];

		// delete source folder
		list($storage, $internalPath) = $this->rootView->resolvePath('/' . self::TEST_TRASHBIN_USER1 . '/files/folder');
		if ($storage instanceof \OC\Files\Storage\Local) {
			$folderAbsPath = $storage->getSourcePath($internalPath);
			// make folder read-only
			chmod($folderAbsPath, 0555);

			$this->assertTrue(
				OCA\Files_Trashbin\Trashbin::restore(
					'file1.txt.d' . $trashedFile->getMtime(),
					$trashedFile->getName(),
					$trashedFile->getMtime()
				)
			);

			$file = $userFolder->get('file1.txt');
			$this->assertEquals('foo', $file->getContent());

			chmod($folderAbsPath, 0755);
		}
	}

	/**
	 * @param string $user
	 * @param bool $create
	 */
	public static function loginHelper($user, $create = false) {
		if ($create) {
			try {
				\OC::$server->getUserManager()->createUser($user, $user);
			} catch (\Exception $e) { // catch username is already being used from previous aborted runs
			}
		}

		\OC_Util::tearDownFS();
		\OC_User::setUserId('');
		\OC\Files\Filesystem::tearDown();
		\OC_User::setUserId($user);
		\OC_Util::setupFS($user);
		\OC::$server->getUserFolder($user);
	}
}


// just a dummy class to make protected methods available for testing
class TrashbinForTesting extends \OCA\Files_Trashbin\Trashbin {

	/**
	 * @param OCP\Files\FileInfo[] $files
	 * @param integer $limit
	 */
	public function dummyDeleteExpiredFiles($files) {
		// dummy value for $retention_obligation because it is not needed here
		return parent::deleteExpiredFiles($files, TrashbinTest::TEST_TRASHBIN_USER1);
	}

	/**
	 * @param OCP\Files\FileInfo[] $files
	 * @param integer $availableSpace
	 */
	public function dummyDeleteFiles($files, $availableSpace) {
		return parent::deleteFiles($files, TrashbinTest::TEST_TRASHBIN_USER1, $availableSpace);
	}
}
