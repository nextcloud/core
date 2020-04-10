<?php

/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\User;

use OC\AllConfig;
use OC\Hooks\PublicEmitter;
use OC\User\User;
use OCP\Comments\ICommentsManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\UserInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

/**
 * Class UserTest
 *
 * @group DB
 *
 * @package Test\User
 */
class UserTest extends TestCase {

	/** @var EventDispatcherInterface|MockObject */
	protected $dispatcher;

	protected function setUp(): void {
		parent::setUp();
		$this->dispatcher = $this->createMock(EventDispatcherInterface::class);
	}

	public function testDisplayName() {
		/**
		 * @var \OC\User\Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\OC\User\Backend::class);
		$backend->expects($this->once())
			->method('getDisplayName')
			->with($this->equalTo('foo'))
			->willReturn('Foo');

		$backend->expects($this->any())
			->method('implementsActions')
			->with($this->equalTo(\OC\User\Backend::GET_DISPLAYNAME))
			->willReturn(true);

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertEquals('Foo', $user->getDisplayName());
	}

	/**
	 * if the display name contain whitespaces only, we expect the uid as result
	 */
	public function testDisplayNameEmpty() {
		/**
		 * @var \OC\User\Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\OC\User\Backend::class);
		$backend->expects($this->once())
			->method('getDisplayName')
			->with($this->equalTo('foo'))
			->willReturn('  ');

		$backend->expects($this->any())
			->method('implementsActions')
			->with($this->equalTo(\OC\User\Backend::GET_DISPLAYNAME))
			->willReturn(true);

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertEquals('foo', $user->getDisplayName());
	}

	public function testDisplayNameNotSupported() {
		/**
		 * @var \OC\User\Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\OC\User\Backend::class);
		$backend->expects($this->never())
			->method('getDisplayName');

		$backend->expects($this->any())
			->method('implementsActions')
			->with($this->equalTo(\OC\User\Backend::GET_DISPLAYNAME))
			->willReturn(false);

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertEquals('foo', $user->getDisplayName());
	}

	public function testSetPassword() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('setPassword')
			->with($this->equalTo('foo'), $this->equalTo('bar'));

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::SET_PASSWORD) {
					return true;
				} else {
					return false;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertTrue($user->setPassword('bar',''));
	}

	public function testSetPasswordNotSupported() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->never())
			->method('setPassword');

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(false);

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertFalse($user->setPassword('bar',''));
	}

	public function testChangeAvatarSupportedYes() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(AvatarUserDummy::class);
		$backend->expects($this->once())
			->method('canChangeAvatar')
			->with($this->equalTo('foo'))
			->willReturn(true);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::PROVIDE_AVATAR) {
					return true;
				} else {
					return false;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertTrue($user->canChangeAvatar());
	}

	public function testChangeAvatarSupportedNo() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(AvatarUserDummy::class);
		$backend->expects($this->once())
			->method('canChangeAvatar')
			->with($this->equalTo('foo'))
			->willReturn(false);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::PROVIDE_AVATAR) {
					return true;
				} else {
					return false;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertFalse($user->canChangeAvatar());
	}

	public function testChangeAvatarNotSupported() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(AvatarUserDummy::class);
		$backend->expects($this->never())
			->method('canChangeAvatar');

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(false);

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertTrue($user->canChangeAvatar());
	}

	public function testDelete() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('deleteUser')
			->with($this->equalTo('foo'));

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertTrue($user->delete());
	}

	public function testDeleteWithDifferentHome() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$backend->expects($this->at(0))
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::GET_HOME) {
					return true;
				} else {
					return false;
				}
			});

		// important: getHome MUST be called before deleteUser because
		// once the user is deleted, getHome implementations might not
		// return anything
		$backend->expects($this->at(1))
			->method('getHome')
			->with($this->equalTo('foo'))
			->willReturn('/home/foo');

		$backend->expects($this->at(2))
			->method('deleteUser')
			->with($this->equalTo('foo'));

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertTrue($user->delete());
	}

	public function testGetHome() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('getHome')
			->with($this->equalTo('foo'))
			->willReturn('/home/foo');

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::GET_HOME) {
					return true;
				} else {
					return false;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertEquals('/home/foo', $user->getHome());
	}

	public function testGetBackendClassName() {
		$user = new User('foo', new \Test\Util\User\Dummy(), $this->dispatcher);
		$this->assertEquals('Dummy', $user->getBackendClassName());
		$user = new User('foo', new \OC\User\Database(), $this->dispatcher);
		$this->assertEquals('Database', $user->getBackendClassName());
	}

	public function testGetHomeNotSupported() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->never())
			->method('getHome');

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(false);

		$allConfig = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$allConfig->expects($this->any())
			->method('getUserValue')
			->willReturn(true);
		$allConfig->expects($this->any())
			->method('getSystemValue')
			->with($this->equalTo('datadirectory'))
			->willReturn('arbitrary/path');

		$user = new User('foo', $backend, $this->dispatcher, null, $allConfig);
		$this->assertEquals('arbitrary/path/foo', $user->getHome());
	}

	public function testCanChangePassword() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::SET_PASSWORD) {
					return true;
				} else {
					return false;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertTrue($user->canChangePassword());
	}

	public function testCanChangePasswordNotSupported() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(false);

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertFalse($user->canChangePassword());
	}

	public function testCanChangeDisplayName() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::SET_DISPLAYNAME) {
					return true;
				} else {
					return false;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertTrue($user->canChangeDisplayName());
	}

	public function testCanChangeDisplayNameNotSupported() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(false);

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertFalse($user->canChangeDisplayName());
	}

	public function testSetDisplayNameSupported() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\OC\User\Database::class);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::SET_DISPLAYNAME) {
					return true;
				} else {
					return false;
				}
			});

		$backend->expects($this->once())
			->method('setDisplayName')
			->with('foo','Foo')
			->willReturn(true);

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertTrue($user->setDisplayName('Foo'));
		$this->assertEquals('Foo',$user->getDisplayName());
	}

	/**
	 * don't allow display names containing whitespaces only
	 */
	public function testSetDisplayNameEmpty() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\OC\User\Database::class);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::SET_DISPLAYNAME) {
					return true;
				} else {
					return false;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertFalse($user->setDisplayName(' '));
		$this->assertEquals('foo',$user->getDisplayName());
	}

