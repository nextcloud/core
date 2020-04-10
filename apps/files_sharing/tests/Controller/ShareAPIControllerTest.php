<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Maxence Lange <maxence@nextcloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files_Sharing\Tests\Controller;

use OCA\Files_Sharing\Controller\ShareAPIController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\Storage;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use OCP\Share;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Test\TestCase;

/**
 * Class ShareAPIControllerTest
 *
 * @package OCA\Files_Sharing\Tests\Controller
 * @group DB
 */
class ShareAPIControllerTest extends TestCase {

	/** @var string */
	private $appName = 'files_sharing';

	/** @var \OC\Share20\Manager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;

	/** @var string|\PHPUnit_Framework_MockObject_MockObject */
	private $currentUser;

	/** @var ShareAPIController */
	private $ocs;

	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l;

	/** @var  IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var IAppManager|\PHPUnit_Framework_MockObject_MockObject */
	private $appManager;

	/** @var IServerContainer|\PHPUnit_Framework_MockObject_MockObject */
	private $serverContainer;

	protected function setUp(): void {
		$this->shareManager = $this->createMock(IManager::class);
		$this->shareManager
			->expects($this->any())
			->method('shareApiEnabled')
			->willReturn(true);
		$this->shareManager
			->expects($this->any())
		->method('shareProviderExists')->willReturn(true);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->request = $this->createMock(IRequest::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->currentUser = 'currentUser';

		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
		$this->config = $this->createMock(IConfig::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->serverContainer = $this->createMock(IServerContainer::class);

		$this->ocs = new ShareAPIController(
			$this->appName,
			$this->request,
			$this->shareManager,
			$this->groupManager,
			$this->userManager,
			$this->rootFolder,
			$this->urlGenerator,
			$this->currentUser,
			$this->l,
			$this->config,
			$this->appManager,
			$this->serverContainer
		);
	}

	/**
	 * @return ShareAPIController|\PHPUnit_Framework_MockObject_MockObject
	 */
	private function mockFormatShare() {
		return $this->getMockBuilder(ShareAPIController::class)
			->setConstructorArgs([
				$this->appName,
				$this->request,
				$this->shareManager,
				$this->groupManager,
				$this->userManager,
				$this->rootFolder,
				$this->urlGenerator,
				$this->currentUser,
				$this->l,
				$this->config,
				$this->appManager,
				$this->serverContainer
			])->setMethods(['formatShare'])
			->getMock();
	}

	private function newShare() {
		return \OC::$server->getShareManager()->newShare();
	}


	public function testDeleteShareShareNotFound() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Wrong share ID, share doesn\'t exist');

		$this->shareManager
			->expects($this->exactly(5))
			->method('getShareById')
			->willReturnCallback(function ($id) {
				if ($id === 'ocinternal:42' || $id === 'ocRoomShare:42' || $id === 'ocFederatedSharing:42' || $id === 'ocCircleShare:42' || $id === 'ocMailShare:42') {
					throw new \OCP\Share\Exceptions\ShareNotFound();
				} else {
					throw new \Exception();
				}
			});

		$this->shareManager->method('outgoingServer2ServerSharesAllowed')->willReturn(true);

		$this->ocs->deleteShare(42);
	}

	public function testDeleteShare() {
		$node = $this->getMockBuilder(File::class)->getMock();

		$share = $this->newShare();
		$share->setSharedBy($this->currentUser)
			->setNode($node);
		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42')
			->willReturn($share);
		$this->shareManager
			->expects($this->once())
			->method('deleteShare')
			->with($share);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$expected = new DataResponse();
		$result = $this->ocs->deleteShare(42);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}


	public function testDeleteShareLocked() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Could not delete share');

		$node = $this->getMockBuilder(File::class)->getMock();

		$share = $this->newShare();
		$share->setNode($node);

		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42')
			->willReturn($share);

