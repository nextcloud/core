<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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

namespace OCA\Files_Trashbin\Tests;

use OC\Files\Filesystem;
use OC\Files\Storage\Temporary;
use OCA\Files_Trashbin\AppInfo\Application;
use OCA\Files_Trashbin\Events\MoveToTrashEvent;
use OCA\Files_Trashbin\Storage;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Cache\ICache;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Storage
 *
 * @group DB
 *
 * @package OCA\Files_Trashbin\Tests
 */
class StorageTest extends \Test\TestCase {
	/**
	 * @var string
	 */
	private $user;

	/**
	 * @var \OC\Files\View
	 */
	private $rootView;

	/**
	 * @var \OC\Files\View
	 */
	private $userView;

	protected function setUp(): void {
		parent::setUp();

		\OC_Hook::clear();
		// register trashbin hooks
		$trashbinApp = new Application();
		$trashbinApp->boot($this->createMock(IBootContext::class));

		$this->user = $this->getUniqueId('user');
		\OC::$server->getUserManager()->createUser($this->user, $this->user);

		// this will setup the FS
		$this->loginAsUser($this->user);

		\OCA\Files_Trashbin\Storage::setupStorage();

		$this->rootView = new \OC\Files\View('/');
		$this->userView = new \OC\Files\View('/' . $this->user . '/files/');
		$this->userView->file_put_contents('test.txt', 'foo');

		$this->userView->mkdir('folder');
		$this->userView->file_put_contents('folder/inside.txt', 'bar');
	}

	protected function tearDown(): void {
		\OC\Files\Filesystem::getLoader()->removeStorageWrapper('oc_trashbin');
		$this->logout();
		$user = \OC::$server->getUserManager()->get($this->user);
		if ($user !== null) {
			$user->delete();
		}
		\OC_Hook::clear();
		parent::tearDown();
	}

	/**
	 * Test that deleting a file puts it into the trashbin.
	 */
	public function testSingleStorageDeleteFile() {
		$this->assertTrue($this->userView->file_exists('test.txt'));
		$this->userView->unlink('test.txt');
		[$storage,] = $this->userView->resolvePath('test.txt');
		$storage->getScanner()->scan(''); // make sure we check the storage
		$this->assertFalse($this->userView->getFileInfo('test.txt'));

		// check if file is in trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files/');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('test.txt', substr($name, 0, strrpos($name, '.')));
	}

	/**
	 * Test that deleting a folder puts it into the trashbin.
	 */
	public function testSingleStorageDeleteFolder() {
		$this->assertTrue($this->userView->file_exists('folder/inside.txt'));
		$this->userView->rmdir('folder');
		[$storage,] = $this->userView->resolvePath('folder/inside.txt');
		$storage->getScanner()->scan(''); // make sure we check the storage
		$this->assertFalse($this->userView->getFileInfo('folder'));

		// check if folder is in trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files/');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('folder', substr($name, 0, strrpos($name, '.')));

		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files/' . $name . '/');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('inside.txt', $name);
	}

	/**
	 * Test that deleting a file from another mounted storage properly
	 * lands in the trashbin. This is a cross-storage situation because
	 * the trashbin folder is in the root storage while the mounted one
	 * isn't.
	 */
	public function testCrossStorageDeleteFile() {
		$storage2 = new Temporary([]);
		\OC\Files\Filesystem::mount($storage2, [], $this->user . '/files/substorage');

		$this->userView->file_put_contents('substorage/subfile.txt', 'foo');
		$storage2->getScanner()->scan('');
		$this->assertTrue($storage2->file_exists('subfile.txt'));
		$this->userView->unlink('substorage/subfile.txt');

		$storage2->getScanner()->scan('');
		$this->assertFalse($this->userView->getFileInfo('substorage/subfile.txt'));
		$this->assertFalse($storage2->file_exists('subfile.txt'));

		// check if file is in trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('subfile.txt', substr($name, 0, strrpos($name, '.')));
	}