	public function testSetDisplayNameNotSupported() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\OC\User\Database::class);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(false);

		$backend->expects($this->never())
			->method('setDisplayName');

		$user = new User('foo', $backend, $this->dispatcher);
		$this->assertFalse($user->setDisplayName('Foo'));
		$this->assertEquals('foo',$user->getDisplayName());
	}

	public function testSetPasswordHooks() {
		$hooksCalled = 0;
		$test = $this;

		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('setPassword');

		/**
		 * @param User $user
		 * @param string $password
		 */
		$hook = function ($user, $password) use ($test, &$hooksCalled) {
			$hooksCalled++;
			$test->assertEquals('foo', $user->getUID());
			$test->assertEquals('bar', $password);
		};

		$emitter = new PublicEmitter();
		$emitter->listen('\OC\User', 'preSetPassword', $hook);
		$emitter->listen('\OC\User', 'postSetPassword', $hook);

		$backend->expects($this->any())
			->method('implementsActions')
			->willReturnCallback(function ($actions) {
				if ($actions === \OC\User\Backend::SET_PASSWORD) {
					return true;
				} else {
					return false;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher, $emitter);

		$user->setPassword('bar','');
		$this->assertEquals(2, $hooksCalled);
	}

	public function dataDeleteHooks() {
		return [
			[true, 2],
			[false, 1],
		];
	}

	/**
	 * @dataProvider dataDeleteHooks
	 * @param bool $result
	 * @param int $expectedHooks
	 */
	public function testDeleteHooks($result, $expectedHooks) {
		$hooksCalled = 0;
		$test = $this;

		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$backend->expects($this->once())
			->method('deleteUser')
			->willReturn($result);
		$emitter = new PublicEmitter();
		$user = new User('foo', $backend, $this->dispatcher, $emitter);

		/**
		 * @param User $user
		 */
		$hook = function ($user) use ($test, &$hooksCalled) {
			$hooksCalled++;
			$test->assertEquals('foo', $user->getUID());
		};

		$emitter->listen('\OC\User', 'preDelete', $hook);
		$emitter->listen('\OC\User', 'postDelete', $hook);

		$config = $this->createMock(IConfig::class);
		$commentsManager = $this->createMock(ICommentsManager::class);
		$notificationManager = $this->createMock(INotificationManager::class);

		if ($result) {
			$config->expects($this->once())
				->method('deleteAllUserValues')
				->with('foo');

			$commentsManager->expects($this->once())
				->method('deleteReferencesOfActor')
				->with('users', 'foo');
			$commentsManager->expects($this->once())
				->method('deleteReadMarksFromUser')
				->with($user);

			$notification = $this->createMock(INotification::class);
			$notification->expects($this->once())
				->method('setUser')
				->with('foo');

			$notificationManager->expects($this->once())
				->method('createNotification')
				->willReturn($notification);
			$notificationManager->expects($this->once())
				->method('markProcessed')
				->with($notification);
		} else {
			$config->expects($this->never())
				->method('deleteAllUserValues');

			$commentsManager->expects($this->never())
				->method('deleteReferencesOfActor');
			$commentsManager->expects($this->never())
				->method('deleteReadMarksFromUser');

			$notificationManager->expects($this->never())
				->method('createNotification');
			$notificationManager->expects($this->never())
				->method('markProcessed');
		}

		$this->overwriteService(\OCP\Notification\IManager::class, $notificationManager);
		$this->overwriteService(\OCP\Comments\ICommentsManager::class, $commentsManager);
		$this->overwriteService(AllConfig::class, $config);

		$this->assertSame($result, $user->delete());

		$this->restoreService(AllConfig::class);
		$this->restoreService(\OCP\Comments\ICommentsManager::class);
		$this->restoreService(\OCP\Notification\IManager::class);

		$this->assertEquals($expectedHooks, $hooksCalled);
	}

	public function testGetCloudId() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);
		$urlGenerator = $this->getMockBuilder('\OC\URLGenerator')
				->setMethods(['getAbsoluteURL'])
				->disableOriginalConstructor()->getMock();
		$urlGenerator
				->expects($this->any())
				->method('getAbsoluteURL')
				->withAnyParameters()
				->willReturn('http://localhost:8888/owncloud');
		$user = new User('foo', $backend, $this->dispatcher, null, null, $urlGenerator);
		$this->assertEquals('foo@localhost:8888/owncloud', $user->getCloudId());
	}

	public function testSetEMailAddressEmpty() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$test = $this;
		$hooksCalled = 0;

		/**
		 * @param IUser $user
		 * @param string $feature
		 * @param string $value
		 */
		$hook = function (IUser $user, $feature, $value) use ($test, &$hooksCalled) {
			$hooksCalled++;
			$test->assertEquals('eMailAddress', $feature);
			$test->assertEquals('', $value);
		};

		$emitter = new PublicEmitter();
		$emitter->listen('\OC\User', 'changeUser', $hook);

		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('deleteUserValue')
			->with(
				'foo',
				'settings',
				'email'
			);

		$user = new User('foo', $backend, $this->dispatcher, $emitter, $config);
		$user->setEMailAddress('');
	}

	public function testSetEMailAddress() {
		/**
		 * @var UserInterface | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$test = $this;
		$hooksCalled = 0;

		/**
		 * @param IUser $user
		 * @param string $feature
		 * @param string $value
		 */
		$hook = function (IUser $user, $feature, $value) use ($test, &$hooksCalled) {
			$hooksCalled++;
			$test->assertEquals('eMailAddress', $feature);
			$test->assertEquals('foo@bar.com', $value);
		};

		$emitter = new PublicEmitter();
		$emitter->listen('\OC\User', 'changeUser', $hook);

		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('setUserValue')
			->with(
				'foo',
				'settings',
				'email',
				'foo@bar.com'
			);

		$user = new User('foo', $backend, $this->dispatcher, $emitter, $config);
		$user->setEMailAddress('foo@bar.com');
	}

	public function testSetEMailAddressNoChange() {
		/**
		 * @var UserInterface | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		/** @var PublicEmitter|\PHPUnit_Framework_MockObject_MockObject $emitter */
		$emitter = $this->createMock(PublicEmitter::class);
		$emitter->expects($this->never())
			->method('emit');

		$config = $this->createMock(IConfig::class);
		$config->expects($this->any())
			->method('getUserValue')
			->willReturn('foo@bar.com');
		$config->expects($this->never())
			->method('setUserValue');

		$user = new User('foo', $backend, $this->dispatcher, $emitter, $config);
		$user->setEMailAddress('foo@bar.com');
	}

	public function testSetQuota() {
		/**
		 * @var UserInterface | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$test = $this;
		$hooksCalled = 0;

		/**
		 * @param IUser $user
		 * @param string $feature
		 * @param string $value
		 */
		$hook = function (IUser $user, $feature, $value) use ($test, &$hooksCalled) {
			$hooksCalled++;
			$test->assertEquals('quota', $feature);
			$test->assertEquals('23 TB', $value);
		};

		$emitter = new PublicEmitter();
		$emitter->listen('\OC\User', 'changeUser', $hook);

		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('setUserValue')
			->with(
				'foo',
				'files',
				'quota',
				'23 TB'
			);

		$user = new User('foo', $backend, $this->dispatcher, $emitter, $config);
		$user->setQuota('23 TB');
	}

	public function testSetQuotaAddressNoChange() {
		/**
		 * @var UserInterface | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		/** @var PublicEmitter|\PHPUnit_Framework_MockObject_MockObject $emitter */
		$emitter = $this->createMock(PublicEmitter::class);
		$emitter->expects($this->never())
			->method('emit');

		$config = $this->createMock(IConfig::class);
		$config->expects($this->any())
			->method('getUserValue')
			->willReturn('23 TB');
		$config->expects($this->never())
			->method('setUserValue');

		$user = new User('foo', $backend, $this->dispatcher, $emitter, $config);
		$user->setQuota('23 TB');
	}

	public function testGetLastLogin() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')
			->willReturnCallback(function ($uid, $app, $key, $default) {
				if ($uid === 'foo' && $app === 'login' && $key === 'lastLogin') {
					return 42;
				} else {
					return $default;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher, null, $config);
		$this->assertSame(42, $user->getLastLogin());
	}

	public function testSetEnabled() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('setUserValue')
			->with(
				$this->equalTo('foo'),
				$this->equalTo('core'),
				$this->equalTo('enabled'),
				'true'
			);

		$user = new User('foo', $backend, $this->dispatcher, null, $config);
		$user->setEnabled(true);
	}

	public function testSetDisabled() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('setUserValue')
			->with(
				$this->equalTo('foo'),
				$this->equalTo('core'),
				$this->equalTo('enabled'),
				'false'
			);

		$user = $this->getMockBuilder(User::class)
			->setConstructorArgs([
				'foo',
				$backend,
				$this->dispatcher,
				null,
				$config,
			])
			->setMethods(['isEnabled', 'triggerChange'])
			->getMock();

		$user->expects($this->once())
			->method('isEnabled')
			->willReturn(true);
		$user->expects($this->once())
			->method('triggerChange')
			->with(
				'enabled',
				false
			);

		$user->setEnabled(false);
	}

	public function testSetDisabledAlreadyDisabled() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$config = $this->createMock(IConfig::class);
		$config->expects($this->never())
			->method('setUserValue');

		$user = $this->getMockBuilder(User::class)
			->setConstructorArgs([
				'foo',
				$backend,
				$this->dispatcher,
				null,
				$config,
			])
			->setMethods(['isEnabled', 'triggerChange'])
			->getMock();

		$user->expects($this->once())
			->method('isEnabled')
			->willReturn(false);
		$user->expects($this->never())
			->method('triggerChange');

		$user->setEnabled(false);
	}

	public function testGetEMailAddress() {
		/**
		 * @var Backend | \PHPUnit_Framework_MockObject_MockObject $backend
		 */
		$backend = $this->createMock(\Test\Util\User\Dummy::class);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')
			->willReturnCallback(function ($uid, $app, $key, $default) {
				if ($uid === 'foo' && $app === 'settings' && $key === 'email') {
					return 'foo@bar.com';
				} else {
					return $default;
				}
			});

		$user = new User('foo', $backend, $this->dispatcher, null, $config);
		$this->assertSame('foo@bar.com', $user->getEMailAddress());
	}
}
