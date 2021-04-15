<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
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

namespace Test\Share20;

use OC\Files\Mount\MoveableMount;
use OC\HintException;
use OC\Share20\DefaultShareProvider;
use OC\Share20\Exception;
use OC\Share20\Manager;
use OC\Share20\Share;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Node;
use OCP\Files\Storage;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Security\Events\ValidatePasswordPolicyEvent;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Share\Exceptions\AlreadySharedException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IProviderFactory;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class ManagerTest
 *
 * @package Test\Share20
 * @group DB
 */
class ManagerTest extends \Test\TestCase {

	/** @var Manager */
	protected $manager;
	/** @var ILogger|MockObject */
	protected $logger;
	/** @var IConfig|MockObject */
	protected $config;
	/** @var ISecureRandom|MockObject */
	protected $secureRandom;
	/** @var IHasher|MockObject */
	protected $hasher;
	/** @var IShareProvider|MockObject */
	protected $defaultProvider;
	/** @var  IMountManager|MockObject */
	protected $mountManager;
	/** @var  IGroupManager|MockObject */
	protected $groupManager;
	/** @var IL10N|MockObject */
	protected $l;
	/** @var IFactory|MockObject */
	protected $l10nFactory;
	/** @var DummyFactory */
	protected $factory;
	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var IRootFolder | MockObject */
	protected $rootFolder;
	/** @var  EventDispatcherInterface | MockObject */
	protected $eventDispatcher;
	/** @var IEventDispatcher|MockObject */
	protected $dispatcher;
	/** @var  IMailer|MockObject */
	protected $mailer;
	/** @var  IURLGenerator|MockObject */
	protected $urlGenerator;
	/** @var  \OC_Defaults|MockObject */
	protected $defaults;

	protected function setUp(): void {
		$this->logger = $this->createMock(ILogger::class);
		$this->config = $this->createMock(IConfig::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->mountManager = $this->createMock(IMountManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->defaults = $this->createMock(\OC_Defaults::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);

		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		$this->factory = new DummyFactory(\OC::$server);

		$this->manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$this->factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);

		$this->defaultProvider = $this->createMock(DefaultShareProvider::class);
		$this->defaultProvider->method('identifier')->willReturn('default');
		$this->factory->setProvider($this->defaultProvider);
	}

	/**
	 * @return MockBuilder
	 */
	private function createManagerMock() {
		return 	$this->getMockBuilder('\OC\Share20\Manager')
			->setConstructorArgs([
				$this->logger,
				$this->config,
				$this->secureRandom,
				$this->hasher,
				$this->mountManager,
				$this->groupManager,
				$this->l,
				$this->l10nFactory,
				$this->factory,
				$this->userManager,
				$this->rootFolder,
				$this->eventDispatcher,
				$this->mailer,
				$this->urlGenerator,
				$this->defaults,
				$this->dispatcher
			]);
	}


	public function testDeleteNoShareId() {
		$this->expectException(\InvalidArgumentException::class);

		$share = $this->manager->newShare();

		$this->manager->deleteShare($share);
	}

	public function dataTestDelete() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('sharedWithUser');

		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('sharedWithGroup');

		return [
			[IShare::TYPE_USER, 'sharedWithUser'],
			[IShare::TYPE_GROUP, 'sharedWithGroup'],
			[IShare::TYPE_LINK, ''],
			[IShare::TYPE_REMOTE, 'foo@bar.com'],
		];
	}

	/**
	 * @dataProvider dataTestDelete
	 */
	public function testDelete($shareType, $sharedWith) {
		$manager = $this->createManagerMock()
			->setMethods(['getShareById', 'deleteChildren'])
			->getMock();

		$manager->method('deleteChildren')->willReturn([]);

		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(1);

		$share = $this->manager->newShare();
		$share->setId(42)
			->setProviderId('prov')
			->setShareType($shareType)
			->setSharedWith($sharedWith)
			->setSharedBy('sharedBy')
			->setNode($path)
			->setTarget('myTarget');

		$manager->expects($this->once())->method('deleteChildren')->with($share);

		$this->defaultProvider
			->expects($this->once())
			->method('delete')
			->with($share);

		$this->eventDispatcher->expects($this->at(0))
			->method('dispatch')
			->with(
				'OCP\Share::preUnshare',
				$this->callBack(function (GenericEvent $e) use ($share) {
					return $e->getSubject() === $share;
				})
			);
		$this->eventDispatcher->expects($this->at(1))
			->method('dispatch')
			->with(
				'OCP\Share::postUnshare',
				$this->callBack(function (GenericEvent $e) use ($share) {
					return $e->getSubject() === $share &&
						$e->getArgument('deletedShares') === [$share];
				})
			);

		$manager->deleteShare($share);
	}

	public function testDeleteLazyShare() {
		$manager = $this->createManagerMock()
			->setMethods(['getShareById', 'deleteChildren'])
			->getMock();

		$manager->method('deleteChildren')->willReturn([]);

		$share = $this->manager->newShare();
		$share->setId(42)
			->setProviderId('prov')
			->setShareType(IShare::TYPE_USER)
			->setSharedWith('sharedWith')
			->setSharedBy('sharedBy')
			->setShareOwner('shareOwner')
			->setTarget('myTarget')
			->setNodeId(1)
			->setNodeType('file');

		$this->rootFolder->expects($this->never())->method($this->anything());

		$manager->expects($this->once())->method('deleteChildren')->with($share);

		$this->defaultProvider
			->expects($this->once())
			->method('delete')
			->with($share);

		$this->eventDispatcher->expects($this->at(0))
			->method('dispatch')
			->with(
				'OCP\Share::preUnshare',
				$this->callBack(function (GenericEvent $e) use ($share) {
					return $e->getSubject() === $share;
				})
			);
		$this->eventDispatcher->expects($this->at(1))
			->method('dispatch')
			->with(
				'OCP\Share::postUnshare',
				$this->callBack(function (GenericEvent $e) use ($share) {
					return $e->getSubject() === $share &&
						$e->getArgument('deletedShares') === [$share];
				})
			);

		$manager->deleteShare($share);
	}

	public function testDeleteNested() {
		$manager = $this->createManagerMock()
			->setMethods(['getShareById'])
			->getMock();

		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(1);

		$share1 = $this->manager->newShare();
		$share1->setId(42)
			->setProviderId('prov')
			->setShareType(IShare::TYPE_USER)
			->setSharedWith('sharedWith1')
			->setSharedBy('sharedBy1')
			->setNode($path)
			->setTarget('myTarget1');

		$share2 = $this->manager->newShare();
		$share2->setId(43)
			->setProviderId('prov')
			->setShareType(IShare::TYPE_GROUP)
			->setSharedWith('sharedWith2')
			->setSharedBy('sharedBy2')
			->setNode($path)
			->setTarget('myTarget2')
			->setParent(42);

		$share3 = $this->manager->newShare();
		$share3->setId(44)
			->setProviderId('prov')
			->setShareType(IShare::TYPE_LINK)
			->setSharedBy('sharedBy3')
			->setNode($path)
			->setTarget('myTarget3')
			->setParent(43);

		$this->defaultProvider
			->method('getChildren')
			->willReturnMap([
				[$share1, [$share2]],
				[$share2, [$share3]],
				[$share3, []],
			]);

		$this->defaultProvider
			->method('delete')
			->withConsecutive([$share3], [$share2], [$share1]);

		$this->eventDispatcher->expects($this->at(0))
			->method('dispatch')
			->with(
				'OCP\Share::preUnshare',
				$this->callBack(function (GenericEvent $e) use ($share1) {
					return $e->getSubject() === $share1;
				})
			);
		$this->eventDispatcher->expects($this->at(1))
			->method('dispatch')
			->with(
				'OCP\Share::postUnshare',
				$this->callBack(function (GenericEvent $e) use ($share1, $share2, $share3) {
					return $e->getSubject() === $share1 &&
						$e->getArgument('deletedShares') === [$share3, $share2, $share1];
				})
			);

		$manager->deleteShare($share1);
	}

	public function testDeleteFromSelf() {
		$manager = $this->createManagerMock()
			->setMethods(['getShareById'])
			->getMock();

		$recipientId = 'unshareFrom';
		$share = $this->manager->newShare();
		$share->setId(42)
			->setProviderId('prov')
			->setShareType(IShare::TYPE_USER)
			->setSharedWith('sharedWith')
			->setSharedBy('sharedBy')
			->setShareOwner('shareOwner')
			->setTarget('myTarget')
			->setNodeId(1)
			->setNodeType('file');

		$this->defaultProvider
			->expects($this->once())
			->method('deleteFromSelf')
			->with($share, $recipientId);

		$this->eventDispatcher->expects($this->at(0))
			->method('dispatch')
			->with(
				'OCP\Share::postUnshareFromSelf',
				$this->callBack(function (GenericEvent $e) use ($share) {
					return $e->getSubject() === $share;
				})
			);

		$manager->deleteFromSelf($share, $recipientId);
	}

	public function testDeleteChildren() {
		$manager = $this->createManagerMock()
			->setMethods(['deleteShare'])
			->getMock();

		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(IShare::TYPE_USER);

		$child1 = $this->createMock(IShare::class);
		$child1->method('getShareType')->willReturn(IShare::TYPE_USER);
		$child2 = $this->createMock(IShare::class);
		$child2->method('getShareType')->willReturn(IShare::TYPE_USER);
		$child3 = $this->createMock(IShare::class);
		$child3->method('getShareType')->willReturn(IShare::TYPE_USER);

		$shares = [
			$child1,
			$child2,
			$child3,
		];

		$this->defaultProvider
			->expects($this->exactly(4))
			->method('getChildren')
			->willReturnCallback(function ($_share) use ($share, $shares) {
				if ($_share === $share) {
					return $shares;
				}
				return [];
			});

		$this->defaultProvider
			->expects($this->exactly(3))
			->method('delete')
			->withConsecutive([$child1], [$child2], [$child3]);

		$result = self::invokePrivate($manager, 'deleteChildren', [$share]);
		$this->assertSame($shares, $result);
	}

	public function testGetShareById() {
		$share = $this->createMock(IShare::class);

		$this->defaultProvider
			->expects($this->once())
			->method('getShareById')
			->with(42)
			->willReturn($share);

		$this->assertEquals($share, $this->manager->getShareById('default:42'));
	}


	public function testGetExpiredShareById() {
		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);

		$manager = $this->createManagerMock()
			->setMethods(['deleteShare'])
			->getMock();

		$date = new \DateTime();
		$date->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($date)
			->setShareType(IShare::TYPE_LINK);

		$this->defaultProvider->expects($this->once())
			->method('getShareById')
			->with('42')
			->willReturn($share);

		$manager->expects($this->once())
			->method('deleteShare')
			->with($share);

