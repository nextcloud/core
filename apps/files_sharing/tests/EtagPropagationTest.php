<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use OC\Files\Filesystem;
use OC\Files\View;
use OCP\Share\IShare;

/**
 * Class EtagPropagationTest
 *
 * @group SLOWDB
 *
 * @package OCA\Files_Sharing\Tests
 */
class EtagPropagationTest extends PropagationTestCase {

	/**
	 * "user1" is the admin who shares a folder "sub1/sub2/folder" with "user2" and "user3"
	 * "user2" receives the folder and puts it in "sub1/sub2/folder"
	 * "user3" receives the folder and puts it in "sub1/sub2/folder"
	 * "user2" reshares the subdir "sub1/sub2/folder/inside" with "user4"
	 * "user4" puts the received "inside" folder into "sub1/sub2/inside" (this is to check if it propagates across multiple subfolders)
	 */
	protected function setUpShares() {
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER1] = [];
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER2] = [];
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER3] = [];
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER4] = [];

		$rootFolder = \OC::$server->getRootFolder();
		$shareManager = \OC::$server->getShareManager();

		$this->rootView = new View('');
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		$view1 = new View('/' . $this->TEST_FILES_SHARING_API_USER1 . '/files');
		$view1->mkdir('/sub1/sub2/folder/inside');
		$view1->mkdir('/directReshare');
		$view1->mkdir('/sub1/sub2/folder/other');
		$view1->file_put_contents('/foo.txt', 'foobar');
		$view1->file_put_contents('/sub1/sub2/folder/file.txt', 'foobar');
		$view1->file_put_contents('/sub1/sub2/folder/inside/file.txt', 'foobar');
		$folderInfo = $view1->getFileInfo('/sub1/sub2/folder');
		$this->assertInstanceOf('\OC\Files\FileInfo', $folderInfo);
		$fileInfo = $view1->getFileInfo('/foo.txt');
		$this->assertInstanceOf('\OC\Files\FileInfo', $fileInfo);

		$node = $rootFolder->getUserFolder($this->TEST_FILES_SHARING_API_USER1)
			->get('/foo.txt');
		$share = $shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith($this->TEST_FILES_SHARING_API_USER2)
			->setSharedBy($this->TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE);
		$share = $shareManager->createShare($share);
		$this->shareManager->acceptShare($share, $this->TEST_FILES_SHARING_API_USER2);
		$node = $rootFolder->getUserFolder($this->TEST_FILES_SHARING_API_USER1)
			->get('/sub1/sub2/folder');

		$share = $shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith($this->TEST_FILES_SHARING_API_USER2)
			->setSharedBy($this->TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $shareManager->createShare($share);
		$this->shareManager->acceptShare($share, $this->TEST_FILES_SHARING_API_USER2);

		$share = $shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith($this->TEST_FILES_SHARING_API_USER3)
			->setSharedBy($this->TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $shareManager->createShare($share);
		$this->shareManager->acceptShare($share, $this->TEST_FILES_SHARING_API_USER3);

		$folderInfo = $view1->getFileInfo('/directReshare');
		$this->assertInstanceOf('\OC\Files\FileInfo', $folderInfo);

		$node = $rootFolder->getUserFolder($this->TEST_FILES_SHARING_API_USER1)
			->get('/directReshare');
		$share = $shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith($this->TEST_FILES_SHARING_API_USER2)
			->setSharedBy($this->TEST_FILES_SHARING_API_USER1)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $shareManager->createShare($share);
		$this->shareManager->acceptShare($share, $this->TEST_FILES_SHARING_API_USER2);

		$this->fileIds[$this->TEST_FILES_SHARING_API_USER1][''] = $view1->getFileInfo('')->getId();
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER1]['sub1'] = $view1->getFileInfo('sub1')->getId();
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER1]['sub1/sub2'] = $view1->getFileInfo('sub1/sub2')->getId();

		/*
		 * User 2
		 */
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		$view2 = new View('/' . $this->TEST_FILES_SHARING_API_USER2 . '/files');
		$view2->mkdir('/sub1/sub2');
		$view2->rename('/folder', '/sub1/sub2/folder');
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);

		$insideInfo = $view2->getFileInfo('/sub1/sub2/folder/inside');
		$this->assertInstanceOf('\OC\Files\FileInfo', $insideInfo);

		$node = $rootFolder->getUserFolder($this->TEST_FILES_SHARING_API_USER2)
			->get('/sub1/sub2/folder/inside');
		$share = $shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith($this->TEST_FILES_SHARING_API_USER4)
			->setSharedBy($this->TEST_FILES_SHARING_API_USER2)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $shareManager->createShare($share);
		$this->shareManager->acceptShare($share, $this->TEST_FILES_SHARING_API_USER4);

		$folderInfo = $view2->getFileInfo('/directReshare');
		$this->assertInstanceOf('\OC\Files\FileInfo', $folderInfo);

		$node = $rootFolder->getUserFolder($this->TEST_FILES_SHARING_API_USER2)
			->get('/directReshare');
		$share = $shareManager->newShare();
		$share->setNode($node)
			->setShareType(IShare::TYPE_USER)
			->setSharedWith($this->TEST_FILES_SHARING_API_USER4)
			->setSharedBy($this->TEST_FILES_SHARING_API_USER2)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share = $shareManager->createShare($share);
		$this->shareManager->acceptShare($share, $this->TEST_FILES_SHARING_API_USER4);

		$this->fileIds[$this->TEST_FILES_SHARING_API_USER2][''] = $view2->getFileInfo('')->getId();
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER2]['sub1'] = $view2->getFileInfo('sub1')->getId();
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER2]['sub1/sub2'] = $view2->getFileInfo('sub1/sub2')->getId();

		/*
		 * User 3
		 */
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER3);
		$view3 = new View('/' . $this->TEST_FILES_SHARING_API_USER3 . '/files');
		$view3->mkdir('/sub1/sub2');
		$view3->rename('/folder', '/sub1/sub2/folder');
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER3][''] = $view3->getFileInfo('')->getId();
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER3]['sub1'] = $view3->getFileInfo('sub1')->getId();
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER3]['sub1/sub2'] = $view3->getFileInfo('sub1/sub2')->getId();

		/*
		 * User 4
		 */
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER4);
		$view4 = new View('/' . $this->TEST_FILES_SHARING_API_USER4 . '/files');
		$view4->mkdir('/sub1/sub2');
		$view4->rename('/inside', '/sub1/sub2/inside');
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER4][''] = $view4->getFileInfo('')->getId();
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER4]['sub1'] = $view4->getFileInfo('sub1')->getId();
		$this->fileIds[$this->TEST_FILES_SHARING_API_USER4]['sub1/sub2'] = $view4->getFileInfo('sub1/sub2')->getId();

		foreach ($this->fileIds as $user => $ids) {
			$this->loginAsUser($user);
			foreach ($ids as $id) {
				$path = $this->rootView->getPath($id);
				$ls = $this->rootView->getDirectoryContent($path);
				$this->fileEtags[$id] = $this->rootView->getFileInfo($path)->getEtag();
			}
		}
	}

	public function testOwnerWritesToShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		Filesystem::file_put_contents('/sub1/sub2/folder/asd.txt', 'bar');
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER4]);
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3]);

		$this->assertAllUnchanged();
	}

	public function testOwnerWritesToSingleFileShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		Filesystem::file_put_contents('/foo.txt', 'longer_bar');
		$t = (int)Filesystem::filemtime('/foo.txt') - 1;
		Filesystem::touch('/foo.txt', $t);
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER4, $this->TEST_FILES_SHARING_API_USER3]);
		$this->assertEtagsChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2]);

		$this->assertAllUnchanged();
	}

	public function testOwnerWritesToShareWithReshare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		Filesystem::file_put_contents('/sub1/sub2/folder/inside/bar.txt', 'bar');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testOwnerRenameInShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER4]);
		Filesystem::rename('/sub1/sub2/folder/file.txt', '/sub1/sub2/folder/renamed.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3]);

		$this->assertAllUnchanged();
	}

	public function testOwnerRenameInReShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		Filesystem::rename('/sub1/sub2/folder/inside/file.txt', '/sub1/sub2/folder/inside/renamed.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testOwnerRenameIntoReShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		Filesystem::rename('/sub1/sub2/folder/file.txt', '/sub1/sub2/folder/inside/renamed.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testOwnerRenameOutOfReShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		Filesystem::rename('/sub1/sub2/folder/inside/file.txt', '/sub1/sub2/folder/renamed.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testOwnerDeleteInShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		Filesystem::unlink('/sub1/sub2/folder/file.txt');
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER4]);
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3]);

		$this->assertAllUnchanged();
	}

	public function testOwnerDeleteInReShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		Filesystem::unlink('/sub1/sub2/folder/inside/file.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testOwnerUnshares() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		$folderInfo = $this->rootView->getFileInfo('/' . $this->TEST_FILES_SHARING_API_USER1 . '/files/sub1/sub2/folder');
		$this->assertInstanceOf('\OC\Files\FileInfo', $folderInfo);

		$node = \OC::$server->getUserFolder($this->TEST_FILES_SHARING_API_USER1)->get('/sub1/sub2/folder');
		$shareManager = \OC::$server->getShareManager();
		$shares = $shareManager->getSharesBy($this->TEST_FILES_SHARING_API_USER1, IShare::TYPE_USER, $node, true);

		foreach ($shares as $share) {
			if ($share->getSharedWith() === $this->TEST_FILES_SHARING_API_USER2) {
				$shareManager->deleteShare($share);
			}
		}

		$this->assertEtagsForFoldersChanged([
			// direct recipient affected
			$this->TEST_FILES_SHARING_API_USER2,
		]);

		$this->assertAllUnchanged();
	}

	public function testOwnerUnsharesFlatReshares() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER1);
		$folderInfo = $this->rootView->getFileInfo('/' . $this->TEST_FILES_SHARING_API_USER1 . '/files/sub1/sub2/folder/inside');
		$this->assertInstanceOf('\OC\Files\FileInfo', $folderInfo);

		$node = \OC::$server->getUserFolder($this->TEST_FILES_SHARING_API_USER1)->get('/sub1/sub2/folder/inside');
		$shareManager = \OC::$server->getShareManager();
		$shares = $shareManager->getSharesBy($this->TEST_FILES_SHARING_API_USER1, IShare::TYPE_USER, $node, true);

		foreach ($shares as $share) {
			$shareManager->deleteShare($share);
		}

		$this->assertEtagsForFoldersChanged([
			// direct recipient affected
			$this->TEST_FILES_SHARING_API_USER4,
		]);

		$this->assertAllUnchanged();
	}

	public function testRecipientUnsharesFromSelf() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		$ls = $this->rootView->getDirectoryContent('/' . $this->TEST_FILES_SHARING_API_USER2 . '/files/sub1/sub2/');
		$this->assertTrue(
			$this->rootView->unlink('/' . $this->TEST_FILES_SHARING_API_USER2 . '/files/sub1/sub2/folder')
		);
		$this->assertEtagsForFoldersChanged([
			// direct recipient affected
			$this->TEST_FILES_SHARING_API_USER2,
		]);

		$this->assertAllUnchanged();
	}

	public function testRecipientWritesToShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		Filesystem::file_put_contents('/sub1/sub2/folder/asd.txt', 'bar');
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER4]);
		$this->assertEtagsForFoldersChanged([
			$this->TEST_FILES_SHARING_API_USER1,
			$this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3
		]);

		$this->assertAllUnchanged();
	}

	public function testRecipientWritesToReshare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		Filesystem::file_put_contents('/sub1/sub2/folder/inside/asd.txt', 'bar');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testRecipientWritesToOtherRecipientsReshare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER3);
		Filesystem::file_put_contents('/sub1/sub2/folder/inside/asd.txt', 'bar');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testRecipientRenameInShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		Filesystem::rename('/sub1/sub2/folder/file.txt', '/sub1/sub2/folder/renamed.txt');
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER4]);
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3]);

		$this->assertAllUnchanged();
	}

	public function testRecipientRenameInReShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		Filesystem::rename('/sub1/sub2/folder/inside/file.txt', '/sub1/sub2/folder/inside/renamed.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testRecipientRenameResharedFolder() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		Filesystem::rename('/directReshare', '/sub1/directReshare');
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);
		$this->assertEtagsChanged([$this->TEST_FILES_SHARING_API_USER2]);

		$this->assertEtagsChanged([$this->TEST_FILES_SHARING_API_USER2], 'sub1');

		$this->assertAllUnchanged();
	}

	public function testRecipientDeleteInShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		Filesystem::unlink('/sub1/sub2/folder/file.txt');
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER4]);
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3]);

		$this->assertAllUnchanged();
	}

	public function testRecipientDeleteInReShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		Filesystem::unlink('/sub1/sub2/folder/inside/file.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testReshareRecipientWritesToReshare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER4);
		Filesystem::file_put_contents('/sub1/sub2/inside/asd.txt', 'bar');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testReshareRecipientRenameInReShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER4);
		Filesystem::rename('/sub1/sub2/inside/file.txt', '/sub1/sub2/inside/renamed.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testReshareRecipientDeleteInReShare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER4);
		Filesystem::unlink('/sub1/sub2/inside/file.txt');
		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2,
			$this->TEST_FILES_SHARING_API_USER3, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testRecipientUploadInDirectReshare() {
		$this->loginAsUser($this->TEST_FILES_SHARING_API_USER2);
		Filesystem::file_put_contents('/directReshare/test.txt', 'sad');
		$this->assertEtagsNotChanged([$this->TEST_FILES_SHARING_API_USER3]);
		$this->assertEtagsChanged([$this->TEST_FILES_SHARING_API_USER1, $this->TEST_FILES_SHARING_API_USER2, $this->TEST_FILES_SHARING_API_USER4]);

		$this->assertAllUnchanged();
	}

	public function testEtagChangeOnPermissionsChange() {
		$userFolder = $this->rootFolder->getUserFolder($this->TEST_FILES_SHARING_API_USER1);
		$node = $userFolder->get('/sub1/sub2/folder');

		$shares = $this->shareManager->getSharesBy($this->TEST_FILES_SHARING_API_USER1, IShare::TYPE_USER, $node);
		/** @var \OCP\Share\IShare[] $shares */
		$shares = array_filter($shares, function (\OCP\Share\IShare $share) {
			return $share->getSharedWith() === $this->TEST_FILES_SHARING_API_USER2;
		});
		$this->assertCount(1, $shares);

		$share = $shares[0];
		$share->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_SHARE);
		$this->shareManager->updateShare($share);

		$this->assertEtagsForFoldersChanged([$this->TEST_FILES_SHARING_API_USER2]);

		$this->assertAllUnchanged();
	}
}