	/**
	 * Test that deleting a folder from another mounted storage properly
	 * lands in the trashbin. This is a cross-storage situation because
	 * the trashbin folder is in the root storage while the mounted one
	 * isn't.
	 */
	public function testCrossStorageDeleteFolder() {
		$storage2 = new Temporary([]);
		\OC\Files\Filesystem::mount($storage2, [], $this->user . '/files/substorage');

		$this->userView->mkdir('substorage/folder');
		$this->userView->file_put_contents('substorage/folder/subfile.txt', 'bar');
		$storage2->getScanner()->scan('');
		$this->assertTrue($storage2->file_exists('folder/subfile.txt'));
		$this->userView->rmdir('substorage/folder');

		$storage2->getScanner()->scan('');
		$this->assertFalse($this->userView->getFileInfo('substorage/folder'));
		$this->assertFalse($storage2->file_exists('folder/subfile.txt'));

		// check if folder is in trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('folder', substr($name, 0, strrpos($name, '.')));

		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files/' . $name . '/');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('subfile.txt', $name);
	}

	/**
	 * Test that deleted versions properly land in the trashbin.
	 */
	public function testDeleteVersionsOfFile() {
		\OCA\Files_Versions\Hooks::connectHooks();

		// trigger a version (multiple would not work because of the expire logic)
		$this->userView->file_put_contents('test.txt', 'v1');

		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/');
		$this->assertEquals(1, count($results));

		$this->userView->unlink('test.txt');

		// rescan trash storage
		[$rootStorage,] = $this->rootView->resolvePath($this->user . '/files_trashbin');
		$rootStorage->getScanner()->scan('');

		// check if versions are in trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/versions');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('test.txt.v', substr($name, 0, strlen('test.txt.v')));

		// versions deleted
		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/');
		$this->assertEquals(0, count($results));
	}

	/**
	 * Test that deleted versions properly land in the trashbin.
	 */
	public function testDeleteVersionsOfFolder() {
		\OCA\Files_Versions\Hooks::connectHooks();

		// trigger a version (multiple would not work because of the expire logic)
		$this->userView->file_put_contents('folder/inside.txt', 'v1');

		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/folder/');
		$this->assertEquals(1, count($results));

		$this->userView->rmdir('folder');

		// rescan trash storage
		[$rootStorage,] = $this->rootView->resolvePath($this->user . '/files_trashbin');
		$rootStorage->getScanner()->scan('');

		// check if versions are in trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/versions');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('folder.d', substr($name, 0, strlen('folder.d')));

		// check if versions are in trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/versions/' . $name . '/');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('inside.txt.v', substr($name, 0, strlen('inside.txt.v')));

		// versions deleted
		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/folder/');
		$this->assertEquals(0, count($results));
	}

	/**
	 * Test that deleted versions properly land in the trashbin when deleting as share recipient.
	 */
	public function testDeleteVersionsOfFileAsRecipient() {
		\OCA\Files_Versions\Hooks::connectHooks();

		$this->userView->mkdir('share');
		// trigger a version (multiple would not work because of the expire logic)
		$this->userView->file_put_contents('share/test.txt', 'v1');
		$this->userView->file_put_contents('share/test.txt', 'v2');

		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/share/');
		$this->assertEquals(1, count($results));

		$recipientUser = $this->getUniqueId('recipient_');
		\OC::$server->getUserManager()->createUser($recipientUser, $recipientUser);

		$node = \OC::$server->getUserFolder($this->user)->get('share');
		$share = \OC::$server->getShareManager()->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedBy($this->user)
			->setSharedWith($recipientUser)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = \OC::$server->getShareManager()->createShare($share);
		\OC::$server->getShareManager()->acceptShare($share, $recipientUser);

		$this->loginAsUser($recipientUser);

		// delete as recipient
		$recipientView = new \OC\Files\View('/' . $recipientUser . '/files');
		$recipientView->unlink('share/test.txt');

		// rescan trash storage for both users
		[$rootStorage,] = $this->rootView->resolvePath($this->user . '/files_trashbin');
		$rootStorage->getScanner()->scan('');

		// check if versions are in trashbin for both users
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/versions');
		$this->assertEquals(1, count($results), 'Versions in owner\'s trashbin');
		$name = $results[0]->getName();
		$this->assertEquals('test.txt.v', substr($name, 0, strlen('test.txt.v')));

		$results = $this->rootView->getDirectoryContent($recipientUser . '/files_trashbin/versions');
		$this->assertEquals(1, count($results), 'Versions in recipient\'s trashbin');
		$name = $results[0]->getName();
		$this->assertEquals('test.txt.v', substr($name, 0, strlen('test.txt.v')));

		// versions deleted
		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/share/');
		$this->assertEquals(0, count($results));
	}

