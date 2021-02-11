<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

namespace OCA\User_LDAP\Tests;

use OC\HintException;
use OC\User\Backend;
use OC\User\Session;
use OCA\User_LDAP\Access;
use OCA\User_LDAP\Connection;
use OCA\User_LDAP\Mapping\AbstractMapping;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\Manager;
use OCA\User_LDAP\User\OfflineUser;
use OCA\User_LDAP\User\User;
use OCA\User_LDAP\User_LDAP;
use OCA\User_LDAP\User_LDAP as UserLDAP;
use OCA\User_LDAP\UserPluginManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use Test\TestCase;

/**
 * Class Test_User_Ldap_Direct
 *
 * @group DB
 *
 * @package OCA\User_LDAP\Tests
 */
class User_LDAPTest extends TestCase {
	/** @var User_LDAP */
	protected $backend;
	/** @var Access|\PHPUnit\Framework\MockObject\MockObject */
	protected $access;
	/** @var  OfflineUser|\PHPUnit\Framework\MockObject\MockObject */
	protected $offlineUser;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var INotificationManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $notificationManager;
	/** @var Session|\PHPUnit\Framework\MockObject\MockObject */
	protected $session;
	/** @var UserPluginManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $pluginManager;
	/** @var Connection|\PHPUnit\Framework\MockObject\MockObject */
	protected $connection;
	/** @var Manager|\PHPUnit\Framework\MockObject\MockObject */
	protected $userManager;

	protected function setUp(): void {
		parent::setUp();

		\OC_User::clearBackends();
		\OC::$server->getGroupManager()->clearBackends();

		$this->connection = $this->createMock(Connection::class);
		$this->userManager = $this->createMock(Manager::class);

		$this->access = $this->createMock(Access::class);
		$this->access->connection = $this->connection;
		$this->access->userManager = $this->userManager;

		$this->config = $this->createMock(IConfig::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		// Cannot use IUserSession because of private listen() methods
		$this->session = $this->createMock(Session::class);
		$this->pluginManager = $this->createMock(UserPluginManager::class);

		$this->backend = new User_LDAP(
			$this->access,
			$this->config,
			$this->notificationManager,
			$this->session,
			$this->pluginManager
		);
	}

	private function prepareMockForUserExists() {
		$this->access->expects($this->any())
			   ->method('username2dn')
			   ->willReturnCallback(function ($uid) {
			   	switch ($uid) {
						case 'gunslinger':
							return 'dnOfRoland,dc=test';
							break;
						case 'formerUser':
							return 'dnOfFormerUser,dc=test';
							break;
						case 'newyorker':
							return 'dnOfNewYorker,dc=test';
							break;
						case 'ladyofshadows':
							return 'dnOfLadyOfShadows,dc=test';
							break;
						default:
							return false;
					}
			   });

		$this->access->method('fetchUsersByLoginName')
			->willReturn([]);
	}

	/**
	 * Prepares the Access mock for checkPassword tests
	 * @param bool $noDisplayName
	 * @return void
	 */
	private function prepareAccessForCheckPassword($noDisplayName = false) {
		$this->connection->expects($this->any())
			   ->method('__get')
			   ->willReturnCallback(function ($name) {
			   	if ($name === 'ldapLoginFilter') {
			   		return '%uid';
			   	}
			   	return null;
			   });

		$this->access->expects($this->any())
			   ->method('fetchListOfUsers')
			   ->willReturnCallback(function ($filter) {
			   	if ($filter === 'roland') {
			   		return [['dn' => ['dnOfRoland,dc=test']]];
			   	}
			   	return [];
			   });
		$this->access->expects($this->any())
			->method('fetchUsersByLoginName')
			->willReturnCallback(function ($uid) {
				if ($uid === 'roland') {
					return [['dn' => ['dnOfRoland,dc=test']]];
				}
				return [];
			});

		$retVal = 'gunslinger';
		if ($noDisplayName === true) {
			$retVal = false;
		}
		$this->access->expects($this->any())
			   ->method('dn2username')
			   ->with($this->equalTo('dnOfRoland,dc=test'))
			   ->willReturn($retVal);
		$this->access->expects($this->any())
			   ->method('stringResemblesDN')
			   ->with($this->equalTo('dnOfRoland,dc=test'))
			   ->willReturn(true);
		$this->access->expects($this->any())
			   ->method('areCredentialsValid')
			   ->willReturnCallback(function ($dn, $pwd) {
			   	if ($pwd === 'dt19') {
			   		return true;
			   	}
			   	return false;
			   });

		$this->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['dn', 'uid', 'mail', 'displayname']);
	}