		$this->shareManager
			->expects($this->never())
			->method('deleteShare')
			->with($share);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED)
			->will($this->throwException(new LockedException('mypath')));

		$this->assertFalse($this->invokePrivate($this->ocs, 'canDeleteFromSelf', [$share]));
		$this->assertFalse($this->invokePrivate($this->ocs, 'canDeleteShare', [$share]));

		$this->ocs->deleteShare(42);
	}

	/**
	 * You can always remove a share that was shared with you
	 */
	public function testDeleteShareWithMe() {
		$node = $this->getMockBuilder(File::class)->getMock();

		$share = $this->newShare();
		$share->setSharedWith($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setNode($node);

		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42')
			->willReturn($share);

		$this->shareManager
			->expects($this->once())
			->method('deleteShare')
			->with($share);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->assertFalse($this->invokePrivate($this->ocs, 'canDeleteFromSelf', [$share]));
		$this->assertTrue($this->invokePrivate($this->ocs, 'canDeleteShare', [$share]));

		$this->ocs->deleteShare(42);
	}

	/**
	 * You can always delete a share you own
	 */
	public function testDeleteShareOwner() {
		$node = $this->getMockBuilder(File::class)->getMock();

		$share = $this->newShare();
		$share->setSharedBy($this->currentUser)
			->setNode($node);

		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42')
			->willReturn($share);

		$this->shareManager
			->expects($this->once())
			->method('deleteShare')
			->with($share);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->assertFalse($this->invokePrivate($this->ocs, 'canDeleteFromSelf', [$share]));
		$this->assertTrue($this->invokePrivate($this->ocs, 'canDeleteShare', [$share]));

		$this->ocs->deleteShare(42);
	}

	/**
	 * You can always delete a share when you own
	 * the file path it belong to
	 */
	public function testDeleteShareFileOwner() {
		$node = $this->getMockBuilder(File::class)->getMock();

		$share = $this->newShare();
		$share->setShareOwner($this->currentUser)
			->setNode($node);

		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42')
			->willReturn($share);

		$this->shareManager
			->expects($this->once())
			->method('deleteShare')
			->with($share);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->assertFalse($this->invokePrivate($this->ocs, 'canDeleteFromSelf', [$share]));
		$this->assertTrue($this->invokePrivate($this->ocs, 'canDeleteShare', [$share]));

		$this->ocs->deleteShare(42);
	}

	/**
	 * You can remove (the mountpoint, not the share)
	 * a share if you're in the group the share is shared with
	 */
	public function testDeleteSharedWithMyGroup() {
		$node = $this->getMockBuilder(File::class)->getMock();

		$share = $this->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
			->setSharedWith('group')
			->setNode($node);

		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42')
			->willReturn($share);

		// canDeleteShareFromSelf
		$user = $this->createMock(IUser::class);
		$group = $this->getMockBuilder('OCP\IGroup')->getMock();
		$this->groupManager
			->method('get')
			->with('group')
			->willReturn($group);
		$this->userManager
			->method('get')
			->with($this->currentUser)
			->willReturn($user);
		$group->method('inGroup')
			->with($user)
			->willReturn(true);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$userFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with($share->getNodeId())
			->willReturn([$share->getNode()]);

		$this->shareManager->expects($this->once())
			->method('deleteFromSelf')
			->with($share, $this->currentUser);

		$this->shareManager->expects($this->never())
			->method('deleteShare');

		$this->assertTrue($this->invokePrivate($this->ocs, 'canDeleteShareFromSelf', [$share]));
		$this->assertFalse($this->invokePrivate($this->ocs, 'canDeleteShare', [$share]));

		$this->ocs->deleteShare(42);
	}

	/**
	 * You cannot remove a share if you're not
	 * in the group the share is shared with
	 */
	public function testDeleteSharedWithGroupIDontBelongTo() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Wrong share ID, share doesn\'t exist');

		$node = $this->getMockBuilder(File::class)->getMock();

		$share = $this->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
			->setSharedWith('group')
			->setNode($node);

		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42')
			->willReturn($share);

		// canDeleteShareFromSelf
		$user = $this->createMock(IUser::class);
		$group = $this->getMockBuilder('OCP\IGroup')->getMock();
		$this->groupManager
			->method('get')
			->with('group')
			->willReturn($group);
		$this->userManager
			->method('get')
			->with($this->currentUser)
			->willReturn($user);
		$group->method('inGroup')
			->with($user)
			->willReturn(false);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$userFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with($share->getNodeId())
			->willReturn([$share->getNode()]);

		$this->shareManager->expects($this->never())
			->method('deleteFromSelf');

		$this->shareManager->expects($this->never())
			->method('deleteShare');

		$this->assertFalse($this->invokePrivate($this->ocs, 'canDeleteShareFromSelf', [$share]));
		$this->assertFalse($this->invokePrivate($this->ocs, 'canDeleteShare', [$share]));

		$this->ocs->deleteShare(42);
	}

	/*
	 * FIXME: Enable once we have a federated Share Provider

	public function testGetGetShareNotExists() {
		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42', 'currentUser')
			->will($this->throwException(new \OC\Share20\Exception\ShareNotFound()));

		$expected = new \OC\OCS\Result(null, 404, 'wrong share ID, share doesn\'t exist.');
		$this->assertEquals($expected, $this->ocs->getShare(42));
	}
	*/

	public function createShare($id, $shareType, $sharedWith, $sharedBy, $shareOwner, $path, $permissions,
								$shareTime, $expiration, $parent, $target, $mail_send, $note = '', $token=null,
								$password=null, $label = '') {
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->method('getId')->willReturn($id);
		$share->method('getShareType')->willReturn($shareType);
		$share->method('getSharedWith')->willReturn($sharedWith);
		$share->method('getSharedBy')->willReturn($sharedBy);
		$share->method('getShareOwner')->willReturn($shareOwner);
		$share->method('getNode')->willReturn($path);
		$share->method('getPermissions')->willReturn($permissions);
		$share->method('getNote')->willReturn($note);
		$share->method('getLabel')->willReturn($label);
		$time = new \DateTime();
		$time->setTimestamp($shareTime);
		$share->method('getShareTime')->willReturn($time);
		$share->method('getExpirationDate')->willReturn($expiration);
		$share->method('getTarget')->willReturn($target);
		$share->method('getMailSend')->willReturn($mail_send);
		$share->method('getToken')->willReturn($token);
		$share->method('getPassword')->willReturn($password);

		if ($shareType === \OCP\Share::SHARE_TYPE_USER  ||
			$shareType === \OCP\Share::SHARE_TYPE_GROUP ||
			$shareType === \OCP\Share::SHARE_TYPE_LINK) {
			$share->method('getFullId')->willReturn('ocinternal:'.$id);
		}

		return $share;
	}

	public function dataGetShare() {
		$data = [];

		$cache = $this->getMockBuilder('OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->method('getNumericStorageId')->willReturn(101);

		$storage = $this->getMockBuilder(Storage::class)
			->disableOriginalConstructor()
			->getMock();
		$storage->method('getId')->willReturn('STORAGE');
		$storage->method('getCache')->willReturn($cache);

		$parentFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$parentFolder->method('getId')->willReturn(3);

		$file = $this->getMockBuilder('OCP\Files\File')->getMock();
		$file->method('getId')->willReturn(1);
		$file->method('getPath')->willReturn('file');
		$file->method('getStorage')->willReturn($storage);
		$file->method('getParent')->willReturn($parentFolder);
		$file->method('getMimeType')->willReturn('myMimeType');

		$folder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$folder->method('getId')->willReturn(2);
		$folder->method('getPath')->willReturn('folder');
		$folder->method('getStorage')->willReturn($storage);
		$folder->method('getParent')->willReturn($parentFolder);
		$folder->method('getMimeType')->willReturn('myFolderMimeType');

		// File shared with user
		$share = $this->createShare(
			100,
			\OCP\Share::SHARE_TYPE_USER,
			'userId',
			'initiatorId',
			'ownerId',
			$file,
			4,
			5,
			null,
			6,
			'target',
			0,
			'personal note'
		);
		$expected = [
			'id' => 100,
			'share_type' => \OCP\Share::SHARE_TYPE_USER,
			'share_with' => 'userId',
			'share_with_displayname' => 'userDisplay',
			'uid_owner' => 'initiatorId',
			'displayname_owner' => 'initiatorDisplay',
			'item_type' => 'file',
			'item_source' => 1,
			'file_source' => 1,
			'file_target' => 'target',
			'file_parent' => 3,
			'token' => null,
			'expiration' => null,
			'permissions' => 4,
			'stime' => 5,
			'parent' => null,
			'storage_id' => 'STORAGE',
			'path' => 'file',
			'storage' => 101,
			'mail_send' => 0,
			'uid_file_owner' => 'ownerId',
			'note' => 'personal note',
			'label' => '',
			'displayname_file_owner' => 'ownerDisplay',
			'mimetype' => 'myMimeType',
			'hide_download' => 0,
			'can_edit' => false,
			'can_delete' => false,
		];
		$data[] = [$share, $expected];

		// Folder shared with group
		$share = $this->createShare(
			101,
			\OCP\Share::SHARE_TYPE_GROUP,
			'groupId',
			'initiatorId',
			'ownerId',
			$folder,
			4,
			5,
			null,
			6,
			'target',
			0,
			'personal note'
		);
		$expected = [
			'id' => 101,
			'share_type' => \OCP\Share::SHARE_TYPE_GROUP,
			'share_with' => 'groupId',
			'share_with_displayname' => 'groupId',
			'uid_owner' => 'initiatorId',
			'displayname_owner' => 'initiatorDisplay',
			'item_type' => 'folder',
			'item_source' => 2,
			'file_source' => 2,
			'file_target' => 'target',
			'file_parent' => 3,
			'token' => null,
			'expiration' => null,
			'permissions' => 4,
			'stime' => 5,
			'parent' => null,
			'storage_id' => 'STORAGE',
			'path' => 'folder',
			'storage' => 101,
			'mail_send' => 0,
			'uid_file_owner' => 'ownerId',
			'note' => 'personal note',
			'label' => '',
			'displayname_file_owner' => 'ownerDisplay',
			'mimetype' => 'myFolderMimeType',
			'hide_download' => 0,
			'can_edit' => false,
			'can_delete' => false,
		];
		$data[] = [$share, $expected];

		// File shared by link with Expire
		$expire = \DateTime::createFromFormat('Y-m-d h:i:s', '2000-01-02 01:02:03');
		$share = $this->createShare(
			101,
			\OCP\Share::SHARE_TYPE_LINK,
			null,
			'initiatorId',
			'ownerId',
			$folder,
			4,
			5,
			$expire,
			6,
			'target',
			0,
			'personal note',
			'token',
			'password',
			'first link share'
		);
		$expected = [
			'id' => 101,
			'share_type' => \OCP\Share::SHARE_TYPE_LINK,
			'password' => 'password',
			'share_with' => 'password',
			'share_with_displayname' => '(Shared link)',
			'send_password_by_talk' => false,
			'uid_owner' => 'initiatorId',
			'displayname_owner' => 'initiatorDisplay',
			'item_type' => 'folder',
			'item_source' => 2,
			'file_source' => 2,
			'file_target' => 'target',
			'file_parent' => 3,
			'token' => 'token',
			'expiration' => '2000-01-02 00:00:00',
			'permissions' => 4,
			'stime' => 5,
			'parent' => null,
			'storage_id' => 'STORAGE',
			'path' => 'folder',
			'storage' => 101,
			'mail_send' => 0,
			'url' => 'url',
			'uid_file_owner' => 'ownerId',
			'note' => 'personal note',
			'label' => 'first link share',
			'displayname_file_owner' => 'ownerDisplay',
			'mimetype' => 'myFolderMimeType',
			'hide_download' => 0,
			'can_edit' => false,
			'can_delete' => false,
		];
		$data[] = [$share, $expected];

		return $data;
	}

	/**
	 * @dataProvider dataGetShare
	 */
	public function testGetShare(\OCP\Share\IShare $share, array $result) {
		/** @var ShareAPIController|\PHPUnit_Framework_MockObject_MockObject $ocs */
		$ocs = $this->getMockBuilder(ShareAPIController::class)
				->setConstructorArgs([
					$this->appName,
					$this->request,
					$this->shareManager,
					$this->groupManager,
					$this->userManager,
					$this->rootFolder,
					$this->urlGenerator,
					$this->currentUser,
					$this->l,
					$this->config,
					$this->appManager,
					$this->serverContainer
				])->setMethods(['canAccessShare'])
				->getMock();

		$ocs->expects($this->any())
			->method('canAccessShare')
			->willReturn(true);

		$this->shareManager
			->expects($this->any())
			->method('getShareById')
			->with($share->getFullId(), 'currentUser')
			->willReturn($share);

		$userFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$userFolder
			->method('getRelativePath')
			->willReturnArgument(0);

		$userFolder->method('getById')
			->with($share->getNodeId())
			->willReturn([$share->getNode()]);

		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$this->urlGenerator
			->method('linkToRouteAbsolute')
			->willReturn('url');

		$initiator = $this->getMockBuilder(IUser::class)->getMock();
		$initiator->method('getUID')->willReturn('initiatorId');
		$initiator->method('getDisplayName')->willReturn('initiatorDisplay');

		$owner = $this->getMockBuilder(IUser::class)->getMock();
		$owner->method('getUID')->willReturn('ownerId');
		$owner->method('getDisplayName')->willReturn('ownerDisplay');

		$user = $this->getMockBuilder(IUser::class)->getMock();
		$user->method('getUID')->willReturn('userId');
		$user->method('getDisplayName')->willReturn('userDisplay');

		$group = $this->getMockBuilder('OCP\IGroup')->getMock();
		$group->method('getGID')->willReturn('groupId');

		$this->userManager->method('get')->willReturnMap([
			['userId', $user],
			['initiatorId', $initiator],
			['ownerId', $owner],
		]);
		$this->groupManager->method('get')->willReturnMap([
			['group', $group],
		]);

		$d = $ocs->getShare($share->getId())->getData()[0];

		$this->assertEquals($result, $ocs->getShare($share->getId())->getData()[0]);
	}


	public function testGetShareInvalidNode() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Wrong share ID, share doesn\'t exist');

		$share = \OC::$server->getShareManager()->newShare();
		$share->setSharedBy('initiator')
			->setSharedWith('recipient')
			->setShareOwner('owner');

		$this->shareManager
			->expects($this->once())
			->method('getShareById')
			->with('ocinternal:42', 'currentUser')
			->willReturn($share);

		$userFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$this->ocs->getShare(42);
	}

	public function dataGetShares() {
		$folder = $this->getMockBuilder(Folder::class)->getMock();
		$file1 = $this->getMockBuilder(File::class)->getMock();
		$file1->method('getName')
			->willReturn('file1');
		$file2 = $this->getMockBuilder(File::class)->getMock();
		$file2->method('getName')
			->willReturn('file2');

		$folder->method('getDirectoryListing')
			->willReturn([$file1, $file2]);

		$file1UserShareOwner = \OC::$server->getShareManager()->newShare();
		$file1UserShareOwner->setShareType(IShare::TYPE_USER)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(4);

		$file1UserShareOwnerExpected = [
			'id' => 4,
			'share_type' => IShare::TYPE_USER,
		];

		$file1UserShareInitiator = \OC::$server->getShareManager()->newShare();
		$file1UserShareInitiator->setShareType(IShare::TYPE_USER)
			->setSharedWith('recipient')
			->setSharedBy('currentUser')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(8);

		$file1UserShareInitiatorExpected = [
			'id' => 8,
			'share_type' => IShare::TYPE_USER,
		];

		$file1UserShareRecipient = \OC::$server->getShareManager()->newShare();
		$file1UserShareRecipient->setShareType(IShare::TYPE_USER)
			->setSharedWith('currentUser')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(15);

		$file1UserShareRecipientExpected = [
			'id' => 15,
			'share_type' => IShare::TYPE_USER,
		];

		$file1UserShareOther = \OC::$server->getShareManager()->newShare();
		$file1UserShareOther->setShareType(IShare::TYPE_USER)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(16);

		$file1UserShareOtherExpected = [
			'id' => 16,
			'share_type' => IShare::TYPE_USER,
		];

		$file1GroupShareOwner = \OC::$server->getShareManager()->newShare();
		$file1GroupShareOwner->setShareType(IShare::TYPE_GROUP)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(23);

		$file1GroupShareOwnerExpected = [
			'id' => 23,
			'share_type' => IShare::TYPE_GROUP,
		];

		$file1GroupShareRecipient = \OC::$server->getShareManager()->newShare();
		$file1GroupShareRecipient->setShareType(IShare::TYPE_GROUP)
			->setSharedWith('currentUserGroup')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(42);

		$file1GroupShareRecipientExpected = [
			'id' => 42,
			'share_type' => IShare::TYPE_GROUP,
		];

		$file1GroupShareOther = \OC::$server->getShareManager()->newShare();
		$file1GroupShareOther->setShareType(IShare::TYPE_GROUP)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(108);

		$file1LinkShareOwner = \OC::$server->getShareManager()->newShare();
		$file1LinkShareOwner->setShareType(IShare::TYPE_LINK)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(415);

		$file1LinkShareOwnerExpected = [
			'id' => 415,
			'share_type' => IShare::TYPE_LINK,
		];

		$file1EmailShareOwner = \OC::$server->getShareManager()->newShare();
		$file1EmailShareOwner->setShareType(IShare::TYPE_EMAIL)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(416);

		$file1EmailShareOwnerExpected = [
			'id' => 416,
			'share_type' => IShare::TYPE_EMAIL,
		];

		$file1CircleShareOwner = \OC::$server->getShareManager()->newShare();
		$file1CircleShareOwner->setShareType(IShare::TYPE_CIRCLE)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(423);

		$file1CircleShareOwnerExpected = [
			'id' => 423,
			'share_type' => IShare::TYPE_CIRCLE,
		];

		$file1RoomShareOwner = \OC::$server->getShareManager()->newShare();
		$file1RoomShareOwner->setShareType(IShare::TYPE_ROOM)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(442);

		$file1RoomShareOwnerExpected = [
			'id' => 442,
			'share_type' => IShare::TYPE_ROOM,
		];

		$file1RemoteShareOwner = \OC::$server->getShareManager()->newShare();
		$file1RemoteShareOwner->setShareType(IShare::TYPE_REMOTE)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(815);

		$file1RemoteShareOwnerExpected = [
			'id' => 815,
			'share_type' => IShare::TYPE_REMOTE,
		];

		$file1RemoteGroupShareOwner = \OC::$server->getShareManager()->newShare();
		$file1RemoteGroupShareOwner->setShareType(IShare::TYPE_REMOTE_GROUP)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1)
			->setId(816);

		$file1RemoteGroupShareOwnerExpected = [
			'id' => 816,
			'share_type' => IShare::TYPE_REMOTE_GROUP,
		];

		$file2UserShareOwner = \OC::$server->getShareManager()->newShare();
		$file2UserShareOwner->setShareType(IShare::TYPE_USER)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file2)
			->setId(823);

		$file2UserShareOwnerExpected = [
			'id' => 823,
			'share_type' => IShare::TYPE_USER,
		];

		$data = [
			[
				[
					'path' => $file1,
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner, $file1UserShareOwner, $file1UserShareOwner],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected
				]
			],
			[
				[
					'path' => $file1,
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner, $file1UserShareRecipient],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected,
				]
			],
			[
				[
					'path' => $file1,
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner, $file1UserShareRecipient, $file1UserShareInitiator, $file1UserShareOther],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected,
					$file1UserShareInitiatorExpected,
					$file1UserShareOtherExpected,
				]
			],
			[
				[
					'path' => $file1,
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareRecipient, $file1UserShareInitiator, $file1UserShareOther],
					],
				],
				[
				],
				[
					$file1UserShareInitiatorExpected,
				]
			],
			[
				[
					'path' => $file1,
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner],
						IShare::TYPE_GROUP => [$file1GroupShareRecipient],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected,
					$file1GroupShareRecipientExpected,
				]
			],
			[
				[
					'path' => $file1,
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner],
						IShare::TYPE_GROUP => [$file1GroupShareOwner],
						IShare::TYPE_LINK => [$file1LinkShareOwner],
						IShare::TYPE_EMAIL => [$file1EmailShareOwner],
						IShare::TYPE_CIRCLE => [$file1CircleShareOwner],
						IShare::TYPE_ROOM => [$file1RoomShareOwner],
						IShare::TYPE_REMOTE => [$file1RemoteShareOwner],
						IShare::TYPE_REMOTE_GROUP => [$file1RemoteGroupShareOwner],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected,
					$file1GroupShareOwnerExpected,
					$file1LinkShareOwnerExpected,
					$file1EmailShareOwnerExpected,
					$file1CircleShareOwnerExpected,
					$file1RoomShareOwnerExpected,
				]
			],
			[
				[
					'path' => $file1,
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner],
						IShare::TYPE_GROUP => [$file1GroupShareOwner],
						IShare::TYPE_LINK => [$file1LinkShareOwner],
						IShare::TYPE_EMAIL => [$file1EmailShareOwner],
						IShare::TYPE_CIRCLE => [$file1CircleShareOwner],
						IShare::TYPE_ROOM => [$file1RoomShareOwner],
						IShare::TYPE_REMOTE => [$file1RemoteShareOwner],
						IShare::TYPE_REMOTE_GROUP => [$file1RemoteGroupShareOwner],
					],
				],
				[
					IShare::TYPE_REMOTE => true,
					IShare::TYPE_REMOTE_GROUP => true,
				],
				[
					$file1UserShareOwnerExpected,
					$file1GroupShareOwnerExpected,
					$file1LinkShareOwnerExpected,
					$file1EmailShareOwnerExpected,
					$file1CircleShareOwnerExpected,
					$file1RoomShareOwnerExpected,
					$file1RemoteShareOwnerExpected,
					$file1RemoteGroupShareOwnerExpected,
				]
			],
			[
				[
					'path' => $folder,
					'subfiles' => 'true',
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner],
					],
					'file2' => [
						IShare::TYPE_USER => [$file2UserShareOwner],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected,
					$file2UserShareOwnerExpected,
				]
			],
			[
				[
					'path' => $folder,
					'subfiles' => 'true',
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner, $file1UserShareOwner, $file1UserShareOwner],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected,
				]
			],
			[
				[
					'path' => $folder,
					'subfiles' => 'true',
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner, $file1UserShareRecipient],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected
				]
			],
			[
				[
					'path' => $folder,
					'subfiles' => 'true',
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareRecipient, $file1UserShareInitiator, $file1UserShareOther],
					],
					'file2' => [
						IShare::TYPE_USER => [$file2UserShareOwner],
					],
				],
				[
				],
				[
					$file1UserShareInitiatorExpected,
					$file1UserShareOtherExpected,
					$file2UserShareOwnerExpected,
				]
			],
			// This might not happen in a real environment, as the combination
			// of shares does not seem to be possible on a folder without
			// resharing rights; if the folder has resharing rights then the
			// share with others would be included too in the results.
			[
				[
					'path' => $folder,
					'subfiles' => 'true',
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareRecipient, $file1UserShareInitiator, $file1UserShareOther],
					],
				],
				[
				],
				[
					$file1UserShareInitiatorExpected,
				]
			],
			[
				[
					'path' => $folder,
					'subfiles' => 'true',
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner],
						IShare::TYPE_GROUP => [$file1GroupShareRecipient],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected,
					$file1GroupShareRecipientExpected,
				]
			],
			[
				[
					'path' => $folder,
					'subfiles' => 'true',
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner],
						IShare::TYPE_GROUP => [$file1GroupShareOwner],
						IShare::TYPE_LINK => [$file1LinkShareOwner],
						IShare::TYPE_EMAIL => [$file1EmailShareOwner],
						IShare::TYPE_CIRCLE => [$file1CircleShareOwner],
						IShare::TYPE_ROOM => [$file1RoomShareOwner],
						IShare::TYPE_REMOTE => [$file1RemoteShareOwner],
						IShare::TYPE_REMOTE_GROUP => [$file1RemoteGroupShareOwner],
					],
				],
				[
				],
				[
					$file1UserShareOwnerExpected,
					$file1GroupShareOwnerExpected,
					$file1LinkShareOwnerExpected,
					$file1EmailShareOwnerExpected,
					$file1CircleShareOwnerExpected,
					$file1RoomShareOwnerExpected,
				]
			],
			[
				[
					'path' => $folder,
					'subfiles' => 'true',
				],
				[
					'file1' => [
						IShare::TYPE_USER => [$file1UserShareOwner],
						IShare::TYPE_GROUP => [$file1GroupShareOwner],
						IShare::TYPE_LINK => [$file1LinkShareOwner],
						IShare::TYPE_EMAIL => [$file1EmailShareOwner],
						IShare::TYPE_CIRCLE => [$file1CircleShareOwner],
						IShare::TYPE_ROOM => [$file1RoomShareOwner],
						IShare::TYPE_REMOTE => [$file1RemoteShareOwner],
						IShare::TYPE_REMOTE_GROUP => [$file1RemoteGroupShareOwner],
					],
				],
				[
					IShare::TYPE_REMOTE => true,
					IShare::TYPE_REMOTE_GROUP => true,
				],
				[
					$file1UserShareOwnerExpected,
					$file1GroupShareOwnerExpected,
					$file1LinkShareOwnerExpected,
					$file1EmailShareOwnerExpected,
					$file1CircleShareOwnerExpected,
					$file1RoomShareOwnerExpected,
					$file1RemoteShareOwnerExpected,
					$file1RemoteGroupShareOwnerExpected,
				]
			],
		];

		return $data;
	}

	/**
	 * @dataProvider dataGetShares
	 */
	public function testGetShares(array $getSharesParameters, array $shares, array $extraShareTypes, array $expected) {
		/** @var \OCA\Files_Sharing\Controller\ShareAPIController $ocs */
		$ocs = $this->getMockBuilder(ShareAPIController::class)
			->setConstructorArgs([
				$this->appName,
				$this->request,
				$this->shareManager,
				$this->groupManager,
				$this->userManager,
				$this->rootFolder,
				$this->urlGenerator,
				$this->currentUser,
				$this->l,
				$this->config,
				$this->appManager,
				$this->serverContainer
			])->setMethods(['formatShare'])
			->getMock();

		$ocs->method('formatShare')
			->willReturnCallback(
				function ($share) {
					return [
						'id' => $share->getId(),
						'share_type' => $share->getShareType()
					];
				}
			);

		$userFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$userFolder->method('get')
			->with('path')
			->willReturn($getSharesParameters['path']);

		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$this->shareManager
			->method('getSharesBy')
			->willReturnCallback(
				function ($user, $shareType, $node) use ($shares) {
					if (!isset($shares[$node->getName()]) || !isset($shares[$node->getName()][$shareType])) {
						return [];
					}
					return $shares[$node->getName()][$shareType];
				}
			);

		$this->shareManager
			->method('outgoingServer2ServerSharesAllowed')
			->willReturn($extraShareTypes[ISHARE::TYPE_REMOTE] ?? false);

		$this->shareManager
			->method('outgoingServer2ServerGroupSharesAllowed')
			->willReturn($extraShareTypes[ISHARE::TYPE_REMOTE_GROUP] ?? false);

		$this->groupManager
			->method('isInGroup')
			->willReturnCallback(
				function ($user, $group) {
					return $group === 'currentUserGroup';
				}
			);

		$result = $ocs->getShares(
			$getSharesParameters['sharedWithMe'] ?? 'false',
			$getSharesParameters['reshares'] ?? 'false',
			$getSharesParameters['subfiles'] ?? 'false',
			'path'
		);

		$this->assertEquals($expected, $result->getData());
	}

	public function testCanAccessShare() {
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->method('getShareOwner')->willReturn($this->currentUser);
		$this->assertTrue($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));

		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->method('getSharedBy')->willReturn($this->currentUser);
		$this->assertTrue($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));

		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_USER);
		$share->method('getSharedWith')->willReturn($this->currentUser);
		$this->assertTrue($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));

		$file = $this->getMockBuilder(File::class)->getMock();

		$userFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with($share->getNodeId())
			->willReturn([$file]);

		$file->method('getPermissions')
			->will($this->onConsecutiveCalls(\OCP\Constants::PERMISSION_SHARE, \OCP\Constants::PERMISSION_READ));

		// getPermissions -> share
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_USER);
		$share->method('getSharedWith')->willReturn($this->getMockBuilder(IUser::class)->getMock());
		$this->assertTrue($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));

		// getPermissions -> read
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_USER);
		$share->method('getSharedWith')->willReturn($this->getMockBuilder(IUser::class)->getMock());
		$this->assertFalse($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));

		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_GROUP);
		$share->method('getSharedWith')->willReturn('group');

		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')
			->with($this->currentUser)
			->willReturn($user);

		$group = $this->getMockBuilder('OCP\IGroup')->getMock();
		$group->method('inGroup')->with($user)->willReturn(true);
		$group2 = $this->getMockBuilder('OCP\IGroup')->getMock();
		$group2->method('inGroup')->with($user)->willReturn(false);

		$this->groupManager->method('get')->willReturnMap([
			['group', $group],
			['group2', $group2],
			['groupnull', null],
		]);
		$this->assertTrue($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));

		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_GROUP);
		$share->method('getSharedWith')->willReturn('group2');
		$this->assertFalse($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));

		// null group
		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_GROUP);
		$share->method('getSharedWith')->willReturn('groupnull');
		$this->assertFalse($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));

		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_LINK);
		$this->assertFalse($this->invokePrivate($this->ocs, 'canAccessShare', [$share]));
	}

	public function dataCanAccessRoomShare() {
		$result = [];

		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(\OCP\Share::SHARE_TYPE_ROOM);
		$share->method('getSharedWith')->willReturn('recipientRoom');

		$result[] = [
			false, $share, false, false
		];

		$result[] = [
			false, $share, false, true
		];

		$result[] = [
			true, $share, true, true
		];

		$result[] = [
			false, $share, true, false
		];

		return $result;
	}

	/**
	 * @dataProvider dataCanAccessRoomShare
	 *
	 * @param bool $expects
	 * @param \OCP\Share\IShare $share
	 * @param bool helperAvailable
	 * @param bool canAccessShareByHelper
	 */
	public function testCanAccessRoomShare(bool $expected, \OCP\Share\IShare $share, bool $helperAvailable, bool $canAccessShareByHelper) {
		$userFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with($share->getNodeId())
			->willReturn([$share->getNode()]);

		if (!$helperAvailable) {
			$this->appManager->method('isEnabledForUser')
				->with('spreed')
				->willReturn(false);
		} else {
			$this->appManager->method('isEnabledForUser')
				->with('spreed')
				->willReturn(true);

			$helper = $this->getMockBuilder('\OCA\Talk\Share\Helper\ShareAPIController')
				->setMethods(['canAccessShare'])
				->getMock();
			$helper->method('canAccessShare')
				->with($share, $this->currentUser)
				->willReturn($canAccessShareByHelper);

			$this->serverContainer->method('query')
				->with('\OCA\Talk\Share\Helper\ShareAPIController')
				->willReturn($helper);
		}

		$this->assertEquals($expected, $this->invokePrivate($this->ocs, 'canAccessShare', [$share]));
	}


	public function testCreateShareNoPath() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Please specify a file or folder path');

		$this->ocs->createShare();
	}


	public function testCreateShareInvalidPath() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Wrong path, file/folder doesn\'t exist');

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('currentUser')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with('invalid-path')
			->will($this->throwException(new \OCP\Files\NotFoundException()));

		$this->ocs->createShare('invalid-path');
	}


	public function testCreateShareInvalidPermissions() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('invalid permissions');

		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
				->method('getUserFolder')
				->with('currentUser')
				->willReturn($userFolder);

		$path = $this->getMockBuilder(File::class)->getMock();
		$userFolder->expects($this->once())
				->method('get')
				->with('valid-path')
				->willReturn($path);

		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->ocs->createShare('valid-path', 32);
	}


	public function testCreateShareUserNoShareWith() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Please specify a valid user');

		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('currentUser')
			->willReturn($userFolder);

		$path = $this->getMockBuilder(File::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$userFolder->expects($this->once())
			->method('get')
			->with('valid-path')
			->willReturn($path);

		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_USER);
	}


	public function testCreateShareUserNoValidShareWith() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Please specify a valid user');

		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('currentUser')
			->willReturn($userFolder);

		$path = $this->getMockBuilder(File::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$userFolder->expects($this->once())
			->method('get')
			->with('valid-path')
			->willReturn($path);
		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);
		$this->userManager->method('userExists')
			->with('invalidUser')
			->willReturn(false);

		$this->ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_USER, 'invalidUser');
	}

	public function testCreateShareUser() {
		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		/** @var \OCA\Files_Sharing\Controller\ShareAPIController $ocs */
		$ocs = $this->getMockBuilder(ShareAPIController::class)
			->setConstructorArgs([
				$this->appName,
				$this->request,
				$this->shareManager,
				$this->groupManager,
				$this->userManager,
				$this->rootFolder,
				$this->urlGenerator,
				$this->currentUser,
				$this->l,
				$this->config,
				$this->appManager,
				$this->serverContainer
			])->setMethods(['formatShare'])
			->getMock();

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
				->method('getUserFolder')
				->with('currentUser')
				->willReturn($userFolder);

		$path = $this->getMockBuilder(File::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$userFolder->expects($this->once())
				->method('get')
				->with('valid-path')
				->willReturn($path);

		$this->userManager->method('userExists')->with('validUser')->willReturn(true);

		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('createShare')
			->with($this->callback(function (\OCP\Share\IShare $share) use ($path) {
				return $share->getNode() === $path &&
					$share->getPermissions() === (
						\OCP\Constants::PERMISSION_ALL &
						~\OCP\Constants::PERMISSION_DELETE &
						~\OCP\Constants::PERMISSION_CREATE
					) &&
					$share->getShareType() === \OCP\Share::SHARE_TYPE_USER &&
					$share->getSharedWith() === 'validUser' &&
					$share->getSharedBy() === 'currentUser';
			}))
			->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_USER, 'validUser');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}


	public function testCreateShareGroupNoValidShareWith() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Please specify a valid group');

		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);
		$this->shareManager->method('createShare')->willReturnArgument(0);
		$this->shareManager->method('allowGroupSharing')->willReturn(true);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
				->method('getUserFolder')
				->with('currentUser')
				->willReturn($userFolder);

		$path = $this->getMockBuilder(File::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$userFolder->expects($this->once())
				->method('get')
				->with('valid-path')
				->willReturn($path);

		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_GROUP, 'invalidGroup');
	}

	public function testCreateShareGroup() {
		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		/** @var ShareAPIController|\PHPUnit_Framework_MockObject_MockObject $ocs */
		$ocs = $this->getMockBuilder(ShareAPIController::class)
			->setConstructorArgs([
				$this->appName,
				$this->request,
				$this->shareManager,
				$this->groupManager,
				$this->userManager,
				$this->rootFolder,
				$this->urlGenerator,
				$this->currentUser,
				$this->l,
				$this->config,
				$this->appManager,
				$this->serverContainer
			])->setMethods(['formatShare'])
			->getMock();

		$this->request
			->method('getParam')
			->willReturnMap([
				['path', null, 'valid-path'],
				['permissions', null, \OCP\Constants::PERMISSION_ALL],
				['shareType', '-1', \OCP\Share::SHARE_TYPE_GROUP],
				['shareWith', null, 'validGroup'],
			]);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('currentUser')
			->willReturn($userFolder);

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$userFolder->expects($this->once())
			->method('get')
			->with('valid-path')
			->willReturn($path);

		$this->groupManager->method('groupExists')->with('validGroup')->willReturn(true);

		$this->shareManager->expects($this->once())
			->method('allowGroupSharing')
			->willReturn(true);

		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('createShare')
			->with($this->callback(function (\OCP\Share\IShare $share) use ($path) {
				return $share->getNode() === $path &&
				$share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
				$share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP &&
				$share->getSharedWith() === 'validGroup' &&
				$share->getSharedBy() === 'currentUser';
			}))
			->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_GROUP, 'validGroup');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}


	public function testCreateShareGroupNotAllowed() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Group sharing is disabled by the administrator');

		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('currentUser')
			->willReturn($userFolder);

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$userFolder->expects($this->once())
			->method('get')
			->with('valid-path')
			->willReturn($path);

		$this->groupManager->method('groupExists')->with('validGroup')->willReturn(true);

		$this->shareManager->expects($this->once())
			->method('allowGroupSharing')
			->willReturn(false);

		$this->ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_GROUP, 'invalidGroup');
	}


	public function testCreateShareLinkNoLinksAllowed() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Public link sharing is disabled by the administrator');

		$this->request
			->method('getParam')
			->willReturnMap([
				['path', null, 'valid-path'],
				['shareType', '-1', \OCP\Share::SHARE_TYPE_LINK],
			]);

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());

		$this->ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK);
	}


	public function testCreateShareLinkNoPublicUpload() {
		$this->expectException(\OCP\AppFramework\OCS\OCSForbiddenException::class);
		$this->expectExceptionMessage('Public upload disabled by the administrator');

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);

		$this->ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK, null, 'true');
	}


	public function testCreateShareLinkPublicUploadFile() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Public upload is only possible for publicly shared folders');

		$path = $this->getMockBuilder(File::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK, null, 'true');
	}

	public function testCreateShareLinkPublicUploadFolder() {
		$ocs = $this->mockFormatShare();

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->shareManager->expects($this->once())->method('createShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($path) {
				return $share->getNode() === $path &&
					$share->getShareType() === \OCP\Share::SHARE_TYPE_LINK &&
					$share->getPermissions() === (\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE) &&
					$share->getSharedBy() === 'currentUser' &&
					$share->getPassword() === null &&
					$share->getExpirationDate() === null;
			})
		)->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK, null, 'true', '', null, '');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testCreateShareLinkPassword() {
		$ocs = $this->mockFormatShare();

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->shareManager->expects($this->once())->method('createShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($path) {
				return $share->getNode() === $path &&
				$share->getShareType() === \OCP\Share::SHARE_TYPE_LINK &&
				$share->getPermissions() === \OCP\Constants::PERMISSION_READ &&
				$share->getSharedBy() === 'currentUser' &&
				$share->getPassword() === 'password' &&
				$share->getExpirationDate() === null;
			})
		)->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK, null, 'false', 'password', null, '');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testCreateShareLinkSendPasswordByTalk() {
		$ocs = $this->mockFormatShare();

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->appManager->method('isEnabledForUser')->with('spreed')->willReturn(true);

		$this->shareManager->expects($this->once())->method('createShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($path) {
				return $share->getNode() === $path &&
				$share->getShareType() === \OCP\Share::SHARE_TYPE_LINK &&
				$share->getPermissions() === \OCP\Constants::PERMISSION_READ &&
				$share->getSharedBy() === 'currentUser' &&
				$share->getPassword() === 'password' &&
				$share->getSendPasswordByTalk() === true &&
				$share->getExpirationDate() === null;
			})
		)->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK, null, 'false', 'password', 'true', '');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}


	public function testCreateShareLinkSendPasswordByTalkWithTalkDisabled() {
		$this->expectException(\OCP\AppFramework\OCS\OCSForbiddenException::class);
		$this->expectExceptionMessage('Sharing valid-path sending the password by Nextcloud Talk failed because Nextcloud Talk is not enabled');

		$ocs = $this->mockFormatShare();

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$path->method('getPath')->willReturn('valid-path');
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->appManager->method('isEnabledForUser')->with('spreed')->willReturn(false);

		$this->shareManager->expects($this->never())->method('createShare');

		$ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK, null, 'false', 'password', 'true', '');
	}

	public function testCreateShareValidExpireDate() {
		$ocs = $this->mockFormatShare();

		$this->request
			->method('getParam')
			->willReturnMap([
				['path', null, 'valid-path'],
				['shareType', '-1', \OCP\Share::SHARE_TYPE_LINK],
				['publicUpload', null, 'false'],
				['expireDate', '', '2000-01-01'],
				['password', '', ''],
			]);

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->shareManager->expects($this->once())->method('createShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($path) {
				$date = new \DateTime('2000-01-01');
				$date->setTime(0,0,0);

				return $share->getNode() === $path &&
				$share->getShareType() === \OCP\Share::SHARE_TYPE_LINK &&
				$share->getPermissions() === \OCP\Constants::PERMISSION_READ &&
				$share->getSharedBy() === 'currentUser' &&
				$share->getPassword() === null &&
				$share->getExpirationDate() == $date;
			})
		)->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK, null, 'false', '', null, '2000-01-01');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}


	public function testCreateShareInvalidExpireDate() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Invalid date, date format must be YYYY-MM-DD');

		$ocs = $this->mockFormatShare();

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$this->rootFolder->method('getUserFolder')->with($this->currentUser)->willReturnSelf();
		$this->rootFolder->method('get')->with('valid-path')->willReturn($path);

		$this->shareManager->method('newShare')->willReturn(\OC::$server->getShareManager()->newShare());
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_LINK, null, 'false', '', null, 'a1b2d3');
	}

	public function testCreateShareRoom() {
		$ocs = $this->mockFormatShare();

		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
				->method('getUserFolder')
				->with('currentUser')
				->willReturn($userFolder);

		$path = $this->getMockBuilder(File::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$userFolder->expects($this->once())
				->method('get')
				->with('valid-path')
				->willReturn($path);

		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->appManager->method('isEnabledForUser')
			->with('spreed')
			->willReturn(true);

		$helper = $this->getMockBuilder('\OCA\Talk\Share\Helper\ShareAPIController')
			->setMethods(['createShare'])
			->getMock();
		$helper->method('createShare')
			->with(
				$share,
				'recipientRoom',
				\OCP\Constants::PERMISSION_ALL &
				~\OCP\Constants::PERMISSION_DELETE &
				~\OCP\Constants::PERMISSION_CREATE,
				''
			)->willReturnCallback(
				function ($share) {
					$share->setSharedWith('recipientRoom');
					$share->setPermissions(
						\OCP\Constants::PERMISSION_ALL &
						~\OCP\Constants::PERMISSION_DELETE &
						~\OCP\Constants::PERMISSION_CREATE
					);
				}
			);

		$this->serverContainer->method('query')
			->with('\OCA\Talk\Share\Helper\ShareAPIController')
			->willReturn($helper);

		$this->shareManager->method('createShare')
			->with($this->callback(function (\OCP\Share\IShare $share) use ($path) {
				return $share->getNode() === $path &&
					$share->getPermissions() === (
						\OCP\Constants::PERMISSION_ALL &
						~\OCP\Constants::PERMISSION_DELETE &
						~\OCP\Constants::PERMISSION_CREATE
					) &&
					$share->getShareType() === \OCP\Share::SHARE_TYPE_ROOM &&
					$share->getSharedWith() === 'recipientRoom' &&
					$share->getSharedBy() === 'currentUser';
			}))
			->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_ROOM, 'recipientRoom');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}


	public function testCreateShareRoomHelperNotAvailable() {
		$this->expectException(\OCP\AppFramework\OCS\OCSForbiddenException::class);
		$this->expectExceptionMessage('Sharing valid-path failed because the back end does not support room shares');

		$ocs = $this->mockFormatShare();

		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
				->method('getUserFolder')
				->with('currentUser')
				->willReturn($userFolder);

		$path = $this->getMockBuilder(File::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$path->method('getPath')->willReturn('valid-path');
		$userFolder->expects($this->once())
				->method('get')
				->with('valid-path')
				->willReturn($path);

		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->appManager->method('isEnabledForUser')
			->with('spreed')
			->willReturn(false);

		$this->shareManager->expects($this->never())->method('createShare');

		$ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_ROOM, 'recipientRoom');
	}


	public function testCreateShareRoomHelperThrowException() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Exception thrown by the helper');

		$ocs = $this->mockFormatShare();

		$share = $this->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
				->method('getUserFolder')
				->with('currentUser')
				->willReturn($userFolder);

		$path = $this->getMockBuilder(File::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$path->method('getStorage')->willReturn($storage);
		$userFolder->expects($this->once())
				->method('get')
				->with('valid-path')
				->willReturn($path);

		$path->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->appManager->method('isEnabledForUser')
			->with('spreed')
			->willReturn(true);

		$helper = $this->getMockBuilder('\OCA\Talk\Share\Helper\ShareAPIController')
			->setMethods(['createShare'])
			->getMock();
		$helper->method('createShare')
			->with(
				$share,
				'recipientRoom',
				\OCP\Constants::PERMISSION_ALL &
				~\OCP\Constants::PERMISSION_DELETE &
				~\OCP\Constants::PERMISSION_CREATE,
				''
			)->willReturnCallback(
				function ($share) {
					throw new OCSNotFoundException("Exception thrown by the helper");
				}
			);

		$this->serverContainer->method('query')
			->with('\OCA\Talk\Share\Helper\ShareAPIController')
			->willReturn($helper);

		$this->shareManager->expects($this->never())->method('createShare');

		$ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_ROOM, 'recipientRoom');
	}

	/**
	 * Test for https://github.com/owncloud/core/issues/22587
	 * TODO: Remove once proper solution is in place
	 */
	public function testCreateReshareOfFederatedMountNoDeletePermissions() {
		$share = \OC::$server->getShareManager()->newShare();
		$this->shareManager->method('newShare')->willReturn($share);

		/** @var ShareAPIController|\PHPUnit_Framework_MockObject_MockObject $ocs */
		$ocs = $this->getMockBuilder(ShareAPIController::class)
			->setConstructorArgs([
				$this->appName,
				$this->request,
				$this->shareManager,
				$this->groupManager,
				$this->userManager,
				$this->rootFolder,
				$this->urlGenerator,
				$this->currentUser,
				$this->l,
				$this->config,
				$this->appManager,
				$this->serverContainer
			])->setMethods(['formatShare'])
			->getMock();

		$userFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('currentUser')
			->willReturn($userFolder);

		$path = $this->getMockBuilder(Folder::class)->getMock();
		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(true);
		$path->method('getStorage')->willReturn($storage);
		$path->method('getPermissions')->willReturn(\OCP\Constants::PERMISSION_READ);
		$userFolder->expects($this->once())
			->method('get')
			->with('valid-path')
			->willReturn($path);

		$this->userManager->method('userExists')->with('validUser')->willReturn(true);

		$this->shareManager
			->expects($this->once())
			->method('createShare')
			->with($this->callback(function (\OCP\Share\IShare $share) {
				return $share->getPermissions() === \OCP\Constants::PERMISSION_READ;
			}))
			->willReturnArgument(0);

		$ocs->createShare('valid-path', \OCP\Constants::PERMISSION_ALL, \OCP\Share::SHARE_TYPE_USER, 'validUser');
	}


	public function testUpdateShareCantAccess() {
		$this->expectException(\OCP\AppFramework\OCS\OCSNotFoundException::class);
		$this->expectExceptionMessage('Wrong share ID, share doesn\'t exist');

		$node = $this->getMockBuilder(Folder::class)->getMock();
		$share = $this->newShare();
		$share->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$userFolder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with($share->getNodeId())
			->willReturn([$share->getNode()]);

		$this->ocs->updateShare(42);
	}


	public function testUpdateNoParametersLink() {
		$this->expectException(\OCP\AppFramework\OCS\OCSBadRequestException::class);
		$this->expectExceptionMessage('Wrong or no update parameter given');

		$node = $this->getMockBuilder(Folder::class)->getMock();
		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->ocs->updateShare(42);
	}


	public function testUpdateNoParametersOther() {
		$this->expectException(\OCP\AppFramework\OCS\OCSBadRequestException::class);
		$this->expectExceptionMessage('Wrong or no update parameter given');

		$node = $this->getMockBuilder(Folder::class)->getMock();
		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->ocs->updateShare(42);
	}

	public function testUpdateLinkShareClear() {
		$ocs = $this->mockFormatShare();

		$node = $this->getMockBuilder(Folder::class)->getMock();
		$node->method('getId')
			->willReturn(42);
		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setExpirationDate(new \DateTime())
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) {
				return $share->getPermissions() === \OCP\Constants::PERMISSION_READ &&
				$share->getPassword() === null &&
				$share->getExpirationDate() === null &&
				// Once set a note or a label are never back to null, only to an
				// empty string.
				$share->getNote() === '' &&
				$share->getLabel() === '' &&
				$share->getHideDownload() === false;
			})
		)->willReturnArgument(0);

		$this->shareManager->method('getSharedWith')
			->willReturn([]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$node]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$node->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, null, '', null, 'false', '', '', '', 'false');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateLinkShareSet() {
		$ocs = $this->mockFormatShare();

		$folder = $this->getMockBuilder(Folder::class)->getMock();
		$folder->method('getId')
			->willReturn(42);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setNode($folder);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) {
				$date = new \DateTime('2000-01-01');
				$date->setTime(0,0,0);

				return $share->getPermissions() === (\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE) &&
				$share->getPassword() === 'password' &&
				$share->getExpirationDate() == $date &&
				$share->getNote() === 'note' &&
				$share->getLabel() === 'label' &&
				$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$this->shareManager->method('getSharedWith')
			->willReturn([]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$folder]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$folder->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, null, 'password', null, 'true', '2000-01-01', 'note', 'label', 'true');

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	/**
	 * @dataProvider publicUploadParamsProvider
	 */
	public function testUpdateLinkShareEnablePublicUpload($permissions, $publicUpload, $expireDate, $password) {
		$ocs = $this->mockFormatShare();

		$folder = $this->getMockBuilder(Folder::class)->getMock();
		$folder->method('getId')
			->willReturn(42);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setNode($folder);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);
		$this->shareManager->method('getSharedWith')->willReturn([]);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) {
				return $share->getPermissions() === (\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE) &&
				$share->getPassword() === 'password' &&
				$share->getExpirationDate() === null;
			})
		)->willReturnArgument(0);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$folder]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$folder->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, $permissions, $password, null, $publicUpload, $expireDate);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}


	public function testUpdateLinkShareInvalidDate() {
		$this->expectException(\OCP\AppFramework\OCS\OCSBadRequestException::class);
		$this->expectExceptionMessage('Invalid date. Format must be YYYY-MM-DD');

		$ocs = $this->mockFormatShare();

		$folder = $this->getMockBuilder(Folder::class)->getMock();

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setNode($folder);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$ocs->updateShare(42, null, 'password', null, 'true', '2000-01-a');
	}

	public function publicUploadParamsProvider() {
		return [
			[null, 'true', null, 'password'],
			// legacy had no delete
			[
				\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE,
				null, null, 'password'
			],
			// correct
			[
				\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE,
				null, null, 'password'
			],
		];
	}

	/**
	 * @dataProvider publicUploadParamsProvider
	 */
	public function testUpdateLinkSharePublicUploadNotAllowed($permissions, $publicUpload, $expireDate, $password) {
		$this->expectException(\OCP\AppFramework\OCS\OCSForbiddenException::class);
		$this->expectExceptionMessage('Public upload disabled by the administrator');

		$ocs = $this->mockFormatShare();

		$folder = $this->getMockBuilder(Folder::class)->getMock();

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setNode($folder);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(false);

		$ocs->updateShare(42, $permissions, $password, null, $publicUpload, $expireDate);
	}


	public function testUpdateLinkSharePublicUploadOnFile() {
		$this->expectException(\OCP\AppFramework\OCS\OCSBadRequestException::class);
		$this->expectExceptionMessage('Public upload is only possible for publicly shared folders');

		$ocs = $this->mockFormatShare();

		$file = $this->getMockBuilder(File::class)->getMock();

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setNode($file);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$ocs->updateShare(42, null, 'password', null, 'true', '');
	}

	public function testUpdateLinkSharePasswordDoesNotChangeOther() {
		$ocs = $this->mockFormatShare();

		$date = new \DateTime('2000-01-01');
		$date->setTime(0,0,0);

		$node = $this->getMockBuilder(File::class)->getMock();
		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($date)
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($date) {
				return $share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
				$share->getPassword() === 'newpassword' &&
				$share->getSendPasswordByTalk() === true &&
				$share->getExpirationDate() === $date &&
				$share->getNote() === 'note' &&
				$share->getLabel() === 'label' &&
				$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, null, 'newpassword', null, null, null, null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateLinkShareSendPasswordByTalkDoesNotChangeOther() {
		$ocs = $this->mockFormatShare();

		$date = new \DateTime('2000-01-01');
		$date->setTime(0,0,0);

		$node = $this->getMockBuilder(File::class)->getMock();
		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(false)
			->setExpirationDate($date)
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->appManager->method('isEnabledForUser')->with('spreed')->willReturn(true);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($date) {
				return $share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
				$share->getPassword() === 'password' &&
				$share->getSendPasswordByTalk() === true &&
				$share->getExpirationDate() === $date &&
				$share->getNote() === 'note' &&
				$share->getLabel() === 'label' &&
				$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, null, null, 'true', null, null, null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}


	public function testUpdateLinkShareSendPasswordByTalkWithTalkDisabledDoesNotChangeOther() {
		$this->expectException(\OCP\AppFramework\OCS\OCSForbiddenException::class);
		$this->expectExceptionMessage('Sharing sending the password by Nextcloud Talk failed because Nextcloud Talk is not enabled');

		$ocs = $this->mockFormatShare();

		$date = new \DateTime('2000-01-01');
		$date->setTime(0,0,0);

		$node = $this->getMockBuilder(File::class)->getMock();
		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(false)
			->setExpirationDate($date)
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->appManager->method('isEnabledForUser')->with('spreed')->willReturn(false);

		$this->shareManager->expects($this->never())->method('updateShare');

		$ocs->updateShare(42, null, null, 'true', null, null, null, null, null);
	}

	public function testUpdateLinkShareDoNotSendPasswordByTalkDoesNotChangeOther() {
		$ocs = $this->mockFormatShare();

		$date = new \DateTime('2000-01-01');
		$date->setTime(0,0,0);

		$node = $this->getMockBuilder(File::class)->getMock();
		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($date)
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->appManager->method('isEnabledForUser')->with('spreed')->willReturn(true);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($date) {
				return $share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
				$share->getPassword() === 'password' &&
				$share->getSendPasswordByTalk() === false &&
				$share->getExpirationDate() === $date &&
				$share->getNote() === 'note' &&
				$share->getLabel() === 'label' &&
				$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, null, null, 'false', null, null, null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateLinkShareDoNotSendPasswordByTalkWithTalkDisabledDoesNotChangeOther() {
		$ocs = $this->mockFormatShare();

		$date = new \DateTime('2000-01-01');
		$date->setTime(0,0,0);

		$node = $this->getMockBuilder(File::class)->getMock();
		$node->method('getId')
			->willReturn(42);

		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($date)
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->appManager->method('isEnabledForUser')->with('spreed')->willReturn(false);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($date) {
				return $share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
				$share->getPassword() === 'password' &&
				$share->getSendPasswordByTalk() === false &&
				$share->getExpirationDate() === $date &&
				$share->getNote() === 'note' &&
				$share->getLabel() === 'label' &&
				$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$node]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$node->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$mountPoint = $this->createMock(IMountPoint::class);
		$node->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, null, null, 'false', null, null, null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateLinkShareExpireDateDoesNotChangeOther() {
		$ocs = $this->mockFormatShare();

		$node = $this->getMockBuilder(File::class)->getMock();
		$node->method('getId')
			->willReturn(42);

		$share = $this->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate(new \DateTime())
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($node);

		$node->expects($this->once())
			->method('lock')
			->with(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) {
				$date = new \DateTime('2010-12-23');
				$date->setTime(0,0,0);

				return $share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
				$share->getPassword() === 'password' &&
				$share->getSendPasswordByTalk() === true &&
				$share->getExpirationDate() == $date &&
				$share->getNote() === 'note' &&
				$share->getLabel() === 'label' &&
				$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$node]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$node->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, null, null, null, null, '2010-12-23', null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateLinkSharePublicUploadDoesNotChangeOther() {
		$ocs = $this->mockFormatShare();

		$date = new \DateTime('2000-01-01');

		$folder = $this->getMockBuilder(Folder::class)->getMock();
		$folder->method('getId')
			->willReturn(42);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($date)
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($folder);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($date) {
				return $share->getPermissions() === (\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE) &&
				$share->getPassword() === 'password' &&
				$share->getSendPasswordByTalk() === true &&
				$share->getExpirationDate() === $date &&
				$share->getNote() === 'note' &&
				$share->getLabel() === 'label' &&
				$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$this->shareManager->method('getSharedWith')
			->willReturn([]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$folder]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$folder->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, null, null, null, 'true', null, null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateLinkSharePermissions() {
		$ocs = $this->mockFormatShare();

		$date = new \DateTime('2000-01-01');

		$folder = $this->getMockBuilder(Folder::class)->getMock();
		$folder->method('getId')
			->willReturn(42);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($date)
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setNode($folder);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($date) {
				return $share->getPermissions() === (\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE) &&
				$share->getPassword() === 'password' &&
				$share->getSendPasswordByTalk() === true &&
				$share->getExpirationDate() === $date &&
				$share->getNote() === 'note' &&
				$share->getLabel() === 'label' &&
				$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$this->shareManager->method('getSharedWith')->willReturn([]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$folder]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$folder->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, 7, null, null, null, null, null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateLinkSharePermissionsShare() {
		$ocs = $this->mockFormatShare();

		$date = new \DateTime('2000-01-01');

		$folder = $this->getMockBuilder(Folder::class)->getMock();
		$folder->method('getId')
			->willReturn(42);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($date)
			->setNote('note')
			->setLabel('label')
			->setHideDownload(true)
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) use ($date) {
				return $share->getPermissions() === (\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE) &&
					$share->getPassword() === 'password' &&
					$share->getSendPasswordByTalk() === true &&
					$share->getExpirationDate() === $date &&
					$share->getNote() === 'note' &&
					$share->getLabel() === 'label' &&
					$share->getHideDownload() === true;
			})
		)->willReturnArgument(0);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$folder]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$folder->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$this->shareManager->method('getSharedWith')->willReturn([]);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, 31, null, null, null, null, null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateOtherPermissions() {
		$ocs = $this->mockFormatShare();

		$file = $this->getMockBuilder(File::class)->getMock();
		$file->method('getId')
			->willReturn(42);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setSharedBy($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setNode($file);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->shareManager->expects($this->once())->method('updateShare')->with(
			$this->callback(function (\OCP\Share\IShare $share) {
				return $share->getPermissions() === \OCP\Constants::PERMISSION_ALL;
			})
		)->willReturnArgument(0);

		$this->shareManager->method('getSharedWith')->willReturn([]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$file]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$file->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$expected = new DataResponse([]);
		$result = $ocs->updateShare(42, 31, null, null, null, null);

		$this->assertInstanceOf(get_class($expected), $result);
		$this->assertEquals($expected->getData(), $result->getData());
	}

	public function testUpdateShareCannotIncreasePermissions() {
		$ocs = $this->mockFormatShare();

		$folder = $this->createMock(Folder::class);
		$folder->method('getId')
			->willReturn(42);

		$share = \OC::$server->getShareManager()->newShare();
		$share
			->setId(42)
			->setSharedBy($this->currentUser)
			->setShareOwner('anotheruser')
			->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
			->setSharedWith('group1')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder);

		// note: updateShare will modify the received instance but getSharedWith will reread from the database,
		// so their values will be different
		$incomingShare = \OC::$server->getShareManager()->newShare();
		$incomingShare
			->setId(42)
			->setSharedBy($this->currentUser)
			->setShareOwner('anotheruser')
			->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
			->setSharedWith('group1')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder);

		$this->request
			->method('getParam')
			->willReturnMap([
				['permissions', null, '31'],
			]);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->shareManager->expects($this->any())
			->method('getSharedWith')
			->willReturnMap([
				['currentUser', \OCP\Share::SHARE_TYPE_USER, $share->getNode(), -1, 0, []],
				['currentUser', \OCP\Share::SHARE_TYPE_GROUP, $share->getNode(), -1, 0, [$incomingShare]],
				['currentUser', \OCP\Share::SHARE_TYPE_ROOM, $share->getNode(), -1, 0, []]
			]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$folder]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$folder->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$this->shareManager->expects($this->once())
			->method('updateShare')
			->with($share)
			->willThrowException(new GenericShareException('Can’t increase permissions of path/file', 'Can’t increase permissions of path/file', 404));

		try {
			$ocs->updateShare(42, 31);
			$this->fail();
		} catch (OCSException $e) {
			$this->assertEquals('Can’t increase permissions of path/file', $e->getMessage());
		}
	}

	public function testUpdateShareCanIncreasePermissionsIfOwner() {
		$ocs = $this->mockFormatShare();

		$folder = $this->createMock(Folder::class);
		$folder->method('getId')
			->willReturn(42);

		$share = \OC::$server->getShareManager()->newShare();
		$share
			->setId(42)
			->setSharedBy($this->currentUser)
			->setShareOwner($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
			->setSharedWith('group1')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder);

		// note: updateShare will modify the received instance but getSharedWith will reread from the database,
		// so their values will be different
		$incomingShare = \OC::$server->getShareManager()->newShare();
		$incomingShare
			->setId(42)
			->setSharedBy($this->currentUser)
			->setShareOwner($this->currentUser)
			->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
			->setSharedWith('group1')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder);

		$this->shareManager->method('getShareById')->with('ocinternal:42')->willReturn($share);

		$this->shareManager->expects($this->any())
			->method('getSharedWith')
			->willReturnMap([
				['currentUser', \OCP\Share::SHARE_TYPE_USER, $share->getNode(), -1, 0, []],
				['currentUser', \OCP\Share::SHARE_TYPE_GROUP, $share->getNode(), -1, 0, [$incomingShare]]
			]);

		$this->shareManager->expects($this->once())
			->method('updateShare')
			->with($share)
			->willReturn($share);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(42)
			->willReturn([$folder]);

		$mountPoint = $this->createMock(IMountPoint::class);
		$folder->method('getMountPoint')
			->willReturn($mountPoint);
		$mountPoint->method('getStorageRootId')
			->willReturn(42);

		$result = $ocs->updateShare(42, 31);
		$this->assertInstanceOf(DataResponse::class, $result);
	}

	public function dataFormatShare() {
		$file = $this->getMockBuilder(File::class)->getMock();
		$folder = $this->getMockBuilder(Folder::class)->getMock();
		$parent = $this->getMockBuilder(Folder::class)->getMock();

		$file->method('getMimeType')->willReturn('myMimeType');
		$folder->method('getMimeType')->willReturn('myFolderMimeType');

		$file->method('getPath')->willReturn('file');
		$folder->method('getPath')->willReturn('folder');

		$parent->method('getId')->willReturn(1);
		$folder->method('getId')->willReturn(2);
		$file->method('getId')->willReturn(3);

		$file->method('getParent')->willReturn($parent);
		$folder->method('getParent')->willReturn($parent);

		$cache = $this->getMockBuilder('OCP\Files\Cache\ICache')->getMock();
		$cache->method('getNumericStorageId')->willReturn(100);
		$storage = $this->createMock(Storage::class);
		$storage->method('getId')->willReturn('storageId');
		$storage->method('getCache')->willReturn($cache);

		$file->method('getStorage')->willReturn($storage);
		$folder->method('getStorage')->willReturn($storage);

		$owner = $this->getMockBuilder(IUser::class)->getMock();
		$owner->method('getDisplayName')->willReturn('ownerDN');
		$initiator = $this->getMockBuilder(IUser::class)->getMock();
		$initiator->method('getDisplayName')->willReturn('initiatorDN');
		$recipient = $this->getMockBuilder(IUser::class)->getMock();
		$recipient->method('getDisplayName')->willReturn('recipientDN');

		$result = [];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);

		// User backend down
		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_USER,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'recipient',
				'share_with_displayname' => 'recipient',
				'note' => 'personal note',
				'label' => null,
				'mail_send' => 0,
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];
		// User backend up
		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_USER,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiatorDN',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'ownerDN',
				'note' => 'personal note',
				'label' => null,
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'recipient',
				'share_with_displayname' => 'recipientDN',
				'mail_send' => 0,
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [
				['owner', $owner],
				['initiator', $initiator],
				['recipient', $recipient],
			], false
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);
		// User backend down
		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_USER,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => 'personal note',
				'label' => null,
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'recipient',
				'share_with_displayname' => 'recipient',
				'mail_send' => 0,
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedWith('recipient')
			->setSharedBy('initiator')
			->setShareOwner('currentUser')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);
		// User backend down
		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_USER,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'currentUser',
				'displayname_file_owner' => 'currentUser',
				'note' => 'personal note',
				'label' => null,
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'recipient',
				'share_with_displayname' => 'recipient',
				'mail_send' => 0,
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'can_edit' => true,
				'can_delete' => true,
			], $share, [], false
		];

		// with existing group

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
			->setSharedWith('recipientGroup')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_GROUP,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => 'personal note',
				'label' => null,
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'recipientGroup',
				'share_with_displayname' => 'recipientGroupDisplayName',
				'mail_send' => 0,
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		// with unknown group / no group backend
		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(Share::SHARE_TYPE_GROUP)
			->setSharedWith('recipientGroup2')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);
		$result[] = [
			[
				'id' => 42,
				'share_type' => Share::SHARE_TYPE_GROUP,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => 'personal note',
				'label' => null,
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'recipientGroup2',
				'share_with_displayname' => 'recipientGroup2',
				'mail_send' => 0,
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setPassword('mypassword')
			->setExpirationDate(new \DateTime('2001-01-02T00:00:00'))
			->setToken('myToken')
			->setNote('personal note')
			->setLabel('new link share')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_LINK,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => '2001-01-02 00:00:00',
				'token' => 'myToken',
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => 'personal note',
				'label' => 'new link share',
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'password' => 'mypassword',
				'share_with' => 'mypassword',
				'share_with_displayname' => '(Shared link)',
				'send_password_by_talk' => false,
				'mail_send' => 0,
				'url' => 'myLink',
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setPassword('mypassword')
			->setSendPasswordByTalk(true)
			->setExpirationDate(new \DateTime('2001-01-02T00:00:00'))
			->setToken('myToken')
			->setNote('personal note')
			->setLabel('new link share')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_LINK,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => '2001-01-02 00:00:00',
				'token' => 'myToken',
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => 'personal note',
				'label' => 'new link share',
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'password' => 'mypassword',
				'share_with' => 'mypassword',
				'share_with_displayname' => '(Shared link)',
				'send_password_by_talk' => true,
				'mail_send' => 0,
				'url' => 'myLink',
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_REMOTE)
			->setSharedBy('initiator')
			->setSharedWith('user@server.com')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_REMOTE,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => 'personal note',
				'label' => null,
				'path' => 'folder',
				'item_type' => 'folder',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 2,
				'file_source' => 2,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'user@server.com',
				'share_with_displayname' => 'foobar',
				'mail_send' => 0,
				'mimetype' => 'myFolderMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		// Circle with id, display name and avatar set by the Circles app
		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_CIRCLE)
			->setSharedBy('initiator')
			->setSharedWith('Circle (Public circle, circleOwner) [4815162342]')
			->setSharedWithDisplayName('The display name')
			->setSharedWithAvatar('path/to/the/avatar')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_CIRCLE,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => '',
				'label' => null,
				'path' => 'folder',
				'item_type' => 'folder',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 2,
				'file_source' => 2,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => '4815162342',
				'share_with_displayname' => 'The display name',
				'share_with_avatar' => 'path/to/the/avatar',
				'mail_send' => 0,
				'mimetype' => 'myFolderMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		// Circle with id set by the Circles app
		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_CIRCLE)
			->setSharedBy('initiator')
			->setSharedWith('Circle (Public circle, circleOwner) [4815162342]')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_CIRCLE,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => '',
				'label' => null,
				'path' => 'folder',
				'item_type' => 'folder',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 2,
				'file_source' => 2,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => '4815162342',
				'share_with_displayname' => 'Circle (Public circle, circleOwner)',
				'share_with_avatar' => '',
				'mail_send' => 0,
				'mimetype' => 'myFolderMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		// Circle with id not set by the Circles app
		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_CIRCLE)
			->setSharedBy('initiator')
			->setSharedWith('Circle (Public circle, circleOwner)')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_CIRCLE,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => '',
				'label' => null,
				'path' => 'folder',
				'item_type' => 'folder',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 2,
				'file_source' => 2,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'Circle',
				'share_with_displayname' => 'Circle (Public circle, circleOwner)',
				'share_with_avatar' => '',
				'mail_send' => 0,
				'mimetype' => 'myFolderMimeType',
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedBy('initiator')
			->setSharedWith('recipient')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);

		$result[] = [
			[], $share, [], true
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_EMAIL)
			->setSharedBy('initiator')
			->setSharedWith('user@server.com')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setId(42)
			->setPassword('password');

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_EMAIL,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => '',
				'label' => null,
				'path' => 'folder',
				'item_type' => 'folder',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 2,
				'file_source' => 2,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'user@server.com',
				'share_with_displayname' => 'mail display name',
				'mail_send' => 0,
				'mimetype' => 'myFolderMimeType',
				'password' => 'password',
				'send_password_by_talk' => false,
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_EMAIL)
			->setSharedBy('initiator')
			->setSharedWith('user@server.com')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setId(42)
			->setPassword('password')
			->setSendPasswordByTalk(true);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_EMAIL,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => '',
				'label' => null,
				'path' => 'folder',
				'item_type' => 'folder',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 2,
				'file_source' => 2,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'user@server.com',
				'share_with_displayname' => 'mail display name',
				'mail_send' => 0,
				'mimetype' => 'myFolderMimeType',
				'password' => 'password',
				'send_password_by_talk' => true,
				'hide_download' => 0,
				'can_edit' => false,
				'can_delete' => false,
			], $share, [], false
		];

		return $result;
	}

	/**
	 * @dataProvider dataFormatShare
	 *
	 * @param array $expects
	 * @param \OCP\Share\IShare $share
	 * @param array $users
	 * @param $exception
	 */
	public function testFormatShare(array $expects, \OCP\Share\IShare $share, array $users, $exception) {
		$this->userManager->method('get')->willReturnMap($users);

		$recipientGroup = $this->createMock('\OCP\IGroup');
		$recipientGroup->method('getDisplayName')->willReturn('recipientGroupDisplayName');
		$this->groupManager->method('get')->willReturnMap([
			['recipientGroup', $recipientGroup],
		]);

		$this->urlGenerator->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.showShare', ['token' => 'myToken'])
			->willReturn('myLink');


		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturnSelf();

		if (!$exception) {
			$this->rootFolder->method('getById')
				->with($share->getNodeId())
				->willReturn([$share->getNode()]);

			$this->rootFolder->method('getRelativePath')
				->with($share->getNode()->getPath())
				->willReturnArgument(0);
		}

		$cm = $this->createMock(\OCP\Contacts\IManager::class);
		$this->overwriteService(\OCP\Contacts\IManager::class, $cm);

		$cm->method('search')
			->willReturnMap([
				['user@server.com', ['CLOUD'], [],
					[
						[
							'CLOUD' => [
								'user@server.com',
							],
							'FN' => 'foobar',
						],
					],
				],
				['user@server.com', ['EMAIL'], [],
					[
						[
							'EMAIL' => [
								'user@server.com',
							],
							'FN' => 'mail display name',
						],
					],
				],
			]);

		try {
			$result = $this->invokePrivate($this->ocs, 'formatShare', [$share]);
			$this->assertFalse($exception);
			$this->assertEquals($expects, $result);
		} catch (NotFoundException $e) {
			$this->assertTrue($exception);
		}
	}

	public function dataFormatRoomShare() {
		$file = $this->getMockBuilder(File::class)->getMock();
		$parent = $this->getMockBuilder(Folder::class)->getMock();

		$file->method('getMimeType')->willReturn('myMimeType');

		$file->method('getPath')->willReturn('file');

		$parent->method('getId')->willReturn(1);
		$file->method('getId')->willReturn(3);

		$file->method('getParent')->willReturn($parent);

		$cache = $this->getMockBuilder('OCP\Files\Cache\ICache')->getMock();
		$cache->method('getNumericStorageId')->willReturn(100);
		$storage = $this->createMock(Storage::class);
		$storage->method('getId')->willReturn('storageId');
		$storage->method('getCache')->willReturn($cache);

		$file->method('getStorage')->willReturn($storage);

		$result = [];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_ROOM)
			->setSharedWith('recipientRoom')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_ROOM,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => 'personal note',
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'recipientRoom',
				'share_with_displayname' => '',
				'mail_send' => 0,
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'label' => '',
				'can_edit' => false,
				'can_delete' => false,
			], $share, false, []
		];

		$share = \OC::$server->getShareManager()->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_ROOM)
			->setSharedWith('recipientRoom')
			->setSharedBy('initiator')
			->setShareOwner('owner')
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file)
			->setShareTime(new \DateTime('2000-01-01T00:01:02'))
			->setTarget('myTarget')
			->setNote('personal note')
			->setId(42);

		$result[] = [
			[
				'id' => 42,
				'share_type' => \OCP\Share::SHARE_TYPE_ROOM,
				'uid_owner' => 'initiator',
				'displayname_owner' => 'initiator',
				'permissions' => 1,
				'stime' => 946684862,
				'parent' => null,
				'expiration' => null,
				'token' => null,
				'uid_file_owner' => 'owner',
				'displayname_file_owner' => 'owner',
				'note' => 'personal note',
				'path' => 'file',
				'item_type' => 'file',
				'storage_id' => 'storageId',
				'storage' => 100,
				'item_source' => 3,
				'file_source' => 3,
				'file_parent' => 1,
				'file_target' => 'myTarget',
				'share_with' => 'recipientRoom',
				'share_with_displayname' => 'recipientRoomName',
				'mail_send' => 0,
				'mimetype' => 'myMimeType',
				'hide_download' => 0,
				'label' => '',
				'can_edit' => false,
				'can_delete' => false,
			], $share, true, [
				'share_with_displayname' => 'recipientRoomName'
			]
		];

		return $result;
	}

	/**
	 * @dataProvider dataFormatRoomShare
	 *
	 * @param array $expects
	 * @param \OCP\Share\IShare $share
	 * @param bool $helperAvailable
	 * @param array $formatShareByHelper
	 */
	public function testFormatRoomShare(array $expects, \OCP\Share\IShare $share, bool $helperAvailable, array $formatShareByHelper) {
		$this->rootFolder->method('getUserFolder')
			->with($this->currentUser)
			->willReturnSelf();

		$this->rootFolder->method('getById')
			->with($share->getNodeId())
			->willReturn([$share->getNode()]);

		$this->rootFolder->method('getRelativePath')
			->with($share->getNode()->getPath())
			->willReturnArgument(0);

		if (!$helperAvailable) {
			$this->appManager->method('isEnabledForUser')
				->with('spreed')
				->willReturn(false);
		} else {
			$this->appManager->method('isEnabledForUser')
				->with('spreed')
				->willReturn(true);

			$helper = $this->getMockBuilder('\OCA\Talk\Share\Helper\ShareAPIController')
				->setMethods(['formatShare'])
				->getMock();
			$helper->method('formatShare')
				->with($share)
				->willReturn($formatShareByHelper);

			$this->serverContainer->method('query')
				->with('\OCA\Talk\Share\Helper\ShareAPIController')
				->willReturn($helper);
		}

		$result = $this->invokePrivate($this->ocs, 'formatShare', [$share]);
		$this->assertEquals($expects, $result);
	}
}
