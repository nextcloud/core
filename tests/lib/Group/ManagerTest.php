<?php

/**
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH.
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

namespace Test\Group;

use OC\Group\Database;
use OC\User\Manager;
use OCP\GroupInterface;
use OCP\ILogger;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class ManagerTest extends TestCase {
	/** @var Manager|MockObject */
	protected $userManager;
	/** @var EventDispatcherInterface|MockObject */
	protected $dispatcher;
	/** @var ILogger|MockObject */
	protected $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(Manager::class);
		$this->dispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->logger = $this->createMock(ILogger::class);
	}

	private function getTestUser($userId) {
		$mockUser = $this->createMock(IUser::class);
		$mockUser->expects($this->any())
			->method('getUID')
			->willReturn($userId);
		$mockUser->expects($this->any())
			->method('getDisplayName')
			->willReturn($userId);
		return $mockUser;
	}

	/**
	 * @param null|int $implementedActions
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	private function getTestBackend($implementedActions = null) {
		if ($implementedActions === null) {
			$implementedActions =
				GroupInterface::ADD_TO_GROUP |
				GroupInterface::REMOVE_FROM_GOUP |
				GroupInterface::COUNT_USERS |
				GroupInterface::CREATE_GROUP |
				GroupInterface::DELETE_GROUP;
		}
		// need to declare it this way due to optional methods
		// thanks to the implementsActions logic
		$backend = $this->getMockBuilder(GroupInterface::class)
			->disableOriginalConstructor()
			->setMethods([
				'getGroupDetails',
				'implementsActions',
				'getUserGroups',
				'inGroup',
				'getGroups',
				'groupExists',
				'usersInGroup',
				'createGroup',
				'addToGroup',
				'removeFromGroup',
			])
			->getMock();
		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) use ($implementedActions) {
				return (bool)($actions & $implementedActions);
			});
		return $backend;
	}

	public function testGet() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$group = $manager->get('group1');
		$this->assertNotNull($group);
		$this->assertEquals('group1', $group->getGID());
	}

	public function testGetNoBackend() {
		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);

		$this->assertNull($manager->get('group1'));
	}

	public function testGetNotExists() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->once())
			->method('groupExists')
			->with('group1')
			->willReturn(false);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$this->assertNull($manager->get('group1'));
	}

	public function testGetDeleted() {
		$backend = new \Test\Util\Group\Dummy();
		$backend->createGroup('group1');

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$group = $manager->get('group1');
		$group->delete();
		$this->assertNull($manager->get('group1'));
	}

	public function testGetMultipleBackends() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend1
		 */
		$backend1 = $this->getTestBackend();
		$backend1->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(false);

		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend2
		 */
		$backend2 = $this->getTestBackend();
		$backend2->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend1);
		$manager->addBackend($backend2);

		$group = $manager->get('group1');
		$this->assertNotNull($group);
		$this->assertEquals('group1', $group->getGID());
	}

	public function testCreate() {
		/**@var \PHPUnit_Framework_MockObject_MockObject|\OC\Group\Backend $backend */
		$backendGroupCreated = false;
		$backend = $this->getTestBackend();
		$backend->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturnCallback(function () use (&$backendGroupCreated) {
				return $backendGroupCreated;
			});
		$backend->expects($this->once())
			->method('createGroup')
			->willReturnCallback(function () use (&$backendGroupCreated) {
				$backendGroupCreated = true;
				return true;
			});

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$group = $manager->createGroup('group1');
		$this->assertEquals('group1', $group->getGID());
	}

	public function testCreateFailure() {
		/**@var \PHPUnit_Framework_MockObject_MockObject|\OC\Group\Backend $backend */
		$backendGroupCreated = false;
		$backend = $this->getTestBackend(
			GroupInterface::ADD_TO_GROUP |
			GroupInterface::REMOVE_FROM_GOUP |
			GroupInterface::COUNT_USERS |
			GroupInterface::CREATE_GROUP |
			GroupInterface::DELETE_GROUP |
			GroupInterface::GROUP_DETAILS
		);
		$backend->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(false);
		$backend->expects($this->once())
			->method('createGroup')
			->willReturn(false);
		$backend->expects($this->once())
			->method('getGroupDetails')
			->willReturn([]);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$group = $manager->createGroup('group1');
		$this->assertEquals(null, $group);
	}

	public function testCreateExists() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|\OC\Group\Backend $backend */
		$backend = $this->getTestBackend();
		$backend->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(true);
		$backend->expects($this->never())
			->method('createGroup');

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$group = $manager->createGroup('group1');
		$this->assertEquals('group1', $group->getGID());
	}

	public function testSearch() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->once())
			->method('getGroups')
			->with('1')
			->willReturn(['group1']);
		$backend->expects($this->once())
			->method('groupExists')
			->with('group1')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$groups = $manager->search('1');
		$this->assertCount(1, $groups);
		$group1 = reset($groups);
		$this->assertEquals('group1', $group1->getGID());
	}

	public function testSearchMultipleBackends() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend1
		 */
		$backend1 = $this->getTestBackend();
		$backend1->expects($this->once())
			->method('getGroups')
			->with('1')
			->willReturn(['group1']);
		$backend1->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend2
		 */
		$backend2 = $this->getTestBackend();
		$backend2->expects($this->once())
			->method('getGroups')
			->with('1')
			->willReturn(['group12', 'group1']);
		$backend2->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend1);
		$manager->addBackend($backend2);

		$groups = $manager->search('1');
		$this->assertCount(2, $groups);
		$group1 = reset($groups);
		$group12 = next($groups);
		$this->assertEquals('group1', $group1->getGID());
		$this->assertEquals('group12', $group12->getGID());
	}

	public function testSearchMultipleBackendsLimitAndOffset() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend1
		 */
		$backend1 = $this->getTestBackend();
		$backend1->expects($this->once())
			->method('getGroups')
			->with('1', 2, 1)
			->willReturn(['group1']);
		$backend1->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend2
		 */
		$backend2 = $this->getTestBackend();
		$backend2->expects($this->once())
			->method('getGroups')
			->with('1', 2, 1)
			->willReturn(['group12']);
		$backend2->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend1);
		$manager->addBackend($backend2);

		$groups = $manager->search('1', 2, 1);
		$this->assertCount(2, $groups);
		$group1 = reset($groups);
		$group12 = next($groups);
		$this->assertEquals('group1', $group1->getGID());
		$this->assertEquals('group12', $group12->getGID());
	}

	public function testSearchResultExistsButGroupDoesNot() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|\OC\Group\Backend $backend */
		$backend = $this->createMock(Database::class);
		$backend->expects($this->once())
			->method('getGroups')
			->with('1')
			->willReturn(['group1']);
		$backend->expects($this->once())
			->method('groupExists')
			->with('group1')
			->willReturn(false);

		/** @var \OC\User\Manager $userManager */
		$userManager = $this->createMock(Manager::class);

		$manager = new \OC\Group\Manager($userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$groups = $manager->search('1');
		$this->assertEmpty($groups);
	}

	public function testGetUserGroups() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->once())
			->method('getUserGroups')
			->with('user1')
			->willReturn(['group1']);
		$backend->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$groups = $manager->getUserGroups($this->getTestUser('user1'));
		$this->assertCount(1, $groups);
		$group1 = reset($groups);
		$this->assertEquals('group1', $group1->getGID());
	}

	public function testGetUserGroupIds() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|\OC\Group\Manager $manager */
		$manager = $this->getMockBuilder(\OC\Group\Manager::class)
			->disableOriginalConstructor()
			->setMethods(['getUserGroups'])
			->getMock();
		$manager->expects($this->once())
			->method('getUserGroups')
			->willReturn([
				'123' => '123',
				'abc' => 'abc',
			]);

		/** @var \OC\User\User|\PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);

		$groups = $manager->getUserGroupIds($user);
		$this->assertCount(2, $groups);

		foreach ($groups as $group) {
			$this->assertIsString($group);
		}
	}

	public function testGetUserGroupsWithDeletedGroup() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->createMock(Database::class);
		$backend->expects($this->once())
			->method('getUserGroups')
			->with('user1')
			->willReturn(['group1']);
		$backend->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(false);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		/** @var \OC\User\User|\PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->atLeastOnce())
			->method('getUID')
			->willReturn('user1');

		$groups = $manager->getUserGroups($user);
		$this->assertEmpty($groups);
	}

	public function testInGroup() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->once())
			->method('getUserGroups')
			->with('user1')
			->willReturn(['group1', 'admin', 'group2']);
		$backend->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$this->assertTrue($manager->isInGroup('user1', 'group1'));
	}

	public function testIsAdmin() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->once())
			->method('getUserGroups')
			->with('user1')
			->willReturn(['group1', 'admin', 'group2']);
		$backend->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$this->assertTrue($manager->isAdmin('user1'));
	}

	public function testNotAdmin() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->once())
			->method('getUserGroups')
			->with('user1')
			->willReturn(['group1', 'group2']);
		$backend->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$this->assertFalse($manager->isAdmin('user1'));
	}

	public function testGetUserGroupsMultipleBackends() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend1
		 */
		$backend1 = $this->getTestBackend();
		$backend1->expects($this->once())
			->method('getUserGroups')
			->with('user1')
			->willReturn(['group1']);
		$backend1->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend2
		 */
		$backend2 = $this->getTestBackend();
		$backend2->expects($this->once())
			->method('getUserGroups')
			->with('user1')
			->willReturn(['group1', 'group2']);
		$backend1->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend1);
		$manager->addBackend($backend2);

		$groups = $manager->getUserGroups($this->getTestUser('user1'));
		$this->assertCount(2, $groups);
		$group1 = reset($groups);
		$group2 = next($groups);
		$this->assertEquals('group1', $group1->getGID());
		$this->assertEquals('group2', $group2->getGID());
	}

	public function testDisplayNamesInGroupWithOneUserBackend() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->exactly(1))
			->method('groupExists')
			->with('testgroup')
			->willReturn(true);

		$backend->expects($this->any())
			->method('inGroup')
			->willReturnCallback(function ($uid, $gid) {
				switch($uid) {
					case 'user1': return false;
					case 'user2': return true;
					case 'user3': return false;
					case 'user33': return true;
					default:
						return null;
					}
			});

		$this->userManager->expects($this->any())
			->method('searchDisplayName')
			->with('user3')
			->willReturnCallback(function ($search, $limit, $offset) {
				switch($offset) {
					case 0: return ['user3' => $this->getTestUser('user3'),
						'user33' => $this->getTestUser('user33')];
					case 2: return [];
				}
				return null;
			});
		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function ($uid) {
				switch($uid) {
					case 'user1': return $this->getTestUser('user1');
					case 'user2': return $this->getTestUser('user2');
					case 'user3': return $this->getTestUser('user3');
					case 'user33': return $this->getTestUser('user33');
					default:
						return null;
				}
			});

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$users = $manager->displayNamesInGroup('testgroup', 'user3');
		$this->assertCount(1, $users);
		$this->assertFalse(isset($users['user1']));
		$this->assertFalse(isset($users['user2']));
		$this->assertFalse(isset($users['user3']));
		$this->assertTrue(isset($users['user33']));
	}

	public function testDisplayNamesInGroupWithOneUserBackendWithLimitSpecified() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->exactly(1))
			->method('groupExists')
			->with('testgroup')
			->willReturn(true);

				$backend->expects($this->any())
			->method('inGroup')
			->willReturnCallback(function ($uid, $gid) {
					switch($uid) {
						case 'user1': return false;
						case 'user2': return true;
						case 'user3': return false;
						case 'user33': return true;
						case 'user333': return true;
						default:
							return null;
					}
			});

		$this->userManager->expects($this->any())
			->method('searchDisplayName')
			->with('user3')
			->willReturnCallback(function ($search, $limit, $offset) {
				switch($offset) {
					case 0: return ['user3' => $this->getTestUser('user3'),
						'user33' => $this->getTestUser('user33')];
					case 2: return ['user333' => $this->getTestUser('user333')];
				}
				return null;
			});
		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function ($uid) {
				switch($uid) {
					case 'user1': return $this->getTestUser('user1');
					case 'user2': return $this->getTestUser('user2');
					case 'user3': return $this->getTestUser('user3');
					case 'user33': return $this->getTestUser('user33');
					case 'user333': return $this->getTestUser('user333');
					default:
						return null;
				}
			});

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$users = $manager->displayNamesInGroup('testgroup', 'user3', 1);
		$this->assertCount(1, $users);
		$this->assertFalse(isset($users['user1']));
		$this->assertFalse(isset($users['user2']));
		$this->assertFalse(isset($users['user3']));
		$this->assertTrue(isset($users['user33']));
		$this->assertFalse(isset($users['user333']));
	}

	public function testDisplayNamesInGroupWithOneUserBackendWithLimitAndOffsetSpecified() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->exactly(1))
			->method('groupExists')
			->with('testgroup')
			->willReturn(true);

		$backend->expects($this->any())
			->method('inGroup')
			->willReturnCallback(function ($uid) {
					switch($uid) {
						case 'user1': return false;
						case 'user2': return true;
						case 'user3': return false;
						case 'user33': return true;
						case 'user333': return true;
						default:
							return null;
					}
			});

		$this->userManager->expects($this->any())
			->method('searchDisplayName')
			->with('user3')
			->willReturnCallback(function ($search, $limit, $offset) {
				switch($offset) {
					case 0:
						return [
							'user3' => $this->getTestUser('user3'),
							'user33' => $this->getTestUser('user33'),
							'user333' => $this->getTestUser('user333')
						];
				}
				return null;
			});
		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function ($uid) {
				switch($uid) {
					case 'user1': return $this->getTestUser('user1');
					case 'user2': return $this->getTestUser('user2');
					case 'user3': return $this->getTestUser('user3');
					case 'user33': return $this->getTestUser('user33');
					case 'user333': return $this->getTestUser('user333');
					default:
						return null;
				}
			});

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$users = $manager->displayNamesInGroup('testgroup', 'user3', 1, 1);
		$this->assertCount(1, $users);
		$this->assertFalse(isset($users['user1']));
		$this->assertFalse(isset($users['user2']));
		$this->assertFalse(isset($users['user3']));
		$this->assertFalse(isset($users['user33']));
		$this->assertTrue(isset($users['user333']));
	}

	public function testDisplayNamesInGroupWithOneUserBackendAndSearchEmpty() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->exactly(1))
			->method('groupExists')
			->with('testgroup')
			->willReturn(true);

				$backend->expects($this->once())
			->method('usersInGroup')
			->with('testgroup', '', -1, 0)
			->willReturn(['user2', 'user33']);

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function ($uid) {
				switch($uid) {
					case 'user1': return $this->getTestUser('user1');
					case 'user2': return $this->getTestUser('user2');
					case 'user3': return $this->getTestUser('user3');
					case 'user33': return $this->getTestUser('user33');
					default:
						return null;
				}
			});

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$users = $manager->displayNamesInGroup('testgroup', '');
		$this->assertCount(2, $users);
		$this->assertFalse(isset($users['user1']));
		$this->assertTrue(isset($users['user2']));
		$this->assertFalse(isset($users['user3']));
		$this->assertTrue(isset($users['user33']));
	}

	public function testDisplayNamesInGroupWithOneUserBackendAndSearchEmptyAndLimitSpecified() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->exactly(1))
			->method('groupExists')
			->with('testgroup')
			->willReturn(true);

		$backend->expects($this->once())
			->method('usersInGroup')
			->with('testgroup', '', 1, 0)
			->willReturn(['user2']);

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function ($uid) {
				switch($uid) {
					case 'user1': return $this->getTestUser('user1');
					case 'user2': return $this->getTestUser('user2');
					case 'user3': return $this->getTestUser('user3');
					case 'user33': return $this->getTestUser('user33');
					default:
						return null;
				}
			});

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$users = $manager->displayNamesInGroup('testgroup', '', 1);
		$this->assertCount(1, $users);
		$this->assertFalse(isset($users['user1']));
		$this->assertTrue(isset($users['user2']));
		$this->assertFalse(isset($users['user3']));
		$this->assertFalse(isset($users['user33']));
	}

	public function testDisplayNamesInGroupWithOneUserBackendAndSearchEmptyAndLimitAndOffsetSpecified() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->exactly(1))
			->method('groupExists')
			->with('testgroup')
			->willReturn(true);

		$backend->expects($this->once())
			->method('usersInGroup')
			->with('testgroup', '', 1, 1)
			->willReturn(['user33']);

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function ($uid) {
				switch($uid) {
					case 'user1': return $this->getTestUser('user1');
					case 'user2': return $this->getTestUser('user2');
					case 'user3': return $this->getTestUser('user3');
					case 'user33': return $this->getTestUser('user33');
					default:
						return null;
				}
			});

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$users = $manager->displayNamesInGroup('testgroup', '', 1, 1);
		$this->assertCount(1, $users);
		$this->assertFalse(isset($users['user1']));
		$this->assertFalse(isset($users['user2']));
		$this->assertFalse(isset($users['user3']));
		$this->assertTrue(isset($users['user33']));
	}

	public function testGetUserGroupsWithAddUser() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$expectedGroups = [];
		$backend->expects($this->any())
			->method('getUserGroups')
			->with('user1')
			->willReturnCallback(function () use (&$expectedGroups) {
				return $expectedGroups;
			});
		$backend->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		// prime cache
		$user1 = $this->getTestUser('user1');
		$groups = $manager->getUserGroups($user1);
		$this->assertEquals([], $groups);

		// add user
		$group = $manager->get('group1');
		$group->addUser($user1);
		$expectedGroups[] = 'group1';

		// check result
		$groups = $manager->getUserGroups($user1);
		$this->assertCount(1, $groups);
		$group1 = reset($groups);
		$this->assertEquals('group1', $group1->getGID());
	}

	public function testGetUserGroupsWithRemoveUser() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$expectedGroups = ['group1'];
		$backend->expects($this->any())
			->method('getUserGroups')
			->with('user1')
			->willReturnCallback(function () use (&$expectedGroups) {
				return $expectedGroups;
			});
		$backend->expects($this->any())
			->method('groupExists')
			->with('group1')
			->willReturn(true);
		$backend->expects($this->once())
			->method('inGroup')
			->willReturn(true);
		$backend->expects($this->once())
			->method('removeFromGroup')
			->willReturn(true);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		// prime cache
		$user1 = $this->getTestUser('user1');
		$groups = $manager->getUserGroups($user1);
		$this->assertCount(1, $groups);
		$group1 = reset($groups);
		$this->assertEquals('group1', $group1->getGID());

		// remove user
		$group = $manager->get('group1');
		$group->removeUser($user1);
		$expectedGroups = [];

		// check result
		$groups = $manager->getUserGroups($user1);
		$this->assertEquals($expectedGroups, $groups);
	}

	public function testGetUserIdGroups() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend();
		$backend->expects($this->any())
			->method('getUserGroups')
			->with('user1')
			->willReturn(null);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		$groups = $manager->getUserIdGroups('user1');
		$this->assertEquals([], $groups);
	}

	public function testGroupDisplayName() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Group\Backend $backend
		 */
		$backend = $this->getTestBackend(
			GroupInterface::ADD_TO_GROUP |
			GroupInterface::REMOVE_FROM_GOUP |
			GroupInterface::COUNT_USERS |
			GroupInterface::CREATE_GROUP |
			GroupInterface::DELETE_GROUP |
			GroupInterface::GROUP_DETAILS
		);
		$backend->expects($this->any())
			->method('getGroupDetails')
			->willReturnMap([
				['group1', ['gid' => 'group1', 'displayName' => 'Group One']],
				['group2', ['gid' => 'group2']],
			]);

		$manager = new \OC\Group\Manager($this->userManager, $this->dispatcher, $this->logger);
		$manager->addBackend($backend);

		// group with display name
		$group = $manager->get('group1');
		$this->assertNotNull($group);
		$this->assertEquals('group1', $group->getGID());
		$this->assertEquals('Group One', $group->getDisplayName());

		// group without display name
		$group = $manager->get('group2');
		$this->assertNotNull($group);
		$this->assertEquals('group2', $group->getGID());
		$this->assertEquals('group2', $group->getDisplayName());
	}

}
