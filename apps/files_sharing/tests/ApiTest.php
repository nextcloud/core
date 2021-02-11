<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Richard Steinmetz <richard@steinmetz.cloud>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

use OC\Files\Cache\Scanner;
use OCA\Files_Sharing\Controller\ShareAPIController;
use OCP\App\IAppManager;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\Share\IShare;
use OCP\UserStatus\IManager as IUserStatusManager;

/**
 * Class ApiTest
 *
 * @group DB
 * TODO: convert to real intergration tests
 */
class ApiTest extends TestCase {
	public const TEST_FOLDER_NAME = '/folder_share_api_test';
	public const APP_NAME = 'files_sharing';

	private static $tempStorage;

	/** @var \OCP\Files\Folder */
	private $userFolder;

	/** @var string */
	private $subsubfolder;

	protected function setUp(): void {
		parent::setUp();

		\OC::$server->getConfig()->setAppValue('core', 'shareapi_exclude_groups', 'no');
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_expire_after_n_days', '7');

		$this->folder = self::TEST_FOLDER_NAME;
		$this->subfolder = '/subfolder_share_api_test';
		$this->subsubfolder = '/subsubfolder_share_api_test';

		$this->filename = '/share-api-test.txt';

		// save file with content
		$this->view->file_put_contents($this->filename, $this->data);
		$this->view->mkdir($this->folder);
		$this->view->mkdir($this->folder . $this->subfolder);
		$this->view->mkdir($this->folder . $this->subfolder . $this->subsubfolder);
		$this->view->file_put_contents($this->folder.$this->filename, $this->data);
		$this->view->file_put_contents($this->folder . $this->subfolder . $this->filename, $this->data);
		$mount = $this->view->getMount($this->filename);
		$mount->getStorage()->getScanner()->scan('', Scanner::SCAN_RECURSIVE);

		$this->userFolder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER1);
	}

	protected function tearDown(): void {
		if ($this->view instanceof \OC\Files\View) {
			$this->view->unlink($this->filename);
			$this->view->deleteAll($this->folder);
		}

		self::$tempStorage = null;

		parent::tearDown();
	}

	/**
	 * @param string $userId The userId of the caller
	 * @return \OCA\Files_Sharing\Controller\ShareAPIController
	 */
	private function createOCS($userId) {
		$l = $this->getMockBuilder(IL10N::class)->getMock();
		$l->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
		$config = $this->createMock(IConfig::class);
		$appManager = $this->createMock(IAppManager::class);
		$serverContainer = $this->createMock(IServerContainer::class);
		$userStatusManager = $this->createMock(IUserStatusManager::class);
		$previewManager = $this->createMock(IPreview::class);

		return new ShareAPIController(
			self::APP_NAME,
			$this->getMockBuilder(IRequest::class)->getMock(),
			$this->shareManager,
			\OC::$server->getGroupManager(),
			\OC::$server->getUserManager(),
			\OC::$server->getRootFolder(),
			\OC::$server->getURLGenerator(),
			$userId,
			$l,
			$config,
			$appManager,
			$serverContainer,
			$userStatusManager,
			$previewManager
		);
	}

	public function testCreateShareUserFile() {
		$this->setUp(); // for some reasons phpunit refuses to do this for us only for this test
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->filename, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_USER, self::TEST_FILES_SHARING_API_USER2);
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals(19, $data['permissions']);
		$this->assertEmpty($data['expiration']);

		$this->shareManager->getShareById('ocinternal:'.$data['id']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);

		$ocs->cleanup();
	}

	public function testCreateShareUserFolder() {
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_USER, self::TEST_FILES_SHARING_API_USER2);
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals(31, $data['permissions']);
		$this->assertEmpty($data['expiration']);

		$this->shareManager->getShareById('ocinternal:'.$data['id']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);
		$ocs->cleanup();
	}


	public function testCreateShareGroupFile() {
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->filename, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_GROUP, self::TEST_FILES_SHARING_API_GROUP1);
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals(19, $data['permissions']);
		$this->assertEmpty($data['expiration']);

		$this->shareManager->getShareById('ocinternal:'.$data['id']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);
		$ocs->cleanup();
	}

	public function testCreateShareGroupFolder() {
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_GROUP, self::TEST_FILES_SHARING_API_GROUP1);
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals(31, $data['permissions']);
		$this->assertEmpty($data['expiration']);

		$this->shareManager->getShareById('ocinternal:'.$data['id']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);
		$ocs->cleanup();
	}

	public function testCreateShareLink() {
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK);
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals(\OCP\Constants::PERMISSION_READ |
			\OCP\Constants::PERMISSION_SHARE,
			$data['permissions']);
		$this->assertEmpty($data['expiration']);
		$this->assertTrue(is_string($data['token']));

		// check for correct link
		$url = \OC::$server->getURLGenerator()->getAbsoluteURL('/index.php/s/' . $data['token']);
		$this->assertEquals($url, $data['url']);

		$this->shareManager->getShareById('ocinternal:'.$data['id']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);
		$ocs->cleanup();
	}

	public function testCreateShareLinkPublicUpload() {
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK, null, 'true');
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals(
			\OCP\Constants::PERMISSION_READ |
			\OCP\Constants::PERMISSION_CREATE |
			\OCP\Constants::PERMISSION_UPDATE |
			\OCP\Constants::PERMISSION_DELETE |
			\OCP\Constants::PERMISSION_SHARE,
			$data['permissions']
		);
		$this->assertEmpty($data['expiration']);
		$this->assertTrue(is_string($data['token']));

		// check for correct link
		$url = \OC::$server->getURLGenerator()->getAbsoluteURL('/index.php/s/' . $data['token']);
		$this->assertEquals($url, $data['url']);

		$this->shareManager->getShareById('ocinternal:'.$data['id']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);
		$ocs->cleanup();
	}

	public function testEnforceLinkPassword() {
		$password = md5(time());
		$config = \OC::$server->getConfig();
		$config->setAppValue('core', 'shareapi_enforce_links_password', 'yes');

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		try {
			$ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK);
			$this->fail();
		} catch (OCSForbiddenException $e) {
		}
		$ocs->cleanup();

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		try {
			$ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK, null, 'false', '');
			$this->fail();
		} catch (OCSForbiddenException $e) {
		}
		$ocs->cleanup();

		// share with password should succeed
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK, null, 'false', $password);
		$ocs->cleanup();

		$data = $result->getData();

		// setting new password should succeed
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->updateShare($data['id'], null, $password);
		$ocs->cleanup();

		// removing password should fail
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		try {
			$ocs->updateShare($data['id']);
			$this->fail();
		} catch (OCSBadRequestException $e) {
		}
		$ocs->cleanup();

		// cleanup
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);
		$ocs->cleanup();

		$config->setAppValue('core', 'shareapi_enforce_links_password', 'no');
		$this->addToAssertionCount(1);
	}

	/**
	 * @medium
	 */
	public function testSharePermissions() {
		// sharing file to a user should work if shareapi_exclude_groups is set
		// to no
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_exclude_groups', 'no');

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->filename, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_USER, self::TEST_FILES_SHARING_API_USER2);
		$ocs->cleanup();

		$data = $result->getData();

		$this->shareManager->getShareById('ocinternal:'.$data['id']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);
		$ocs->cleanup();

		// exclude groups, but not the group the user belongs to. Sharing should still work
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_exclude_groups', 'yes');
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_exclude_groups_list', 'admin,group1,group2');

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->filename, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_USER, self::TEST_FILES_SHARING_API_USER2);
		$ocs->cleanup();

		$data = $result->getData();

		$this->shareManager->getShareById('ocinternal:' . $data['id']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($data['id']);
		$ocs->cleanup();

		// now we exclude the group the user belongs to ('group'), sharing should fail now
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_exclude_groups_list', 'admin,group');

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->createShare($this->filename, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_USER, self::TEST_FILES_SHARING_API_USER2);
		$ocs->cleanup();

		// cleanup
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_exclude_groups', 'no');
		\OC::$server->getConfig()->setAppValue('core', 'shareapi_exclude_groups_list', '');

		$this->addToAssertionCount(1);
	}


	/**
	 * @medium
	 */
	public function testGetAllShares() {
		$node = $this->userFolder->get($this->filename);

		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);

		$share = $this->shareManager->createShare($share);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares();
		$ocs->cleanup();

		$this->assertTrue(count($result->getData()) === 1);

		$this->shareManager->deleteShare($share);
	}

	public function testGetAllSharesWithMe() {
		$node1 = $this->userFolder->get($this->filename);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share1);
		$share1->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share1);

		$node2 = $this->userFolder->get($this->folder);
		$share2 = $this->shareManager->newShare();
		$share2->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share2 = $this->shareManager->createShare($share2);
		$share2->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share2);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$result = $ocs->getShares('true');
		$ocs->cleanup();

		$this->assertCount(2, $result->getData());

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
	}

	/**
	 * @medium
	 */
	public function testPublicLinkUrl() {
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK);
		$ocs->cleanup();

		$data = $result->getData();

		// check if we have a token
		$this->assertTrue(is_string($data['token']));
		$id = $data['id'];

		// check for correct link
		$url = \OC::$server->getURLGenerator()->getAbsoluteURL('/index.php/s/' . $data['token']);
		$this->assertEquals($url, $data['url']);

		// check for link in getall shares
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares();
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals($url, current($data)['url']);

		// check for path
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares();
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals($url, current($data)['url']);

		// check in share id
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShare($id);
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertEquals($url, current($data)['url']);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($id);
		$ocs->cleanup();
	}

	/**
	 * @medium
	 * @depends testCreateShareUserFile
	 * @depends testCreateShareLink
	 */
	public function testGetShareFromSource() {
		$node = $this->userFolder->get($this->filename);
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share);

		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share2 = $this->shareManager->createShare($share);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares();
		$ocs->cleanup();

		// test should return one share created from testCreateShare()
		$this->assertTrue(count($result->getData()) === 2);

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
	}

	/**
	 * @medium
	 * @depends testCreateShareUserFile
	 * @depends testCreateShareLink
	 */
	public function testGetShareFromSourceWithReshares() {
		$node = $this->userFolder->get($this->filename);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share1);

		$share2 = $this->shareManager->newShare();
		$share2->setNode($node)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER2)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER3)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share2 = $this->shareManager->createShare($share2);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares();
		$ocs->cleanup();

		// test should return one share
		$this->assertTrue(count($result->getData()) === 1);

		// now also ask for the reshares
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares('false', 'true', 'false', $this->filename);
		$ocs->cleanup();

		// now we should get two shares, the initial share and the reshare
		$this->assertCount(2, $result->getData());

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
	}

	/**
	 * @medium
	 * @depends testCreateShareUserFile
	 */
	public function testGetShareFromId() {
		$node = $this->userFolder->get($this->filename);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share1);

		// call getShare() with share ID
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShare($share1->getId());
		$ocs->cleanup();

		// test should return one share created from testCreateShare()
		$this->assertEquals(1, count($result->getData()));

		$this->shareManager->deleteShare($share1);
	}

	/**
	 * @medium
	 */
	public function testGetShareFromFolder() {
		$node1 = $this->userFolder->get($this->filename);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share1);

		$node2 = $this->userFolder->get($this->folder.'/'.$this->filename);
		$share2 = $this->shareManager->newShare();
		$share2->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share2 = $this->shareManager->createShare($share2);


		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares('false', 'false', 'true', $this->folder);
		$ocs->cleanup();

		// test should return one share within $this->folder
		$this->assertTrue(count($result->getData()) === 1);

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
	}

	public function testGetShareFromFolderWithFile() {
		$node1 = $this->userFolder->get($this->filename);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share1);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		try {
			$ocs->getShares('false', 'false', 'true', $this->filename);
			$this->fail();
		} catch (OCSBadRequestException $e) {
			$this->assertEquals('Not a directory', $e->getMessage());
		}
		$ocs->cleanup();

		$this->shareManager->deleteShare($share1);
	}

	/**
	 * share a folder, than reshare a file within the shared folder and check if we construct the correct path
	 * @medium
	 */
	public function testGetShareFromFolderReshares() {
		$node1 = $this->userFolder->get($this->folder);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share1 = $this->shareManager->createShare($share1);
		$share1->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share1);

		$node2 = $this->userFolder->get($this->folder.'/'.$this->filename);
		$share2 = $this->shareManager->newShare();
		$share2->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share2 = $this->shareManager->createShare($share2);
		$share2->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share2);

		$node3 = $this->userFolder->get($this->folder.'/'.$this->subfolder.'/'.$this->filename);
		$share3 = $this->shareManager->newShare();
		$share3->setNode($node3)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share3 = $this->shareManager->createShare($share3);
		$share3->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share3);

		$testValues = [
			['query' => $this->folder,
				'expectedResult' => $this->folder . $this->filename],
			['query' => $this->folder . $this->subfolder,
				'expectedResult' => $this->folder . $this->subfolder . $this->filename],
		];

		foreach ($testValues as $value) {
			$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
			$result = $ocs->getShares('false', 'false', 'true', $value['query']);
			$ocs->cleanup();

			// test should return one share within $this->folder
			$data = $result->getData();

			$this->assertEquals($value['expectedResult'], $data[0]['path']);
		}

		// cleanup
		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
		$this->shareManager->deleteShare($share3);
	}

	/**
	 * reshare a sub folder and check if we get the correct path
	 * @medium
	 */
	public function testGetShareFromSubFolderReShares() {
		$node1 = $this->userFolder->get($this->folder . $this->subfolder);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share1 = $this->shareManager->createShare($share1);
		$share1->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share1);

		$node2 = \OC::$server->getRootFolder()->getUserFolder(self::TEST_FILES_SHARING_API_USER2)->get($this->subfolder);
		$share2 = $this->shareManager->newShare();
		$share2->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share2 = $this->shareManager->createShare($share2);
		$share2->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share2);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$result = $ocs->getShares();
		$ocs->cleanup();

		// test should return one share within $this->folder
		$data = $result->getData();

		// we should get exactly one result
		$this->assertCount(1, $data);

		$this->assertEquals($this->subfolder, $data[0]['path']);

		$this->shareManager->deleteShare($share2);
		$this->shareManager->deleteShare($share1);
	}

	/**
	 * test re-re-share of folder if the path gets constructed correctly
	 * @medium
	 */
	public function XtestGetShareFromFolderReReShares() {
		$node1 = $this->userFolder->get($this->folder . $this->subfolder);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share1 = $this->shareManager->createShare($share1);

		$node2 = $this->userFolder->get($this->folder . $this->subfolder . $this->subsubfolder);
		$share2 = $this->shareManager->newShare();
		$share2->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER2)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER3)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share2 = $this->shareManager->createShare($share2);

		$share3 = $this->shareManager->newShare();
		$share3->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER3)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share3 = $this->shareManager->createShare($share3);

		/*
		 * Test as recipient
		 */
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER3);
		$result = $ocs->getShares();
		$ocs->cleanup();

		// test should return one share within $this->folder
		$data = $result->getData();

		// we should get exactly one result
		$this->assertCount(1, $data);
		$this->assertEquals($this->subsubfolder, $data[0]['path']);

		/*
		 * Test for first owner/initiator
		 */
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares();
		$ocs->cleanup();

		// test should return one share within $this->folder
		$data = $result->getData();

		// we should get exactly one result
		$this->assertCount(1, $data);
		$this->assertEquals($this->folder . $this->subfolder, $data[0]['path']);

		/*
		 * Test for second initiator
		 */
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$result = $ocs->getShares();
		$ocs->cleanup();

		// test should return one share within $this->folder
		$data = $result->getData();

		// we should get exactly one result
		$this->assertCount(1, $data);
		$this->assertEquals($this->subfolder . $this->subsubfolder, $data[0]['path']);

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
		$this->shareManager->deleteShare($share3);
	}

	/**
	 * test multiple shared folder if the path gets constructed correctly
	 * @medium
	 */
	public function testGetShareMultipleSharedFolder() {
		$this->setUp();
		$node1 = $this->userFolder->get($this->folder . $this->subfolder);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share1 = $this->shareManager->createShare($share1);
		$share1->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share1);

		$node2 = $this->userFolder->get($this->folder);
		$share2 = $this->shareManager->newShare();
		$share2->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share2 = $this->shareManager->createShare($share2);
		$share2->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share2);

		$share3 = $this->shareManager->newShare();
		$share3->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share3 = $this->shareManager->createShare($share3);
		$share3->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share3);

		// $request = $this->createRequest(['path' => $this->subfolder]);
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$result1 = $ocs->getShares('false','false','false', $this->subfolder);
		$ocs->cleanup();

		// test should return one share within $this->folder
		$data1 = $result1->getData();
		$this->assertCount(1, $data1);
		$s1 = reset($data1);

		//$request = $this->createRequest(['path' => $this->folder.$this->subfolder]);
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$result2 = $ocs->getShares('false', 'false', 'false', $this->folder . $this->subfolder);
		$ocs->cleanup();

		// test should return one share within $this->folder
		$data2 = $result2->getData();
		$this->assertCount(1, $data2);
		$s2 = reset($data2);

		$this->assertEquals($this->subfolder, $s1['path']);
		$this->assertEquals($this->folder.$this->subfolder, $s2['path']);

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
		$this->shareManager->deleteShare($share3);
	}

	/**
	 * test re-re-share of folder if the path gets constructed correctly
	 * @medium
	 */
	public function testGetShareFromFileReReShares() {
		$node1 = $this->userFolder->get($this->folder . $this->subfolder);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share1 = $this->shareManager->createShare($share1);
		$share1->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share1);

		$user2Folder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER2);
		$node2 = $user2Folder->get($this->subfolder . $this->filename);
		$share2 = $this->shareManager->newShare();
		$share2->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER2)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER3)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share2 = $this->shareManager->createShare($share2);
		$share2->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share2);

		$user3Folder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER3);
		$node3 = $user3Folder->get($this->filename);
		$share3 = $this->shareManager->newShare();
		$share3->setNode($node3)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER3)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share3 = $this->shareManager->createShare($share3);
		$share3->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share3);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER3);
		$result = $ocs->getShares();
		$ocs->cleanup();

		// test should return one share within $this->folder
		$data = $result->getData();

		// we should get exactly one result
		$this->assertCount(1, $data);

		$this->assertEquals($this->filename, $data[0]['path']);

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
		$this->shareManager->deleteShare($share3);
	}

	/**
	 * @medium
	 */
	public function testGetShareFromUnknownId() {
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER3);
		try {
			$ocs->getShare(0);
			$this->fail();
		} catch (OCSNotFoundException $e) {
			$this->assertEquals('Wrong share ID, share doesn\'t exist', $e->getMessage());
		}
		$ocs->cleanup();
	}

	/**
	 * @medium
	 * @depends testCreateShareUserFile
	 * @depends testCreateShareLink
	 */
	public function testUpdateShare() {
		$password = md5(time());

		$node1 = $this->userFolder->get($this->filename);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share1);

		$share2 = $this->shareManager->newShare();
		$share2->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share2 = $this->shareManager->createShare($share2);

		// update permissions
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->updateShare($share1->getId(), 1);
		$ocs->cleanup();

		$share1 = $this->shareManager->getShareById('ocinternal:' . $share1->getId());
		$this->assertEquals(1, $share1->getPermissions());

		// update password for link share
		$this->assertNull($share2->getPassword());

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->updateShare($share2->getId(), null, $password);
		$ocs->cleanup();

		$share2 = $this->shareManager->getShareById('ocinternal:' . $share2->getId());
		$this->assertNotNull($share2->getPassword());

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->updateShare($share2->getId(), null, '');
		$ocs->cleanup();

		$share2 = $this->shareManager->getShareById('ocinternal:' . $share2->getId());
		$this->assertNull($share2->getPassword());

		$this->shareManager->deleteShare($share1);
		$this->shareManager->deleteShare($share2);
	}

	/**
	 * @medium
	 */
	public function testUpdateShareUpload() {
		$node1 = $this->userFolder->get($this->folder);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share1 = $this->shareManager->createShare($share1);

		// update public upload
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->updateShare($share1->getId(), null, null, null, 'true');
		$ocs->cleanup();

		$share1 = $this->shareManager->getShareById($share1->getFullId());
		$this->assertEquals(
			\OCP\Constants::PERMISSION_READ |
			\OCP\Constants::PERMISSION_CREATE |
			\OCP\Constants::PERMISSION_UPDATE |
			\OCP\Constants::PERMISSION_DELETE |
			\OCP\Constants::PERMISSION_SHARE,
			$share1->getPermissions()
		);

		// cleanup
		$this->shareManager->deleteShare($share1);
	}

	/**
	 * @medium
	 */
	public function testUpdateShareExpireDate() {
		$node1 = $this->userFolder->get($this->folder);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share1 = $this->shareManager->createShare($share1);
		$share1->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share1);

		$config = \OC::$server->getConfig();

		// enforce expire date, by default 7 days after the file was shared
		$config->setAppValue('core', 'shareapi_default_expire_date', 'yes');
		$config->setAppValue('core', 'shareapi_enforce_expire_date', 'yes');

		$dateWithinRange = new \DateTime();
		$dateWithinRange->setTime(0,0,0);
		$dateWithinRange->add(new \DateInterval('P5D'));
		$dateOutOfRange = new \DateTime();
		$dateOutOfRange->setTime(0,0,0);
		$dateOutOfRange->add(new \DateInterval('P8D'));

		// update expire date to a valid value
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->updateShare($share1->getId(), null, null, null, null, $dateWithinRange->format('Y-m-d'));
		$ocs->cleanup();

		$share1 = $this->shareManager->getShareById($share1->getFullId());

		// date should be changed
		$this->assertEquals($dateWithinRange, $share1->getExpirationDate());

		// update expire date to a value out of range
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		try {
			$ocs->updateShare($share1->getId());
			$this->fail();
		} catch (OCSBadRequestException $e) {
		}
		$ocs->cleanup();

		$share1 = $this->shareManager->getShareById($share1->getFullId());

		// date shouldn't be changed
		$this->assertEquals($dateWithinRange, $share1->getExpirationDate());

		// Try to remove expire date
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		try {
			$ocs->updateShare($share1->getId());
			$this->fail();
		} catch (OCSBadRequestException $e) {
		}
		$ocs->cleanup();

		$share1 = $this->shareManager->getShareById($share1->getFullId());


		// date shouldn't be changed
		$this->assertEquals($dateWithinRange, $share1->getExpirationDate());
		// cleanup
		$config->setAppValue('core', 'shareapi_default_expire_date', 'no');
		$config->setAppValue('core', 'shareapi_enforce_expire_date', 'no');
		$this->shareManager->deleteShare($share1);
	}

	/**
	 * @medium
	 * @depends testCreateShareUserFile
	 */
	public function testDeleteShare() {
		$node1 = $this->userFolder->get($this->filename);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share1);

		$share2 = $this->shareManager->newShare();
		$share2->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share2 = $this->shareManager->createShare($share1);

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($share1->getId());
		$ocs->cleanup();

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($share2->getId());
		$ocs->cleanup();

		$this->assertEmpty($this->shareManager->getSharesBy(self::TEST_FILES_SHARING_API_USER2, IShare::TYPE_USER));
		$this->assertEmpty($this->shareManager->getSharesBy(self::TEST_FILES_SHARING_API_USER2, IShare::TYPE_LINK));
	}

	/**
	 * test unshare of a reshared file
	 */
	public function testDeleteReshare() {
		$node1 = $this->userFolder->get($this->folder);
		$share1 = $this->shareManager->newShare();
		$share1->setNode($node1)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(31);
		$share1 = $this->shareManager->createShare($share1);
		$share1->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share1);

		$user2folder = \OC::$server->getUserFolder(self::TEST_FILES_SHARING_API_USER2);
		$node2 = $user2folder->get($this->folder.'/'.$this->filename);
		$share2 = $this->shareManager->newShare();
		$share2->setNode($node2)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER2)
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(1);
		$share2 = $this->shareManager->createShare($share2);
		$share2->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share2);

		// test if we can unshare the link again
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$ocs->deleteShare($share2->getId());
		$ocs->cleanup();

		$this->shareManager->deleteShare($share1);
		$this->addToAssertionCount(1);
	}

	/**
	 * share a folder which contains a share mount point, should be forbidden
	 */
	public function testShareFolderWithAMountPoint() {
		// user 1 shares a folder with user2
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);

		// user2 shares a file from the folder as link
		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		$view = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');
		$view->mkdir("localDir");

		// move mount point to the folder "localDir"
		$result = $view->rename($this->folder, 'localDir/'.$this->folder);
		$this->assertTrue($result !== false);

		// try to share "localDir"
		$fileInfo2 = $view->getFileInfo('localDir');

		$this->assertTrue($fileInfo2 instanceof \OC\Files\FileInfo);

		$pass = true;
		try {
			$this->share(
				IShare::TYPE_USER,
				'localDir',
				self::TEST_FILES_SHARING_API_USER2,
				self::TEST_FILES_SHARING_API_USER3,
				\OCP\Constants::PERMISSION_ALL
			);
		} catch (\Exception $e) {
			$pass = false;
		}

		$this->assertFalse($pass);

		//cleanup

		$result = $view->rename('localDir/' . $this->folder, $this->folder);
		$this->assertTrue($result !== false);
		$view->unlink('localDir');

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$this->shareManager->deleteShare($share);
	}

	/**
	 * Post init mount points hook for mounting simulated ext storage
	 */
	public static function initTestMountPointsHook($data) {
		if ($data['user'] === self::TEST_FILES_SHARING_API_USER1) {
			\OC\Files\Filesystem::mount(self::$tempStorage, [], '/' . self::TEST_FILES_SHARING_API_USER1 . '/files' . self::TEST_FOLDER_NAME);
		}
	}

	/**
	 * Tests mounting a folder that is an external storage mount point.
	 */
	public function testShareStorageMountPoint() {
		$tempStorage = new \OC\Files\Storage\Temporary([]);
		$tempStorage->file_put_contents('test.txt', 'abcdef');
		$tempStorage->getScanner()->scan('');

		$this->registerMount(self::TEST_FILES_SHARING_API_USER1, $tempStorage, self::TEST_FILES_SHARING_API_USER1 . '/files' . self::TEST_FOLDER_NAME);

		// logging in will auto-mount the temp storage for user1 as well
		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		// user 1 shares the mount point folder with user2
		$share = $this->share(
			IShare::TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);
		$share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share);

		// user2: check that mount point name appears correctly
		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		$view = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2 . '/files');

		$this->assertTrue($view->file_exists($this->folder));
		$this->assertTrue($view->file_exists($this->folder . '/test.txt'));

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		$this->shareManager->deleteShare($share);

		\OC_Hook::clear('OC_Filesystem', 'post_initMountPoints');
		\OC_Hook::clear('\OCA\Files_Sharing\Tests\ApiTest', 'initTestMountPointsHook');
	}

	public function datesProvider() {
		$date = new \DateTime();
		$date->add(new \DateInterval('P5D'));

		return [
			[$date->format('Y-m-d'), true],
			['abc', false],
			[$date->format('Y-m-d') . 'xyz', false],
		];
	}

	/**
	 * Make sure only ISO 8601 dates are accepted
	 *
	 * @dataProvider datesProvider
	 */
	public function testPublicLinkExpireDate($date, $valid) {
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);

		try {
			$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK, null, 'false', '', null, $date);
			$this->assertTrue($valid);
		} catch (OCSNotFoundException $e) {
			$this->assertFalse($valid);
			$this->assertEquals('Invalid date, date format must be YYYY-MM-DD', $e->getMessage());
			$ocs->cleanup();
			return;
		}
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertTrue(is_string($data['token']));
		$this->assertEquals($date, substr($data['expiration'], 0, 10));

		// check for correct link
		$url = \OC::$server->getURLGenerator()->getAbsoluteURL('/index.php/s/' . $data['token']);
		$this->assertEquals($url, $data['url']);

		$share = $this->shareManager->getShareById('ocinternal:'.$data['id']);

		$this->assertEquals($date, $share->getExpirationDate()->format('Y-m-d'));

		$this->shareManager->deleteShare($share);
	}

	public function testCreatePublicLinkExpireDateValid() {
		$config = \OC::$server->getConfig();

		// enforce expire date, by default 7 days after the file was shared
		$config->setAppValue('core', 'shareapi_default_expire_date', 'yes');
		$config->setAppValue('core', 'shareapi_enforce_expire_date', 'yes');

		$date = new \DateTime();
		$date->add(new \DateInterval('P5D'));

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->filename, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK, null, 'false', '', null, $date->format('Y-m-d'));
		$ocs->cleanup();

		$data = $result->getData();
		$this->assertTrue(is_string($data['token']));
		$this->assertEquals($date->format('Y-m-d') . ' 00:00:00', $data['expiration']);

		// check for correct link
		$url = \OC::$server->getURLGenerator()->getAbsoluteURL('/index.php/s/' . $data['token']);
		$this->assertEquals($url, $data['url']);

		$share = $this->shareManager->getShareById('ocinternal:'.$data['id']);
		$date->setTime(0,0,0);
		$this->assertEquals($date, $share->getExpirationDate());

		$this->shareManager->deleteShare($share);

		$config->setAppValue('core', 'shareapi_default_expire_date', 'no');
		$config->setAppValue('core', 'shareapi_enforce_expire_date', 'no');
	}

	public function testCreatePublicLinkExpireDateInvalidFuture() {
		$config = \OC::$server->getConfig();

		// enforce expire date, by default 7 days after the file was shared
		$config->setAppValue('core', 'shareapi_default_expire_date', 'yes');
		$config->setAppValue('core', 'shareapi_enforce_expire_date', 'yes');

		$date = new \DateTime();
		$date->add(new \DateInterval('P8D'));

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);

		try {
			$ocs->createShare($this->filename, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK, null, 'false', '', null, $date->format('Y-m-d'));
			$this->fail();
		} catch (OCSException $e) {
			$this->assertEquals(404, $e->getCode());
			$this->assertEquals('Can’t set expiration date more than 7 days in the future', $e->getMessage());
		}
		$ocs->cleanup();

		$config->setAppValue('core', 'shareapi_default_expire_date', 'no');
		$config->setAppValue('core', 'shareapi_enforce_expire_date', 'no');
	}

	public function XtestCreatePublicLinkExpireDateInvalidPast() {
		$config = \OC::$server->getConfig();

		$date = new \DateTime();
		$date->sub(new \DateInterval('P8D'));

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);

		try {
			$ocs->createShare($this->filename, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK, null, 'false', '', null, $date->format('Y-m-d'));
			$this->fail();
		} catch (OCSException $e) {
			$this->assertEquals(404, $e->getCode());
			$this->assertEquals('Expiration date is in the past', $e->getMessage());
		}
		$ocs->cleanup();

		$config->setAppValue('core', 'shareapi_default_expire_date', 'no');
		$config->setAppValue('core', 'shareapi_enforce_expire_date', 'no');
	}

	/**
	 * test for no invisible shares
	 * See: https://github.com/owncloud/core/issues/22295
	 */
	public function testInvisibleSharesUser() {
		// simulate a post request
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_USER, self::TEST_FILES_SHARING_API_USER2);
		$ocs->cleanup();
		$data = $result->getData();

		$topId = $data['id'];

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$ocs->acceptShare($topId);
		$ocs->cleanup();

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK);
		$ocs->cleanup();

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($topId);
		$ocs->cleanup();

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares();
		$ocs->cleanup();

		$this->assertEmpty($result->getData());
	}

	/**
	 * test for no invisible shares
	 * See: https://github.com/owncloud/core/issues/22295
	 */
	public function testInvisibleSharesGroup() {
		// simulate a post request
		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_GROUP, self::TEST_FILES_SHARING_API_GROUP1);
		$ocs->cleanup();
		$data = $result->getData();

		$topId = $data['id'];

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$ocs->acceptShare($topId);
		$ocs->cleanup();

		\OC_Util::tearDownFS();

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER2);
		$ocs->createShare($this->folder, \OCP\Constants::PERMISSION_ALL, IShare::TYPE_LINK);
		$ocs->cleanup();

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$ocs->deleteShare($topId);
		$ocs->cleanup();

		$ocs = $this->createOCS(self::TEST_FILES_SHARING_API_USER1);
		$result = $ocs->getShares();
		$ocs->cleanup();

		$this->assertEmpty($result->getData());
	}
}