	/**
	 * Test that deleted versions properly land in the trashbin when deleting as share recipient.
	 */
	public function testDeleteVersionsOfFolderAsRecipient() {
		\OCA\Files_Versions\Hooks::connectHooks();

		$this->userView->mkdir('share');
		$this->userView->mkdir('share/folder');
		// trigger a version (multiple would not work because of the expire logic)
		$this->userView->file_put_contents('share/folder/test.txt', 'v1');
		$this->userView->file_put_contents('share/folder/test.txt', 'v2');

		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/share/folder/');
		$this->assertEquals(1, count($results));

		$recipientUser = $this->getUniqueId('recipient_');
		\OC::$server->getUserManager()->createUser($recipientUser, $recipientUser);

		$node = \OC::$server->getUserFolder($this->user)->get('share');
		$share = \OC::$server->getShareManager()->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedBy($this->user)
			->setSharedWith($recipientUser)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = \OC::$server->getShareManager()->createShare($share);
		\OC::$server->getShareManager()->acceptShare($share, $recipientUser);

		$this->loginAsUser($recipientUser);

		// delete as recipient
		$recipientView = new \OC\Files\View('/' . $recipientUser . '/files');
		$recipientView->rmdir('share/folder');

		// rescan trash storage
		[$rootStorage,] = $this->rootView->resolvePath($this->user . '/files_trashbin');
		$rootStorage->getScanner()->scan('');

		// check if versions are in trashbin for owner
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/versions');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('folder.d', substr($name, 0, strlen('folder.d')));

		// check if file versions are in trashbin for owner
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/versions/' . $name . '/');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('test.txt.v', substr($name, 0, strlen('test.txt.v')));

		// check if versions are in trashbin for recipient
		$results = $this->rootView->getDirectoryContent($recipientUser . '/files_trashbin/versions');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('folder.d', substr($name, 0, strlen('folder.d')));

		// check if file versions are in trashbin for recipient
		$results = $this->rootView->getDirectoryContent($recipientUser . '/files_trashbin/versions/' . $name . '/');
		$this->assertEquals(1, count($results));
		$name = $results[0]->getName();
		$this->assertEquals('test.txt.v', substr($name, 0, strlen('test.txt.v')));

		// versions deleted
		$results = $this->rootView->getDirectoryContent($recipientUser . '/files_versions/share/folder/');
		$this->assertEquals(0, count($results));
	}

	/**
	 * Test that versions are not auto-trashed when moving a file between
	 * storages. This is because rename() between storages would call
	 * unlink() which should NOT trigger the version deletion logic.
	 */
	public function testKeepFileAndVersionsWhenMovingFileBetweenStorages() {
		\OCA\Files_Versions\Hooks::connectHooks();

		$storage2 = new Temporary([]);
		\OC\Files\Filesystem::mount($storage2, [], $this->user . '/files/substorage');

		// trigger a version (multiple would not work because of the expire logic)
		$this->userView->file_put_contents('test.txt', 'v1');

		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files');
		$this->assertEquals(0, count($results));

		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/');
		$this->assertEquals(1, count($results));

		// move to another storage
		$this->userView->rename('test.txt', 'substorage/test.txt');
		$this->assertTrue($this->userView->file_exists('substorage/test.txt'));

		// rescan trash storage
		[$rootStorage,] = $this->rootView->resolvePath($this->user . '/files_trashbin');
		$rootStorage->getScanner()->scan('');

		// versions were moved too
		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/substorage');
		$this->assertEquals(1, count($results));

		// check that nothing got trashed by the rename's unlink() call
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files');
		$this->assertEquals(0, count($results));

		// check that versions were moved and not trashed
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/versions/');
		$this->assertEquals(0, count($results));
	}