	public function testCheckPasswordUidReturn() {
		$user = $this->createMock(User::class);
		$user->expects($this->any())
			->method('getUsername')
			->willReturn('gunslinger');

		$this->prepareAccessForCheckPassword();
		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($user);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		\OC_User::useBackend($backend);

		$result = $backend->checkPassword('roland', 'dt19');
		$this->assertEquals('gunslinger', $result);
	}

	public function testCheckPasswordWrongPassword() {
		$this->prepareAccessForCheckPassword();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$result = $backend->checkPassword('roland', 'wrong');
		$this->assertFalse($result);
	}

	public function testCheckPasswordWrongUser() {
		$this->prepareAccessForCheckPassword();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$result = $backend->checkPassword('mallory', 'evil');
		$this->assertFalse($result);
	}

	public function testCheckPasswordNoDisplayName() {
		$this->prepareAccessForCheckPassword(true);

		$this->prepareAccessForCheckPassword();
		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn(null);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$result = $backend->checkPassword('roland', 'dt19');
		$this->assertFalse($result);
	}

	public function testCheckPasswordPublicAPI() {
		$user = $this->createMock(User::class);
		$user->expects($this->any())
			->method('getUsername')
			->willReturn('gunslinger');

		$this->prepareAccessForCheckPassword();
		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($user);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$user = \OC::$server->getUserManager()->checkPassword('roland', 'dt19');
		$result = false;
		if ($user !== false) {
			$result = $user->getUID();
		}
		$this->assertEquals('gunslinger', $result);
	}

	public function testCheckPasswordPublicAPIWrongPassword() {
		$this->prepareAccessForCheckPassword();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$user = \OC::$server->getUserManager()->checkPassword('roland', 'wrong');
		$result = false;
		if ($user !== false) {
			$result = $user->getUID();
		}
		$this->assertFalse($result);
	}

	public function testCheckPasswordPublicAPIWrongUser() {
		$this->prepareAccessForCheckPassword();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$user = \OC::$server->getUserManager()->checkPassword('mallory', 'evil');
		$result = false;
		if ($user !== false) {
			$result = $user->getUID();
		}
		$this->assertFalse($result);
	}

	public function testDeleteUserCancel() {
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$result = $backend->deleteUser('notme');
		$this->assertFalse($result);
	}

	public function testDeleteUserSuccess() {
		$uid = 'jeremy';
		$home = '/var/vhome/jdings/';

		$mapping = $this->createMock(UserMapping::class);
		$mapping->expects($this->once())
			->method('unmap')
			->willReturn(true);
		$this->access->expects($this->once())
			->method('getUserMapper')
			->willReturn($mapping);
		$this->connection->expects($this->any())
			->method('getConnectionResource')
			->willReturn('this is an ldap link');

		$this->config->expects($this->any())
			->method('getUserValue')
			->with($uid, 'user_ldap', 'isDeleted')
			->willReturn('1');

		$offlineUser = $this->createMock(OfflineUser::class);
		$offlineUser->expects($this->once())
			->method('getHomePath')
			->willReturn($home);
		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($offlineUser);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		$result = $backend->deleteUser($uid);
		$this->assertTrue($result);
		/** @noinspection PhpUnhandledExceptionInspection */
		$this->assertSame($backend->getHome($uid), $home);
	}

	public function testDeleteUserWithPlugin() {
		$this->pluginManager->expects($this->once())
			->method('canDeleteUser')
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('deleteUser')
			->with('uid')
			->willReturn(true);

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('uid', 'user_ldap', 'isDeleted', 0)
			->willReturn(1);

		$mapper = $this->createMock(UserMapping::class);
		$mapper->expects($this->once())
			->method('unmap')
			->with('uid');

		$this->access->expects($this->atLeastOnce())
			->method('getUserMapper')
			->willReturn($mapper);

		$this->userManager->expects($this->once())
			->method('invalidate')
			->with('uid');

		$this->assertEquals(true, $this->backend->deleteUser('uid'));
	}