		$manager->getShareById('default:42');
	}


	public function testVerifyPasswordNullButEnforced() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Passwords are enforced for link and mail shares');

		$this->config->method('getAppValue')->willReturnMap([
			['core', 'shareapi_enforce_links_password', 'no', 'yes'],
		]);

		self::invokePrivate($this->manager, 'verifyPassword', [null]);
	}

	public function testVerifyPasswordNull() {
		$this->config->method('getAppValue')->willReturnMap([
			['core', 'shareapi_enforce_links_password', 'no', 'no'],
		]);

		$result = self::invokePrivate($this->manager, 'verifyPassword', [null]);
		$this->assertNull($result);
	}

	public function testVerifyPasswordHook() {
		$this->config->method('getAppValue')->willReturnMap([
			['core', 'shareapi_enforce_links_password', 'no', 'no'],
		]);

		$this->eventDispatcher->expects($this->once())->method('dispatch')
			->willReturnCallback(function (Event $event) {
				$this->assertInstanceOf(ValidatePasswordPolicyEvent::class, $event);
				/** @var ValidatePasswordPolicyEvent $event */
				$this->assertSame('password', $event->getPassword());
			}
		);

		$result = self::invokePrivate($this->manager, 'verifyPassword', ['password']);
		$this->assertNull($result);
	}


	public function testVerifyPasswordHookFails() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('password not accepted');

		$this->config->method('getAppValue')->willReturnMap([
			['core', 'shareapi_enforce_links_password', 'no', 'no'],
		]);

		$this->eventDispatcher->expects($this->once())->method('dispatch')
			->willReturnCallback(function (Event $event) {
				$this->assertInstanceOf(ValidatePasswordPolicyEvent::class, $event);
				/** @var ValidatePasswordPolicyEvent $event */
				$this->assertSame('password', $event->getPassword());
				throw new HintException('message', 'password not accepted');
			}
		);

		self::invokePrivate($this->manager, 'verifyPassword', ['password']);
	}

	public function createShare($id, $type, $path, $sharedWith, $sharedBy, $shareOwner,
		$permissions, $expireDate = null, $password = null) {
		$share = $this->createMock(IShare::class);

		$share->method('getShareType')->willReturn($type);
		$share->method('getSharedWith')->willReturn($sharedWith);
		$share->method('getSharedBy')->willReturn($sharedBy);
		$share->method('getShareOwner')->willReturn($shareOwner);
		$share->method('getNode')->willReturn($path);
		$share->method('getPermissions')->willReturn($permissions);
		$share->method('getExpirationDate')->willReturn($expireDate);
		$share->method('getPassword')->willReturn($password);

		return $share;
	}

	public function dataGeneralChecks() {
		$user0 = 'user0';
		$user2 = 'user1';
		$group0 = 'group0';
		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')
			->willReturn($user0);

		$file = $this->createMock(File::class);
		$node = $this->createMock(Node::class);
		$storage = $this->createMock(Storage\IStorage::class);
		$storage->method('instanceOfStorage')
			->with('\OCA\Files_Sharing\External\Storage')
			->willReturn(false);
		$file->method('getStorage')
			->willReturn($storage);
		$node->method('getStorage')
			->willReturn($storage);

		$data = [
			[$this->createShare(null, IShare::TYPE_USER,  $file, null, $user0, $user0, 31, null, null), 'SharedWith is not a valid user', true],
			[$this->createShare(null, IShare::TYPE_USER,  $file, $group0, $user0, $user0, 31, null, null), 'SharedWith is not a valid user', true],
			[$this->createShare(null, IShare::TYPE_USER,  $file, 'foo@bar.com', $user0, $user0, 31, null, null), 'SharedWith is not a valid user', true],
			[$this->createShare(null, IShare::TYPE_GROUP, $file, null, $user0, $user0, 31, null, null), 'SharedWith is not a valid group', true],
			[$this->createShare(null, IShare::TYPE_GROUP, $file, $user2, $user0, $user0, 31, null, null), 'SharedWith is not a valid group', true],
			[$this->createShare(null, IShare::TYPE_GROUP, $file, 'foo@bar.com', $user0, $user0, 31, null, null), 'SharedWith is not a valid group', true],
			[$this->createShare(null, IShare::TYPE_LINK,  $file, $user2, $user0, $user0, 31, null, null), 'SharedWith should be empty', true],
			[$this->createShare(null, IShare::TYPE_LINK,  $file, $group0, $user0, $user0, 31, null, null), 'SharedWith should be empty', true],
			[$this->createShare(null, IShare::TYPE_LINK,  $file, 'foo@bar.com', $user0, $user0, 31, null, null), 'SharedWith should be empty', true],
			[$this->createShare(null, -1, $file, null, $user0, $user0, 31, null, null), 'unknown share type', true],

			[$this->createShare(null, IShare::TYPE_USER,  $file, $user2, null, $user0, 31, null, null), 'SharedBy should be set', true],
			[$this->createShare(null, IShare::TYPE_GROUP, $file, $group0, null, $user0, 31, null, null), 'SharedBy should be set', true],
			[$this->createShare(null, IShare::TYPE_LINK,  $file, null, null, $user0, 31, null, null), 'SharedBy should be set', true],

			[$this->createShare(null, IShare::TYPE_USER,  $file, $user0, $user0, $user0, 31, null, null), 'Can’t share with yourself', true],

			[$this->createShare(null, IShare::TYPE_USER,  null, $user2, $user0, $user0, 31, null, null), 'Path should be set', true],
			[$this->createShare(null, IShare::TYPE_GROUP, null, $group0, $user0, $user0, 31, null, null), 'Path should be set', true],
			[$this->createShare(null, IShare::TYPE_LINK,  null, null, $user0, $user0, 31, null, null), 'Path should be set', true],

			[$this->createShare(null, IShare::TYPE_USER,  $node, $user2, $user0, $user0, 31, null, null), 'Path should be either a file or a folder', true],
			[$this->createShare(null, IShare::TYPE_GROUP, $node, $group0, $user0, $user0, 31, null, null), 'Path should be either a file or a folder', true],
			[$this->createShare(null, IShare::TYPE_LINK,  $node, null, $user0, $user0, 31, null, null), 'Path should be either a file or a folder', true],
		];

		$nonShareAble = $this->createMock(Folder::class);
		$nonShareAble->method('isShareable')->willReturn(false);
		$nonShareAble->method('getPath')->willReturn('path');
		$nonShareAble->method('getName')->willReturn('name');
		$nonShareAble->method('getOwner')
			->willReturn($owner);
		$nonShareAble->method('getStorage')
			->willReturn($storage);

		$data[] = [$this->createShare(null, IShare::TYPE_USER,  $nonShareAble, $user2, $user0, $user0, 31, null, null), 'You are not allowed to share name', true];
		$data[] = [$this->createShare(null, IShare::TYPE_GROUP, $nonShareAble, $group0, $user0, $user0, 31, null, null), 'You are not allowed to share name', true];
		$data[] = [$this->createShare(null, IShare::TYPE_LINK,  $nonShareAble, null, $user0, $user0, 31, null, null), 'You are not allowed to share name', true];

		$limitedPermssions = $this->createMock(File::class);
		$limitedPermssions->method('isShareable')->willReturn(true);
		$limitedPermssions->method('getPermissions')->willReturn(\OCP\Constants::PERMISSION_READ);
		$limitedPermssions->method('getId')->willReturn(108);
		$limitedPermssions->method('getPath')->willReturn('path');
		$limitedPermssions->method('getName')->willReturn('name');
		$limitedPermssions->method('getOwner')
			->willReturn($owner);
		$limitedPermssions->method('getStorage')
			->willReturn($storage);

		$data[] = [$this->createShare(null, IShare::TYPE_USER,  $limitedPermssions, $user2, $user0, $user0, null, null, null), 'A share requires permissions', true];
		$data[] = [$this->createShare(null, IShare::TYPE_GROUP, $limitedPermssions, $group0, $user0, $user0, null, null, null), 'A share requires permissions', true];
		$data[] = [$this->createShare(null, IShare::TYPE_LINK,  $limitedPermssions, null, $user0, $user0, null, null, null), 'A share requires permissions', true];

		$mount = $this->createMock(MoveableMount::class);
		$limitedPermssions->method('getMountPoint')->willReturn($mount);


		$data[] = [$this->createShare(null, IShare::TYPE_USER,  $limitedPermssions, $user2, $user0, $user0, 31, null, null), 'Can’t increase permissions of path', true];
		$data[] = [$this->createShare(null, IShare::TYPE_GROUP, $limitedPermssions, $group0, $user0, $user0, 17, null, null), 'Can’t increase permissions of path', true];
		$data[] = [$this->createShare(null, IShare::TYPE_LINK,  $limitedPermssions, null, $user0, $user0, 3, null, null), 'Can’t increase permissions of path', true];

		$nonMoveableMountPermssions = $this->createMock(Folder::class);
		$nonMoveableMountPermssions->method('isShareable')->willReturn(true);
		$nonMoveableMountPermssions->method('getPermissions')->willReturn(\OCP\Constants::PERMISSION_READ);
		$nonMoveableMountPermssions->method('getId')->willReturn(108);
		$nonMoveableMountPermssions->method('getPath')->willReturn('path');
		$nonMoveableMountPermssions->method('getName')->willReturn('name');
		$nonMoveableMountPermssions->method('getOwner')
			->willReturn($owner);
		$nonMoveableMountPermssions->method('getStorage')
			->willReturn($storage);

		$data[] = [$this->createShare(null, IShare::TYPE_USER,  $nonMoveableMountPermssions, $user2, $user0, $user0, 11, null, null), 'Can’t increase permissions of path', false];
		$data[] = [$this->createShare(null, IShare::TYPE_GROUP, $nonMoveableMountPermssions, $group0, $user0, $user0, 11, null, null), 'Can’t increase permissions of path', false];

		$rootFolder = $this->createMock(Folder::class);
		$rootFolder->method('isShareable')->willReturn(true);
		$rootFolder->method('getPermissions')->willReturn(\OCP\Constants::PERMISSION_ALL);
		$rootFolder->method('getId')->willReturn(42);

		$data[] = [$this->createShare(null, IShare::TYPE_USER,  $rootFolder, $user2, $user0, $user0, 30, null, null), 'You can’t share your root folder', true];
		$data[] = [$this->createShare(null, IShare::TYPE_GROUP, $rootFolder, $group0, $user0, $user0, 2, null, null), 'You can’t share your root folder', true];
		$data[] = [$this->createShare(null, IShare::TYPE_LINK,  $rootFolder, null, $user0, $user0, 16, null, null), 'You can’t share your root folder', true];

		$allPermssions = $this->createMock(Folder::class);
		$allPermssions->method('isShareable')->willReturn(true);
		$allPermssions->method('getPermissions')->willReturn(\OCP\Constants::PERMISSION_ALL);
		$allPermssions->method('getId')->willReturn(108);
		$allPermssions->method('getOwner')
			->willReturn($owner);
		$allPermssions->method('getStorage')
			->willReturn($storage);

		$data[] = [$this->createShare(null, IShare::TYPE_USER,  $allPermssions, $user2, $user0, $user0, 30, null, null), 'Shares need at least read permissions', true];
		$data[] = [$this->createShare(null, IShare::TYPE_GROUP, $allPermssions, $group0, $user0, $user0, 2, null, null), 'Shares need at least read permissions', true];

		$data[] = [$this->createShare(null, IShare::TYPE_USER,  $allPermssions, $user2, $user0, $user0, 31, null, null), null, false];
		$data[] = [$this->createShare(null, IShare::TYPE_GROUP, $allPermssions, $group0, $user0, $user0, 3, null, null), null, false];
		$data[] = [$this->createShare(null, IShare::TYPE_LINK,  $allPermssions, null, $user0, $user0, 17, null, null), null, false];


		$remoteStorage = $this->createMock(Storage\IStorage::class);
		$remoteStorage->method('instanceOfStorage')
			->with('\OCA\Files_Sharing\External\Storage')
			->willReturn(true);
		$remoteFile = $this->createMock(Folder::class);
		$remoteFile->method('isShareable')->willReturn(true);
		$remoteFile->method('getPermissions')->willReturn(\OCP\Constants::PERMISSION_READ ^ \OCP\Constants::PERMISSION_UPDATE);
		$remoteFile->method('getId')->willReturn(108);
		$remoteFile->method('getOwner')
			->willReturn($owner);
		$remoteFile->method('getStorage')
			->willReturn($storage);
		$data[] = [$this->createShare(null, IShare::TYPE_REMOTE, $remoteFile, $user2, $user0, $user0, 1, null, null), null, false];
		$data[] = [$this->createShare(null, IShare::TYPE_REMOTE, $remoteFile, $user2, $user0, $user0, 3, null, null), null, false];
		$data[] = [$this->createShare(null, IShare::TYPE_REMOTE, $remoteFile, $user2, $user0, $user0, 31, null, null), 'Can’t increase permissions of ', true];

		return $data;
	}

	/**
	 * @dataProvider dataGeneralChecks
	 *
	 * @param $share
	 * @param $exceptionMessage
	 * @param $exception
	 */
	public function testGeneralChecks($share, $exceptionMessage, $exception) {
		$thrown = null;

		$this->userManager->method('userExists')->willReturnMap([
			['user0', true],
			['user1', true],
		]);

		$this->groupManager->method('groupExists')->willReturnMap([
			['group0', true],
		]);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->any())
			->method('getId')
			->willReturn(42);
		// Id 108 is used in the data to refer to the node of the share.
		$userFolder->expects($this->any())
			->method('getById')
			->with(108)
			->willReturn([$share->getNode()]);
		$userFolder->expects($this->any())
			->method('getRelativePath')
			->willReturnArgument(0);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);


		try {
			self::invokePrivate($this->manager, 'generalCreateChecks', [$share]);
			$thrown = false;
		} catch (\OCP\Share\Exceptions\GenericShareException $e) {
			$this->assertEquals($exceptionMessage, $e->getHint());
			$thrown = true;
		} catch (\InvalidArgumentException $e) {
			$this->assertEquals($exceptionMessage, $e->getMessage());
			$thrown = true;
		}

		$this->assertSame($exception, $thrown);
	}


	public function testGeneralCheckShareRoot() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('You can’t share your root folder');

		$thrown = null;

		$this->userManager->method('userExists')->willReturnMap([
			['user0', true],
			['user1', true],
		]);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('isSubNode')->with($userFolder)->willReturn(false);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);

		$share = $this->manager->newShare();

		$share->setShareType(IShare::TYPE_USER)
			->setSharedWith('user0')
			->setSharedBy('user1')
			->setNode($userFolder);

		self::invokePrivate($this->manager, 'generalCreateChecks', [$share]);
	}

	public function testValidateExpirationDateInternalInPast() {
		$this->expectException(\OCP\Share\Exceptions\GenericShareException::class);
		$this->expectExceptionMessage('Expiration date is in the past');

		// Expire date in the past
		$past = new \DateTime();
		$past->sub(new \DateInterval('P1D'));

		$share = $this->manager->newShare();
		$share->setExpirationDate($past);

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);
	}

	public function testValidateExpirationDateInternalEnforceButNotSet() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Expiration date is enforced');

		$share = $this->manager->newShare();
		$share->setProviderId('foo')->setId('bar');

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_default_internal_expire_date', 'no', 'yes'],
				['core', 'shareapi_enforce_internal_expire_date', 'no', 'yes'],
			]);

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);
	}

	public function testValidateExpirationDateInternalEnforceButNotEnabledAndNotSet() {
		$share = $this->manager->newShare();
		$share->setProviderId('foo')->setId('bar');

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_internal_expire_date', 'no', 'yes'],
			]);

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertNull($share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalEnforceButNotSetNewShare() {
		$share = $this->manager->newShare();

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_internal_expire_date', 'no', 'yes'],
				['core', 'shareapi_internal_expire_after_n_days', '7', '3'],
				['core', 'shareapi_default_internal_expire_date', 'no', 'yes'],
				['core', 'internal_defaultExpDays', '3', '3'],
			]);

		$expected = new \DateTime();
		$expected->setTime(0,0,0);
		$expected->add(new \DateInterval('P3D'));

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertNotNull($share->getExpirationDate());
		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalEnforceRelaxedDefaultButNotSetNewShare() {
		$share = $this->manager->newShare();

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_internal_expire_date', 'no', 'yes'],
				['core', 'shareapi_internal_expire_after_n_days', '7', '3'],
				['core', 'shareapi_default_internal_expire_date', 'no', 'yes'],
				['core', 'internal_defaultExpDays', '3', '1'],
			]);

		$expected = new \DateTime();
		$expected->setTime(0,0,0);
		$expected->add(new \DateInterval('P1D'));

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertNotNull($share->getExpirationDate());
		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalEnforceTooFarIntoFuture() {
		$this->expectException(\OCP\Share\Exceptions\GenericShareException::class);
		$this->expectExceptionMessage('Can’t set expiration date more than 3 days in the future');

		$future = new \DateTime();
		$future->add(new \DateInterval('P7D'));

		$share = $this->manager->newShare();
		$share->setExpirationDate($future);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_internal_expire_date', 'no', 'yes'],
				['core', 'shareapi_internal_expire_after_n_days', '7', '3'],
				['core', 'shareapi_default_internal_expire_date', 'no', 'yes'],
			]);

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);
	}

	public function testValidateExpirationDateInternalEnforceValid() {
		$future = new \DateTime();
		$future->add(new \DateInterval('P2D'));
		$future->setTime(1,2,3);

		$expected = clone $future;
		$expected->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($future);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_internal_expire_date', 'no', 'yes'],
				['core', 'shareapi_internal_expire_after_n_days', '7', '3'],
				['core', 'shareapi_default_internal_expire_date', 'no', 'yes'],
			]);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) use ($future) {
			return $data['expirationDate'] == $future;
		}));

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalNoDefault() {
		$date = new \DateTime();
		$date->add(new \DateInterval('P5D'));
		$date->setTime(1,2,3);

		$expected = clone $date;
		$expected->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($date);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) use ($expected) {
			return $data['expirationDate'] == $expected && $data['passwordSet'] === false;
		}));

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalNoDateNoDefault() {
		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) {
			return $data['expirationDate'] === null && $data['passwordSet'] === true;
		}));

		$share = $this->manager->newShare();
		$share->setPassword('password');

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertNull($share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalNoDateDefault() {
		$share = $this->manager->newShare();

		$expected = new \DateTime();
		$expected->add(new \DateInterval('P3D'));
		$expected->setTime(0,0,0);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_default_internal_expire_date', 'no', 'yes'],
				['core', 'shareapi_internal_expire_after_n_days', '7', '3'],
				['core', 'internal_defaultExpDays', '3', '3'],
			]);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) use ($expected) {
			return $data['expirationDate'] == $expected;
		}));

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalDefault() {
		$future = new \DateTime();
		$future->add(new \DateInterval('P5D'));
		$future->setTime(1,2,3);

		$expected = clone $future;
		$expected->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($future);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_default_internal_expire_date', 'no', 'yes'],
				['core', 'shareapi_internal_expire_after_n_days', '7', '3'],
				['core', 'internal_defaultExpDays', '3', '1'],
			]);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) use ($expected) {
			return $data['expirationDate'] == $expected;
		}));

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalHookModification() {
		$nextWeek = new \DateTime();
		$nextWeek->add(new \DateInterval('P7D'));
		$nextWeek->setTime(0,0,0);

		$save = clone $nextWeek;

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->willReturnCallback(function ($data) {
			$data['expirationDate']->sub(new \DateInterval('P2D'));
		});

		$share = $this->manager->newShare();
		$share->setExpirationDate($nextWeek);

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$save->sub(new \DateInterval('P2D'));
		$this->assertEquals($save, $share->getExpirationDate());
	}

	public function testValidateExpirationDateInternalHookException() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid date!');

		$nextWeek = new \DateTime();
		$nextWeek->add(new \DateInterval('P7D'));
		$nextWeek->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($nextWeek);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->willReturnCallback(function ($data) {
			$data['accepted'] = false;
			$data['message'] = 'Invalid date!';
		});

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);
	}

	public function testValidateExpirationDateInternalExistingShareNoDefault() {
		$share = $this->manager->newShare();

		$share->setId('42')->setProviderId('foo');

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_default_internal_expire_date', 'no', 'yes'],
				['core', 'shareapi_internal_expire_after_n_days', '7', '6'],
			]);

		self::invokePrivate($this->manager, 'validateExpirationDateInternal', [$share]);

		$this->assertEquals(null, $share->getExpirationDate());
	}

	public function testValidateExpirationDateInPast() {
		$this->expectException(\OCP\Share\Exceptions\GenericShareException::class);
		$this->expectExceptionMessage('Expiration date is in the past');

		// Expire date in the past
		$past = new \DateTime();
		$past->sub(new \DateInterval('P1D'));

		$share = $this->manager->newShare();
		$share->setExpirationDate($past);

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);
	}

	public function testValidateExpirationDateEnforceButNotSet() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Expiration date is enforced');

		$share = $this->manager->newShare();
		$share->setProviderId('foo')->setId('bar');

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_default_expire_date', 'no', 'yes'],
				['core', 'shareapi_enforce_expire_date', 'no', 'yes'],
			]);

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);
	}

	public function testValidateExpirationDateEnforceButNotEnabledAndNotSet() {
		$share = $this->manager->newShare();
		$share->setProviderId('foo')->setId('bar');

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_expire_date', 'no', 'yes'],
			]);

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertNull($share->getExpirationDate());
	}

	public function testValidateExpirationDateEnforceButNotSetNewShare() {
		$share = $this->manager->newShare();

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_expire_date', 'no', 'yes'],
				['core', 'shareapi_expire_after_n_days', '7', '3'],
				['core', 'shareapi_default_expire_date', 'no', 'yes'],
				['core', 'link_defaultExpDays', 3, '3'],
			]);

		$expected = new \DateTime();
		$expected->setTime(0,0,0);
		$expected->add(new \DateInterval('P3D'));

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertNotNull($share->getExpirationDate());
		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateEnforceRelaxedDefaultButNotSetNewShare() {
		$share = $this->manager->newShare();

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_expire_date', 'no', 'yes'],
				['core', 'shareapi_expire_after_n_days', '7', '3'],
				['core', 'shareapi_default_expire_date', 'no', 'yes'],
				['core', 'link_defaultExpDays', 3, '1'],
			]);

		$expected = new \DateTime();
		$expected->setTime(0,0,0);
		$expected->add(new \DateInterval('P1D'));

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertNotNull($share->getExpirationDate());
		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateEnforceTooFarIntoFuture() {
		$this->expectException(\OCP\Share\Exceptions\GenericShareException::class);
		$this->expectExceptionMessage('Can’t set expiration date more than 3 days in the future');

		$future = new \DateTime();
		$future->add(new \DateInterval('P7D'));

		$share = $this->manager->newShare();
		$share->setExpirationDate($future);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_expire_date', 'no', 'yes'],
				['core', 'shareapi_expire_after_n_days', '7', '3'],
				['core', 'shareapi_default_expire_date', 'no', 'yes'],
			]);

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);
	}

	public function testValidateExpirationDateEnforceValid() {
		$future = new \DateTime();
		$future->add(new \DateInterval('P2D'));
		$future->setTime(1,2,3);

		$expected = clone $future;
		$expected->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($future);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enforce_expire_date', 'no', 'yes'],
				['core', 'shareapi_expire_after_n_days', '7', '3'],
				['core', 'shareapi_default_expire_date', 'no', 'yes'],
			]);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) use ($future) {
			return $data['expirationDate'] == $future;
		}));

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateNoDefault() {
		$date = new \DateTime();
		$date->add(new \DateInterval('P5D'));
		$date->setTime(1,2,3);

		$expected = clone $date;
		$expected->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($date);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) use ($expected) {
			return $data['expirationDate'] == $expected && $data['passwordSet'] === false;
		}));

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateNoDateNoDefault() {
		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) {
			return $data['expirationDate'] === null && $data['passwordSet'] === true;
		}));

		$share = $this->manager->newShare();
		$share->setPassword('password');

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertNull($share->getExpirationDate());
	}

	public function testValidateExpirationDateNoDateDefault() {
		$share = $this->manager->newShare();

		$expected = new \DateTime();
		$expected->add(new \DateInterval('P3D'));
		$expected->setTime(0,0,0);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_default_expire_date', 'no', 'yes'],
				['core', 'shareapi_expire_after_n_days', '7', '3'],
				['core', 'link_defaultExpDays', 3, '3'],
			]);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) use ($expected) {
			return $data['expirationDate'] == $expected;
		}));

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateDefault() {
		$future = new \DateTime();
		$future->add(new \DateInterval('P5D'));
		$future->setTime(1,2,3);

		$expected = clone $future;
		$expected->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($future);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_default_expire_date', 'no', 'yes'],
				['core', 'shareapi_expire_after_n_days', '7', '3'],
				['core', 'link_defaultExpDays', 3, '1'],
			]);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->with($this->callback(function ($data) use ($expected) {
			return $data['expirationDate'] == $expected;
		}));

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertEquals($expected, $share->getExpirationDate());
	}

	public function testValidateExpirationDateHookModification() {
		$nextWeek = new \DateTime();
		$nextWeek->add(new \DateInterval('P7D'));
		$nextWeek->setTime(0,0,0);

		$save = clone $nextWeek;

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->willReturnCallback(function ($data) {
			$data['expirationDate']->sub(new \DateInterval('P2D'));
		});

		$share = $this->manager->newShare();
		$share->setExpirationDate($nextWeek);

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$save->sub(new \DateInterval('P2D'));
		$this->assertEquals($save, $share->getExpirationDate());
	}

	public function testValidateExpirationDateHookException() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid date!');

		$nextWeek = new \DateTime();
		$nextWeek->add(new \DateInterval('P7D'));
		$nextWeek->setTime(0,0,0);

		$share = $this->manager->newShare();
		$share->setExpirationDate($nextWeek);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['listener'])->getMock();
		\OCP\Util::connectHook('\OC\Share', 'verifyExpirationDate', $hookListener, 'listener');
		$hookListener->expects($this->once())->method('listener')->willReturnCallback(function ($data) {
			$data['accepted'] = false;
			$data['message'] = 'Invalid date!';
		});

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);
	}

	public function testValidateExpirationDateExistingShareNoDefault() {
		$share = $this->manager->newShare();

		$share->setId('42')->setProviderId('foo');

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_default_expire_date', 'no', 'yes'],
				['core', 'shareapi_expire_after_n_days', '7', '6'],
			]);

		self::invokePrivate($this->manager, 'validateExpirationDateLink', [$share]);

		$this->assertEquals(null, $share->getExpirationDate());
	}

	public function testUserCreateChecksShareWithGroupMembersOnlyDifferentGroups() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sharing is only allowed with group members');

		$share = $this->manager->newShare();

		$sharedBy = $this->createMock(IUser::class);
		$sharedWith = $this->createMock(IUser::class);
		$share->setSharedBy('sharedBy')->setSharedWith('sharedWith');

		$this->groupManager
			->method('getUserGroupIds')
			->willReturnMap(
				[
					[$sharedBy, ['group1']],
					[$sharedWith, ['group2']],
				]
			);

		$this->userManager->method('get')->willReturnMap([
			['sharedBy', $sharedBy],
			['sharedWith', $sharedWith],
		]);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
			]);

		self::invokePrivate($this->manager, 'userCreateChecks', [$share]);
	}

	public function testUserCreateChecksShareWithGroupMembersOnlySharedGroup() {
		$share = $this->manager->newShare();

		$sharedBy = $this->createMock(IUser::class);
		$sharedWith = $this->createMock(IUser::class);
		$share->setSharedBy('sharedBy')->setSharedWith('sharedWith');

		$path = $this->createMock(Node::class);
		$share->setNode($path);

		$this->groupManager
			->method('getUserGroupIds')
			->willReturnMap(
				[
					[$sharedBy, ['group1', 'group3']],
					[$sharedWith, ['group2', 'group3']],
				]
			);

		$this->userManager->method('get')->willReturnMap([
			['sharedBy', $sharedBy],
			['sharedWith', $sharedWith],
		]);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
			]);

		$this->defaultProvider
			->method('getSharesByPath')
			->with($path)
			->willReturn([]);

		self::invokePrivate($this->manager, 'userCreateChecks', [$share]);
		$this->addToAssertionCount(1);
	}


	public function testUserCreateChecksIdenticalShareExists() {
		$this->expectException(AlreadySharedException::class);
		$this->expectExceptionMessage('Sharing name.txt failed, because this item is already shared with user user');

		$share = $this->manager->newShare();
		$share->setSharedWithDisplayName('user');
		$share2 = $this->manager->newShare();

		$sharedWith = $this->createMock(IUser::class);
		$path = $this->createMock(Node::class);

		$share->setSharedWith('sharedWith')->setNode($path)
			->setProviderId('foo')->setId('bar');

		$share2->setSharedWith('sharedWith')->setNode($path)
			->setProviderId('foo')->setId('baz');

		$this->defaultProvider
			->method('getSharesByPath')
			->with($path)
			->willReturn([$share2]);

		$path->method('getName')
			->willReturn('name.txt');

		self::invokePrivate($this->manager, 'userCreateChecks', [$share]);
	}


	public function testUserCreateChecksIdenticalPathSharedViaGroup() {
		$this->expectException(AlreadySharedException::class);
		$this->expectExceptionMessage('Sharing name2.txt failed, because this item is already shared with user userName');

		$share = $this->manager->newShare();

		$sharedWith = $this->createMock(IUser::class);
		$sharedWith->method('getUID')->willReturn('sharedWith');

		$this->userManager->method('get')->with('sharedWith')->willReturn($sharedWith);

		$path = $this->createMock(Node::class);

		$share->setSharedWith('sharedWith')
			->setNode($path)
			->setShareOwner('shareOwner')
			->setSharedWithDisplayName('userName')
			->setProviderId('foo')
			->setId('bar');

		$share2 = $this->manager->newShare();
		$share2->setShareType(IShare::TYPE_GROUP)
			->setShareOwner('shareOwner2')
			->setProviderId('foo')
			->setId('baz')
			->setSharedWith('group');

		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')
			->with($sharedWith)
			->willReturn(true);

		$this->groupManager->method('get')->with('group')->willReturn($group);

		$this->defaultProvider
			->method('getSharesByPath')
			->with($path)
			->willReturn([$share2]);

		$path->method('getName')
			->willReturn('name2.txt');

		self::invokePrivate($this->manager, 'userCreateChecks', [$share]);
	}

	public function testUserCreateChecksIdenticalPathSharedViaDeletedGroup() {
		$share = $this->manager->newShare();

		$sharedWith = $this->createMock(IUser::class);
		$sharedWith->method('getUID')->willReturn('sharedWith');

		$this->userManager->method('get')->with('sharedWith')->willReturn($sharedWith);

		$path = $this->createMock(Node::class);

		$share->setSharedWith('sharedWith')
			->setNode($path)
			->setShareOwner('shareOwner')
			->setProviderId('foo')
			->setId('bar');

		$share2 = $this->manager->newShare();
		$share2->setShareType(IShare::TYPE_GROUP)
			->setShareOwner('shareOwner2')
			->setProviderId('foo')
			->setId('baz')
			->setSharedWith('group');

		$this->groupManager->method('get')->with('group')->willReturn(null);

		$this->defaultProvider
			->method('getSharesByPath')
			->with($path)
			->willReturn([$share2]);

		$this->assertNull($this->invokePrivate($this->manager, 'userCreateChecks', [$share]));
	}

	public function testUserCreateChecksIdenticalPathNotSharedWithUser() {
		$share = $this->manager->newShare();
		$sharedWith = $this->createMock(IUser::class);
		$path = $this->createMock(Node::class);
		$share->setSharedWith('sharedWith')
			->setNode($path)
			->setShareOwner('shareOwner')
			->setProviderId('foo')
			->setId('bar');

		$this->userManager->method('get')->with('sharedWith')->willReturn($sharedWith);

		$share2 = $this->manager->newShare();
		$share2->setShareType(IShare::TYPE_GROUP)
			->setShareOwner('shareOwner2')
			->setProviderId('foo')
			->setId('baz');

		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')
			->with($sharedWith)
			->willReturn(false);

		$this->groupManager->method('get')->with('group')->willReturn($group);

		$share2->setSharedWith('group');

		$this->defaultProvider
			->method('getSharesByPath')
			->with($path)
			->willReturn([$share2]);

		self::invokePrivate($this->manager, 'userCreateChecks', [$share]);
		$this->addToAssertionCount(1);
	}


	public function testGroupCreateChecksShareWithGroupMembersGroupSharingNotAllowed() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Group sharing is now allowed');

		$share = $this->manager->newShare();

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_allow_group_sharing', 'yes', 'no'],
			]);

		self::invokePrivate($this->manager, 'groupCreateChecks', [$share]);
	}


	public function testGroupCreateChecksShareWithGroupMembersOnlyNotInGroup() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sharing is only allowed within your own groups');

		$share = $this->manager->newShare();

		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);
		$share->setSharedBy('user')->setSharedWith('group');

		$group->method('inGroup')->with($user)->willReturn(false);

		$this->groupManager->method('get')->with('group')->willReturn($group);
		$this->userManager->method('get')->with('user')->willReturn($user);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
				['core', 'shareapi_allow_group_sharing', 'yes', 'yes'],
			]);

		self::invokePrivate($this->manager, 'groupCreateChecks', [$share]);
	}


	public function testGroupCreateChecksShareWithGroupMembersOnlyNullGroup() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Sharing is only allowed within your own groups');

		$share = $this->manager->newShare();

		$user = $this->createMock(IUser::class);
		$share->setSharedBy('user')->setSharedWith('group');

		$this->groupManager->method('get')->with('group')->willReturn(null);
		$this->userManager->method('get')->with('user')->willReturn($user);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
				['core', 'shareapi_allow_group_sharing', 'yes', 'yes'],
			]);

		$this->assertNull($this->invokePrivate($this->manager, 'groupCreateChecks', [$share]));
	}

	public function testGroupCreateChecksShareWithGroupMembersOnlyInGroup() {
		$share = $this->manager->newShare();

		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);
		$share->setSharedBy('user')->setSharedWith('group');

		$this->userManager->method('get')->with('user')->willReturn($user);
		$this->groupManager->method('get')->with('group')->willReturn($group);

		$group->method('inGroup')->with($user)->willReturn(true);

		$path = $this->createMock(Node::class);
		$share->setNode($path);

		$this->defaultProvider->method('getSharesByPath')
			->with($path)
			->willReturn([]);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
				['core', 'shareapi_allow_group_sharing', 'yes', 'yes'],
			]);

		self::invokePrivate($this->manager, 'groupCreateChecks', [$share]);
		$this->addToAssertionCount(1);
	}


	public function testGroupCreateChecksPathAlreadySharedWithSameGroup() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Path is already shared with this group');

		$share = $this->manager->newShare();

		$path = $this->createMock(Node::class);
		$share->setSharedWith('sharedWith')
			->setNode($path)
			->setProviderId('foo')
			->setId('bar');

		$share2 = $this->manager->newShare();
		$share2->setSharedWith('sharedWith')
			->setProviderId('foo')
			->setId('baz');

		$this->defaultProvider->method('getSharesByPath')
			->with($path)
			->willReturn([$share2]);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_allow_group_sharing', 'yes', 'yes'],
			]);

		self::invokePrivate($this->manager, 'groupCreateChecks', [$share]);
	}

	public function testGroupCreateChecksPathAlreadySharedWithDifferentGroup() {
		$share = $this->manager->newShare();

		$share->setSharedWith('sharedWith');

		$path = $this->createMock(Node::class);
		$share->setNode($path);

		$share2 = $this->manager->newShare();
		$share2->setSharedWith('sharedWith2');

		$this->defaultProvider->method('getSharesByPath')
			->with($path)
			->willReturn([$share2]);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_allow_group_sharing', 'yes', 'yes'],
			]);

		self::invokePrivate($this->manager, 'groupCreateChecks', [$share]);
		$this->addToAssertionCount(1);
	}


	public function testLinkCreateChecksNoLinkSharesAllowed() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Link sharing is not allowed');

		$share = $this->manager->newShare();

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_allow_links', 'yes', 'no'],
			]);

		self::invokePrivate($this->manager, 'linkCreateChecks', [$share]);
	}


	public function testLinkCreateChecksNoPublicUpload() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Public upload is not allowed');

		$share = $this->manager->newShare();

		$share->setPermissions(\OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_allow_links', 'yes', 'yes'],
				['core', 'shareapi_allow_public_upload', 'yes', 'no']
			]);

		self::invokePrivate($this->manager, 'linkCreateChecks', [$share]);
	}

	public function testLinkCreateChecksPublicUpload() {
		$share = $this->manager->newShare();

		$share->setPermissions(\OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_allow_links', 'yes', 'yes'],
				['core', 'shareapi_allow_public_upload', 'yes', 'yes']
			]);

		self::invokePrivate($this->manager, 'linkCreateChecks', [$share]);
		$this->addToAssertionCount(1);
	}

	public function testLinkCreateChecksReadOnly() {
		$share = $this->manager->newShare();

		$share->setPermissions(\OCP\Constants::PERMISSION_READ);

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_allow_links', 'yes', 'yes'],
				['core', 'shareapi_allow_public_upload', 'yes', 'no']
			]);

		self::invokePrivate($this->manager, 'linkCreateChecks', [$share]);
		$this->addToAssertionCount(1);
	}


	public function testPathCreateChecksContainsSharedMount() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Path contains files shared with you');

		$path = $this->createMock(Folder::class);
		$path->method('getPath')->willReturn('path');

		$mount = $this->createMock(IMountPoint::class);
		$storage = $this->createMock(Storage::class);
		$mount->method('getStorage')->willReturn($storage);
		$storage->method('instanceOfStorage')->with('\OCA\Files_Sharing\ISharedStorage')->willReturn(true);

		$this->mountManager->method('findIn')->with('path')->willReturn([$mount]);

		self::invokePrivate($this->manager, 'pathCreateChecks', [$path]);
	}

	public function testPathCreateChecksContainsNoSharedMount() {
		$path = $this->createMock(Folder::class);
		$path->method('getPath')->willReturn('path');

		$mount = $this->createMock(IMountPoint::class);
		$storage = $this->createMock(Storage::class);
		$mount->method('getStorage')->willReturn($storage);
		$storage->method('instanceOfStorage')->with('\OCA\Files_Sharing\ISharedStorage')->willReturn(false);

		$this->mountManager->method('findIn')->with('path')->willReturn([$mount]);

		self::invokePrivate($this->manager, 'pathCreateChecks', [$path]);
		$this->addToAssertionCount(1);
	}

	public function testPathCreateChecksContainsNoFolder() {
		$path = $this->createMock(File::class);

		self::invokePrivate($this->manager, 'pathCreateChecks', [$path]);
		$this->addToAssertionCount(1);
	}

	public function dataIsSharingDisabledForUser() {
		$data = [];

		// No exclude groups
		$data[] = ['no', null, null, [], false];

		// empty exclude list, user no groups
		$data[] = ['yes', '', json_encode(['']), [], false];

		// empty exclude list, user groups
		$data[] = ['yes', '', json_encode(['']), ['group1', 'group2'], false];

		// Convert old list to json
		$data[] = ['yes', 'group1,group2', json_encode(['group1', 'group2']), [], false];

		// Old list partly groups in common
		$data[] = ['yes', 'group1,group2', json_encode(['group1', 'group2']), ['group1', 'group3'], false];

		// Old list only groups in common
		$data[] = ['yes', 'group1,group2', json_encode(['group1', 'group2']), ['group1'], true];

		// New list partly in common
		$data[] = ['yes', json_encode(['group1', 'group2']), null, ['group1', 'group3'], false];

		// New list only groups in common
		$data[] = ['yes', json_encode(['group1', 'group2']), null, ['group2'], true];

		return $data;
	}

	/**
	 * @dataProvider dataIsSharingDisabledForUser
	 *
	 * @param string $excludeGroups
	 * @param string $groupList
	 * @param string $setList
	 * @param string[] $groupIds
	 * @param bool $expected
	 */
	public function testIsSharingDisabledForUser($excludeGroups, $groupList, $setList, $groupIds, $expected) {
		$user = $this->createMock(IUser::class);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_exclude_groups', 'no', $excludeGroups],
				['core', 'shareapi_exclude_groups_list', '', $groupList],
			]);

		if ($setList !== null) {
			$this->config->expects($this->once())
				->method('setAppValue')
				->with('core', 'shareapi_exclude_groups_list', $setList);
		} else {
			$this->config->expects($this->never())
				->method('setAppValue');
		}

		$this->groupManager->method('getUserGroupIds')
			->with($user)
			->willReturn($groupIds);

		$this->userManager->method('get')->with('user')->willReturn($user);

		$res = $this->manager->sharingDisabledForUser('user');
		$this->assertEquals($expected, $res);
	}

	public function dataCanShare() {
		$data = [];

		/*
		 * [expected, sharing enabled, disabled for user]
		 */

		$data[] = [false, 'no', false];
		$data[] = [false, 'no', true];
		$data[] = [true, 'yes', false];
		$data[] = [false, 'yes', true];

		return $data;
	}

	/**
	 * @dataProvider dataCanShare
	 *
	 * @param bool $expected
	 * @param string $sharingEnabled
	 * @param bool $disabledForUser
	 */
	public function testCanShare($expected, $sharingEnabled, $disabledForUser) {
		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enabled', 'yes', $sharingEnabled],
			]);

		$manager = $this->createManagerMock()
			->setMethods(['sharingDisabledForUser'])
			->getMock();

		$manager->method('sharingDisabledForUser')
			->with('user')
			->willReturn($disabledForUser);

		$share = $this->manager->newShare();
		$share->setSharedBy('user');

		$exception = false;
		try {
			$res = self::invokePrivate($manager, 'canShare', [$share]);
		} catch (\Exception $e) {
			$exception = true;
		}

		$this->assertEquals($expected, !$exception);
	}

	public function testCreateShareUser() {
		$manager = $this->createManagerMock()
			->setMethods(['canShare', 'generalCreateChecks', 'userCreateChecks', 'pathCreateChecks'])
			->getMock();

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$storage = $this->createMock(Storage::class);
		$path = $this->createMock(File::class);
		$path->method('getOwner')->willReturn($shareOwner);
		$path->method('getName')->willReturn('target');
		$path->method('getStorage')->willReturn($storage);

		$share = $this->createShare(
			null,
			IShare::TYPE_USER,
			$path,
			'sharedWith',
			'sharedBy',
			null,
			\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())
			->method('canShare')
			->with($share)
			->willReturn(true);
		$manager->expects($this->once())
			->method('generalCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('userCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('pathCreateChecks')
			->with($path);

		$this->defaultProvider
			->expects($this->once())
			->method('create')
			->with($share)
			->willReturnArgument(0);

		$share->expects($this->once())
			->method('setShareOwner')
			->with('shareOwner');
		$share->expects($this->once())
			->method('setTarget')
			->with('/target');

		$manager->createShare($share);
	}

	public function testCreateShareGroup() {
		$manager = $this->createManagerMock()
			->setMethods(['canShare', 'generalCreateChecks', 'groupCreateChecks', 'pathCreateChecks'])
			->getMock();

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$storage = $this->createMock(Storage::class);
		$path = $this->createMock(File::class);
		$path->method('getOwner')->willReturn($shareOwner);
		$path->method('getName')->willReturn('target');
		$path->method('getStorage')->willReturn($storage);

		$share = $this->createShare(
			null,
			IShare::TYPE_GROUP,
			$path,
			'sharedWith',
			'sharedBy',
			null,
			\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())
			->method('canShare')
			->with($share)
			->willReturn(true);
		$manager->expects($this->once())
			->method('generalCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('groupCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('pathCreateChecks')
			->with($path);

		$this->defaultProvider
			->expects($this->once())
			->method('create')
			->with($share)
			->willReturnArgument(0);

		$share->expects($this->once())
			->method('setShareOwner')
			->with('shareOwner');
		$share->expects($this->once())
			->method('setTarget')
			->with('/target');

		$manager->createShare($share);
	}

	public function testCreateShareLink() {
		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'generalCreateChecks',
				'linkCreateChecks',
				'pathCreateChecks',
				'validateExpirationDateLink',
				'verifyPassword',
				'setLinkParent',
			])
			->getMock();

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$storage = $this->createMock(Storage::class);
		$path = $this->createMock(File::class);
		$path->method('getOwner')->willReturn($shareOwner);
		$path->method('getName')->willReturn('target');
		$path->method('getId')->willReturn(1);
		$path->method('getStorage')->willReturn($storage);

		$date = new \DateTime();

		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_LINK)
			->setNode($path)
			->setSharedBy('sharedBy')
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setExpirationDate($date)
			->setPassword('password');

		$manager->expects($this->once())
			->method('canShare')
			->with($share)
			->willReturn(true);
		$manager->expects($this->once())
			->method('generalCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('linkCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('pathCreateChecks')
			->with($path);
		$manager->expects($this->once())
			->method('validateExpirationDateLink')
			->with($share)
			->willReturn($share);
		$manager->expects($this->once())
			->method('verifyPassword')
			->with('password');
		$manager->expects($this->once())
			->method('setLinkParent')
			->with($share);

		$this->hasher->expects($this->once())
			->method('hash')
			->with('password')
			->willReturn('hashed');

		$this->secureRandom->method('generate')
			->willReturn('token');

		$this->defaultProvider
			->expects($this->once())
			->method('create')
			->with($share)
			->willReturnCallback(function (Share $share) {
				return $share->setId(42);
			});

		// Pre share
		$this->eventDispatcher->expects($this->at(0))
			->method('dispatch')
			->with(
				$this->equalTo('OCP\Share::preShare'),
				$this->callback(function (GenericEvent $e) use ($path, $date) {
					/** @var IShare $share */
					$share = $e->getSubject();

					return $share->getShareType() === IShare::TYPE_LINK &&
						$share->getNode() === $path &&
						$share->getSharedBy() === 'sharedBy' &&
						$share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
						$share->getExpirationDate() === $date &&
						$share->getPassword() === 'hashed' &&
						$share->getToken() === 'token';
				})
			);

		// Post share
		$this->eventDispatcher->expects($this->at(1))
			->method('dispatch')
			->with(
				$this->equalTo('OCP\Share::postShare'),
				$this->callback(function (GenericEvent $e) use ($path, $date) {
					/** @var IShare $share */
					$share = $e->getSubject();

					return $share->getShareType() === IShare::TYPE_LINK &&
						$share->getNode() === $path &&
						$share->getSharedBy() === 'sharedBy' &&
						$share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
						$share->getExpirationDate() === $date &&
						$share->getPassword() === 'hashed' &&
						$share->getToken() === 'token' &&
						$share->getId() === '42' &&
						$share->getTarget() === '/target';
				})
			);

		/** @var IShare $share */
		$share = $manager->createShare($share);

		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertEquals('/target', $share->getTarget());
		$this->assertSame($date, $share->getExpirationDate());
		$this->assertEquals('token', $share->getToken());
		$this->assertEquals('hashed', $share->getPassword());
	}

	public function testCreateShareMail() {
		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'generalCreateChecks',
				'linkCreateChecks',
				'pathCreateChecks',
				'validateExpirationDateLink',
				'verifyPassword',
				'setLinkParent',
			])
			->getMock();

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$storage = $this->createMock(Storage::class);
		$path = $this->createMock(File::class);
		$path->method('getOwner')->willReturn($shareOwner);
		$path->method('getName')->willReturn('target');
		$path->method('getId')->willReturn(1);
		$path->method('getStorage')->willReturn($storage);

		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_EMAIL)
			->setNode($path)
			->setSharedBy('sharedBy')
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())
			->method('canShare')
			->with($share)
			->willReturn(true);
		$manager->expects($this->once())
			->method('generalCreateChecks')
			->with($share);

		$manager->expects($this->once())
			->method('linkCreateChecks');
		$manager->expects($this->once())
			->method('pathCreateChecks')
			->with($path);
		$manager->expects($this->once())
			->method('validateExpirationDateLink')
			->with($share)
			->willReturn($share);
		$manager->expects($this->once())
			->method('verifyPassword');
		$manager->expects($this->once())
			->method('setLinkParent');

		$this->secureRandom->method('generate')
			->willReturn('token');

		$this->defaultProvider
			->expects($this->once())
			->method('create')
			->with($share)
			->willReturnCallback(function (Share $share) {
				return $share->setId(42);
			});

		// Pre share
		$this->eventDispatcher->expects($this->at(0))
			->method('dispatch')
			->with(
				$this->equalTo('OCP\Share::preShare'),
				$this->callback(function (GenericEvent $e) use ($path) {
					/** @var IShare $share */
					$share = $e->getSubject();

					return $share->getShareType() === IShare::TYPE_EMAIL &&
						$share->getNode() === $path &&
						$share->getSharedBy() === 'sharedBy' &&
						$share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
						$share->getExpirationDate() === null &&
						$share->getPassword() === null &&
						$share->getToken() === 'token';
				})
			);

		// Post share
		$this->eventDispatcher->expects($this->at(1))
			->method('dispatch')
			->with(
				$this->equalTo('OCP\Share::postShare'),
				$this->callback(function (GenericEvent $e) use ($path) {
					/** @var IShare $share */
					$share = $e->getSubject();

					return $share->getShareType() === IShare::TYPE_EMAIL &&
						$share->getNode() === $path &&
						$share->getSharedBy() === 'sharedBy' &&
						$share->getPermissions() === \OCP\Constants::PERMISSION_ALL &&
						$share->getExpirationDate() === null &&
						$share->getPassword() === null &&
						$share->getToken() === 'token' &&
						$share->getId() === '42' &&
						$share->getTarget() === '/target';
				})
			);

		/** @var IShare $share */
		$share = $manager->createShare($share);

		$this->assertSame('shareOwner', $share->getShareOwner());
		$this->assertEquals('/target', $share->getTarget());
		$this->assertEquals('token', $share->getToken());
	}


	public function testCreateShareHookError() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('I won\'t let you share');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'generalCreateChecks',
				'userCreateChecks',
				'pathCreateChecks',
			])
			->getMock();

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$storage = $this->createMock(Storage::class);
		$path = $this->createMock(File::class);
		$path->method('getOwner')->willReturn($shareOwner);
		$path->method('getName')->willReturn('target');
		$path->method('getStorage')->willReturn($storage);

		$share = $this->createShare(
			null,
			IShare::TYPE_USER,
			$path,
			'sharedWith',
			'sharedBy',
			null,
			\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())
			->method('canShare')
			->with($share)
			->willReturn(true);
		$manager->expects($this->once())
			->method('generalCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('userCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('pathCreateChecks')
			->with($path);

		$share->expects($this->once())
			->method('setShareOwner')
			->with('shareOwner');
		$share->expects($this->once())
			->method('setTarget')
			->with('/target');

		// Pre share
		$this->eventDispatcher->expects($this->once())
			->method('dispatch')
			->with(
				$this->equalTo('OCP\Share::preShare'),
				$this->isInstanceOf(GenericEvent::class)
			)->willReturnCallback(function ($name, GenericEvent $e) {
				$e->setArgument('error', 'I won\'t let you share!');
				$e->stopPropagation();
			}
			);

		$manager->createShare($share);
	}

	public function testCreateShareOfIncomingFederatedShare() {
		$manager = $this->createManagerMock()
			->setMethods(['canShare', 'generalCreateChecks', 'userCreateChecks', 'pathCreateChecks'])
			->getMock();

		$shareOwner = $this->createMock(IUser::class);
		$shareOwner->method('getUID')->willReturn('shareOwner');

		$storage = $this->createMock(Storage::class);
		$storage->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(true);

		$storage2 = $this->createMock(Storage::class);
		$storage2->method('instanceOfStorage')
			->with('OCA\Files_Sharing\External\Storage')
			->willReturn(false);

		$path = $this->createMock(File::class);
		$path->expects($this->never())->method('getOwner');
		$path->method('getName')->willReturn('target');
		$path->method('getStorage')->willReturn($storage);

		$parent = $this->createMock(Folder::class);
		$parent->method('getStorage')->willReturn($storage);

		$parentParent = $this->createMock(Folder::class);
		$parentParent->method('getStorage')->willReturn($storage2);
		$parentParent->method('getOwner')->willReturn($shareOwner);

		$path->method('getParent')->willReturn($parent);
		$parent->method('getParent')->willReturn($parentParent);

		$share = $this->createShare(
			null,
			IShare::TYPE_USER,
			$path,
			'sharedWith',
			'sharedBy',
			null,
			\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())
			->method('canShare')
			->with($share)
			->willReturn(true);
		$manager->expects($this->once())
			->method('generalCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('userCreateChecks')
			->with($share);
		;
		$manager->expects($this->once())
			->method('pathCreateChecks')
			->with($path);

		$this->defaultProvider
			->expects($this->once())
			->method('create')
			->with($share)
			->willReturnArgument(0);

		$share->expects($this->once())
			->method('setShareOwner')
			->with('shareOwner');
		$share->expects($this->once())
			->method('setTarget')
			->with('/target');

		$manager->createShare($share);
	}

	public function testGetSharesBy() {
		$share = $this->manager->newShare();

		$node = $this->createMock(Folder::class);

		$this->defaultProvider->expects($this->once())
			->method('getSharesBy')
			->with(
				$this->equalTo('user'),
				$this->equalTo(IShare::TYPE_USER),
				$this->equalTo($node),
				$this->equalTo(true),
				$this->equalTo(1),
				$this->equalTo(1)
			)->willReturn([$share]);

		$shares = $this->manager->getSharesBy('user', IShare::TYPE_USER, $node, true, 1, 1);

		$this->assertCount(1, $shares);
		$this->assertSame($share, $shares[0]);
	}

	/**
	 * Test to ensure we correctly remove expired link shares
	 *
	 * We have 8 Shares and we want the 3 first valid shares.
	 * share 3-6 and 8 are expired. Thus at the end of this test we should
	 * have received share 1,2 and 7. And from the manager. Share 3-6 should be
	 * deleted (as they are evaluated). but share 8 should still be there.
	 */
	public function testGetSharesByExpiredLinkShares() {
		$manager = $this->createManagerMock()
			->setMethods(['deleteShare'])
			->getMock();

		/** @var \OCP\Share\IShare[] $shares */
		$shares = [];

		/*
		 * This results in an array of 8 IShare elements
		 */
		for ($i = 0; $i < 8; $i++) {
			$share = $this->manager->newShare();
			$share->setId($i);
			$shares[] = $share;
		}

		$today = new \DateTime();
		$today->setTime(0,0,0);

		/*
		 * Set the expiration date to today for some shares
		 */
		$shares[2]->setExpirationDate($today);
		$shares[3]->setExpirationDate($today);
		$shares[4]->setExpirationDate($today);
		$shares[5]->setExpirationDate($today);

		/** @var \OCP\Share\IShare[] $i */
		$shares2 = [];
		for ($i = 0; $i < 8; $i++) {
			$shares2[] = clone $shares[$i];
		}

		$node = $this->createMock(File::class);

		/*
		 * Simulate the getSharesBy call.
		 */
		$this->defaultProvider
			->method('getSharesBy')
			->willReturnCallback(function ($uid, $type, $node, $reshares, $limit, $offset) use (&$shares2) {
				return array_slice($shares2, $offset, $limit);
			});

		/*
		 * Simulate the deleteShare call.
		 */
		$manager->method('deleteShare')
			->willReturnCallback(function ($share) use (&$shares2) {
				for ($i = 0; $i < count($shares2); $i++) {
					if ($shares2[$i]->getId() === $share->getId()) {
						array_splice($shares2, $i, 1);
						break;
					}
				}
			});

		$res = $manager->getSharesBy('user', IShare::TYPE_LINK, $node, true, 3, 0);

		$this->assertCount(3, $res);
		$this->assertEquals($shares[0]->getId(), $res[0]->getId());
		$this->assertEquals($shares[1]->getId(), $res[1]->getId());
		$this->assertEquals($shares[6]->getId(), $res[2]->getId());

		$this->assertCount(4, $shares2);
		$this->assertEquals(0, $shares2[0]->getId());
		$this->assertEquals(1, $shares2[1]->getId());
		$this->assertEquals(6, $shares2[2]->getId());
		$this->assertEquals(7, $shares2[3]->getId());
		$this->assertSame($today, $shares[3]->getExpirationDate());
	}

	public function testGetShareByToken() {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('yes');

		$factory = $this->createMock(IProviderFactory::class);

		$manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);

		$share = $this->createMock(IShare::class);

		$factory->expects($this->once())
			->method('getProviderForType')
			->with(IShare::TYPE_LINK)
			->willReturn($this->defaultProvider);

		$this->defaultProvider->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);

		$ret = $manager->getShareByToken('token');
		$this->assertSame($share, $ret);
	}

	public function testGetShareByTokenRoom() {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('no');

		$factory = $this->createMock(IProviderFactory::class);

		$manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);

		$share = $this->createMock(IShare::class);

		$roomShareProvider = $this->createMock(IShareProvider::class);

		$factory->expects($this->any())
			->method('getProviderForType')
			->willReturnCallback(function ($shareType) use ($roomShareProvider) {
				if ($shareType !== IShare::TYPE_ROOM) {
					throw new Exception\ProviderException();
				}

				return $roomShareProvider;
			});

		$roomShareProvider->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);

		$ret = $manager->getShareByToken('token');
		$this->assertSame($share, $ret);
	}

	public function testGetShareByTokenWithException() {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('yes');

		$factory = $this->createMock(IProviderFactory::class);

		$manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);

		$share = $this->createMock(IShare::class);

		$factory->expects($this->at(0))
			->method('getProviderForType')
			->with(IShare::TYPE_LINK)
			->willReturn($this->defaultProvider);
		$factory->expects($this->at(1))
			->method('getProviderForType')
			->with(IShare::TYPE_REMOTE)
			->willReturn($this->defaultProvider);

		$this->defaultProvider->expects($this->at(0))
			->method('getShareByToken')
			->with('token')
			->will($this->throwException(new ShareNotFound()));
		$this->defaultProvider->expects($this->at(1))
			->method('getShareByToken')
			->with('token')
			->willReturn($share);

		$ret = $manager->getShareByToken('token');
		$this->assertSame($share, $ret);
	}


	public function testGetShareByTokenExpired() {
		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);
		$this->expectExceptionMessage('The requested share does not exist anymore');

		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('yes');

		$this->l->expects($this->once())
			->method('t')
			->willReturnArgument(0);

		$manager = $this->createManagerMock()
			->setMethods(['deleteShare'])
			->getMock();

		$date = new \DateTime();
		$date->setTime(0,0,0);
		$share = $this->manager->newShare();
		$share->setExpirationDate($date);

		$this->defaultProvider->expects($this->once())
			->method('getShareByToken')
			->with('expiredToken')
			->willReturn($share);

		$manager->expects($this->once())
			->method('deleteShare')
			->with($this->equalTo($share));

		$manager->getShareByToken('expiredToken');
	}

	public function testGetShareByTokenNotExpired() {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('yes');

		$date = new \DateTime();
		$date->setTime(0,0,0);
		$date->add(new \DateInterval('P2D'));
		$share = $this->manager->newShare();
		$share->setExpirationDate($date);

		$this->defaultProvider->expects($this->once())
			->method('getShareByToken')
			->with('expiredToken')
			->willReturn($share);

		$res = $this->manager->getShareByToken('expiredToken');

		$this->assertSame($share, $res);
	}


	public function testGetShareByTokenWithPublicLinksDisabled() {
		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);

		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('no');
		$this->manager->getShareByToken('validToken');
	}

	public function testGetShareByTokenPublicUploadDisabled() {
		$this->config
			->expects($this->at(0))
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('yes');

		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_LINK)
			->setPermissions(\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE);

		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_allow_public_upload', 'yes', 'no'],
			]);

		$this->defaultProvider->expects($this->once())
			->method('getShareByToken')
			->willReturn('validToken')
			->willReturn($share);

		$res = $this->manager->getShareByToken('validToken');

		$this->assertSame(\OCP\Constants::PERMISSION_READ, $res->getPermissions());
	}

	public function testCheckPasswordNoLinkShare() {
		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(IShare::TYPE_USER);
		$this->assertFalse($this->manager->checkPassword($share, 'password'));
	}

	public function testCheckPasswordNoPassword() {
		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(IShare::TYPE_LINK);
		$this->assertFalse($this->manager->checkPassword($share, 'password'));

		$share->method('getPassword')->willReturn('password');
		$this->assertFalse($this->manager->checkPassword($share, null));
	}

	public function testCheckPasswordInvalidPassword() {
		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(IShare::TYPE_LINK);
		$share->method('getPassword')->willReturn('password');

		$this->hasher->method('verify')->with('invalidpassword', 'password', '')->willReturn(false);

		$this->assertFalse($this->manager->checkPassword($share, 'invalidpassword'));
	}

	public function testCheckPasswordValidPassword() {
		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(IShare::TYPE_LINK);
		$share->method('getPassword')->willReturn('passwordHash');

		$this->hasher->method('verify')->with('password', 'passwordHash', '')->willReturn(true);

		$this->assertTrue($this->manager->checkPassword($share, 'password'));
	}

	public function testCheckPasswordUpdateShare() {
		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_LINK)
			->setPassword('passwordHash');

		$this->hasher->method('verify')->with('password', 'passwordHash', '')
			->willReturnCallback(function ($pass, $hash, &$newHash) {
				$newHash = 'newHash';

				return true;
			});

		$this->defaultProvider->expects($this->once())
			->method('update')
			->with($this->callback(function (\OCP\Share\IShare $share) {
				return $share->getPassword() === 'newHash';
			}));

		$this->assertTrue($this->manager->checkPassword($share, 'password'));
	}


	public function testUpdateShareCantChangeShareType() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Can’t change share type');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById'
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_GROUP);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_USER);

		$manager->updateShare($share);
	}


	public function testUpdateShareCantChangeRecipientForGroupShare() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Can only update recipient on user shares');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById'
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_GROUP)
			->setSharedWith('origGroup');

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_GROUP)
			->setSharedWith('newGroup');

		$manager->updateShare($share);
	}


	public function testUpdateShareCantShareWithOwner() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Can’t share with the share owner');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById'
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_USER)
			->setSharedWith('sharedWith');

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_USER)
			->setSharedWith('newUser')
			->setShareOwner('newUser');

		$manager->updateShare($share);
	}

	public function testUpdateShareUser() {
		$this->userManager->expects($this->any())->method('userExists')->willReturn(true);

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'userCreateChecks',
				'pathCreateChecks',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_USER)
			->setSharedWith('origUser')
			->setPermissions(1);

		$node = $this->createMock(File::class);
		$node->method('getId')->willReturn(100);
		$node->method('getPath')->willReturn('/newUser/files/myPath');

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_USER)
			->setSharedWith('origUser')
			->setShareOwner('newUser')
			->setSharedBy('sharer')
			->setPermissions(31)
			->setNode($node);

		$this->defaultProvider->expects($this->once())
			->method('update')
			->with($share)
			->willReturn($share);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$this->rootFolder->method('getUserFolder')->with('newUser')->willReturnSelf();
		$this->rootFolder->method('getRelativePath')->with('/newUser/files/myPath')->willReturn('/myPath');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener2, 'post');
		$hookListener2->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'shareType' => IShare::TYPE_USER,
			'shareWith' => 'origUser',
			'uidOwner' => 'sharer',
			'permissions' => 31,
			'path' => '/myPath',
		]);

		$manager->updateShare($share);
	}

	public function testUpdateShareGroup() {
		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'groupCreateChecks',
				'pathCreateChecks',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_GROUP)
			->setSharedWith('origUser')
			->setPermissions(31);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);

		$node = $this->createMock(File::class);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_GROUP)
			->setSharedWith('origUser')
			->setShareOwner('owner')
			->setNode($node)
			->setPermissions(31);

		$this->defaultProvider->expects($this->once())
			->method('update')
			->with($share)
			->willReturn($share);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener2, 'post');
		$hookListener2->expects($this->never())->method('post');

		$manager->updateShare($share);
	}

	public function testUpdateShareLink() {
		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'linkCreateChecks',
				'pathCreateChecks',
				'verifyPassword',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_LINK)
			->setPermissions(15);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_LINK)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword('password')
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(15);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('validateExpirationDateLink')->with($share);
		$manager->expects($this->once())->method('verifyPassword')->with('password');

		$this->hasher->expects($this->once())
			->method('hash')
			->with('password')
			->willReturn('hashed');

		$this->defaultProvider->expects($this->once())
			->method('update')
			->with($share)
			->willReturn($share);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'date' => $tomorrow,
			'uidOwner' => 'owner',
		]);

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'uidOwner' => 'owner',
			'token' => 'token',
			'disabled' => false,
		]);

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');


		$manager->updateShare($share);
	}

	public function testUpdateShareLinkEnableSendPasswordByTalkWithNoPassword() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Can’t enable sending the password by Talk with an empty password');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'linkCreateChecks',
				'pathCreateChecks',
				'verifyPassword',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_LINK)
			->setPermissions(15);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_LINK)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword(null)
			->setSendPasswordByTalk(true)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(15);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->once())->method('linkCreateChecks')->with($share);
		$manager->expects($this->never())->method('verifyPassword');
		$manager->expects($this->never())->method('pathCreateChecks');
		$manager->expects($this->never())->method('validateExpirationDateLink');

		$this->hasher->expects($this->never())
			->method('hash');

		$this->defaultProvider->expects($this->never())
			->method('update');

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->never())->method('post');

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}

	public function testUpdateShareMail() {
		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword('password')
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->once())->method('verifyPassword')->with('password');
		$manager->expects($this->once())->method('pathCreateChecks')->with($file);
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->once())->method('validateExpirationDateLink');

		$this->hasher->expects($this->once())
			->method('hash')
			->with('password')
			->willReturn('hashed');

		$this->defaultProvider->expects($this->once())
			->method('update')
			->with($share, 'password')
			->willReturn($share);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'date' => $tomorrow,
			'uidOwner' => 'owner',
		]);

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'uidOwner' => 'owner',
			'token' => 'token',
			'disabled' => false,
		]);

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}

	public function testUpdateShareMailEnableSendPasswordByTalk() {
		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setPassword(null)
			->setSendPasswordByTalk(false);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->once())->method('verifyPassword')->with('password');
		$manager->expects($this->once())->method('pathCreateChecks')->with($file);
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->once())->method('validateExpirationDateLink');

		$this->hasher->expects($this->once())
			->method('hash')
			->with('password')
			->willReturn('hashed');

		$this->defaultProvider->expects($this->once())
			->method('update')
			->with($share, 'password')
			->willReturn($share);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'date' => $tomorrow,
			'uidOwner' => 'owner',
		]);

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'uidOwner' => 'owner',
			'token' => 'token',
			'disabled' => false,
		]);

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}

	public function testUpdateShareMailEnableSendPasswordByTalkWithDifferentPassword() {
		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setPassword('anotherPasswordHash')
			->setSendPasswordByTalk(false);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->once())->method('verifyPassword')->with('password');
		$manager->expects($this->once())->method('pathCreateChecks')->with($file);
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->once())->method('validateExpirationDateLink');

		$this->hasher->expects($this->once())
			->method('verify')
			->with('password', 'anotherPasswordHash')
			->willReturn(false);

		$this->hasher->expects($this->once())
			->method('hash')
			->with('password')
			->willReturn('hashed');

		$this->defaultProvider->expects($this->once())
			->method('update')
			->with($share, 'password')
			->willReturn($share);

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'date' => $tomorrow,
			'uidOwner' => 'owner',
		]);

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->once())->method('post')->with([
			'itemType' => 'file',
			'itemSource' => 100,
			'uidOwner' => 'owner',
			'token' => 'token',
			'disabled' => false,
		]);

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}

	public function testUpdateShareMailEnableSendPasswordByTalkWithNoPassword() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Can’t enable sending the password by Talk with an empty password');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setPassword(null)
			->setSendPasswordByTalk(false);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword(null)
			->setSendPasswordByTalk(true)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->never())->method('verifyPassword');
		$manager->expects($this->never())->method('pathCreateChecks');
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->never())->method('validateExpirationDateLink');

		// If the password is empty, we have nothing to hash
		$this->hasher->expects($this->never())
			->method('hash');

		$this->defaultProvider->expects($this->never())
			->method('update');

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->never())->method('post');

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}


	public function testUpdateShareMailEnableSendPasswordByTalkRemovingPassword() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Can’t enable sending the password by Talk with an empty password');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setPassword('passwordHash')
			->setSendPasswordByTalk(false);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword(null)
			->setSendPasswordByTalk(true)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->once())->method('verifyPassword');
		$manager->expects($this->never())->method('pathCreateChecks');
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->never())->method('validateExpirationDateLink');

		// If the password is empty, we have nothing to hash
		$this->hasher->expects($this->never())
			->method('hash');

		$this->defaultProvider->expects($this->never())
			->method('update');

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->never())->method('post');

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}


	public function testUpdateShareMailEnableSendPasswordByTalkRemovingPasswordWithEmptyString() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Can’t enable sending the password by Talk with an empty password');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setPassword('passwordHash')
			->setSendPasswordByTalk(false);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword('')
			->setSendPasswordByTalk(true)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->once())->method('verifyPassword');
		$manager->expects($this->never())->method('pathCreateChecks');
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->never())->method('validateExpirationDateLink');

		// If the password is empty, we have nothing to hash
		$this->hasher->expects($this->never())
			->method('hash');

		$this->defaultProvider->expects($this->never())
			->method('update');

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->never())->method('post');

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}


	public function testUpdateShareMailEnableSendPasswordByTalkWithPreviousPassword() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Can’t enable sending the password by Talk without setting a new password');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setPassword('password')
			->setSendPasswordByTalk(false);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword('password')
			->setSendPasswordByTalk(true)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->never())->method('verifyPassword');
		$manager->expects($this->never())->method('pathCreateChecks');
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->never())->method('validateExpirationDateLink');

		// If the old & new passwords are the same, we don't do anything
		$this->hasher->expects($this->never())
			->method('verify');
		$this->hasher->expects($this->never())
			->method('hash');

		$this->defaultProvider->expects($this->never())
			->method('update');

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->never())->method('post');

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}

	public function testUpdateShareMailDisableSendPasswordByTalkWithPreviousPassword() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Can’t disable sending the password by Talk without setting a new password');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setPassword('passwordHash')
			->setSendPasswordByTalk(true);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword('passwordHash')
			->setSendPasswordByTalk(false)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->never())->method('verifyPassword');
		$manager->expects($this->never())->method('pathCreateChecks');
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->never())->method('validateExpirationDateLink');

		// If the old & new passwords are the same, we don't do anything
		$this->hasher->expects($this->never())
			->method('verify');
		$this->hasher->expects($this->never())
			->method('hash');

		$this->defaultProvider->expects($this->never())
			->method('update');

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->never())->method('post');

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}

	public function testUpdateShareMailDisableSendPasswordByTalkWithoutChangingPassword() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Can’t disable sending the password by Talk without setting a new password');

		$manager = $this->createManagerMock()
			->setMethods([
				'canShare',
				'getShareById',
				'generalCreateChecks',
				'verifyPassword',
				'pathCreateChecks',
				'linkCreateChecks',
				'validateExpirationDateLink',
			])
			->getMock();

		$originalShare = $this->manager->newShare();
		$originalShare->setShareType(IShare::TYPE_EMAIL)
			->setPermissions(\OCP\Constants::PERMISSION_ALL)
			->setPassword('passwordHash')
			->setSendPasswordByTalk(true);

		$tomorrow = new \DateTime();
		$tomorrow->setTime(0,0,0);
		$tomorrow->add(new \DateInterval('P1D'));

		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);

		$share = $this->manager->newShare();
		$share->setProviderId('foo')
			->setId('42')
			->setShareType(IShare::TYPE_EMAIL)
			->setToken('token')
			->setSharedBy('owner')
			->setShareOwner('owner')
			->setPassword('passwordHash')
			->setSendPasswordByTalk(false)
			->setExpirationDate($tomorrow)
			->setNode($file)
			->setPermissions(\OCP\Constants::PERMISSION_ALL);

		$manager->expects($this->once())->method('canShare')->willReturn(true);
		$manager->expects($this->once())->method('getShareById')->with('foo:42')->willReturn($originalShare);
		$manager->expects($this->once())->method('generalCreateChecks')->with($share);
		$manager->expects($this->never())->method('verifyPassword');
		$manager->expects($this->never())->method('pathCreateChecks');
		$manager->expects($this->once())->method('linkCreateChecks');
		$manager->expects($this->never())->method('validateExpirationDateLink');

		// If the old & new passwords are the same, we don't do anything
		$this->hasher->expects($this->never())
			->method('verify');
		$this->hasher->expects($this->never())
			->method('hash');

		$this->defaultProvider->expects($this->never())
			->method('update');

		$hookListener = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_set_expiration_date', $hookListener, 'post');
		$hookListener->expects($this->never())->method('post');

		$hookListener2 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_password', $hookListener2, 'post');
		$hookListener2->expects($this->never())->method('post');

		$hookListener3 = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_update_permissions', $hookListener3, 'post');
		$hookListener3->expects($this->never())->method('post');

		$manager->updateShare($share);
	}

	public function testMoveShareLink() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Can’t change target of link share');

		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_LINK);

		$recipient = $this->createMock(IUser::class);

		$this->manager->moveShare($share, $recipient);
	}


	public function testMoveShareUserNotRecipient() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid recipient');

		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_USER);

		$share->setSharedWith('sharedWith');

		$this->manager->moveShare($share, 'recipient');
	}

	public function testMoveShareUser() {
		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_USER)
			->setId('42')
			->setProviderId('foo');

		$share->setSharedWith('recipient');

		$this->defaultProvider->method('move')->with($share, 'recipient')->willReturnArgument(0);

		$this->manager->moveShare($share, 'recipient');
		$this->addToAssertionCount(1);
	}


	public function testMoveShareGroupNotRecipient() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid recipient');

		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_GROUP);

		$sharedWith = $this->createMock(IGroup::class);
		$share->setSharedWith('shareWith');

		$recipient = $this->createMock(IUser::class);
		$sharedWith->method('inGroup')->with($recipient)->willReturn(false);

		$this->groupManager->method('get')->with('shareWith')->willReturn($sharedWith);
		$this->userManager->method('get')->with('recipient')->willReturn($recipient);

		$this->manager->moveShare($share, 'recipient');
	}


	public function testMoveShareGroupNull() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Group "shareWith" does not exist');

		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_GROUP);
		$share->setSharedWith('shareWith');

		$recipient = $this->createMock(IUser::class);

		$this->groupManager->method('get')->with('shareWith')->willReturn(null);
		$this->userManager->method('get')->with('recipient')->willReturn($recipient);

		$this->manager->moveShare($share, 'recipient');
	}

	public function testMoveShareGroup() {
		$share = $this->manager->newShare();
		$share->setShareType(IShare::TYPE_GROUP)
			->setId('42')
			->setProviderId('foo');

		$group = $this->createMock(IGroup::class);
		$share->setSharedWith('group');

		$recipient = $this->createMock(IUser::class);
		$group->method('inGroup')->with($recipient)->willReturn(true);

		$this->groupManager->method('get')->with('group')->willReturn($group);
		$this->userManager->method('get')->with('recipient')->willReturn($recipient);

		$this->defaultProvider->method('move')->with($share, 'recipient')->willReturnArgument(0);

		$this->manager->moveShare($share, 'recipient');
		$this->addToAssertionCount(1);
	}

	/**
	 * @dataProvider dataTestShareProviderExists
	 */
	public function testShareProviderExists($shareType, $expected) {
		$factory = $this->getMockBuilder('OCP\Share\IProviderFactory')->getMock();
		$factory->expects($this->any())->method('getProviderForType')
			->willReturnCallback(function ($id) {
				if ($id === IShare::TYPE_USER) {
					return true;
				}
				throw new Exception\ProviderException();
			});

		$manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);
		$this->assertSame($expected,
			$manager->shareProviderExists($shareType)
		);
	}

	public function dataTestShareProviderExists() {
		return [
			[IShare::TYPE_USER, true],
			[42, false],
		];
	}

	public function testGetSharesInFolder() {
		$factory = new DummyFactory2($this->createMock(IServerContainer::class));

		$manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);

		$factory->setProvider($this->defaultProvider);
		$extraProvider = $this->createMock(IShareProvider::class);
		$factory->setSecondProvider($extraProvider);

		$share1 = $this->createMock(IShare::class);
		$share2 = $this->createMock(IShare::class);
		$share3 = $this->createMock(IShare::class);
		$share4 = $this->createMock(IShare::class);

		$folder = $this->createMock(Folder::class);

		$this->defaultProvider->method('getSharesInFolder')
			->with(
				$this->equalTo('user'),
				$this->equalTo($folder),
				$this->equalTo(false)
			)->willReturn([
				1 => [$share1],
				2 => [$share2],
			]);

		$extraProvider->method('getSharesInFolder')
			->with(
				$this->equalTo('user'),
				$this->equalTo($folder),
				$this->equalTo(false)
			)->willReturn([
				2 => [$share3],
				3 => [$share4],
			]);

		$result = $manager->getSharesInFolder('user', $folder, false);

		$expects = [
			1 => [$share1],
			2 => [$share2, $share3],
			3 => [$share4],
		];

		$this->assertSame($expects, $result);
	}

	public function testGetAccessList() {
		$factory = new DummyFactory2($this->createMock(IServerContainer::class));

		$manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);

		$factory->setProvider($this->defaultProvider);
		$extraProvider = $this->createMock(IShareProvider::class);
		$factory->setSecondProvider($extraProvider);

		$nodeOwner = $this->createMock(IUser::class);
		$nodeOwner->expects($this->once())
			->method('getUID')
			->willReturn('user1');

		$node = $this->createMock(Node::class);
		$node->expects($this->once())
			->method('getOwner')
			->willReturn($nodeOwner);
		$node->method('getId')
			->willReturn(42);

		$userFolder = $this->createMock(Folder::class);
		$file = $this->createMock(File::class);
		$folder = $this->createMock(Folder::class);

		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
			->method('getUID')
			->willReturn('owner');

		$file->method('getParent')
			->willReturn($folder);
		$file->method('getPath')
			->willReturn('/owner/files/folder/file');
		$file->method('getOwner')
			->willReturn($owner);
		$file->method('getId')
			->willReturn(23);
		$folder->method('getParent')
			->willReturn($userFolder);
		$folder->method('getPath')
			->willReturn('/owner/files/folder');
		$userFolder->method('getById')
			->with($this->equalTo(42))
			->willReturn([12 => $file]);
		$userFolder->method('getPath')
			->willReturn('/user1/files');

		$this->userManager->method('userExists')
			->with($this->equalTo('user1'))
			->willReturn(true);

		$this->defaultProvider->method('getAccessList')
			->with(
				$this->equalTo([$file, $folder]),
				false
			)
			->willReturn([
				'users' => [
					'user1',
					'user2',
					'user3',
					'123456',
				],
				'public' => true,
			]);

		$extraProvider->method('getAccessList')
			->with(
				$this->equalTo([$file, $folder]),
				false
			)
			->willReturn([
				'users' => [
					'user3',
					'user4',
					'user5',
					'234567',
				],
				'remote' => true,
			]);

		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo('user1'))
			->willReturn($userFolder);

		$expected = [
			'users' => ['owner', 'user1', 'user2', 'user3', '123456','user4', 'user5', '234567'],
			'remote' => true,
			'public' => true,
		];

		$result = $manager->getAccessList($node, true, false);

		$this->assertSame($expected['public'], $result['public']);
		$this->assertSame($expected['remote'], $result['remote']);
		$this->assertSame($expected['users'], $result['users']);
	}

	public function testGetAccessListWithCurrentAccess() {
		$factory = new DummyFactory2($this->createMock(IServerContainer::class));

		$manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);

		$factory->setProvider($this->defaultProvider);
		$extraProvider = $this->createMock(IShareProvider::class);
		$factory->setSecondProvider($extraProvider);

		$nodeOwner = $this->createMock(IUser::class);
		$nodeOwner->expects($this->once())
			->method('getUID')
			->willReturn('user1');

		$node = $this->createMock(Node::class);
		$node->expects($this->once())
			->method('getOwner')
			->willReturn($nodeOwner);
		$node->method('getId')
			->willReturn(42);

		$userFolder = $this->createMock(Folder::class);
		$file = $this->createMock(File::class);

		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
			->method('getUID')
			->willReturn('owner');
		$folder = $this->createMock(Folder::class);

		$file->method('getParent')
			->willReturn($folder);
		$file->method('getPath')
			->willReturn('/owner/files/folder/file');
		$file->method('getOwner')
			->willReturn($owner);
		$file->method('getId')
			->willReturn(23);
		$folder->method('getParent')
			->willReturn($userFolder);
		$folder->method('getPath')
			->willReturn('/owner/files/folder');
		$userFolder->method('getById')
			->with($this->equalTo(42))
			->willReturn([42 => $file]);
		$userFolder->method('getPath')
			->willReturn('/user1/files');

		$this->userManager->method('userExists')
			->with($this->equalTo('user1'))
			->willReturn(true);

		$this->defaultProvider->method('getAccessList')
			->with(
				$this->equalTo([$file, $folder]),
				true
			)
			->willReturn([
				'users' => [
					'user1' => [],
					'user2' => [],
					'user3' => [],
					'123456' => [],
				],
				'public' => true,
			]);

		$extraProvider->method('getAccessList')
			->with(
				$this->equalTo([$file, $folder]),
				true
			)
			->willReturn([
				'users' => [
					'user3' => [],
					'user4' => [],
					'user5' => [],
					'234567' => [],
				],
				'remote' => [
					'remote1',
				],
			]);

		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo('user1'))
			->willReturn($userFolder);

		$expected = [
			'users' => [
				'owner' => [
					'node_id' => 23,
					'node_path' => '/folder/file'
				]
				, 'user1' => [], 'user2' => [], 'user3' => [], '123456' => [], 'user4' => [], 'user5' => [], '234567' => []],
			'remote' => [
				'remote1',
			],
			'public' => true,
		];

		$result = $manager->getAccessList($node, true, true);

		$this->assertSame($expected['public'], $result['public']);
		$this->assertSame($expected['remote'], $result['remote']);
		$this->assertSame($expected['users'], $result['users']);
	}

	public function testGetAllShares() {
		$factory = new DummyFactory2($this->createMock(IServerContainer::class));

		$manager = new Manager(
			$this->logger,
			$this->config,
			$this->secureRandom,
			$this->hasher,
			$this->mountManager,
			$this->groupManager,
			$this->l,
			$this->l10nFactory,
			$factory,
			$this->userManager,
			$this->rootFolder,
			$this->eventDispatcher,
			$this->mailer,
			$this->urlGenerator,
			$this->defaults,
			$this->dispatcher
		);

		$factory->setProvider($this->defaultProvider);
		$extraProvider = $this->createMock(IShareProvider::class);
		$factory->setSecondProvider($extraProvider);

		$share1 = $this->createMock(IShare::class);
		$share2 = $this->createMock(IShare::class);
		$share3 = $this->createMock(IShare::class);
		$share4 = $this->createMock(IShare::class);

		$this->defaultProvider->method('getAllShares')
			->willReturnCallback(function () use ($share1, $share2) {
				yield $share1;
				yield $share2;
			});
		$extraProvider->method('getAllShares')
			->willReturnCallback(function () use ($share3, $share4) {
				yield $share3;
				yield $share4;
			});

		// "yield from", used in "getAllShares()", does not reset the keys, so
		// "use_keys" has to be disabled to collect all the values while
		// ignoring the keys returned by the generator.
		$result = iterator_to_array($manager->getAllShares(), $use_keys = false);

		$expects = [$share1, $share2, $share3, $share4];

		$this->assertSame($expects, $result);
	}
}

class DummyFactory implements IProviderFactory {

	/** @var IShareProvider */
	protected $provider;

	public function __construct(\OCP\IServerContainer $serverContainer) {
	}

	/**
	 * @param IShareProvider $provider
	 */
	public function setProvider($provider) {
		$this->provider = $provider;
	}

	/**
	 * @param string $id
	 * @return IShareProvider
	 */
	public function getProvider($id) {
		return $this->provider;
	}

	/**
	 * @param int $shareType
	 * @return IShareProvider
	 */
	public function getProviderForType($shareType) {
		return $this->provider;
	}

	/**
	 * @return IShareProvider[]
	 */
	public function getAllProviders() {
		return [$this->provider];
	}

	public function registerProvider(string $shareProvier): void {
	}
}

class DummyFactory2 extends DummyFactory {
	/** @var IShareProvider */
	private $provider2;

	/**
	 * @param IShareProvider $provider
	 */
	public function setSecondProvider($provider) {
		$this->provider2 = $provider;
	}

	public function getAllProviders() {
		return [$this->provider, $this->provider2];
	}

	public function registerProvider(string $shareProvier): void {
	}
}
