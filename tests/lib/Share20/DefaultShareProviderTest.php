<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace Test\Share20;

use OC\Share20\DefaultShareProvider;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Defaults;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class DefaultShareProviderTest
 *
 * @package Test\Share20
 * @group DB
 */
class DefaultShareProviderTest extends \Test\TestCase {

	/** @var IDBConnection */
	protected $dbConn;

	/** @var IUserManager | \PHPUnit\Framework\MockObject\MockObject */
	protected $userManager;

	/** @var IGroupManager | \PHPUnit\Framework\MockObject\MockObject */
	protected $groupManager;

	/** @var IRootFolder | \PHPUnit\Framework\MockObject\MockObject */
	protected $rootFolder;

	/** @var DefaultShareProvider */
	protected $provider;

	/** @var \PHPUnit\Framework\MockObject\MockObject|IMailer */
	protected $mailer;

	/** @var IFactory|MockObject */
	protected $l10nFactory;

	/** @var \PHPUnit\Framework\MockObject\MockObject|IL10N */
	protected $l10n;

	/** @var \PHPUnit\Framework\MockObject\MockObject|Defaults */
	protected $defaults;

	/** @var \PHPUnit\Framework\MockObject\MockObject|IURLGenerator */
	protected $urlGenerator;

	/** @var IConfig|MockObject */
	protected $config;

	protected function setUp(): void {
		$this->dbConn = \OC::$server->getDatabaseConnection();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->defaults = $this->getMockBuilder(Defaults::class)->disableOriginalConstructor()->getMock();
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(IConfig::class);

		$this->userManager->expects($this->any())->method('userExists')->willReturn(true);

		//Empty share table
		$this->dbConn->getQueryBuilder()->delete('share')->execute();

		$this->provider = new DefaultShareProvider(
			$this->dbConn,
			$this->userManager,
			$this->groupManager,
			$this->rootFolder,
			$this->mailer,
			$this->defaults,
			$this->l10nFactory,
			$this->urlGenerator,
			$this->config
		);
	}

	protected function tearDown(): void {
		$this->dbConn->getQueryBuilder()->delete('share')->execute();
		$this->dbConn->getQueryBuilder()->delete('filecache')->execute();
		$this->dbConn->getQueryBuilder()->delete('storages')->execute();
	}

	/**
	 * @param int $shareType
	 * @param string $sharedWith
	 * @param string $sharedBy
	 * @param string $shareOwner
	 * @param string $itemType
	 * @param int $fileSource
	 * @param string $fileTarget
	 * @param int $permissions
	 * @param $token
	 * @param $expiration
	 * @return int
	 */
	private function addShareToDB($shareType, $sharedWith, $sharedBy, $shareOwner,
			$itemType, $fileSource, $fileTarget, $permissions, $token, $expiration,
			$parent = null) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share');

		if ($shareType) {
			$qb->setValue('share_type', $qb->expr()->literal($shareType));
		}
		if ($sharedWith) {
			$qb->setValue('share_with', $qb->expr()->literal($sharedWith));
		}
		if ($sharedBy) {
			$qb->setValue('uid_initiator', $qb->expr()->literal($sharedBy));
		}
		if ($shareOwner) {
			$qb->setValue('uid_owner', $qb->expr()->literal($shareOwner));
		}
		if ($itemType) {
			$qb->setValue('item_type', $qb->expr()->literal($itemType));
		}
		if ($fileSource) {
			$qb->setValue('file_source', $qb->expr()->literal($fileSource));
		}
		if ($fileTarget) {
			$qb->setValue('file_target', $qb->expr()->literal($fileTarget));
		}
		if ($permissions) {
			$qb->setValue('permissions', $qb->expr()->literal($permissions));
		}
		if ($token) {
			$qb->setValue('token', $qb->expr()->literal($token));
		}
		if ($expiration) {
			$qb->setValue('expiration', $qb->createNamedParameter($expiration, IQueryBuilder::PARAM_DATE));
		}
		if ($parent) {
			$qb->setValue('parent', $qb->expr()->literal($parent));
		}