	/**
	 * Prepares the Access mock for getUsers tests
	 */
	private function prepareAccessForGetUsers() {
		$this->access->expects($this->once())
			   ->method('escapeFilterPart')
			   ->willReturnCallback(function ($search) {
			   	return $search;
			   });
		$this->access->expects($this->any())
			   ->method('getFilterPartForUserSearch')
			   ->willReturnCallback(function ($search) {
			   	return $search;
			   });
		$this->access->expects($this->any())
			   ->method('combineFilterWithAnd')
			   ->willReturnCallback(function ($param) {
			   	return $param[2];
			   });
		$this->access->expects($this->any())
			   ->method('fetchListOfUsers')
			   ->willReturnCallback(function ($search, $a, $l, $o) {
			   	$users = ['gunslinger', 'newyorker', 'ladyofshadows'];
			   	if (empty($search)) {
			   		$result = $users;
			   	} else {
			   		$result = [];
			   		foreach ($users as $user) {
			   			if (stripos($user,  $search) !== false) {
			   				$result[] = $user;
			   			}
			   		}
			   	}
			   	if (!is_null($l) || !is_null($o)) {
			   		$result = array_slice($result, $o, $l);
			   	}
			   	return $result;
			   });
		$this->access->expects($this->any())
			   ->method('nextcloudUserNames')
			   ->willReturnArgument(0);
		$this->access->method('fetchUsersByLoginName')
			->willReturn([]);

		$this->access->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['dn', 'uid', 'mail', 'displayname']);
	}

	public function testGetUsersNoParam() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		$result = $backend->getUsers();
		$this->assertEquals(3, count($result));
	}

	public function testGetUsersLimitOffset() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		$result = $backend->getUsers('', 1, 2);
		$this->assertEquals(1, count($result));
	}

	public function testGetUsersLimitOffset2() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		$result = $backend->getUsers('', 2, 1);
		$this->assertEquals(2, count($result));
	}

	public function testGetUsersSearchWithResult() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		$result = $backend->getUsers('yo');
		$this->assertEquals(2, count($result));
	}

	public function testGetUsersSearchEmptyResult() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		$result = $backend->getUsers('nix');
		$this->assertEquals(0, count($result));
	}

	private function getUsers($search = '', $limit = null, $offset = null) {
		$users = \OC::$server->getUserManager()->search($search, $limit, $offset);
		$uids = array_map(function (IUser $user) {
			return $user->getUID();
		}, $users);
		return $uids;
	}

	public function testGetUsersViaAPINoParam() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$result = $this->getUsers();
		$this->assertEquals(3, count($result));
	}

	public function testGetUsersViaAPILimitOffset() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$result = $this->getUsers('', 1, 2);
		$this->assertEquals(1, count($result));
	}

	public function testGetUsersViaAPILimitOffset2() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$result = $this->getUsers('', 2, 1);
		$this->assertEquals(2, count($result));
	}

	public function testGetUsersViaAPISearchWithResult() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$result = $this->getUsers('yo');
		$this->assertEquals(2, count($result));
	}

	public function testGetUsersViaAPISearchEmptyResult() {
		$this->prepareAccessForGetUsers();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$result = $this->getUsers('nix');
		$this->assertEquals(0, count($result));
	}

	public function testUserExists() {
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$user = $this->createMock(User::class);

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($user);
		$this->access->expects($this->any())
			->method('getUserMapper')
			->willReturn($this->createMock(UserMapping::class));

		//test for existing user
		/** @noinspection PhpUnhandledExceptionInspection */
		$result = $backend->userExists('gunslinger');
		$this->assertTrue($result);
	}

	public function testUserExistsForDeleted() {
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$mapper = $this->createMock(UserMapping::class);
		$mapper->expects($this->any())
			->method('getUUIDByDN')
			->with('dnOfFormerUser,dc=test')
			->willReturn('45673458748');

		$this->access->expects($this->any())
			->method('getUserMapper')
			->willReturn($mapper);

		$user = $this->createMock(User::class);

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($user);

		//test for deleted user – always returns true as long as we have the user in DB
		$this->assertTrue($backend->userExists('formerUser'));
	}

	public function testUserExistsForNeverExisting() {
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$this->access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn) {
				if ($dn === 'dnOfRoland,dc=test') {
					return [];
				}
				return false;
			});

		//test for never-existing user
		/** @noinspection PhpUnhandledExceptionInspection */
		$result = $backend->userExists('mallory');
		$this->assertFalse($result);
	}

	public function testUserExistsPublicAPI() {
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();
		\OC_User::useBackend($backend);

		$user = $this->createMock(User::class);
		$user->expects($this->any())
			->method('getDN')
			->willReturn('dnOfRoland,dc=test');

		$this->access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn) {
				if ($dn === 'dnOfRoland,dc=test') {
					return [];
				}
				return false;
			});
		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($user);
		$this->access->expects($this->any())
			->method('getUserMapper')
			->willReturn($this->createMock(UserMapping::class));

		//test for existing user
		$result = \OC::$server->getUserManager()->userExists('gunslinger');
		$this->assertTrue($result);
	}

	public function testDeleteUserExisting() {
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		//we do not support deleting existing users at all
		$result = $backend->deleteUser('gunslinger');
		$this->assertFalse($result);
	}

	public function testGetHomeAbsolutePath() {
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$this->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'homeFolderNamingRule') {
					return 'attr:testAttribute';
				}
				return null;
			});

		$this->access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn, $attr) {
				switch ($dn) {
					case 'dnOfRoland,dc=test':
						if ($attr === 'testAttribute') {
							return ['/tmp/rolandshome/'];
						}
						return [];
						break;
					default:
						return false;
				}
			});

		$user = $this->createMock(User::class);
		$user->expects($this->any())
			->method('getUsername')
			->willReturn('gunslinger');
		$user->expects($this->any())
			->method('getDN')
			->willReturn('dnOfRoland,dc=test');
		$user->expects($this->any())
			->method('getHomePath')
			->willReturn('/tmp/rolandshome/');

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($user);

		//absolute path
		/** @noinspection PhpUnhandledExceptionInspection */
		$result = $backend->getHome('gunslinger');
		$this->assertEquals('/tmp/rolandshome/', $result);
	}

	public function testGetHomeRelative() {
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$dataDir = \OC::$server->getConfig()->getSystemValue(
			'datadirectory', \OC::$SERVERROOT.'/data');

		$this->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'homeFolderNamingRule') {
					return 'attr:testAttribute';
				}
				return null;
			});

		$this->access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn, $attr) {
				switch ($dn) {
					case 'dnOfLadyOfShadows,dc=test':
						if ($attr === 'testAttribute') {
							return ['susannah/'];
						}
						return [];
						break;
					default:
						return false;
				}
			});

		$user = $this->createMock(User::class);
		$user->expects($this->any())
			->method('getUsername')
			->willReturn('ladyofshadows');
		$user->expects($this->any())
			->method('getDN')
			->willReturn('dnOfLadyOfShadows,dc=test');
		$user->expects($this->any())
			->method('getHomePath')
			->willReturn($dataDir.'/susannah/');

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($user);

		/** @noinspection PhpUnhandledExceptionInspection */
		$result = $backend->getHome('ladyofshadows');
		$this->assertEquals($dataDir.'/susannah/', $result);
	}


	public function testGetHomeNoPath() {
		$this->expectException(\Exception::class);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$this->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'homeFolderNamingRule') {
					return 'attr:testAttribute';
				}
				return null;
			});
		$this->access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn, $attr) {
				switch ($dn) {
					default:
						return false;
				}
			});
		$this->access->connection->expects($this->any())
			->method('getFromCache')
			->willReturnCallback(function ($key) {
				if ($key === 'userExistsnewyorker') {
					return true;
				}
				return null;
			});

		$user = $this->createMock(User::class);
		$user->expects($this->any())
			->method('getUsername')
			->willReturn('newyorker');
		$user->expects($this->any())
			->method('getHomePath')
			->willThrowException(new \Exception());

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($user);

		//no path at all – triggers OC default behaviour
		$result = $backend->getHome('newyorker');
		$this->assertFalse($result);
	}

	public function testGetHomeDeletedUser() {
		$uid = 'newyorker';

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$this->connection->expects($this->any())
				->method('__get')
				->willReturnCallback(function ($name) {
					if ($name === 'homeFolderNamingRule') {
						return 'attr:testAttribute';
					}
					return null;
				});

		$this->access->expects($this->any())
				->method('readAttribute')
				->willReturn([]);

		$userMapper = $this->createMock(UserMapping::class);

		$this->access->expects($this->any())
				->method('getUserMapper')
				->willReturn($userMapper);

		$this->config->expects($this->any())
			->method('getUserValue')
			->willReturn(true);

		$offlineUser = $this->createMock(OfflineUser::class);
		$offlineUser->expects($this->atLeastOnce())
			->method('getHomePath')
			->willReturn('');

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($offlineUser);

		$result = $backend->getHome($uid);
		$this->assertFalse($result);
	}

	public function testGetHomeWithPlugin() {
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::GET_HOME)
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('getHome')
			->with('uid')
			->willReturn('result');

		$this->connection->expects($this->any())
			->method('getFromCache')
			->willReturnCallback(function ($uid) {
				return true;
			});

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->assertEquals($this->backend->getHome('uid'),'result');
	}

	private function prepareAccessForGetDisplayName() {
		$this->connection->expects($this->any())
			   ->method('__get')
			   ->willReturnCallback(function ($name) {
			   	if ($name === 'ldapUserDisplayName') {
			   		return 'displayname';
			   	}
			   	return null;
			   });

		$this->access->expects($this->any())
			   ->method('readAttribute')
			   ->willReturnCallback(function ($dn, $attr) {
			   	switch ($dn) {
						case 'dnOfRoland,dc=test':
							if ($attr === 'displayname') {
								return ['Roland Deschain'];
							}
							return [];
							break;

						default:
							return false;
				   }
			   });
		$this->access->method('fetchUsersByLoginName')
			->willReturn([]);
	}

	public function testGetDisplayName() {
		$this->prepareAccessForGetDisplayName();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$this->connection->expects($this->any())
			->method('getConnectionResource')
			->willReturnCallback(function () {
				return true;
			});

		$user1 = $this->createMock(User::class);
		$user1->expects($this->once())
			->method('composeAndStoreDisplayName')
			->willReturn('Roland Deschain');
		$user1->expects($this->any())
			->method('getDN')
			->willReturn('dnOfRoland,dc=test');

		$user2 = $this->createMock(User::class);
		$user2->expects($this->never())
			->method('composeAndStoreDisplayName');
		$user2->expects($this->any())
			->method('getDN')
			->willReturn('another DN');

		$mapper = $this->createMock(UserMapping::class);
		$mapper->expects($this->any())
			->method('getUUIDByDN')
			->willReturnCallback(function ($dn) {
				return $dn;
			});

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function ($uid) use ($user1, $user2) {
				if ($uid === 'gunslinger') {
					return $user1;
				} elseif ($uid === 'newyorker') {
					return $user2;
				}
				return null;
			});
		$this->access->expects($this->any())
			->method('getUserMapper')
			->willReturn($mapper);
		$this->access->expects($this->any())
			->method('getUserDnByUuid')
			->willReturnCallback(function ($uuid) {
				return $uuid . '1';
			});

		//with displayName
		$result = $backend->getDisplayName('gunslinger');
		$this->assertEquals('Roland Deschain', $result);

		//empty displayname retrieved
		$result = $backend->getDisplayName('newyorker');
		$this->assertEquals(null, $result);
	}

	public function testGetDisplayNamePublicAPI() {
		$this->access->expects($this->any())
			->method('username2dn')
			->willReturnCallback(function ($uid) {
				switch ($uid) {
					case 'gunslinger':
						return 'dnOfRoland,dc=test';
						break;
					case 'formerUser':
						return 'dnOfFormerUser,dc=test';
						break;
					case 'newyorker':
						return 'dnOfNewYorker,dc=test';
						break;
					case 'ladyofshadows':
						return 'dnOfLadyOfShadows,dc=test';
						break;
					default:
						return false;
				}
			});
		$this->prepareAccessForGetDisplayName();
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->prepareMockForUserExists();

		$this->connection->expects($this->any())
			->method('getConnectionResource')
			->willReturnCallback(function () {
				return true;
			});

		\OC_User::useBackend($backend);

		$user1 = $this->createMock(User::class);
		$user1->expects($this->once())
			->method('composeAndStoreDisplayName')
			->willReturn('Roland Deschain');
		$user1->expects($this->any())
			->method('getDN')
			->willReturn('dnOfRoland,dc=test');

		$user2 = $this->createMock(User::class);
		$user2->expects($this->never())
			->method('composeAndStoreDisplayName');
		$user2->expects($this->any())
			->method('getDN')
			->willReturn('another DN');

		$mapper = $this->createMock(UserMapping::class);
		$mapper->expects($this->any())
			->method('getUUIDByDN')
			->willReturnCallback(function ($dn) {
				return $dn;
			});

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function ($uid) use ($user1, $user2) {
				if ($uid === 'gunslinger') {
					return $user1;
				} elseif ($uid === 'newyorker') {
					return $user2;
				}
				return null;
			});
		$this->access->expects($this->any())
			->method('getUserMapper')
			->willReturn($mapper);
		$this->access->expects($this->any())
			->method('getUserDnByUuid')
			->willReturnCallback(function ($uuid) {
				return $uuid . '1';
			});

		//with displayName
		$result = \OC::$server->getUserManager()->get('gunslinger')->getDisplayName();
		$this->assertEquals('Roland Deschain', $result);

		//empty displayname retrieved
		$result = \OC::$server->getUserManager()->get('newyorker') === null ? 'newyorker' : \OC::$server->getUserManager()->get('newyorker')->getDisplayName();
		$this->assertEquals('newyorker', $result);
	}

	public function testGetDisplayNameWithPlugin() {
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::GET_DISPLAYNAME)
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('getDisplayName')
			->with('uid')
			->willReturn('result');

		$this->assertEquals($this->backend->getDisplayName('uid'),'result');
	}

	//no test for getDisplayNames, because it just invokes getUsers and
	//getDisplayName

	public function testCountUsers() {
		$this->access->expects($this->once())
			   ->method('countUsers')
			   ->willReturn(5);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		$result = $backend->countUsers();
		$this->assertEquals(5, $result);
	}

	public function testCountUsersFailing() {
		$this->access->expects($this->once())
			   ->method('countUsers')
			   ->willReturn(false);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);

		$result = $backend->countUsers();
		$this->assertFalse($result);
	}

	public function testCountUsersWithPlugin() {
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::COUNT_USERS)
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('countUsers')
			->willReturn(42);

		$this->assertEquals($this->backend->countUsers(),42);
	}

	public function testLoginName2UserNameSuccess() {
		$loginName = 'Alice';
		$username = 'alice';
		$dn = 'uid=alice,dc=what,dc=ever';

		$this->access->expects($this->once())
			->method('fetchUsersByLoginName')
			->with($this->equalTo($loginName))
			->willReturn([['dn' => [$dn]]]);
		$this->access->expects($this->any())
			->method('stringResemblesDN')
			->with($this->equalTo($dn))
			->willReturn(true);
		$this->access->expects($this->any())
			->method('dn2username')
			->with($this->equalTo($dn))
			->willReturn($username);

		$this->connection->expects($this->exactly(2))
			->method('getFromCache')
			->with($this->equalTo('loginName2UserName-'.$loginName))
			->willReturnOnConsecutiveCalls(null, $username);
		$this->connection->expects($this->once())
			->method('writeToCache')
			->with($this->equalTo('loginName2UserName-'.$loginName), $this->equalTo($username));

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$user = $this->createMock(User::class);
		$user->expects($this->any())
			->method('getUsername')
			->willReturn('alice');

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->with($dn)
			->willReturn($user);
		$this->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['dn', 'uid', 'mail', 'displayname']);

		$name = $backend->loginName2UserName($loginName);
		$this->assertSame($username, $name);

		// and once again to verify that caching works
		$backend->loginName2UserName($loginName);
	}

	public function testLoginName2UserNameNoUsersOnLDAP() {
		$loginName = 'Loki';

		$this->access->expects($this->once())
			->method('fetchUsersByLoginName')
			->with($this->equalTo($loginName))
			->willReturn([]);
		$this->access->expects($this->never())
			->method('stringResemblesDN');
		$this->access->expects($this->never())
			->method('dn2username');

		$this->connection->expects($this->exactly(2))
			->method('getFromCache')
			->with($this->equalTo('loginName2UserName-'.$loginName))
			->willReturnOnConsecutiveCalls(null, false);
		$this->connection->expects($this->once())
			->method('writeToCache')
			->with($this->equalTo('loginName2UserName-'.$loginName), false);

		$this->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['dn', 'uid', 'mail', 'displayname']);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$name = $backend->loginName2UserName($loginName);
		$this->assertSame(false, $name);

		// and once again to verify that caching works
		$backend->loginName2UserName($loginName);
	}

	public function testLoginName2UserNameOfflineUser() {
		$loginName = 'Alice';
		$dn = 'uid=alice,dc=what,dc=ever';

		$offlineUser = $this->getMockBuilder(OfflineUser::class)
			->disableOriginalConstructor()
			->getMock();

		$this->access->expects($this->once())
			->method('fetchUsersByLoginName')
			->with($this->equalTo($loginName))
			->willReturn([['dn' => [$dn]]]);

		$this->connection->expects($this->exactly(2))
			->method('getFromCache')
			->with($this->equalTo('loginName2UserName-'.$loginName))
			->willReturnOnConsecutiveCalls(null, false);
		$this->connection->expects($this->once())
			->method('writeToCache')
			->with($this->equalTo('loginName2UserName-'.$loginName), $this->equalTo(false));

		$this->userManager->expects($this->any())
			->method('get')
			->with($dn)
			->willReturn($offlineUser);
		$this->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['dn', 'uid', 'mail', 'displayname']);

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$name = $backend->loginName2UserName($loginName);
		$this->assertSame(false, $name);

		// and once again to verify that caching works
		$backend->loginName2UserName($loginName);
	}

	/**
	 * Prepares the Access mock for setPassword tests
	 *
	 * @param bool $enablePasswordChange
	 */
	private function prepareAccessForSetPassword($enablePasswordChange = true) {
		$this->connection->expects($this->any())
			   ->method('__get')
			   ->willReturnCallback(function ($name) use (&$enablePasswordChange) {
			   	if ($name === 'ldapLoginFilter') {
			   		return '%uid';
			   	}
			   	if ($name === 'turnOnPasswordChange') {
			   		return $enablePasswordChange?1:0;
			   	}
			   	return null;
			   });
		$this->connection->expects($this->any())
			   ->method('getFromCache')
			   ->willReturnCallback(function ($uid) {
			   	if ($uid === 'userExists'.'roland') {
			   		return true;
			   	}
			   	return null;
			   });

		$this->access->expects($this->any())
			   ->method('fetchListOfUsers')
			   ->willReturnCallback(function ($filter) {
			   	if ($filter === 'roland') {
			   		return [['dn' => ['dnOfRoland,dc=test']]];
			   	}
			   	return [];
			   });
		$this->access->expects($this->any())
			->method('fetchUsersByLoginName')
			->willReturnCallback(function ($uid) {
				if ($uid === 'roland') {
					return [['dn' => ['dnOfRoland,dc=test']]];
				}
				return [];
			});
		$this->access->expects($this->any())
			   ->method('dn2username')
			   ->with($this->equalTo('dnOfRoland,dc=test'))
			   ->willReturn('roland');
		$this->access->expects($this->any())
			   ->method('stringResemblesDN')
			   ->with($this->equalTo('dnOfRoland,dc=test'))
			   ->willReturn(true);
		$this->access->expects($this->any())
			   ->method('setPassword')
			   ->willReturnCallback(function ($uid, $password) {
			   	if (strlen($password) <= 5) {
			   		throw new HintException('Password fails quality checking policy', '', 19);
			   	}
			   	return true;
			   });
	}


	public function testSetPasswordInvalid() {
		$this->expectException(\OC\HintException::class);
		$this->expectExceptionMessage('Password fails quality checking policy');

		$this->prepareAccessForSetPassword($this->access);
		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($this->createMock(User::class));
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$this->assertTrue(\OC_User::setPassword('roland', 'dt'));
	}

	public function testSetPasswordValid() {
		$this->prepareAccessForSetPassword($this->access);

		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($this->createMock(User::class));

		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($this->createMock(User::class));

		\OC_User::useBackend($backend);

		$this->assertTrue(\OC_User::setPassword('roland', 'dt12234$'));
	}

	public function testSetPasswordValidDisabled() {
		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($this->createMock(User::class));

		$this->prepareAccessForSetPassword(false);
		$backend = new UserLDAP($this->access, $this->config, $this->notificationManager, $this->session, $this->pluginManager);
		\OC_User::useBackend($backend);

		$this->assertFalse(\OC_User::setPassword('roland', 'dt12234$'));
	}


	public function testSetPasswordWithInvalidUser() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('LDAP setPassword: Could not get user object for uid NotExistingUser. Maybe the LDAP entry has no set display name attribute?');

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('NotExistingUser')
			->willReturn(null);

		$this->backend->setPassword('NotExistingUser', 'Password');
	}

	public function testSetPasswordWithUsernameFalse() {
		$user = $this->createMock(User::class);
		$user
			->expects($this->once())
			->method('getUsername')
			->willReturn(false);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('NotExistingUser')
			->willReturn($user);

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->assertFalse($this->backend->setPassword('NotExistingUser', 'Password'));
	}

	public function testSetPasswordWithPlugin() {
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::SET_PASSWORD)
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('setPassword')
			->with('uid','password')
			->willReturn('result');

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->assertEquals($this->backend->setPassword('uid', 'password'),'result');
	}

	public function avatarDataProvider() {
		return [
			[ 'validImageData', false ],
			[ 'corruptImageData', true ],
			[ false, true]
		];
	}

	/** @dataProvider avatarDataProvider */
	public function testCanChangeAvatar($imageData, $expected) {
		$isValidImage = strpos((string)$imageData, 'valid') === 0;

		$user = $this->createMock(User::class);
		$user->expects($this->once())
			->method('getAvatarImage')
			->willReturn($imageData);
		$user->expects($this->atMost(1))
			->method('updateAvatar')
			->willReturn($isValidImage);

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->willReturn($user);

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->assertSame($expected, $this->backend->canChangeAvatar('uid'));
	}

	public function testCanChangeAvatarWithPlugin() {
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::PROVIDE_AVATAR)
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('canChangeAvatar')
			->with('uid')
			->willReturn('result');

		$this->assertEquals($this->backend->canChangeAvatar('uid'),'result');
	}

	public function testSetDisplayNameWithPlugin() {
		$newDisplayName = 'J. Baker';
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::SET_DISPLAYNAME)
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('setDisplayName')
			->with('uid', $newDisplayName)
			->willReturn($newDisplayName);
		$this->access->expects($this->once())
			->method('cacheUserDisplayName');

		$this->assertEquals($newDisplayName, $this->backend->setDisplayName('uid', $newDisplayName));
	}


	public function testSetDisplayNameErrorWithPlugin() {
		$this->expectException(\OC\HintException::class);

		$newDisplayName = 'J. Baker';
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::SET_DISPLAYNAME)
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('setDisplayName')
			->with('uid', $newDisplayName)
			->willThrowException(new HintException('something happned'));
		$this->access->expects($this->never())
			->method('cacheUserDisplayName');

		$this->backend->setDisplayName('uid', $newDisplayName);
	}

	public function testSetDisplayNameFailing() {
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::SET_DISPLAYNAME)
			->willReturn(false);
		$this->access->expects($this->never())
			->method('cacheUserDisplayName');

		$this->assertFalse($this->backend->setDisplayName('uid', 'displayName'));
	}

	public function testCreateUserWithPlugin() {
		$uid = 'alien6372';
		$uuid = '123-2345-36756-123-2345234-4431';
		$pwd = 'passwørd';

		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::CREATE_USER)
			->willReturn(true);
		$this->pluginManager->expects($this->once())
			->method('createUser')
			->with($uid, $pwd)
			->willReturn('result');

		$this->access->expects($this->atLeastOnce())
			->method('getUUID')
			->willReturn($uuid);
		$this->access->expects($this->once())
			->method('mapAndAnnounceIfApplicable')
			->with($this->isInstanceOf(AbstractMapping::class), $this->anything(), $uid, $uuid, true);
		$this->access->expects($this->any())
			->method('getUserMapper')
			->willReturn($this->createMock(UserMapping::class));

		$this->assertEquals($this->backend->createUser($uid, $pwd),true);
	}

	public function testCreateUserFailing() {
		$this->pluginManager->expects($this->once())
			->method('implementsActions')
			->with(Backend::CREATE_USER)
			->willReturn(false);

		$this->assertFalse($this->backend->createUser('uid', 'password'));
	}

	public function actionProvider() {
		return [
			[ 'ldapUserAvatarRule', 'default', Backend::PROVIDE_AVATAR, true]	,
			[ 'ldapUserAvatarRule', 'data:selfiePhoto', Backend::PROVIDE_AVATAR, true],
			[ 'ldapUserAvatarRule', 'none', Backend::PROVIDE_AVATAR, false],
			[ 'turnOnPasswordChange', 0, Backend::SET_PASSWORD, false],
			[ 'turnOnPasswordChange', 1, Backend::SET_PASSWORD, true],
		];
	}

	/**
	 * @dataProvider actionProvider
	 */
	public function testImplementsAction($configurable, $value, $actionCode, $expected) {
		$this->pluginManager->expects($this->once())
			->method('getImplementedActions')
			->willReturn(0);

		$this->connection->expects($this->any())
			->method('__get')
			->willReturnMap([
				[$configurable, $value],
			]);

		$this->assertSame($expected, $this->backend->implementsActions($actionCode));
	}
}
