<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
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

use OC\Files\Storage\Temporary;
use OC\Files\Storage\Wrapper\Jail;
use OCA\Files_Sharing\SharedStorage;
use OCP\Share\IShare;

/**
 * Class CacheTest
 *
 * @group DB
 */
class CacheTest extends TestCase {

	/**
	 * @var \OC\Files\View
	 */
	public $user2View;

	/** @var \OC\Files\Cache\Cache */
	protected $ownerCache;

	/** @var \OC\Files\Cache\Cache */
	protected $sharedCache;

	/** @var \OC\Files\Storage\Storage */
	protected $ownerStorage;

	/** @var \OC\Files\Storage\Storage */
	protected $sharedStorage;

	/** @var \OCP\Share\IManager */
	protected $shareManager;

	protected function setUp(): void {
		parent::setUp();

		$this->shareManager = \OC::$server->getShareManager();


		$userManager = \OC::$server->getUserManager();
		$userManager->get(self::TEST_FILES_SHARING_API_USER1)->setDisplayName('User One');
		$userManager->get(self::TEST_FILES_SHARING_API_USER2)->setDisplayName('User Two');

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$this->user2View = new \OC\Files\View('/'. self::TEST_FILES_SHARING_API_USER2 . '/files');

		// prepare user1's dir structure
		$this->view->mkdir('container');
		$this->view->mkdir('container/shareddir');
		$this->view->mkdir('container/shareddir/subdir');
		$this->view->mkdir('container/shareddir/emptydir');

		$textData = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$this->view->file_put_contents('container/not shared.txt', $textData);
		$this->view->file_put_contents('container/shared single file.txt', $textData);
		$this->view->file_put_contents('container/shareddir/bar.txt', $textData);
		$this->view->file_put_contents('container/shareddir/subdir/another.txt', $textData);
		$this->view->file_put_contents('container/shareddir/subdir/another too.txt', $textData);
		$this->view->file_put_contents('container/shareddir/subdir/not a text file.xml', '<xml></xml>');

		list($this->ownerStorage,) = $this->view->resolvePath('');
		$this->ownerCache = $this->ownerStorage->getCache();
		$this->ownerStorage->getScanner()->scan('');

		// share "shareddir" with user2
		$rootFolder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER1);

