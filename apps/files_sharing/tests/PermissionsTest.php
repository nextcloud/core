<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

use OC\Files\Cache\Cache;
use OC\Files\Storage\Storage;
use OC\Files\View;
use OCP\Share\IShare;

/**
 * Class PermissionsTest
 *
 * @group DB
 */
class PermissionsTest extends TestCase {

	/** @var Storage */
	private $sharedStorageRestrictedShare;

	/** @var Storage */
	private $sharedCacheRestrictedShare;

	/** @var View */
	private $secondView;

	/** @var Storage */
	private $ownerStorage;

	/** @var Storage */
	private $sharedStorage;

	/** @var Cache */
	private $sharedCache;

	/** @var Cache */
	private $ownerCache;

	protected function setUp(): void {
		parent::setUp();

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		// prepare user1's dir structure
		$textData = "dummy file data\n";
		$this->view->mkdir('container');
		$this->view->mkdir('container/shareddir');
		$this->view->mkdir('container/shareddir/subdir');
		$this->view->mkdir('container/shareddirrestricted');
		$this->view->mkdir('container/shareddirrestricted/subdir');
		$this->view->file_put_contents('container/shareddir/textfile.txt', $textData);
		$this->view->file_put_contents('container/shareddirrestricted/textfile1.txt', $textData);

		list($this->ownerStorage, $internalPath) = $this->view->resolvePath('');
		$this->ownerCache = $this->ownerStorage->getCache();
		$this->ownerStorage->getScanner()->scan('');

		// share "shareddir" with user2
		$rootFolder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER1);

		$node = $rootFolder->get('container/shareddir');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);

		$node = $rootFolder->get('container/shareddirrestricted');
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE);
		$share = $this->shareManager->createShare($share);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);

		// login as user2
		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		// retrieve the shared storage
		$this->secondView = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2);
		list($this->sharedStorage, $internalPath) = $this->secondView->resolvePath('files/shareddir');
		list($this->sharedStorageRestrictedShare, $internalPath) = $this->secondView->resolvePath('files/shareddirrestricted');
		$this->sharedCache = $this->sharedStorage->getCache();
		$this->sharedCacheRestrictedShare = $this->sharedStorageRestrictedShare->getCache();
	}

	protected function tearDown(): void {
		if ($this->sharedCache) {
			$this->sharedCache->clear();
		}

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$shares = $this->shareManager->getSharesBy(self::TEST_FILES_SHARING_API_USER1, \OCP\Share::SHARE_TYPE_USER);
		foreach ($shares as $share) {
			$this->shareManager->deleteShare($share);
		}

		$this->view->deleteAll('container');

		$this->ownerCache->clear();

		parent::tearDown();
	}

	/**
	 * Test that the permissions of shared directory are returned correctly
	 */
	function testGetPermissions() {
		$sharedDirPerms = $this->sharedStorage->getPermissions('');
		$this->assertEquals(31, $sharedDirPerms);
		$sharedDirPerms = $this->sharedStorage->getPermissions('textfile.txt');
		$this->assertEquals(27, $sharedDirPerms);
		$sharedDirRestrictedPerms = $this->sharedStorageRestrictedShare->getPermissions('');
		$this->assertEquals(15, $sharedDirRestrictedPerms);
		$sharedDirRestrictedPerms = $this->sharedStorageRestrictedShare->getPermissions('textfile1.txt');
		$this->assertEquals(3, $sharedDirRestrictedPerms);
	}

	/**
	 * Test that the permissions of shared directory are returned correctly
	 */
	function testGetDirectoryPermissions() {
		$contents = $this->secondView->getDirectoryContent('files/shareddir');
		$this->assertEquals('subdir', $contents[0]['name']);
		$this->assertEquals(31, $contents[0]['permissions']);
		$this->assertEquals('textfile.txt', $contents[1]['name']);
		// 27 is correct because create is reserved to folders only - requires more unit tests overall to ensure this
		$this->assertEquals(27, $contents[1]['permissions']);
		$contents = $this->secondView->getDirectoryContent('files/shareddirrestricted');
		$this->assertEquals('subdir', $contents[0]['name']);
		$this->assertEquals(7, $contents[0]['permissions']);
		$this->assertEquals('textfile1.txt', $contents[1]['name']);
		// 3 is correct because create is reserved to folders only
		$this->assertEquals(3, $contents[1]['permissions']);
	}
}