	/**
	 * Test that versions are not auto-trashed when moving a file between
	 * storages. This is because rename() between storages would call
	 * unlink() which should NOT trigger the version deletion logic.
	 */
	public function testKeepFileAndVersionsWhenMovingFolderBetweenStorages() {
		\OCA\Files_Versions\Hooks::connectHooks();

		$storage2 = new Temporary([]);
		\OC\Files\Filesystem::mount($storage2, [], $this->user . '/files/substorage');

		// trigger a version (multiple would not work because of the expire logic)
		$this->userView->file_put_contents('folder/inside.txt', 'v1');

		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files');
		$this->assertEquals(0, count($results));

		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/folder/');
		$this->assertEquals(1, count($results));

		// move to another storage
		$this->userView->rename('folder', 'substorage/folder');
		$this->assertTrue($this->userView->file_exists('substorage/folder/inside.txt'));

		// rescan trash storage
		[$rootStorage,] = $this->rootView->resolvePath($this->user . '/files_trashbin');
		$rootStorage->getScanner()->scan('');

		// versions were moved too
		$results = $this->rootView->getDirectoryContent($this->user . '/files_versions/substorage/folder/');
		$this->assertEquals(1, count($results));

		// check that nothing got trashed by the rename's unlink() call
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files');
		$this->assertEquals(0, count($results));

		// check that versions were moved and not trashed
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/versions/');
		$this->assertEquals(0, count($results));
	}

	/**
	 * Delete should fail if the source file can't be deleted.
	 */
	public function testSingleStorageDeleteFileFail() {
		/**
		 * @var \OC\Files\Storage\Temporary | \PHPUnit\Framework\MockObject\MockObject $storage
		 */
		$storage = $this->getMockBuilder('\OC\Files\Storage\Temporary')
			->setConstructorArgs([[]])
			->setMethods(['rename', 'unlink', 'moveFromStorage'])
			->getMock();

		$storage->expects($this->any())
			->method('rename')
			->willReturn(false);
		$storage->expects($this->any())
			->method('moveFromStorage')
			->willReturn(false);
		$storage->expects($this->any())
			->method('unlink')
			->willReturn(false);

		$cache = $storage->getCache();

		Filesystem::mount($storage, [], '/' . $this->user);
		$storage->mkdir('files');
		$this->userView->file_put_contents('test.txt', 'foo');
		$this->assertTrue($storage->file_exists('files/test.txt'));
		$this->assertFalse($this->userView->unlink('test.txt'));
		$this->assertTrue($storage->file_exists('files/test.txt'));
		$this->assertTrue($cache->inCache('files/test.txt'));

		// file should not be in the trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files/');
		$this->assertEquals(0, count($results));
	}

	/**
	 * Delete should fail if the source folder can't be deleted.
	 */
	public function testSingleStorageDeleteFolderFail() {
		/**
		 * @var \OC\Files\Storage\Temporary | \PHPUnit\Framework\MockObject\MockObject $storage
		 */
		$storage = $this->getMockBuilder('\OC\Files\Storage\Temporary')
			->setConstructorArgs([[]])
			->setMethods(['rename', 'unlink', 'rmdir'])
			->getMock();

		$storage->expects($this->any())
			->method('rmdir')
			->willReturn(false);

		$cache = $storage->getCache();

		Filesystem::mount($storage, [], '/' . $this->user);
		$storage->mkdir('files');
		$this->userView->mkdir('folder');
		$this->userView->file_put_contents('folder/test.txt', 'foo');
		$this->assertTrue($storage->file_exists('files/folder/test.txt'));
		$this->assertFalse($this->userView->rmdir('files/folder'));
		$this->assertTrue($storage->file_exists('files/folder'));
		$this->assertTrue($storage->file_exists('files/folder/test.txt'));
		$this->assertTrue($cache->inCache('files/folder'));
		$this->assertTrue($cache->inCache('files/folder/test.txt'));

		// file should not be in the trashbin
		$results = $this->rootView->getDirectoryContent($this->user . '/files_trashbin/files/');
		$this->assertEquals(0, count($results));
	}