		$node = $rootFolder->get('container/shareddir');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);

		$node = $rootFolder->get('container/shared single file.txt');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL & ~(\OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_DELETE));
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);

		// login as user2
		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		// retrieve the shared storage
		$secondView = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2);
		list($this->sharedStorage,) = $secondView->resolvePath('files/shareddir');
		$this->sharedCache = $this->sharedStorage->getCache();
	}

	protected function tearDown(): void {
		if ($this->sharedCache) {
			$this->sharedCache->clear();
		}

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$shares = $this->shareManager->getSharesBy(self::TEST_FILES_SHARING_API_USER1, IShare::TYPE_USER);
		foreach ($shares as $share) {
			$this->shareManager->deleteShare($share);
		}

		$this->view->deleteAll('container');

		$this->ownerCache->clear();

		parent::tearDown();
	}

	public function searchDataProvider() {
		return [
			['%another%',
				[
					['name' => 'another too.txt', 'path' => 'subdir/another too.txt'],
					['name' => 'another.txt', 'path' => 'subdir/another.txt'],
				]
			],
			['%Another%',
				[
					['name' => 'another too.txt', 'path' => 'subdir/another too.txt'],
					['name' => 'another.txt', 'path' => 'subdir/another.txt'],
				]
			],
			['%dir%',
				[
					['name' => 'emptydir', 'path' => 'emptydir'],
					['name' => 'subdir', 'path' => 'subdir'],
					['name' => 'shareddir', 'path' => ''],
				]
			],
			['%Dir%',
				[
					['name' => 'emptydir', 'path' => 'emptydir'],
					['name' => 'subdir', 'path' => 'subdir'],
					['name' => 'shareddir', 'path' => ''],
				]
			],
			['%txt%',
				[
					['name' => 'bar.txt', 'path' => 'bar.txt'],
					['name' => 'another too.txt', 'path' => 'subdir/another too.txt'],
					['name' => 'another.txt', 'path' => 'subdir/another.txt'],
				]
			],
			['%Txt%',
				[
					['name' => 'bar.txt', 'path' => 'bar.txt'],
					['name' => 'another too.txt', 'path' => 'subdir/another too.txt'],
					['name' => 'another.txt', 'path' => 'subdir/another.txt'],
				]
			],
			['%',
				[
					['name' => 'bar.txt', 'path' => 'bar.txt'],
					['name' => 'emptydir', 'path' => 'emptydir'],
					['name' => 'subdir', 'path' => 'subdir'],
					['name' => 'another too.txt', 'path' => 'subdir/another too.txt'],
					['name' => 'another.txt', 'path' => 'subdir/another.txt'],
					['name' => 'not a text file.xml', 'path' => 'subdir/not a text file.xml'],
					['name' => 'shareddir', 'path' => ''],
				]
			],
			['%nonexistent%',
				[
				]
			],
		];
	}

	/**
	 * we cannot use a dataProvider because that would cause the stray hook detection to remove the hooks
	 * that were added in setUpBeforeClass.
	 */
	public function testSearch() {
		foreach ($this->searchDataProvider() as $data) {
			list($pattern, $expectedFiles) = $data;

			$results = $this->sharedStorage->getCache()->search($pattern);

			$this->verifyFiles($expectedFiles, $results);
		}
	}
	/**
	 * Test searching by mime type
	 */
	public function testSearchByMime() {
		$results = $this->sharedStorage->getCache()->searchByMime('text');
		$check = [
			[
				'name' => 'bar.txt',
				'path' => 'bar.txt'
			],
			[
				'name' => 'another too.txt',
				'path' => 'subdir/another too.txt'
			],
			[
				'name' => 'another.txt',
				'path' => 'subdir/another.txt'
			],
		];
		$this->verifyFiles($check, $results);
	}

	public function testGetFolderContentsInRoot() {
		$results = $this->user2View->getDirectoryContent('/');

		// we should get the shared items "shareddir" and "shared single file.txt"
		// additional root will always contain the example file "welcome.txt",
		//  so this will be part of the result
		$this->verifyFiles(
			[
				[
					'name' => 'welcome.txt',
					'path' => 'files/welcome.txt',
					'mimetype' => 'text/plain',
				],
				[
					'name' => 'shareddir',
					'path' => 'files/shareddir',
					'mimetype' => 'httpd/unix-directory',
					'uid_owner' => self::TEST_FILES_SHARING_API_USER1,
					'displayname_owner' => 'User One',
				],
				[
					'name' => 'shared single file.txt',
					'path' => 'files/shared single file.txt',
					'mimetype' => 'text/plain',
					'uid_owner' => self::TEST_FILES_SHARING_API_USER1,
					'displayname_owner' => 'User One',
				],
			],
			$results
		);
	}

	public function testGetFolderContentsInSubdir() {
		$results = $this->user2View->getDirectoryContent('/shareddir');

		$this->verifyFiles(
			[
				[
					'name' => 'bar.txt',
					'path' => 'bar.txt',
					'mimetype' => 'text/plain',
					'uid_owner' => self::TEST_FILES_SHARING_API_USER1,
					'displayname_owner' => 'User One',
				],
				[
					'name' => 'emptydir',
					'path' => 'emptydir',
					'mimetype' => 'httpd/unix-directory',
					'uid_owner' => self::TEST_FILES_SHARING_API_USER1,
					'displayname_owner' => 'User One',
				],
				[
					'name' => 'subdir',
					'path' => 'subdir',
					'mimetype' => 'httpd/unix-directory',
					'uid_owner' => self::TEST_FILES_SHARING_API_USER1,
					'displayname_owner' => 'User One',
				],
			],
			$results
		);
	}

	public function testGetFolderContentsWhenSubSubdirShared() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$rootFolder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER1);
		$node = $rootFolder->get('container/shareddir/subdir');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER3)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER3);

		$thirdView = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER3 . '/files');
		$results = $thirdView->getDirectoryContent('/subdir');

		$this->verifyFiles(
			[
				[
					'name' => 'another too.txt',
					'path' => 'another too.txt',
					'mimetype' => 'text/plain',
					'uid_owner' => self::TEST_FILES_SHARING_API_USER1,
					'displayname_owner' => 'User One',
				],
				[
					'name' => 'another.txt',
					'path' => 'another.txt',
					'mimetype' => 'text/plain',
					'uid_owner' => self::TEST_FILES_SHARING_API_USER1,
					'displayname_owner' => 'User One',
				],
				[
					'name' => 'not a text file.xml',
					'path' => 'not a text file.xml',
					'mimetype' => 'application/xml',
					'uid_owner' => self::TEST_FILES_SHARING_API_USER1,
					'displayname_owner' => 'User One',
				],
			],
			$results
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$this->shareManager->deleteShare($share);
	}

	/**
	 * Check if 'results' contains the expected 'examples' only.
	 *
	 * @param array $examples array of example files
	 * @param array $results array of files
	 */
	private function verifyFiles($examples, $results) {
		$this->assertEquals(count($examples), count($results));

		foreach ($examples as $example) {
			foreach ($results as $key => $result) {
				if ($result['name'] === $example['name']) {
					$this->verifyKeys($example, $result);
					unset($results[$key]);
					break;
				}
			}
		}
		$this->assertEquals([], $results);
	}

	/**
	 * verify if each value from the result matches the expected result
	 * @param array $example array with the expected results
	 * @param array $result array with the results
	 */
	private function verifyKeys($example, $result) {
		foreach ($example as $key => $value) {
			$this->assertEquals($value, $result[$key]);
		}
	}

	public function testGetPathByIdDirectShare() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		\OC\Files\Filesystem::file_put_contents('test.txt', 'foo');
		$info = \OC\Files\Filesystem::getFileInfo('test.txt');

		$rootFolder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER1);
		$node = $rootFolder->get('test.txt');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE);
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);

		\OC_Util::tearDownFS();

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$this->assertTrue(\OC\Files\Filesystem::file_exists('/test.txt'));
		list($sharedStorage) = \OC\Files\Filesystem::resolvePath('/' . self::TEST_FILES_SHARING_API_USER2 . '/files/test.txt');
		/**
		 * @var \OCA\Files_Sharing\SharedStorage $sharedStorage
		 */

		$sharedCache = $sharedStorage->getCache();
		$this->assertEquals('', $sharedCache->getPathById($info->getId()));
	}

	public function testGetPathByIdShareSubFolder() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		\OC\Files\Filesystem::mkdir('foo');
		\OC\Files\Filesystem::mkdir('foo/bar');
		\OC\Files\Filesystem::touch('foo/bar/test.txt');
		$folderInfo = \OC\Files\Filesystem::getFileInfo('foo');
		$fileInfo = \OC\Files\Filesystem::getFileInfo('foo/bar/test.txt');

		$rootFolder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER1);
		$node = $rootFolder->get('foo');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);
		\OC_Util::tearDownFS();

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$this->assertTrue(\OC\Files\Filesystem::file_exists('/foo'));
		list($sharedStorage) = \OC\Files\Filesystem::resolvePath('/' . self::TEST_FILES_SHARING_API_USER2 . '/files/foo');
		/**
		 * @var \OCA\Files_Sharing\SharedStorage $sharedStorage
		 */

		$sharedCache = $sharedStorage->getCache();
		$this->assertEquals('', $sharedCache->getPathById($folderInfo->getId()));
		$this->assertEquals('bar/test.txt', $sharedCache->getPathById($fileInfo->getId()));
	}

	public function testNumericStorageId() {
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);
		\OC\Files\Filesystem::mkdir('foo');

		$rootFolder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER1);
		$node = $rootFolder->get('foo');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);
		\OC_Util::tearDownFS();

		list($sourceStorage) = \OC\Files\Filesystem::resolvePath('/' . self::TEST_FILES_SHARING_API_USER1 . '/files/foo');

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$this->assertTrue(\OC\Files\Filesystem::file_exists('/foo'));
		/** @var SharedStorage $sharedStorage */
		list($sharedStorage) = \OC\Files\Filesystem::resolvePath('/' . self::TEST_FILES_SHARING_API_USER2 . '/files/foo');

		$this->assertEquals($sourceStorage->getCache()->getNumericStorageId(), $sharedStorage->getCache()->getNumericStorageId());
	}

	public function testShareJailedStorage() {
		$sourceStorage = new Temporary();
		$sourceStorage->mkdir('jail');
		$sourceStorage->mkdir('jail/sub');
		$sourceStorage->file_put_contents('jail/sub/foo.txt', 'foo');
		$jailedSource = new Jail([
			'storage' => $sourceStorage,
			'root' => 'jail'
		]);
		$sourceStorage->getScanner()->scan('');
		$this->registerMount(self::TEST_FILES_SHARING_API_USER1, $jailedSource, '/' . self::TEST_FILES_SHARING_API_USER1 . '/files/foo');

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$rootFolder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER1);
		$node = $rootFolder->get('foo/sub');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);
		\OC_Util::tearDownFS();

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);
		$this->assertEquals('foo', \OC\Files\Filesystem::file_get_contents('/sub/foo.txt'));

		\OC\Files\Filesystem::file_put_contents('/sub/bar.txt', 'bar');
		/** @var SharedStorage $sharedStorage */
		list($sharedStorage) = \OC\Files\Filesystem::resolvePath('/' . self::TEST_FILES_SHARING_API_USER2 . '/files/sub');

		$this->assertTrue($sharedStorage->getCache()->inCache('bar.txt'));

		$this->assertTrue($sourceStorage->getCache()->inCache('jail/sub/bar.txt'));
	}
}
