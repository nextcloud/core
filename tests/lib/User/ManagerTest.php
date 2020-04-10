<?php

/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\User;

use OC\AllConfig;
use OC\User\Database;
use OC\User\Manager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

/**
 * Class ManagerTest
 *
 * @group DB
 *
 * @package Test\User
 */
class ManagerTest extends TestCase {

	/** @var IConfig */
	private $config;
	/** @var EventDispatcherInterface */
	private $oldDispatcher;
	/** @var IEventDispatcher */
	private $eventDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->oldDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
	}

	public function testGetBackends() {
		$userDummyBackend = $this->createMock(\Test\Util\User\Dummy::class);
		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($userDummyBackend);
		$this->assertEquals([$userDummyBackend], $manager->getBackends());
		$dummyDatabaseBackend = $this->createMock(Database::class);
		$manager->registerBackend($dummyDatabaseBackend);
		$this->assertEquals([$userDummyBackend, $dummyDatabaseBackend], $manager->getBackends());
	}


	public function testUserExistsSingleBackendExists() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(true);

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$this->assertTrue($manager->userExists('foo'));
	}

	public function testUserExistsSingleBackendNotExists() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(false);

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$this->assertFalse($manager->userExists('foo'));
	}

	public function testUserExistsNoBackends() {
		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);

		$this->assertFalse($manager->userExists('foo'));
	}

	public function testUserExistsTwoBackendsSecondExists() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend1
		 */
		$backend1 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend1->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(false);

		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend2
		 */
		$backend2 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend2->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(true);

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend1);
		$manager->registerBackend($backend2);

		$this->assertTrue($manager->userExists('foo'));
	}

	public function testUserExistsTwoBackendsFirstExists() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend1
		 */
		$backend1 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend1->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(true);

		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend2
		 */
		$backend2 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend2->expects($this->never())
			->method('userExists');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend1);
		$manager->registerBackend($backend2);

		$this->assertTrue($manager->userExists('foo'));
	}

	public function testCheckPassword() {
		/**
		 * @var \OC\User\Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('checkPassword')
			->with($this->equalTo('foo'), $this->equalTo('bar'))
			->willReturn(true);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\USER\BACKEND::CHECK_PASSWORD) {
					return true;
				} else {
					return false;
				}
			});

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$user = $manager->checkPassword('foo', 'bar');
		$this->assertTrue($user instanceof \OC\User\User);
	}

	public function testCheckPasswordNotSupported() {
		/**
		 * @var \OC\User\Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->never())
			->method('checkPassword');

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(false);

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$this->assertFalse($manager->checkPassword('foo', 'bar'));
	}

	public function testGetOneBackendExists() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(true);
		$backend->expects($this->never())
			->method('loginName2UserName');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$this->assertEquals('foo', $manager->get('foo')->getUID());
	}

	public function testGetOneBackendNotExists() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(false);

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$this->assertEquals(null, $manager->get('foo'));
	}

	public function testGetOneBackendDoNotTranslateLoginNames() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('userExists')
			->with($this->equalTo('bLeNdEr'))
			->willReturn(true);
		$backend->expects($this->never())
			->method('loginName2UserName');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$this->assertEquals('bLeNdEr', $manager->get('bLeNdEr')->getUID());
	}

	public function testSearchOneBackend() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('getUsers')
			->with($this->equalTo('fo'))
			->willReturn(['foo', 'afoo', 'Afoo1', 'Bfoo']);
		$backend->expects($this->never())
			->method('loginName2UserName');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$result = $manager->search('fo');
		$this->assertEquals(4, count($result));
		$this->assertEquals('afoo', array_shift($result)->getUID());
		$this->assertEquals('Afoo1', array_shift($result)->getUID());
		$this->assertEquals('Bfoo', array_shift($result)->getUID());
		$this->assertEquals('foo', array_shift($result)->getUID());
	}

	public function testSearchTwoBackendLimitOffset() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend1
		 */
		$backend1 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend1->expects($this->once())
			->method('getUsers')
			->with($this->equalTo('fo'), $this->equalTo(3), $this->equalTo(1))
			->willReturn(['foo1', 'foo2']);
		$backend1->expects($this->never())
			->method('loginName2UserName');

		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend2
		 */
		$backend2 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend2->expects($this->once())
			->method('getUsers')
			->with($this->equalTo('fo'), $this->equalTo(3), $this->equalTo(1))
			->willReturn(['foo3']);
		$backend2->expects($this->never())
			->method('loginName2UserName');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend1);
		$manager->registerBackend($backend2);

		$result = $manager->search('fo', 3, 1);
		$this->assertEquals(3, count($result));
		$this->assertEquals('foo1', array_shift($result)->getUID());
		$this->assertEquals('foo2', array_shift($result)->getUID());
		$this->assertEquals('foo3', array_shift($result)->getUID());
	}

	public function dataCreateUserInvalid() {
		return [
			['te?st', 'foo', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			["te\tst", '', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			["te\nst", '', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			["te\rst", '', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			["te\0st", '', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			["te\x0Bst", '', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			["te\xe2st", '', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			["te\x80st", '', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			["te\x8bst", '', 'Only the following characters are allowed in a username:'
				. ' "a-z", "A-Z", "0-9", and "_.@-\'"'],
			['', 'foo', 'A valid username must be provided'],
			[' ', 'foo', 'A valid username must be provided'],
			[' test', 'foo', 'Username contains whitespace at the beginning or at the end'],
			['test ', 'foo', 'Username contains whitespace at the beginning or at the end'],
			['.', 'foo', 'Username must not consist of dots only'],
			['..', 'foo', 'Username must not consist of dots only'],
			['.test', '', 'A valid password must be provided'],
			['test', '', 'A valid password must be provided'],
		];
	}

	/**
	 * @dataProvider dataCreateUserInvalid
	 */
	public function testCreateUserInvalid($uid, $password, $exception) {
		/** @var \Test\Util\User\Dummy|\PHPUnit_Framework_MockObject_MockObject $backend */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('implementsActions')
			->with(\OC\User\Backend::CREATE_USER)
			->willReturn(true);


		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$this->expectException(\InvalidArgumentException::class, $exception);
		$manager->createUser($uid, $password);
	}

	public function testCreateUserSingleBackendNotExists() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(true);

		$backend->expects($this->once())
			->method('createUser')
			->with($this->equalTo('foo'), $this->equalTo('bar'));

		$backend->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(false);
		$backend->expects($this->never())
			->method('loginName2UserName');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$user = $manager->createUser('foo', 'bar');
		$this->assertEquals('foo', $user->getUID());
	}


	public function testCreateUserSingleBackendExists() {
		$this->expectException(\Exception::class);

		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(true);

		$backend->expects($this->never())
			->method('createUser');

		$backend->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(true);

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$manager->createUser('foo', 'bar');
	}

	public function testCreateUserSingleBackendNotSupported() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(false);

		$backend->expects($this->never())
			->method('createUser');

		$backend->expects($this->never())
			->method('userExists');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$this->assertFalse($manager->createUser('foo', 'bar'));
	}

	public function testCreateUserNoBackends() {
		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);

		$this->assertFalse($manager->createUser('foo', 'bar'));
	}


	public function testCreateUserFromBackendWithBackendError() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Could not create user');

		/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject $config */
		$config = $this->createMock(IConfig::class);
		/** @var \Test\Util\User\Dummy|\PHPUnit_Framework_MockObject_MockObject $backend */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend
			->expects($this->once())
			->method('createUser')
			->with('MyUid', 'MyPassword')
			->willReturn(false);

		$manager = new Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->createUserFromBackend('MyUid', 'MyPassword', $backend);
	}


	public function testCreateUserTwoBackendExists() {
		$this->expectException(\Exception::class);

		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend1
		 */
		$backend1 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend1->expects($this->any())
			->method('implementsActions')
			->willReturn(true);

		$backend1->expects($this->never())
			->method('createUser');

		$backend1->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(false);

		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend2
		 */
		$backend2 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend2->expects($this->any())
			->method('implementsActions')
			->willReturn(true);

		$backend2->expects($this->never())
			->method('createUser');

		$backend2->expects($this->once())
			->method('userExists')
			->with($this->equalTo('foo'))
			->willReturn(true);

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend1);
		$manager->registerBackend($backend2);

		$manager->createUser('foo', 'bar');
	}

	public function testCountUsersNoBackend() {
		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);

		$result = $manager->countUsers();
		$this->assertTrue(is_array($result));
		$this->assertTrue(empty($result));
	}

	public function testCountUsersOneBackend() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('countUsers')
			->willReturn(7);

		$backend->expects($this->once())
			->method('implementsActions')
			->with(\OC\USER\BACKEND::COUNT_USERS)
			->willReturn(true);

		$backend->expects($this->once())
			->method('getBackendName')
			->willReturn('Mock_Test_Util_User_Dummy');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$result = $manager->countUsers();
		$keys = array_keys($result);
		$this->assertTrue(strpos($keys[0], 'Mock_Test_Util_User_Dummy') !== false);

		$users = array_shift($result);
		$this->assertEquals(7, $users);
	}

	public function testCountUsersTwoBackends() {
		/**
		 * @var \Test\Util\User\Dummy | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend1 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend1->expects($this->once())
			->method('countUsers')
			->willReturn(7);

		$backend1->expects($this->once())
			->method('implementsActions')
			->with(\OC\USER\BACKEND::COUNT_USERS)
			->willReturn(true);
		$backend1->expects($this->once())
			->method('getBackendName')
			->willReturn('Mock_Test_Util_User_Dummy');

		$backend2 = $this->createMock(\Test\Util\User\Dummy::class);
		$backend2->expects($this->once())
			->method('countUsers')
			->willReturn(16);

		$backend2->expects($this->once())
			->method('implementsActions')
			->with(\OC\USER\BACKEND::COUNT_USERS)
			->willReturn(true);
		$backend2->expects($this->once())
			->method('getBackendName')
			->willReturn('Mock_Test_Util_User_Dummy');

		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend1);
		$manager->registerBackend($backend2);

		$result = $manager->countUsers();
		//because the backends have the same class name, only one value expected
		$this->assertEquals(1, count($result));
		$keys = array_keys($result);
		$this->assertTrue(strpos($keys[0], 'Mock_Test_Util_User_Dummy') !== false);

		$users = array_shift($result);
		//users from backends shall be summed up
		$this->assertEquals(7 + 16, $users);
	}

	public function testCountUsersOnlyDisabled() {
		$manager = \OC::$server->getUserManager();
		// count other users in the db before adding our own
		$countBefore = $manager->countDisabledUsers();

		//Add test users
		$user1 = $manager->createUser('testdisabledcount1', 'testdisabledcount1');

		$user2 = $manager->createUser('testdisabledcount2', 'testdisabledcount2');
		$user2->setEnabled(false);

		$user3 = $manager->createUser('testdisabledcount3', 'testdisabledcount3');

		$user4 = $manager->createUser('testdisabledcount4', 'testdisabledcount4');
		$user4->setEnabled(false);

		$this->assertEquals($countBefore + 2, $manager->countDisabledUsers());

		//cleanup
		$user1->delete();
		$user2->delete();
		$user3->delete();
		$user4->delete();
	}

	public function testCountUsersOnlySeen() {
		$manager = \OC::$server->getUserManager();
		// count other users in the db before adding our own
		$countBefore = $manager->countUsers(true);

		//Add test users
		$user1 = $manager->createUser('testseencount1', 'testseencount1');
		$user1->updateLastLoginTimestamp();

		$user2 = $manager->createUser('testseencount2', 'testseencount2');
		$user2->updateLastLoginTimestamp();

		$user3 = $manager->createUser('testseencount3', 'testseencount3');

		$user4 = $manager->createUser('testseencount4', 'testseencount4');
		$user4->updateLastLoginTimestamp();

		$this->assertEquals($countBefore + 3, $manager->countUsers(true));

		//cleanup
		$user1->delete();
		$user2->delete();
		$user3->delete();
		$user4->delete();
	}

	public function testCallForSeenUsers() {
		$manager = \OC::$server->getUserManager();
		// count other users in the db before adding our own
		$count = 0;
		$function = function (IUser $user) use (&$count) {
			$count++;
		};
		$manager->callForAllUsers($function, '', true);
		$countBefore = $count;

		//Add test users
		$user1 = $manager->createUser('testseen1', 'testseen1');
		$user1->updateLastLoginTimestamp();

		$user2 = $manager->createUser('testseen2', 'testseen2');
		$user2->updateLastLoginTimestamp();

		$user3 = $manager->createUser('testseen3', 'testseen3');

		$user4 = $manager->createUser('testseen4', 'testseen4');
		$user4->updateLastLoginTimestamp();

		$count = 0;
		$manager->callForAllUsers($function, '', true);

		$this->assertEquals($countBefore + 3, $count);

		//cleanup
		$user1->delete();
		$user2->delete();
		$user3->delete();
		$user4->delete();
	}

	public function testDeleteUser() {
		$manager = new \OC\User\Manager($this->config, $this->oldDispatcher, $this->eventDispatcher);
		$backend = new \Test\Util\User\Dummy();

		$manager->registerBackend($backend);
		$backend->createUser('foo', 'bar');
		$this->assertTrue($manager->userExists('foo'));
		$manager->get('foo')->delete();
		$this->assertFalse($manager->userExists('foo'));
	}

	public function testGetByEmail() {
		$config = $this->getMockBuilder(AllConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$config
			->expects($this->at(0))
			->method('getUsersForUserValueCaseInsensitive')
			->with('settings', 'email', 'test@example.com')
			->willReturn(['uid1', 'uid99', 'uid2']);

		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->at(0))
			->method('userExists')
			->with($this->equalTo('uid1'))
			->willReturn(true);
		$backend->expects($this->at(1))
			->method('userExists')
			->with($this->equalTo('uid99'))
			->willReturn(false);
		$backend->expects($this->at(2))
			->method('userExists')
			->with($this->equalTo('uid2'))
			->willReturn(true);

		$manager = new \OC\User\Manager($config, $this->oldDispatcher, $this->eventDispatcher);
		$manager->registerBackend($backend);

		$users = $manager->getByEmail('test@example.com');
		$this->assertCount(2, $users);
		$this->assertEquals('uid1', $users[0]->getUID());
		$this->assertEquals('uid2', $users[1]->getUID());
	}
}