	/**
	 * @dataProvider dataTestShouldMoveToTrash
	 */
	public function testShouldMoveToTrash($mountPoint, $path, $userExists, $appDisablesTrash, $expected) {
		$fileID = 1;
		$cache = $this->createMock(ICache::class);
		$cache->expects($this->any())->method('getId')->willReturn($fileID);
		$tmpStorage = $this->getMockBuilder('\OC\Files\Storage\Temporary')
			->disableOriginalConstructor()->getMock($cache);
		$tmpStorage->expects($this->any())->method('getCache')->willReturn($cache);
		$userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()->getMock();
		$userManager->expects($this->any())
			->method('userExists')->willReturn($userExists);
		$logger = $this->getMockBuilder(ILogger::class)->getMock();
		$eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$rootFolder = $this->createMock(IRootFolder::class);
		$userFolder = $this->createMock(Folder::class);
		$node = $this->getMockBuilder(Node::class)->disableOriginalConstructor()->getMock();
		$trashManager = $this->createMock(ITrashManager::class);
		$event = $this->getMockBuilder(MoveToTrashEvent::class)->disableOriginalConstructor()->getMock();
		$event->expects($this->any())->method('shouldMoveToTrashBin')->willReturn(!$appDisablesTrash);

		$userFolder->expects($this->any())->method('getById')->with($fileID)->willReturn([$node]);
		$rootFolder->expects($this->any())->method('getUserFolder')->willReturn($userFolder);

		$storage = $this->getMockBuilder(Storage::class)
			->setConstructorArgs(
				[
					['mountPoint' => $mountPoint, 'storage' => $tmpStorage],
					$trashManager,
					$userManager,
					$logger,
					$eventDispatcher,
					$rootFolder
				]
			)->setMethods(['createMoveToTrashEvent'])->getMock();

		$storage->expects($this->any())->method('createMoveToTrashEvent')->with($node)
			->willReturn($event);

		$this->assertSame($expected,
			$this->invokePrivate($storage, 'shouldMoveToTrash', [$path])
		);
	}

	public function dataTestShouldMoveToTrash() {
		return [
			['/schiesbn/', '/files/test.txt', true, false, true],
			['/schiesbn/', '/files/test.txt', false, false, false],
			['/schiesbn/', '/test.txt', true, false, false],
			['/schiesbn/', '/test.txt', false, false, false],
			// other apps disables the trashbin
			['/schiesbn/', '/files/test.txt', true, true, false],
			['/schiesbn/', '/files/test.txt', false, true, false],
		];
	}

	/**
	 * Test that deleting a file doesn't error when nobody is logged in
	 */
	public function testSingleStorageDeleteFileLoggedOut() {
		$this->logout();

		if (!$this->userView->file_exists('test.txt')) {
			$this->markTestSkipped('Skipping since the current home storage backend requires the user to logged in');
		} else {
			$this->userView->unlink('test.txt');
			$this->addToAssertionCount(1);
		}
	}

	public function testTrashbinCollision() {
		$this->userView->file_put_contents('test.txt', 'foo');
		$this->userView->file_put_contents('folder/test.txt', 'bar');

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')
			->willReturn(1000);

		$lockingProvider = \OC::$server->getLockingProvider();

		$this->overwriteService(ITimeFactory::class, $timeFactory);

		$this->userView->unlink('test.txt');

		$this->assertTrue($this->rootView->file_exists('/' . $this->user . '/files_trashbin/files/test.txt.d1000'));

		/** @var \OC\Files\Storage\Storage $trashStorage */
		[$trashStorage, $trashInternalPath] = $this->rootView->resolvePath('/' . $this->user . '/files_trashbin/files/test.txt.d1000');

		/// simulate a concurrent delete
		$trashStorage->acquireLock($trashInternalPath, ILockingProvider::LOCK_EXCLUSIVE, $lockingProvider);

		$this->userView->unlink('folder/test.txt');

		$trashStorage->releaseLock($trashInternalPath, ILockingProvider::LOCK_EXCLUSIVE, $lockingProvider);

		$this->assertTrue($this->rootView->file_exists($this->user . '/files_trashbin/files/test.txt.d1001'));

		$this->assertEquals('foo', $this->rootView->file_get_contents($this->user . '/files_trashbin/files/test.txt.d1000'));
		$this->assertEquals('bar', $this->rootView->file_get_contents($this->user . '/files_trashbin/files/test.txt.d1001'));
	}
}