		$this->assertEquals(1, $qb->execute());
		return$qb->getLastInsertId();
	}




	public function testGetShareByIdNotExist() {
		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);

		$this->provider->getShareById(1);
	}

	public function testGetShareByIdUserShare() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		$id = $qb->getLastInsertId();

		$sharedBy = $this->createMock(IUser::class);
		$sharedBy->method('getUID')->willReturn('sharedBy');
		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$ownerPath = $this->createMock(File::class);
		$shareOwnerFolder = $this->createMock(Folder::class);
		$shareOwnerFolder->method('getById')->with(42)->willReturn([$ownerPath]);

		$this->rootFolder
			->method('getUserFolder')
			->willReturnMap([
				['shareOwner', $shareOwnerFolder],
			]);

		$share = $this->provider->getShareById($id);

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals($ownerPath, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetShareByIdLazy() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		$id = $qb->getLastInsertId();

		$this->rootFolder->expects($this->never())->method('getUserFolder');

		$share = $this->provider->getShareById($id);

		// We do not fetch the node so the rootfolder is never called.

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetShareByIdLazy2() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		$id = $qb->getLastInsertId();

		$ownerPath = $this->createMock(File::class);

		$shareOwnerFolder = $this->createMock(Folder::class);
		$shareOwnerFolder->method('getById')->with(42)->willReturn([$ownerPath]);

		$this->rootFolder
			->method('getUserFolder')
			->with('shareOwner')
			->willReturn($shareOwnerFolder);

		$share = $this->provider->getShareById($id);

		// We fetch the node so the root folder is eventually called

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals($ownerPath, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetShareByIdGroupShare() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());

		// Get the id
		$id = $qb->getLastInsertId();

		$ownerPath = $this->createMock(Folder::class);
		$shareOwnerFolder = $this->createMock(Folder::class);
		$shareOwnerFolder->method('getById')->with(42)->willReturn([$ownerPath]);

		$this->rootFolder
				->method('getUserFolder')
				->willReturnMap([
					['shareOwner', $shareOwnerFolder],
				]);

		$share = $this->provider->getShareById($id);

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(IShare::TYPE_GROUP, $share->getShareType());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals($ownerPath, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetShareByIdUserGroupShare() {
		$id = $this->addShareToDB(IShare::TYPE_GROUP, 'group0', 'user0', 'user0', 'file', 42, 'myTarget', 31, null, null);
		$this->addShareToDB(2, 'user1', 'user0', 'user0', 'file', 42, 'userTarget', 0, null, null, $id);

		$user0 = $this->createMock(IUser::class);
		$user0->method('getUID')->willReturn('user0');
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');

		$group0 = $this->createMock(IGroup::class);
		$group0->method('inGroup')->with($user1)->willReturn(true);

		$node = $this->createMock(Folder::class);
		$node->method('getId')->willReturn(42);

		$this->rootFolder->method('getUserFolder')->with('user0')->willReturnSelf();
		$this->rootFolder->method('getById')->willReturn([$node]);

		$this->userManager->method('get')->willReturnMap([
			['user0', $user0],
			['user1', $user1],
		]);
		$this->groupManager->method('get')->with('group0')->willReturn($group0);

		$share = $this->provider->getShareById($id, 'user1');

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(IShare::TYPE_GROUP, $share->getShareType());
		$this->assertSame('group0', $share->getSharedWith());
		$this->assertSame('user0', $share->getSharedBy());
		$this->assertSame('user0', $share->getShareOwner());
		$this->assertSame($node, $share->getNode());
		$this->assertEquals(0, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals(null, $share->getExpirationDate());
		$this->assertEquals('userTarget', $share->getTarget());
	}

	public function testGetShareByIdLinkShare() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_LINK),
				'password' => $qb->expr()->literal('password'),
				'password_by_talk' => $qb->expr()->literal(true),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
				'token' => $qb->expr()->literal('token'),
				'expiration' => $qb->expr()->literal('2000-01-02 00:00:00'),
			]);
		$this->assertEquals(1, $qb->execute());

		$id = $qb->getLastInsertId();

		$ownerPath = $this->createMock(Folder::class);
		$shareOwnerFolder = $this->createMock(Folder::class);
		$shareOwnerFolder->method('getById')->with(42)->willReturn([$ownerPath]);

		$this->rootFolder
				->method('getUserFolder')
				->willReturnMap([
					['shareOwner', $shareOwnerFolder],
				]);

		$share = $this->provider->getShareById($id);

		$this->assertEquals($id, $share->getId());
		$this->assertEquals(IShare::TYPE_LINK, $share->getShareType());
		$this->assertNull($share->getSharedWith());
		$this->assertEquals('password', $share->getPassword());
		$this->assertEquals(true, $share->getSendPasswordByTalk());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals($ownerPath, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals('token', $share->getToken());
		$this->assertEquals(\DateTime::createFromFormat('Y-m-d H:i:s', '2000-01-02 00:00:00'), $share->getExpirationDate());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testDeleteSingleShare() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());

		$id = $qb->getLastInsertId();

		$share = $this->createMock(IShare::class);
		$share->method('getId')->willReturn($id);

		/** @var DefaultShareProvider $provider */
		$provider = $this->getMockBuilder(DefaultShareProvider::class)
			->setConstructorArgs([
				$this->dbConn,
				$this->userManager,
				$this->groupManager,
				$this->rootFolder,
				$this->mailer,
				$this->defaults,
				$this->l10nFactory,
				$this->urlGenerator,
				$this->config
			])
			->setMethods(['getShareById'])
			->getMock();

		$provider->delete($share);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$cursor = $qb->execute();
		$result = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertEmpty($result);
	}

	public function testDeleteSingleShareLazy() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());

		$id = $qb->getLastInsertId();

		$this->rootFolder->expects($this->never())->method($this->anything());

		$share = $this->provider->getShareById($id);
		$this->provider->delete($share);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$cursor = $qb->execute();
		$result = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertEmpty($result);
	}

	public function testDeleteGroupShareWithUserGroupShares() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(2),
				'share_with' => $qb->expr()->literal('sharedWithUser'),
				'uid_owner' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		$share = $this->createMock(IShare::class);
		$share->method('getId')->willReturn($id);
		$share->method('getShareType')->willReturn(IShare::TYPE_GROUP);

		/** @var DefaultShareProvider $provider */
		$provider = $this->getMockBuilder(DefaultShareProvider::class)
			->setConstructorArgs([
				$this->dbConn,
				$this->userManager,
				$this->groupManager,
				$this->rootFolder,
				$this->mailer,
				$this->defaults,
				$this->l10nFactory,
				$this->urlGenerator,
				$this->config
			])
			->setMethods(['getShareById'])
			->getMock();

		$provider->delete($share);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$cursor = $qb->execute();
		$result = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertEmpty($result);
	}

	public function testGetChildren() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		// Get the id
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('user1'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('user2'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2),
				'parent' => $qb->expr()->literal($id),
			]);
		$qb->execute();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('group1'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('user3'),
				'item_type' => $qb->expr()->literal('folder'),
				'file_source' => $qb->expr()->literal(3),
				'file_target' => $qb->expr()->literal('myTarget2'),
				'permissions' => $qb->expr()->literal(4),
				'parent' => $qb->expr()->literal($id),
			]);
		$qb->execute();

		$ownerPath = $this->createMock(Folder::class);
		$ownerFolder = $this->createMock(Folder::class);
		$ownerFolder->method('getById')->willReturn([$ownerPath]);

		$this->rootFolder
			->method('getUserFolder')
			->willReturnMap([
				['shareOwner', $ownerFolder],
			]);

		$share = $this->createMock(IShare::class);
		$share->method('getId')->willReturn($id);

		$children = $this->provider->getChildren($share);

		$this->assertCount(2, $children);

		//Child1
		$this->assertEquals(IShare::TYPE_USER, $children[0]->getShareType());
		$this->assertEquals('user1', $children[0]->getSharedWith());
		$this->assertEquals('user2', $children[0]->getSharedBy());
		$this->assertEquals('shareOwner', $children[0]->getShareOwner());
		$this->assertEquals($ownerPath, $children[0]->getNode());
		$this->assertEquals(2, $children[0]->getPermissions());
		$this->assertEquals(null, $children[0]->getToken());
		$this->assertEquals(null, $children[0]->getExpirationDate());
		$this->assertEquals('myTarget1', $children[0]->getTarget());

		//Child2
		$this->assertEquals(IShare::TYPE_GROUP, $children[1]->getShareType());
		$this->assertEquals('group1', $children[1]->getSharedWith());
		$this->assertEquals('user3', $children[1]->getSharedBy());
		$this->assertEquals('shareOwner', $children[1]->getShareOwner());
		$this->assertEquals($ownerPath, $children[1]->getNode());
		$this->assertEquals(4, $children[1]->getPermissions());
		$this->assertEquals(null, $children[1]->getToken());
		$this->assertEquals(null, $children[1]->getExpirationDate());
		$this->assertEquals('myTarget2', $children[1]->getTarget());
	}

	public function testCreateUserShare() {
		$share = new \OC\Share20\Share($this->rootFolder, $this->userManager);

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(100);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->createMock(Folder::class);
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->willReturnMap([
				['sharedBy', $userFolder],
				['shareOwner', $ownerFolder],
			]);

		$userFolder->method('getById')
			->with(100)
			->willReturn([$path]);
		$ownerFolder->method('getById')
			->with(100)
			->willReturn([$path]);

		$share->setShareType(IShare::TYPE_USER);
		$share->setSharedWith('sharedWith');
		$share->setSharedBy('sharedBy');
		$share->setShareOwner('shareOwner');
		$share->setNode($path);
		$share->setSharedWithDisplayName('Displayed Name');
		$share->setSharedWithAvatar('/path/to/image.svg');
		$share->setPermissions(1);
		$share->setTarget('/target');

		$share2 = $this->provider->create($share);

		$this->assertNotNull($share2->getId());
		$this->assertSame('ocinternal:'.$share2->getId(), $share2->getFullId());
		$this->assertSame(IShare::TYPE_USER, $share2->getShareType());
		$this->assertSame('sharedWith', $share2->getSharedWith());
		$this->assertSame('sharedBy', $share2->getSharedBy());
		$this->assertSame('shareOwner', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
		$this->assertSame('/target', $share2->getTarget());
		$this->assertLessThanOrEqual(new \DateTime(), $share2->getShareTime());
		$this->assertSame($path, $share2->getNode());

		// nothing from setSharedWithDisplayName/setSharedWithAvatar is saved in DB
		$this->assertSame('Displayed Name', $share->getSharedWithDisplayName());
		$this->assertSame('/path/to/image.svg', $share->getSharedWithAvatar());
		$this->assertSame(null, $share2->getSharedWithDisplayName());
		$this->assertSame(null, $share2->getSharedWithAvatar());
	}

	public function testCreateGroupShare() {
		$share = new \OC\Share20\Share($this->rootFolder, $this->userManager);

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$path = $this->createMock(Folder::class);
		$path->method('getId')->willReturn(100);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->createMock(Folder::class);
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder
			->method('getUserFolder')
			->willReturnMap([
				['sharedBy', $userFolder],
				['shareOwner', $ownerFolder],
			]);

		$userFolder->method('getById')
			->with(100)
			->willReturn([$path]);
		$ownerFolder->method('getById')
			->with(100)
			->willReturn([$path]);

		$share->setShareType(IShare::TYPE_GROUP);
		$share->setSharedWith('sharedWith');
		$share->setSharedBy('sharedBy');
		$share->setShareOwner('shareOwner');
		$share->setNode($path);
		$share->setPermissions(1);
		$share->setSharedWithDisplayName('Displayed Name');
		$share->setSharedWithAvatar('/path/to/image.svg');
		$share->setTarget('/target');

		$share2 = $this->provider->create($share);

		$this->assertNotNull($share2->getId());
		$this->assertSame('ocinternal:'.$share2->getId(), $share2->getFullId());
		$this->assertSame(IShare::TYPE_GROUP, $share2->getShareType());
		$this->assertSame('sharedWith', $share2->getSharedWith());
		$this->assertSame('sharedBy', $share2->getSharedBy());
		$this->assertSame('shareOwner', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
		$this->assertSame('/target', $share2->getTarget());
		$this->assertLessThanOrEqual(new \DateTime(), $share2->getShareTime());
		$this->assertSame($path, $share2->getNode());

		// nothing from setSharedWithDisplayName/setSharedWithAvatar is saved in DB
		$this->assertSame('Displayed Name', $share->getSharedWithDisplayName());
		$this->assertSame('/path/to/image.svg', $share->getSharedWithAvatar());
		$this->assertSame(null, $share2->getSharedWithDisplayName());
		$this->assertSame(null, $share2->getSharedWithAvatar());
	}

	public function testCreateLinkShare() {
		$share = new \OC\Share20\Share($this->rootFolder, $this->userManager);

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$path = $this->createMock(Folder::class);
		$path->method('getId')->willReturn(100);
		$path->method('getOwner')->willReturn($shareOwner);

		$ownerFolder = $this->createMock(Folder::class);
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder
				->method('getUserFolder')
				->willReturnMap([
					['sharedBy', $userFolder],
					['shareOwner', $ownerFolder],
				]);

		$userFolder->method('getById')
				->with(100)
				->willReturn([$path]);
		$ownerFolder->method('getById')
				->with(100)
				->willReturn([$path]);

		$share->setShareType(IShare::TYPE_LINK);
		$share->setSharedBy('sharedBy');
		$share->setShareOwner('shareOwner');
		$share->setNode($path);
		$share->setPermissions(1);
		$share->setPassword('password');
		$share->setSendPasswordByTalk(true);
		$share->setToken('token');
		$expireDate = new \DateTime();
		$share->setExpirationDate($expireDate);
		$share->setTarget('/target');

		$share2 = $this->provider->create($share);

		$this->assertNotNull($share2->getId());
		$this->assertSame('ocinternal:'.$share2->getId(), $share2->getFullId());
		$this->assertSame(IShare::TYPE_LINK, $share2->getShareType());
		$this->assertSame('sharedBy', $share2->getSharedBy());
		$this->assertSame('shareOwner', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
		$this->assertSame('/target', $share2->getTarget());
		$this->assertLessThanOrEqual(new \DateTime(), $share2->getShareTime());
		$this->assertSame($path, $share2->getNode());
		$this->assertSame('password', $share2->getPassword());
		$this->assertSame(true, $share2->getSendPasswordByTalk());
		$this->assertSame('token', $share2->getToken());
		$this->assertEquals($expireDate->getTimestamp(), $share2->getExpirationDate()->getTimestamp());
	}

	public function testGetShareByToken() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_LINK),
				'password' => $qb->expr()->literal('password'),
				'password_by_talk' => $qb->expr()->literal(true),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
				'token' => $qb->expr()->literal('secrettoken'),
			]);
		$qb->execute();
		$id = $qb->getLastInsertId();

		$file = $this->createMock(File::class);

		$this->rootFolder->method('getUserFolder')->with('shareOwner')->willReturnSelf();
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getShareByToken('secrettoken');
		$this->assertEquals($id, $share->getId());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('sharedBy', $share->getSharedBy());
		$this->assertSame('secrettoken', $share->getToken());
		$this->assertSame('password', $share->getPassword());
		$this->assertSame(true, $share->getSendPasswordByTalk());
		$this->assertSame(null, $share->getSharedWith());
	}


	public function testGetShareByTokenNotFound() {
		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);

		$this->provider->getShareByToken('invalidtoken');
	}

	private function createTestStorageEntry($storageStringId) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('storages')
			->values([
				'id' => $qb->expr()->literal($storageStringId),
			]);
		$this->assertEquals(1, $qb->execute());
		return $qb->getLastInsertId();
	}

	private function createTestFileEntry($path, $storage = 1) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('filecache')
			->values([
				'storage' => $qb->expr()->literal($storage),
				'path' => $qb->expr()->literal($path),
				'path_hash' => $qb->expr()->literal(md5($path)),
				'name' => $qb->expr()->literal(basename($path)),
			]);
		$this->assertEquals(1, $qb->execute());
		return $qb->getLastInsertId();
	}

	public function storageAndFileNameProvider() {
		return [
			// regular file on regular storage
			['home::shareOwner', 'files/test.txt', 'files/test2.txt'],
			// regular file on external storage
			['smb::whatever', 'files/test.txt', 'files/test2.txt'],
			// regular file on external storage in trashbin-like folder,
			['smb::whatever', 'files_trashbin/files/test.txt', 'files_trashbin/files/test2.txt'],
		];
	}

	/**
	 * @dataProvider storageAndFileNameProvider
	 */
	public function testGetSharedWithUser($storageStringId, $fileName1, $fileName2) {
		$storageId = $this->createTestStorageEntry($storageStringId);
		$fileId = $this->createTestFileEntry($fileName1, $storageId);
		$fileId2 = $this->createTestFileEntry($fileName2, $storageId);
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal($fileId),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith2'),
				'uid_owner' => $qb->expr()->literal('shareOwner2'),
				'uid_initiator' => $qb->expr()->literal('sharedBy2'),
				'item_type' => $qb->expr()->literal('file2'),
				'file_source' => $qb->expr()->literal($fileId2),
				'file_target' => $qb->expr()->literal('myTarget2'),
				'permissions' => $qb->expr()->literal(14),
			]);
		$this->assertEquals(1, $qb->execute());

		$file = $this->createMock(File::class);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->willReturnSelf();
		$this->rootFolder->method('getById')->with($fileId)->willReturn([$file]);

		$share = $this->provider->getSharedWith('sharedWith', IShare::TYPE_USER, null, 1 , 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
	}

	/**
	 * @dataProvider storageAndFileNameProvider
	 */
	public function testGetSharedWithGroup($storageStringId, $fileName1, $fileName2) {
		$storageId = $this->createTestStorageEntry($storageStringId);
		$fileId = $this->createTestFileEntry($fileName1, $storageId);
		$fileId2 = $this->createTestFileEntry($fileName2, $storageId);
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner2'),
				'uid_initiator' => $qb->expr()->literal('sharedBy2'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal($fileId2),
				'file_target' => $qb->expr()->literal('myTarget2'),
				'permissions' => $qb->expr()->literal(14),
			]);
		$this->assertEquals(1, $qb->execute());

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal($fileId),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$groups = [];
		foreach (range(0, 100) as $i) {
			$groups[] = 'group'.$i;
		}

		$groups[] = 'sharedWith';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('sharedWith');
		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('shareOwner');
		$initiator = $this->createMock(IUser::class);
		$initiator->method('getUID')->willReturn('sharedBy');

		$this->userManager->method('get')->willReturnMap([
			['sharedWith', $user],
			['shareOwner', $owner],
			['sharedBy', $initiator],
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn($groups);

		$file = $this->createMock(File::class);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->willReturnSelf();
		$this->rootFolder->method('getById')->with($fileId)->willReturn([$file]);

		$share = $this->provider->getSharedWith('sharedWith', IShare::TYPE_GROUP, null, 20 , 1);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals(IShare::TYPE_GROUP, $share->getShareType());
	}

	/**
	 * @dataProvider storageAndFileNameProvider
	 */
	public function testGetSharedWithGroupUserModified($storageStringId, $fileName1, $fileName2) {
		$storageId = $this->createTestStorageEntry($storageStringId);
		$fileId = $this->createTestFileEntry($fileName1, $storageId);
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal($fileId),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		/*
		 * Wrong share. Should not be taken by code.
		 */
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(2),
				'share_with' => $qb->expr()->literal('user2'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal($fileId),
				'file_target' => $qb->expr()->literal('wrongTarget'),
				'permissions' => $qb->expr()->literal(31),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		/*
		 * Correct share. should be taken by code path.
		 */
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(2),
				'share_with' => $qb->expr()->literal('user'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal($fileId),
				'file_target' => $qb->expr()->literal('userTarget'),
				'permissions' => $qb->expr()->literal(0),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		$groups = ['sharedWith'];

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user');
		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('shareOwner');
		$initiator = $this->createMock(IUser::class);
		$initiator->method('getUID')->willReturn('sharedBy');

		$this->userManager->method('get')->willReturnMap([
			['user', $user],
			['shareOwner', $owner],
			['sharedBy', $initiator],
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn($groups);

		$file = $this->createMock(File::class);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->willReturnSelf();
		$this->rootFolder->method('getById')->with($fileId)->willReturn([$file]);

		$share = $this->provider->getSharedWith('user', IShare::TYPE_GROUP, null, -1, 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertSame((string)$id, $share->getId());
		$this->assertSame('sharedWith', $share->getSharedWith());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('sharedBy', $share->getSharedBy());
		$this->assertSame(IShare::TYPE_GROUP, $share->getShareType());
		$this->assertSame(0, $share->getPermissions());
		$this->assertSame('userTarget', $share->getTarget());
	}

	/**
	 * @dataProvider storageAndFileNameProvider
	 */
	public function testGetSharedWithUserWithNode($storageStringId, $fileName1, $fileName2) {
		$storageId = $this->createTestStorageEntry($storageStringId);
		$fileId = $this->createTestFileEntry($fileName1, $storageId);
		$fileId2 = $this->createTestFileEntry($fileName2, $storageId);
		$this->addShareToDB(IShare::TYPE_USER, 'user0', 'user1', 'user1',
			'file', $fileId, 'myTarget', 31, null, null, null);
		$id = $this->addShareToDB(IShare::TYPE_USER, 'user0', 'user1', 'user1',
			'file', $fileId2, 'myTarget', 31, null, null, null);

		$user0 = $this->createMock(IUser::class);
		$user0->method('getUID')->willReturn('user0');
		$user0->method('getDisplayName')->willReturn('user0');
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user0->method('getDisplayName')->willReturn('user0');

		$this->userManager->method('get')->willReturnMap([
			['user0', $user0],
			['user1', $user1],
		]);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn($fileId2);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with($fileId2)->willReturn([$file]);

		$share = $this->provider->getSharedWith('user0', IShare::TYPE_USER, $file, -1, 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertSame('user0', $share->getSharedWith());
		$this->assertSame('user1', $share->getShareOwner());
		$this->assertSame('user1', $share->getSharedBy());
		$this->assertSame($file, $share->getNode());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
	}

	/**
	 * @dataProvider storageAndFileNameProvider
	 */
	public function testGetSharedWithGroupWithNode($storageStringId, $fileName1, $fileName2) {
		$storageId = $this->createTestStorageEntry($storageStringId);
		$fileId = $this->createTestFileEntry($fileName1, $storageId);
		$fileId2 = $this->createTestFileEntry($fileName2, $storageId);
		$this->addShareToDB(IShare::TYPE_GROUP, 'group0', 'user1', 'user1',
			'file', $fileId, 'myTarget', 31, null, null, null);
		$id = $this->addShareToDB(IShare::TYPE_GROUP, 'group0', 'user1', 'user1',
			'file', $fileId2, 'myTarget', 31, null, null, null);

		$user0 = $this->createMock(IUser::class);
		$user0->method('getUID')->willReturn('user0');
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');

		$this->userManager->method('get')->willReturnMap([
			['user0', $user0],
			['user1', $user1],
		]);

		$this->groupManager->method('getUserGroupIds')->with($user0)->willReturn(['group0']);

		$node = $this->createMock(Folder::class);
		$node->method('getId')->willReturn($fileId2);
		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with($fileId2)->willReturn([$node]);

		$share = $this->provider->getSharedWith('user0', IShare::TYPE_GROUP, $node, -1, 0);
		$this->assertCount(1, $share);

		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertSame('group0', $share->getSharedWith());
		$this->assertSame('user1', $share->getShareOwner());
		$this->assertSame('user1', $share->getSharedBy());
		$this->assertSame($node, $share->getNode());
		$this->assertEquals(IShare::TYPE_GROUP, $share->getShareType());
	}

	public function shareTypesProvider() {
		return [
			[IShare::TYPE_USER, false],
			[IShare::TYPE_GROUP, false],
			[IShare::TYPE_USER, true],
			[IShare::TYPE_GROUP, true],
		];
	}

	/**
	 * @dataProvider shareTypesProvider
	 */
	public function testGetSharedWithWithDeletedFile($shareType, $trashed) {
		if ($trashed) {
			// exists in database but is in trash
			$storageId = $this->createTestStorageEntry('home::shareOwner');
			$deletedFileId = $this->createTestFileEntry('files_trashbin/files/test.txt.d1465553223', $storageId);
		} else {
			// fileid that doesn't exist in the database
			$deletedFileId = 123;
		}
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal($shareType),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal($deletedFileId),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());

		$file = $this->createMock(File::class);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->willReturnSelf();
		$this->rootFolder->method('getById')->with($deletedFileId)->willReturn([$file]);

		$groups = [];
		foreach (range(0, 100) as $i) {
			$groups[] = 'group'.$i;
		}

		$groups[] = 'sharedWith';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('sharedWith');
		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('shareOwner');
		$initiator = $this->createMock(IUser::class);
		$initiator->method('getUID')->willReturn('sharedBy');

		$this->userManager->method('get')->willReturnMap([
			['sharedWith', $user],
			['shareOwner', $owner],
			['sharedBy', $initiator],
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn($groups);

		$share = $this->provider->getSharedWith('sharedWith', $shareType, null, 1 , 0);
		$this->assertCount(0, $share);
	}

	public function testGetSharesBy() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy2'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('userTarget'),
				'permissions' => $qb->expr()->literal(0),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		$file = $this->createMock(File::class);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->willReturnSelf();
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getSharesBy('sharedBy', IShare::TYPE_USER, null, false, 1, 0);
		$this->assertCount(1, $share);

		/** @var IShare $share */
		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetSharesNode() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(43),
				'file_target' => $qb->expr()->literal('userTarget'),
				'permissions' => $qb->expr()->literal(0),
				'parent' => $qb->expr()->literal($id),
			]);
		$this->assertEquals(1, $qb->execute());

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(42);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->willReturnSelf();
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$share = $this->provider->getSharesBy('sharedBy', IShare::TYPE_USER, $file, false, 1, 0);
		$this->assertCount(1, $share);

		/** @var IShare $share */
		$share = $share[0];
		$this->assertEquals($id, $share->getId());
		$this->assertEquals('sharedWith', $share->getSharedWith());
		$this->assertEquals('shareOwner', $share->getShareOwner());
		$this->assertEquals('sharedBy', $share->getSharedBy());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals('myTarget', $share->getTarget());
	}

	public function testGetSharesReshare() {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('shareOwner'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$this->assertEquals(1, $qb->execute());
		$id1 = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith'),
				'uid_owner' => $qb->expr()->literal('shareOwner'),
				'uid_initiator' => $qb->expr()->literal('sharedBy'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('userTarget'),
				'permissions' => $qb->expr()->literal(0),
			]);
		$this->assertEquals(1, $qb->execute());
		$id2 = $qb->getLastInsertId();

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(42);
		$this->rootFolder->method('getUserFolder')->with('shareOwner')->willReturnSelf();
		$this->rootFolder->method('getById')->with(42)->willReturn([$file]);

		$shares = $this->provider->getSharesBy('shareOwner', IShare::TYPE_USER, null, true, -1, 0);
		$this->assertCount(2, $shares);

		/** @var IShare $share */
		$share = $shares[0];
		$this->assertEquals($id1, $share->getId());
		$this->assertSame('sharedWith', $share->getSharedWith());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('shareOwner', $share->getSharedBy());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals('myTarget', $share->getTarget());

		$share = $shares[1];
		$this->assertEquals($id2, $share->getId());
		$this->assertSame('sharedWith', $share->getSharedWith());
		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertSame('sharedBy', $share->getSharedBy());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
		$this->assertEquals(0, $share->getPermissions());
		$this->assertEquals('userTarget', $share->getTarget());
	}

	public function testDeleteFromSelfGroupNoCustomShare() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('group'),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->willReturnMap([
			['user1', $user1],
			['user2', $user2],
		]);

		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('group');
		$group->method('inGroup')->with($user2)->willReturn(true);
		$this->groupManager->method('get')->with('group')->willReturn($group);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(2)))
			->execute();

		$shares = $stmt->fetchAll();
		$stmt->closeCursor();

		$this->assertCount(1, $shares);
		$share2 = $shares[0];
		$this->assertEquals($id, $share2['parent']);
		$this->assertEquals(0, $share2['permissions']);
		$this->assertEquals('user2', $share2['share_with']);
	}

	public function testDeleteFromSelfGroupAlreadyCustomShare() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('group'),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(2),
				'share_with' => $qb->expr()->literal('user2'),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2),
				'parent' => $qb->expr()->literal($id),
			])->execute();
		$this->assertEquals(1, $stmt);

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->willReturnMap([
			['user1', $user1],
			['user2', $user2],
		]);

		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('group');
		$group->method('inGroup')->with($user2)->willReturn(true);
		$this->groupManager->method('get')->with('group')->willReturn($group);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(2)))
			->execute();

		$shares = $stmt->fetchAll();
		$stmt->closeCursor();

		$this->assertCount(1, $shares);
		$share2 = $shares[0];
		$this->assertEquals($id, $share2['parent']);
		$this->assertEquals(0, $share2['permissions']);
		$this->assertEquals('user2', $share2['share_with']);
	}


	public function testDeleteFromSelfGroupUserNotInGroup() {
		$this->expectException(\OC\Share20\Exception\ProviderException::class);
		$this->expectExceptionMessage('Recipient not in receiving group');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('group'),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->willReturnMap([
			['user1', $user1],
			['user2', $user2],
		]);

		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('group');
		$group->method('inGroup')->with($user2)->willReturn(false);
		$this->groupManager->method('get')->with('group')->willReturn($group);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');
	}


	public function testDeleteFromSelfGroupDoesNotExist() {
		$this->expectException(\OC\Share20\Exception\ProviderException::class);
		$this->expectExceptionMessage('Group "group" does not exist');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('group'),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$this->userManager->method('get')->willReturnMap([
			['user1', $user1],
			['user2', $user2],
		]);

		$this->groupManager->method('get')->with('group')->willReturn(null);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');
	}

	public function testDeleteFromSelfUser() {
		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('user2'),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user1->method('getDisplayName')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('getDisplayName')->willReturn('user2');
		$this->userManager->method('get')->willReturnMap([
			['user1', $user1],
			['user2', $user2],
		]);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, 'user2');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->execute();

		$shares = $stmt->fetchAll();
		$stmt->closeCursor();

		$this->assertCount(0, $shares);
	}


	public function testDeleteFromSelfUserNotRecipient() {
		$this->expectException(\OC\Share20\Exception\ProviderException::class);
		$this->expectExceptionMessage('Recipient does not match');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('user2'),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2)
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user1->method('getDisplayName')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('getDisplayName')->willReturn('user2');
		$user3 = $this->createMock(IUser::class);
		$this->userManager->method('get')->willReturnMap([
			['user1', $user1],
			['user2', $user2],
		]);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, $user3);
	}


	public function testDeleteFromSelfLink() {
		$this->expectException(\OC\Share20\Exception\ProviderException::class);
		$this->expectExceptionMessage('Invalid shareType');

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_LINK),
				'uid_owner' => $qb->expr()->literal('user1'),
				'uid_initiator' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(1),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(2),
				'token' => $qb->expr()->literal('token'),
			])->execute();
		$this->assertEquals(1, $stmt);
		$id = $qb->getLastInsertId();

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$this->userManager->method('get')->willReturnMap([
			['user1', $user1],
		]);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(1);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->with(1)->willReturn([$file]);

		$share = $this->provider->getShareById($id);

		$this->provider->deleteFromSelf($share, $user1);
	}

	public function testUpdateUser() {
		$id = $this->addShareToDB(IShare::TYPE_USER, 'user0', 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$users = [];
		for ($i = 0; $i < 6; $i++) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('user'.$i);
			$user->method('getDisplayName')->willReturn('user' . $i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->willReturnCallback(
			function ($userId) use ($users) {
				return $users[$userId];
			}
		);

		$file1 = $this->createMock(File::class);
		$file1->method('getId')->willReturn(42);
		$file2 = $this->createMock(File::class);
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->createMock(Folder::class);
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->createMock(Folder::class);
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->willReturnMap([
			['user2', $folder1],
			['user5', $folder2],
		]);

		$share = $this->provider->getShareById($id);

		$share->setSharedWith('user3');
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		$this->assertSame('user3', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());

		$share2 = $this->provider->getShareById($id);

		$this->assertEquals($id, $share2->getId());
		$this->assertSame('user3', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
	}

	public function testUpdateLink() {
		$id = $this->addShareToDB(IShare::TYPE_LINK, null, 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$users = [];
		for ($i = 0; $i < 6; $i++) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->willReturnCallback(
			function ($userId) use ($users) {
				return $users[$userId];
			}
		);

		$file1 = $this->createMock(File::class);
		$file1->method('getId')->willReturn(42);
		$file2 = $this->createMock(File::class);
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->createMock(Folder::class);
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->createMock(Folder::class);
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->willReturnMap([
			['user2', $folder1],
			['user5', $folder2],
		]);

		$share = $this->provider->getShareById($id);

		$share->setPassword('password');
		$share->setSendPasswordByTalk(true);
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		$this->assertEquals('password', $share2->getPassword());
		$this->assertSame(true, $share2->getSendPasswordByTalk());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());

		$share2 = $this->provider->getShareById($id);

		$this->assertEquals($id, $share2->getId());
		$this->assertEquals('password', $share2->getPassword());
		$this->assertSame(true, $share2->getSendPasswordByTalk());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
	}

	public function testUpdateLinkRemovePassword() {
		$id = $this->addShareToDB(IShare::TYPE_LINK, 'foo', 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->update('share');
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->set('password', $qb->createNamedParameter('password'));
		$this->assertEquals(1, $qb->execute());

		$users = [];
		for ($i = 0; $i < 6; $i++) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->willReturnCallback(
			function ($userId) use ($users) {
				return $users[$userId];
			}
		);

		$file1 = $this->createMock(File::class);
		$file1->method('getId')->willReturn(42);
		$file2 = $this->createMock(File::class);
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->createMock(Folder::class);
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->createMock(Folder::class);
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->willReturnMap([
			['user2', $folder1],
			['user5', $folder2],
		]);

		$share = $this->provider->getShareById($id);

		$share->setPassword(null);
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		$this->assertEquals(null, $share2->getPassword());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());

		$share2 = $this->provider->getShareById($id);

		$this->assertEquals($id, $share2->getId());
		$this->assertEquals(null, $share2->getPassword());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
	}

	public function testUpdateGroupNoSub() {
		$id = $this->addShareToDB(IShare::TYPE_GROUP, 'group0', 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$users = [];
		for ($i = 0; $i < 6; $i++) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->willReturnCallback(
			function ($userId) use ($users) {
				return $users[$userId];
			}
		);

		$groups = [];
		for ($i = 0; $i < 2; $i++) {
			$group = $this->createMock(IGroup::class);
			$group->method('getGID')->willReturn('group'.$i);
			$groups['group'.$i] = $group;
		}

		$this->groupManager->method('get')->willReturnCallback(
			function ($groupId) use ($groups) {
				return $groups[$groupId];
			}
		);

		$file1 = $this->createMock(File::class);
		$file1->method('getId')->willReturn(42);
		$file2 = $this->createMock(File::class);
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->createMock(Folder::class);
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->createMock(Folder::class);
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->willReturnMap([
			['user2', $folder1],
			['user5', $folder2],
		]);

		$share = $this->provider->getShareById($id);

		$share->setSharedWith('group0');
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		// Group shares do not allow updating the recipient
		$this->assertSame('group0', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());

		$share2 = $this->provider->getShareById($id);

		$this->assertEquals($id, $share2->getId());
		// Group shares do not allow updating the recipient
		$this->assertSame('group0', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());
	}

	public function testUpdateGroupSubShares() {
		$id = $this->addShareToDB(IShare::TYPE_GROUP, 'group0', 'user1', 'user2',
			'file', 42, 'target', 31, null, null);

		$id2 = $this->addShareToDB(2, 'user0', 'user1', 'user2',
			'file', 42, 'mytarget', 31, null, null, $id);

		$id3 = $this->addShareToDB(2, 'user3', 'user1', 'user2',
			'file', 42, 'mytarget2', 0, null, null, $id);

		$users = [];
		for ($i = 0; $i < 6; $i++) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('user'.$i);
			$users['user'.$i] = $user;
		}

		$this->userManager->method('get')->willReturnCallback(
			function ($userId) use ($users) {
				return $users[$userId];
			}
		);

		$groups = [];
		for ($i = 0; $i < 2; $i++) {
			$group = $this->createMock(IGroup::class);
			$group->method('getGID')->willReturn('group'.$i);
			$groups['group'.$i] = $group;
		}

		$this->groupManager->method('get')->willReturnCallback(
			function ($groupId) use ($groups) {
				return $groups[$groupId];
			}
		);

		$file1 = $this->createMock(File::class);
		$file1->method('getId')->willReturn(42);
		$file2 = $this->createMock(File::class);
		$file2->method('getId')->willReturn(43);

		$folder1 = $this->createMock(Folder::class);
		$folder1->method('getById')->with(42)->willReturn([$file1]);
		$folder2 = $this->createMock(Folder::class);
		$folder2->method('getById')->with(43)->willReturn([$file2]);

		$this->rootFolder->method('getUserFolder')->willReturnMap([
			['user2', $folder1],
			['user5', $folder2],
		]);

		$share = $this->provider->getShareById($id);

		$share->setSharedWith('group0');
		$share->setSharedBy('user4');
		$share->setShareOwner('user5');
		$share->setNode($file2);
		$share->setPermissions(1);

		$share2 = $this->provider->update($share);

		$this->assertEquals($id, $share2->getId());
		// Group shares do not allow updating the recipient
		$this->assertSame('group0', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());

		$share2 = $this->provider->getShareById($id);

		$this->assertEquals($id, $share2->getId());
		// Group shares do not allow updating the recipient
		$this->assertSame('group0', $share2->getSharedWith());
		$this->assertSame('user4', $share2->getSharedBy());
		$this->assertSame('user5', $share2->getShareOwner());
		$this->assertSame(1, $share2->getPermissions());

		$qb = $this->dbConn->getQueryBuilder();
		$stmt = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($id)))
			->orderBy('id')
			->execute();

		$shares = $stmt->fetchAll();

		$this->assertSame('user0', $shares[0]['share_with']);
		$this->assertSame('user4', $shares[0]['uid_initiator']);
		$this->assertSame('user5', $shares[0]['uid_owner']);
		$this->assertSame(1, (int)$shares[0]['permissions']);

		$this->assertSame('user3', $shares[1]['share_with']);
		$this->assertSame('user4', $shares[1]['uid_initiator']);
		$this->assertSame('user5', $shares[1]['uid_owner']);
		$this->assertSame(0, (int)$shares[1]['permissions']);


		$stmt->closeCursor();
	}

	public function testMoveUserShare() {
		$id = $this->addShareToDB(IShare::TYPE_USER, 'user0', 'user1', 'user1', 'file',
			42, 'mytaret', 31, null, null);

		$user0 = $this->createMock(IUser::class);
		$user0->method('getUID')->willReturn('user0');
		$user0->method('getDisplayName')->willReturn('user0');
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user1->method('getDisplayName')->willReturn('user1');

		$this->userManager->method('get')->willReturnMap([
			['user0', $user0],
			['user1', $user1],
		]);

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(42);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->willReturn([$file]);

		$share = $this->provider->getShareById($id, null);

		$share->setTarget('/newTarget');
		$this->provider->move($share, $user0);

		$share = $this->provider->getShareById($id, null);
		$this->assertSame('/newTarget', $share->getTarget());
	}

	public function testMoveGroupShare() {
		$id = $this->addShareToDB(IShare::TYPE_GROUP, 'group0', 'user1', 'user1', 'file',
			42, 'mytaret', 31, null, null);

		$user0 = $this->createMock(IUser::class);
		$user0->method('getUID')->willReturn('user0');
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');

		$group0 = $this->createMock(IGroup::class);
		$group0->method('getGID')->willReturn('group0');
		$group0->method('inGroup')->with($user0)->willReturn(true);

		$this->groupManager->method('get')->with('group0')->willReturn($group0);

		$this->userManager->method('get')->willReturnMap([
			['user0', $user0],
			['user1', $user1],
		]);

		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn(42);

		$this->rootFolder->method('getUserFolder')->with('user1')->willReturnSelf();
		$this->rootFolder->method('getById')->willReturn([$folder]);

		$share = $this->provider->getShareById($id, 'user0');

		$share->setTarget('/newTarget');
		$this->provider->move($share, 'user0');

		$share = $this->provider->getShareById($id, 'user0');
		$this->assertSame('/newTarget', $share->getTarget());

		$share->setTarget('/ultraNewTarget');
		$this->provider->move($share, 'user0');

		$share = $this->provider->getShareById($id, 'user0');
		$this->assertSame('/ultraNewTarget', $share->getTarget());
	}

	public function dataDeleteUser() {
		return [
			[IShare::TYPE_USER, 'a', 'b', 'c', 'a', true],
			[IShare::TYPE_USER, 'a', 'b', 'c', 'b', false],
			[IShare::TYPE_USER, 'a', 'b', 'c', 'c', true],
			[IShare::TYPE_USER, 'a', 'b', 'c', 'd', false],
			[IShare::TYPE_GROUP, 'a', 'b', 'c', 'a', true],
			[IShare::TYPE_GROUP, 'a', 'b', 'c', 'b', false],
			// The group c is still valid but user c is deleted so group share stays
			[IShare::TYPE_GROUP, 'a', 'b', 'c', 'c', false],
			[IShare::TYPE_GROUP, 'a', 'b', 'c', 'd', false],
			[IShare::TYPE_LINK, 'a', 'b', 'c', 'a', true],
			// To avoid invisible link shares delete initiated link shares as well (see #22327)
			[IShare::TYPE_LINK, 'a', 'b', 'c', 'b', true],
			[IShare::TYPE_LINK, 'a', 'b', 'c', 'c', false],
			[IShare::TYPE_LINK, 'a', 'b', 'c', 'd', false],
		];
	}

	/**
	 * @dataProvider dataDeleteUser
	 *
	 * @param int $type The shareType (user/group/link)
	 * @param string $owner The owner of the share (uid)
	 * @param string $initiator The initiator of the share (uid)
	 * @param string $recipient The recipient of the share (uid/gid/pass)
	 * @param string $deletedUser The user that is deleted
	 * @param bool $rowDeleted Is the row deleted in this setup
	 */
	public function testDeleteUser($type, $owner, $initiator, $recipient, $deletedUser, $rowDeleted) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter($type))
			->setValue('uid_owner', $qb->createNamedParameter($owner))
			->setValue('uid_initiator', $qb->createNamedParameter($initiator))
			->setValue('share_with', $qb->createNamedParameter($recipient))
			->setValue('item_type', $qb->createNamedParameter('file'))
			->setValue('item_source', $qb->createNamedParameter(42))
			->setValue('file_source', $qb->createNamedParameter(42))
			->execute();

		$id = $qb->getLastInsertId();

		$this->provider->userDeleted($deletedUser, $type);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertCount($rowDeleted ? 0 : 1, $data);
	}

	public function dataDeleteUserGroup() {
		return [
			['a', 'b', 'c', 'a', true, true],
			['a', 'b', 'c', 'b', false, false],
			['a', 'b', 'c', 'c', false, true],
			['a', 'b', 'c', 'd', false, false],
		];
	}

	/**
	 * @dataProvider dataDeleteUserGroup
	 *
	 * @param string $owner The owner of the share (uid)
	 * @param string $initiator The initiator of the share (uid)
	 * @param string $recipient The recipient of the usergroup share (uid)
	 * @param string $deletedUser The user that is deleted
	 * @param bool $groupShareDeleted
	 * @param bool $userGroupShareDeleted
	 */
	public function testDeleteUserGroup($owner, $initiator, $recipient, $deletedUser, $groupShareDeleted, $userGroupShareDeleted) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP))
			->setValue('uid_owner', $qb->createNamedParameter($owner))
			->setValue('uid_initiator', $qb->createNamedParameter($initiator))
			->setValue('share_with', $qb->createNamedParameter('group'))
			->setValue('item_type', $qb->createNamedParameter('file'))
			->setValue('item_source', $qb->createNamedParameter(42))
			->setValue('file_source', $qb->createNamedParameter(42))
			->execute();
		$groupId = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(2))
			->setValue('uid_owner', $qb->createNamedParameter($owner))
			->setValue('uid_initiator', $qb->createNamedParameter($initiator))
			->setValue('share_with', $qb->createNamedParameter($recipient))
			->setValue('item_type', $qb->createNamedParameter('file'))
			->setValue('item_source', $qb->createNamedParameter(42))
			->setValue('file_source', $qb->createNamedParameter(42))
			->execute();
		$userGroupId = $qb->getLastInsertId();

		$this->provider->userDeleted($deletedUser, IShare::TYPE_GROUP);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($userGroupId))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();
		$this->assertCount($userGroupShareDeleted ? 0 : 1, $data);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($groupId))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();
		$this->assertCount($groupShareDeleted ? 0 : 1, $data);
	}

	public function dataGroupDeleted() {
		return [
			[
				[
					'type' => IShare::TYPE_USER,
					'recipient' => 'user',
					'children' => []
				], 'group', false
			],
			[
				[
					'type' => IShare::TYPE_USER,
					'recipient' => 'user',
					'children' => []
				], 'user', false
			],
			[
				[
					'type' => IShare::TYPE_LINK,
					'recipient' => 'user',
					'children' => []
				], 'group', false
			],
			[
				[
					'type' => IShare::TYPE_GROUP,
					'recipient' => 'group1',
					'children' => [
						'foo',
						'bar'
					]
				], 'group2', false
			],
			[
				[
					'type' => IShare::TYPE_GROUP,
					'recipient' => 'group1',
					'children' => [
						'foo',
						'bar'
					]
				], 'group1', true
			],
		];
	}

	/**
	 * @dataProvider dataGroupDeleted
	 *
	 * @param $shares
	 * @param $groupToDelete
	 * @param $shouldBeDeleted
	 */
	public function testGroupDeleted($shares, $groupToDelete, $shouldBeDeleted) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter($shares['type']))
			->setValue('uid_owner', $qb->createNamedParameter('owner'))
			->setValue('uid_initiator', $qb->createNamedParameter('initiator'))
			->setValue('share_with', $qb->createNamedParameter($shares['recipient']))
			->setValue('item_type', $qb->createNamedParameter('file'))
			->setValue('item_source', $qb->createNamedParameter(42))
			->setValue('file_source', $qb->createNamedParameter(42))
			->execute();
		$ids = [$qb->getLastInsertId()];

		foreach ($shares['children'] as $child) {
			$qb = $this->dbConn->getQueryBuilder();
			$qb->insert('share')
				->setValue('share_type', $qb->createNamedParameter(2))
				->setValue('uid_owner', $qb->createNamedParameter('owner'))
				->setValue('uid_initiator', $qb->createNamedParameter('initiator'))
				->setValue('share_with', $qb->createNamedParameter($child))
				->setValue('item_type', $qb->createNamedParameter('file'))
				->setValue('item_source', $qb->createNamedParameter(42))
				->setValue('file_source', $qb->createNamedParameter(42))
				->setValue('parent', $qb->createNamedParameter($ids[0]))
				->execute();
			$ids[] = $qb->getLastInsertId();
		}

		$this->provider->groupDeleted($groupToDelete);

		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select('*')
			->from('share')
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->execute();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertCount($shouldBeDeleted ? 0 : count($ids), $data);
	}

	public function dataUserDeletedFromGroup() {
		return [
			['group1', 'user1', true],
			['group1', 'user2', false],
			['group2', 'user1', false],
		];
	}

	/**
	 * Given a group share with 'group1'
	 * And a user specific group share with 'user1'.
	 * User $user is deleted from group $gid.
	 *
	 * @dataProvider dataUserDeletedFromGroup
	 *
	 * @param string $group
	 * @param string $user
	 * @param bool $toDelete
	 */
	public function testUserDeletedFromGroup($group, $user, $toDelete) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP))
			->setValue('uid_owner', $qb->createNamedParameter('owner'))
			->setValue('uid_initiator', $qb->createNamedParameter('initiator'))
			->setValue('share_with', $qb->createNamedParameter('group1'))
			->setValue('item_type', $qb->createNamedParameter('file'))
			->setValue('item_source', $qb->createNamedParameter(42))
			->setValue('file_source', $qb->createNamedParameter(42));
		$qb->execute();
		$id1 = $qb->getLastInsertId();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(2))
			->setValue('uid_owner', $qb->createNamedParameter('owner'))
			->setValue('uid_initiator', $qb->createNamedParameter('initiator'))
			->setValue('share_with', $qb->createNamedParameter('user1'))
			->setValue('item_type', $qb->createNamedParameter('file'))
			->setValue('item_source', $qb->createNamedParameter(42))
			->setValue('file_source', $qb->createNamedParameter(42))
			->setValue('parent', $qb->createNamedParameter($id1));
		$qb->execute();
		$id2 = $qb->getLastInsertId();

		$this->provider->userDeletedFromGroup($user, $group);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id2)));
		$cursor = $qb->execute();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();

		$this->assertCount($toDelete ? 0 : 1, $data);
	}

	public function testGetSharesInFolder() {
		$userManager = \OC::$server->getUserManager();
		$groupManager = \OC::$server->getGroupManager();
		$rootFolder = \OC::$server->getRootFolder();

		$provider = new DefaultShareProvider(
			$this->dbConn,
			$userManager,
			$groupManager,
			$rootFolder,
			$this->mailer,
			$this->defaults,
			$this->l10nFactory,
			$this->urlGenerator,
			$this->config
		);

		$password = md5(time());

		$u1 = $userManager->createUser('testShare1', $password);
		$u2 = $userManager->createUser('testShare2', $password);
		$u3 = $userManager->createUser('testShare3', $password);

		$g1 = $groupManager->createGroup('group1');

		$u1Folder = $rootFolder->getUserFolder($u1->getUID());
		$folder1 = $u1Folder->newFolder('foo');
		$file1 = $folder1->newFile('bar');
		$folder2 = $folder1->newFolder('baz');

		$shareManager = \OC::$server->getShareManager();
		$share1 = $shareManager->newShare();
		$share1->setNode($folder1)
			->setSharedBy($u1->getUID())
			->setSharedWith($u2->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_USER)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share1 = $this->provider->create($share1);

		$share2 = $shareManager->newShare();
		$share2->setNode($file1)
			->setSharedBy($u2->getUID())
			->setSharedWith($u3->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_USER)
			->setPermissions(\OCP\Constants::PERMISSION_READ);
		$share2 = $this->provider->create($share2);

		$share3 = $shareManager->newShare();
		$share3->setNode($folder2)
			->setSharedBy($u2->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(\OCP\Constants::PERMISSION_READ);
		$share3 = $this->provider->create($share3);

		$share4 = $shareManager->newShare();
		$share4->setNode($folder2)
			->setSharedBy($u1->getUID())
			->setSharedWith($g1->getGID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_GROUP)
			->setPermissions(\OCP\Constants::PERMISSION_READ);
		$share4 = $this->provider->create($share4);

		$result = $provider->getSharesInFolder($u1->getUID(), $folder1, false);
		$this->assertCount(1, $result);
		$shares = array_pop($result);
		$this->assertCount(1, $shares);
		$this->assertSame($folder2->getId(), $shares[0]->getNodeId());

		$result = $provider->getSharesInFolder($u1->getUID(), $folder1, true);
		$this->assertCount(2, $result);

		$file_shares = $result[$file1->getId()];
		$this->assertCount(1, $file_shares);
		$this->assertSame($file1->getId(), $file_shares[0]->getNodeId());
		$this->assertSame(IShare::TYPE_USER, $file_shares[0]->getShareType());

		$folder_shares = $result[$folder2->getId()];
		$this->assertCount(2, $folder_shares);
		$this->assertSame($folder2->getId(), $folder_shares[0]->getNodeId());
		$this->assertSame($folder2->getId(), $folder_shares[1]->getNodeId());
		$this->assertSame(IShare::TYPE_LINK, $folder_shares[0]->getShareType());
		$this->assertSame(IShare::TYPE_GROUP, $folder_shares[1]->getShareType());

		$provider->delete($share1);
		$provider->delete($share2);
		$provider->delete($share3);
		$provider->delete($share4);

		$u1->delete();
		$u2->delete();
		$u3->delete();
		$g1->delete();
	}

	public function testGetAccessListNoCurrentAccessRequired() {
		$userManager = \OC::$server->getUserManager();
		$groupManager = \OC::$server->getGroupManager();
		$rootFolder = \OC::$server->getRootFolder();

		$provider = new DefaultShareProvider(
			$this->dbConn,
			$userManager,
			$groupManager,
			$rootFolder,
			$this->mailer,
			$this->defaults,
			$this->l10nFactory,
			$this->urlGenerator,
			$this->config
		);

		$u1 = $userManager->createUser('testShare1', 'test');
		$u2 = $userManager->createUser('testShare2', 'test');
		$u3 = $userManager->createUser('testShare3', 'test');
		$u4 = $userManager->createUser('testShare4', 'test');
		$u5 = $userManager->createUser('testShare5', 'test');

		$g1 = $groupManager->createGroup('group1');
		$g1->addUser($u3);
		$g1->addUser($u4);

		$u1Folder = $rootFolder->getUserFolder($u1->getUID());
		$folder1 = $u1Folder->newFolder('foo');
		$folder2 = $folder1->newFolder('baz');
		$file1 = $folder2->newFile('bar');

		$result = $provider->getAccessList([$folder1, $folder2, $file1], false);
		$this->assertCount(0, $result['users']);
		$this->assertFalse($result['public']);

		$shareManager = \OC::$server->getShareManager();
		$share1 = $shareManager->newShare();
		$share1->setNode($folder1)
			->setSharedBy($u1->getUID())
			->setSharedWith($u2->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_USER)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share1 = $this->provider->create($share1);

		$share2 = $shareManager->newShare();
		$share2->setNode($folder2)
			->setSharedBy($u2->getUID())
			->setSharedWith($g1->getGID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_GROUP)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share2 = $this->provider->create($share2);

		$shareManager->deleteFromSelf($share2, $u4->getUID());

		$share3 = $shareManager->newShare();
		$share3->setNode($file1)
			->setSharedBy($u3->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(\OCP\Constants::PERMISSION_READ);
		$share3 = $this->provider->create($share3);

		$share4 = $shareManager->newShare();
		$share4->setNode($file1)
			->setSharedBy($u3->getUID())
			->setSharedWith($u5->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_USER)
			->setPermissions(\OCP\Constants::PERMISSION_READ);
		$share4 = $this->provider->create($share4);

		$result = $provider->getAccessList([$folder1, $folder2, $file1], false);

		$this->assertCount(4, $result['users']);
		$this->assertContains('testShare2', $result['users']);
		$this->assertContains('testShare3', $result['users']);
		$this->assertContains('testShare4', $result['users']);
		$this->assertContains('testShare5', $result['users']);
		$this->assertTrue($result['public']);

		$provider->delete($share1);
		$provider->delete($share2);
		$provider->delete($share3);
		$provider->delete($share4);

		$u1->delete();
		$u2->delete();
		$u3->delete();
		$u4->delete();
		$u5->delete();
		$g1->delete();
	}

	public function testGetAccessListCurrentAccessRequired() {
		$userManager = \OC::$server->getUserManager();
		$groupManager = \OC::$server->getGroupManager();
		$rootFolder = \OC::$server->getRootFolder();

		$provider = new DefaultShareProvider(
			$this->dbConn,
			$userManager,
			$groupManager,
			$rootFolder,
			$this->mailer,
			$this->defaults,
			$this->l10nFactory,
			$this->urlGenerator,
			$this->config
		);

		$u1 = $userManager->createUser('testShare1', 'test');
		$u2 = $userManager->createUser('testShare2', 'test');
		$u3 = $userManager->createUser('testShare3', 'test');
		$u4 = $userManager->createUser('testShare4', 'test');
		$u5 = $userManager->createUser('testShare5', 'test');

		$g1 = $groupManager->createGroup('group1');
		$g1->addUser($u3);
		$g1->addUser($u4);

		$u1Folder = $rootFolder->getUserFolder($u1->getUID());
		$folder1 = $u1Folder->newFolder('foo');
		$folder2 = $folder1->newFolder('baz');
		$file1 = $folder2->newFile('bar');

		$result = $provider->getAccessList([$folder1, $folder2, $file1], false);
		$this->assertCount(0, $result['users']);
		$this->assertFalse($result['public']);

		$shareManager = \OC::$server->getShareManager();
		$share1 = $shareManager->newShare();
		$share1->setNode($folder1)
			->setSharedBy($u1->getUID())
			->setSharedWith($u2->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_USER)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share1 = $this->provider->create($share1);

		$share2 = $shareManager->newShare();
		$share2->setNode($folder2)
			->setSharedBy($u2->getUID())
			->setSharedWith($g1->getGID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_GROUP)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);
		$share2 = $this->provider->create($share2);

		$shareManager->deleteFromSelf($share2, $u4->getUID());

		$share3 = $shareManager->newShare();
		$share3->setNode($file1)
			->setSharedBy($u3->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_LINK)
			->setPermissions(\OCP\Constants::PERMISSION_READ);
		$share3 = $this->provider->create($share3);

		$share4 = $shareManager->newShare();
		$share4->setNode($file1)
			->setSharedBy($u3->getUID())
			->setSharedWith($u5->getUID())
			->setShareOwner($u1->getUID())
			->setShareType(IShare::TYPE_USER)
			->setPermissions(\OCP\Constants::PERMISSION_READ);
		$share4 = $this->provider->create($share4);

		$result = $provider->getAccessList([$folder1, $folder2, $file1], true);

		$this->assertCount(3, $result['users']);
		$this->assertArrayHasKey('testShare2', $result['users']);
		$this->assertArrayHasKey('testShare3', $result['users']);
		$this->assertArrayHasKey('testShare5', $result['users']);
		$this->assertTrue($result['public']);

		$provider->delete($share1);
		$provider->delete($share2);
		$provider->delete($share3);
		$provider->delete($share4);

		$u1->delete();
		$u2->delete();
		$u3->delete();
		$u4->delete();
		$u5->delete();
		$g1->delete();
	}

	public function testGetAllShares() {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_USER),
				'share_with' => $qb->expr()->literal('sharedWith1'),
				'uid_owner' => $qb->expr()->literal('shareOwner1'),
				'uid_initiator' => $qb->expr()->literal('sharedBy1'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(42),
				'file_target' => $qb->expr()->literal('myTarget1'),
				'permissions' => $qb->expr()->literal(13),
			]);
		$qb->execute();

		$id1 = $qb->getLastInsertId();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_GROUP),
				'share_with' => $qb->expr()->literal('sharedWith2'),
				'uid_owner' => $qb->expr()->literal('shareOwner2'),
				'uid_initiator' => $qb->expr()->literal('sharedBy2'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(43),
				'file_target' => $qb->expr()->literal('myTarget2'),
				'permissions' => $qb->expr()->literal(14),
			]);
		$qb->execute();

		$id2 = $qb->getLastInsertId();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_LINK),
				'token' => $qb->expr()->literal('token3'),
				'uid_owner' => $qb->expr()->literal('shareOwner3'),
				'uid_initiator' => $qb->expr()->literal('sharedBy3'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(44),
				'file_target' => $qb->expr()->literal('myTarget3'),
				'permissions' => $qb->expr()->literal(15),
			]);
		$qb->execute();

		$id3 = $qb->getLastInsertId();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_EMAIL),
				'share_with' => $qb->expr()->literal('shareOwner4'),
				'token' => $qb->expr()->literal('token4'),
				'uid_owner' => $qb->expr()->literal('shareOwner4'),
				'uid_initiator' => $qb->expr()->literal('sharedBy4'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(45),
				'file_target' => $qb->expr()->literal('myTarget4'),
				'permissions' => $qb->expr()->literal(16),
			]);
		$qb->execute();

		$id4 = $qb->getLastInsertId();

		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(IShare::TYPE_LINK),
				'token' => $qb->expr()->literal('token5'),
				'uid_owner' => $qb->expr()->literal('shareOwner5'),
				'uid_initiator' => $qb->expr()->literal('sharedBy5'),
				'item_type' => $qb->expr()->literal('file'),
				'file_source' => $qb->expr()->literal(46),
				'file_target' => $qb->expr()->literal('myTarget5'),
				'permissions' => $qb->expr()->literal(17),
			]);
		$qb->execute();

		$id5 = $qb->getLastInsertId();

		$ownerPath1 = $this->createMock(File::class);
		$shareOwner1Folder = $this->createMock(Folder::class);
		$shareOwner1Folder->method('getById')->willReturn([$ownerPath1]);

		$ownerPath2 = $this->createMock(File::class);
		$shareOwner2Folder = $this->createMock(Folder::class);
		$shareOwner2Folder->method('getById')->willReturn([$ownerPath2]);

		$ownerPath3 = $this->createMock(File::class);
		$shareOwner3Folder = $this->createMock(Folder::class);
		$shareOwner3Folder->method('getById')->willReturn([$ownerPath3]);

		$ownerPath4 = $this->createMock(File::class);
		$shareOwner4Folder = $this->createMock(Folder::class);
		$shareOwner4Folder->method('getById')->willReturn([$ownerPath4]);

		$ownerPath5 = $this->createMock(File::class);
		$shareOwner5Folder = $this->createMock(Folder::class);
		$shareOwner5Folder->method('getById')->willReturn([$ownerPath5]);

		$this->rootFolder
			->method('getUserFolder')
			->willReturnMap(
				[
					['shareOwner1', $shareOwner1Folder],
					['shareOwner2', $shareOwner2Folder],
					['shareOwner3', $shareOwner3Folder],
					['shareOwner4', $shareOwner4Folder],
					['shareOwner5', $shareOwner5Folder],
				]
			);

		$shares = iterator_to_array($this->provider->getAllShares());
		$this->assertEquals(4, count($shares));

		$share = $shares[0];

		// We fetch the node so the root folder is eventually called

		$this->assertEquals($id1, $share->getId());
		$this->assertEquals(IShare::TYPE_USER, $share->getShareType());
		$this->assertEquals('sharedWith1', $share->getSharedWith());
		$this->assertEquals('sharedBy1', $share->getSharedBy());
		$this->assertEquals('shareOwner1', $share->getShareOwner());
		$this->assertEquals($ownerPath1, $share->getNode());
		$this->assertEquals(13, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals('myTarget1', $share->getTarget());

		$share = $shares[1];

		$this->assertEquals($id2, $share->getId());
		$this->assertEquals(IShare::TYPE_GROUP, $share->getShareType());
		$this->assertEquals('sharedWith2', $share->getSharedWith());
		$this->assertEquals('sharedBy2', $share->getSharedBy());
		$this->assertEquals('shareOwner2', $share->getShareOwner());
		$this->assertEquals($ownerPath2, $share->getNode());
		$this->assertEquals(14, $share->getPermissions());
		$this->assertEquals(null, $share->getToken());
		$this->assertEquals('myTarget2', $share->getTarget());

		$share = $shares[2];

		$this->assertEquals($id3, $share->getId());
		$this->assertEquals(IShare::TYPE_LINK, $share->getShareType());
		$this->assertEquals(null, $share->getSharedWith());
		$this->assertEquals('sharedBy3', $share->getSharedBy());
		$this->assertEquals('shareOwner3', $share->getShareOwner());
		$this->assertEquals($ownerPath3, $share->getNode());
		$this->assertEquals(15, $share->getPermissions());
		$this->assertEquals('token3', $share->getToken());
		$this->assertEquals('myTarget3', $share->getTarget());

		$share = $shares[3];

		$this->assertEquals($id5, $share->getId());
		$this->assertEquals(IShare::TYPE_LINK, $share->getShareType());
		$this->assertEquals(null, $share->getSharedWith());
		$this->assertEquals('sharedBy5', $share->getSharedBy());
		$this->assertEquals('shareOwner5', $share->getShareOwner());
		$this->assertEquals($ownerPath5, $share->getNode());
		$this->assertEquals(17, $share->getPermissions());
		$this->assertEquals('token5', $share->getToken());
		$this->assertEquals('myTarget5', $share->getTarget());
	}
}
