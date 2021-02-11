<?php
/**
 * @copyright Copyright (c) 2016, Roger Szabo (roger.szabo@web.de)
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author root <root@localhost.localdomain>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\User_LDAP\Tests;

use OC\User\Manager;
use OCA\User_LDAP\Access;
use OCA\User_LDAP\Connection;
use OCA\User_LDAP\IGroupLDAP;
use OCA\User_LDAP\IUserLDAP;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IServerContainer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class LDAPProviderTest
 *
 * @group DB
 *
 * @package OCA\User_LDAP\Tests
 */
class LDAPProviderTest extends \Test\TestCase {
	protected function setUp(): void {
		parent::setUp();
	}

	private function getServerMock(IUserLDAP $userBackend, IGroupLDAP $groupBackend) {
		$server = $this->getMockBuilder('OC\Server')
			 ->setMethods(['getUserManager', 'getBackends', 'getGroupManager'])
			 ->setConstructorArgs(['', new \OC\Config(\OC::$configDir)])
			 ->getMock();
		$server->expects($this->at(1))
			->method('getBackends')
			->willReturn([$userBackend]);
		$server->expects($this->any())
			->method('getUserManager')
			->willReturn($this->getUserManagerMock($userBackend));
		$server->expects($this->any())
			->method('getGroupManager')
			->willReturn($this->getGroupManagerMock($groupBackend));
		$server->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		return $server;
	}

	private function getUserManagerMock(IUserLDAP $userBackend) {
		$userManager = $this->getMockBuilder(Manager::class)
			->setMethods(['getBackends'])
			->setConstructorArgs([
				$this->createMock(IConfig::class),
				$this->createMock(EventDispatcherInterface::class),
				$this->createMock(IEventDispatcher::class)
			])
			->getMock();
		$userManager->expects($this->any())
			->method('getBackends')
			->willReturn([$userBackend]);
		return $userManager;
	}

	private function getGroupManagerMock(IGroupLDAP $groupBackend) {
		$groupManager = $this->getMockBuilder('OC\Group\Manager')
			->setMethods(['getBackends'])
			->disableOriginalConstructor()
			->getMock();
		$groupManager->expects($this->any())
			->method('getBackends')
			->willReturn([$groupBackend]);
		return $groupManager;
	}

	private function getDefaultGroupBackendMock() {
		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->disableOriginalConstructor()
			->getMock();

		return $groupBackend;
	}

	private function getLDAPProvider(IServerContainer $serverContainer) {
		$factory = new \OCA\User_LDAP\LDAPProviderFactory($serverContainer);
		return $factory->getLDAPProvider();
	}


	public function testGetUserDNUserIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())->method('userExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getUserDN('nonexisting_user');
	}

	public function testGetUserDN() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists', 'getLDAPAccess', 'username2dn'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->at(0))
			->method('userExists')
			->willReturn(true);
		$userBackend->expects($this->at(2))
			->method('username2dn')
			->willReturn('cn=existing_user,ou=Are Sufficient To,ou=Test,dc=example,dc=org');
		$userBackend->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals('cn=existing_user,ou=Are Sufficient To,ou=Test,dc=example,dc=org',
			$ldapProvider->getUserDN('existing_user'));
	}


	public function testGetGroupDNGroupIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Group id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->disableOriginalConstructor()
			->getMock();

		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->setMethods(['groupExists'])
			->disableOriginalConstructor()
			->getMock();

		$groupBackend->expects($this->any())->method('groupExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $groupBackend);

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getGroupDN('nonexisting_group');
	}

	public function testGetGroupDN() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->setMethods(['userExists', 'getLDAPAccess', 'username2dn'])
			->disableOriginalConstructor()
			->getMock();

		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->setMethods(['groupExists', 'getLDAPAccess', 'groupname2dn'])
			->disableOriginalConstructor()
			->getMock();

		$groupBackend->expects($this->at(0))
			->method('groupExists')
			->willReturn(true);
		$groupBackend->expects($this->at(2))
			->method('groupname2dn')
			->willReturn('cn=existing_group,ou=Are Sufficient To,ou=Test,dc=example,dc=org');
		$groupBackend->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$server = $this->getServerMock($userBackend, $groupBackend);

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals('cn=existing_group,ou=Are Sufficient To,ou=Test,dc=example,dc=org',
			$ldapProvider->getGroupDN('existing_group'));
	}

	public function testGetUserName() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['dn2UserName'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())
			->method('dn2UserName')
			->willReturn('existing_user');

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals('existing_user',
			$ldapProvider->getUserName('cn=existing_user,ou=Are Sufficient To,ou=Test,dc=example,dc=org'));
	}

	public function testDNasBaseParameter() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods([])
			 ->disableOriginalConstructor()
			 ->getMock();

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$helper = new \OCA\User_LDAP\Helper(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals(
			$helper->DNasBaseParameter('cn=existing_user,ou=Are Sufficient To,ou=Test,dc=example,dc=org'),
			$ldapProvider->DNasBaseParameter('cn=existing_user,ou=Are Sufficient To,ou=Test,dc=example,dc=org'));
	}

	public function testSanitizeDN() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods([])
			 ->disableOriginalConstructor()
			 ->getMock();

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$helper = new \OCA\User_LDAP\Helper(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals(
			$helper->sanitizeDN('cn=existing_user,ou=Are Sufficient To,ou=Test,dc=example,dc=org'),
			$ldapProvider->sanitizeDN('cn=existing_user,ou=Are Sufficient To,ou=Test,dc=example,dc=org'));
	}


	public function testGetLDAPConnectionUserIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())->method('userExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getLDAPConnection('nonexisting_user');
	}

	public function testGetLDAPConnection() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists', 'getNewLDAPConnection'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())
			->method('userExists')
			->willReturn(true);
		$userBackend->expects($this->any())
			->method('getNewLDAPConnection')
			->willReturn(true);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertTrue($ldapProvider->getLDAPConnection('existing_user'));
	}


	public function testGetGroupLDAPConnectionGroupIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Group id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->disableOriginalConstructor()
			->getMock();

		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->setMethods(['groupExists'])
			->disableOriginalConstructor()
			->getMock();

		$groupBackend->expects($this->any())->method('groupExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $groupBackend);

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getGroupLDAPConnection('nonexisting_group');
	}

	public function testGetGroupLDAPConnection() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->disableOriginalConstructor()
			->getMock();

		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->setMethods(['groupExists','getNewLDAPConnection'])
			->disableOriginalConstructor()
			->getMock();

		$groupBackend->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$groupBackend->expects($this->any())
			->method('getNewLDAPConnection')
			->willReturn(true);

		$server = $this->getServerMock($userBackend, $groupBackend);

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertTrue($ldapProvider->getGroupLDAPConnection('existing_group'));
	}


	public function testGetLDAPBaseUsersUserIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())->method('userExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getLDAPBaseUsers('nonexisting_user');
	}

	public function testGetLDAPBaseUsers() {
		$bases = [
			'ou=users,ou=foobar,dc=example,dc=org',
			'ou=users,ou=barfoo,dc=example,dc=org',
		];
		$dn = 'uid=malik,' . $bases[1];

		$connection = $this->createMock(Connection::class);
		$connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($key) use ($bases) {
				switch ($key) {
					case 'ldapBaseUsers':
						return $bases;
				}
				return null;
			});

		$access = $this->createMock(Access::class);
		$access->expects($this->any())
			->method('getConnection')
			->willReturn($connection);
		$access->expects($this->exactly(2))
			->method('isDNPartOfBase')
			->willReturnOnConsecutiveCalls(false, true);
		$access->expects($this->atLeastOnce())
			->method('username2dn')
			->willReturn($dn);

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists', 'getLDAPAccess', 'getConnection', 'getConfiguration'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->atLeastOnce())
			->method('userExists')
			->willReturn(true);
		$userBackend->expects($this->any())
			->method('getLDAPAccess')
			->willReturn($access);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals($bases[1], $ldapProvider->getLDAPBaseUsers('existing_user'));
	}


	public function testGetLDAPBaseGroupsUserIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())->method('userExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getLDAPBaseGroups('nonexisting_user');
	}

	public function testGetLDAPBaseGroups() {
		$bases = [
			'ou=groupd,ou=foobar,dc=example,dc=org',
			'ou=groups,ou=barfoo,dc=example,dc=org',
		];

		$connection = $this->createMock(Connection::class);
		$connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($key) use ($bases) {
				switch ($key) {
					case 'ldapBaseGroups':
						return $bases;
				}
				return null;
			});

		$access = $this->createMock(Access::class);
		$access->expects($this->any())
			->method('getConnection')
			->willReturn($connection);

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists', 'getLDAPAccess', 'getConnection', 'getConfiguration'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())
			->method('userExists')
			->willReturn(true);
		$userBackend->expects($this->any())
			->method('getLDAPAccess')
			->willReturn($access);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals($bases[0], $ldapProvider->getLDAPBaseGroups('existing_user'));
	}


	public function testClearCacheUserIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())->method('userExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->clearCache('nonexisting_user');
	}

	public function testClearCache() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['userExists', 'getLDAPAccess', 'getConnection', 'clearCache'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->at(0))
			->method('userExists')
			->willReturn(true);
		$userBackend->expects($this->at(3))
			->method('clearCache')
			->willReturn(true);
		$userBackend->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->clearCache('existing_user');
		$this->addToAssertionCount(1);
	}


	public function testClearGroupCacheGroupIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Group id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->disableOriginalConstructor()
			->getMock();
		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->setMethods(['groupExists'])
			->disableOriginalConstructor()
			->getMock();
		$groupBackend->expects($this->any())->method('groupExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $groupBackend);

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->clearGroupCache('nonexisting_group');
	}

	public function testClearGroupCache() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->disableOriginalConstructor()
			->getMock();
		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->setMethods(['groupExists', 'getLDAPAccess', 'getConnection', 'clearCache'])
			->disableOriginalConstructor()
			->getMock();
		$groupBackend->expects($this->at(0))
			->method('groupExists')
			->willReturn(true);
		$groupBackend->expects($this->at(3))
			->method('clearCache')
			->willReturn(true);
		$groupBackend->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$server = $this->getServerMock($userBackend, $groupBackend);

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->clearGroupCache('existing_group');
		$this->addToAssertionCount(1);
	}

	public function testDnExists() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods(['dn2UserName'])
			 ->disableOriginalConstructor()
			 ->getMock();
		$userBackend->expects($this->any())
			->method('dn2UserName')
			->willReturn('existing_user');

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertTrue($ldapProvider->dnExists('cn=existing_user,ou=Are Sufficient To,ou=Test,dc=example,dc=org'));
	}

	public function testFlagRecord() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods([])
			 ->disableOriginalConstructor()
			 ->getMock();

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->flagRecord('existing_user');
		$this->addToAssertionCount(1);
	}

	public function testUnflagRecord() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			 ->setMethods([])
			 ->disableOriginalConstructor()
			 ->getMock();

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->unflagRecord('existing_user');
		$this->addToAssertionCount(1);
	}


	public function testGetLDAPDisplayNameFieldUserIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->setMethods(['userExists'])
			->disableOriginalConstructor()
			->getMock();
		$userBackend->expects($this->any())->method('userExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getLDAPDisplayNameField('nonexisting_user');
	}

	public function testGetLDAPDisplayNameField() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->setMethods(['userExists', 'getLDAPAccess', 'getConnection', 'getConfiguration'])
			->disableOriginalConstructor()
			->getMock();
		$userBackend->expects($this->at(0))
			->method('userExists')
			->willReturn(true);
		$userBackend->expects($this->at(3))
			->method('getConfiguration')
			->willReturn(['ldap_display_name' => 'displayName']);
		$userBackend->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals('displayName', $ldapProvider->getLDAPDisplayNameField('existing_user'));
	}


	public function testGetLDAPEmailFieldUserIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('User id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->setMethods(['userExists'])
			->disableOriginalConstructor()
			->getMock();
		$userBackend->expects($this->any())->method('userExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getLDAPEmailField('nonexisting_user');
	}

	public function testGetLDAPEmailField() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->setMethods(['userExists', 'getLDAPAccess', 'getConnection', 'getConfiguration'])
			->disableOriginalConstructor()
			->getMock();
		$userBackend->expects($this->at(0))
			->method('userExists')
			->willReturn(true);
		$userBackend->expects($this->at(3))
			->method('getConfiguration')
			->willReturn(['ldap_email_attr' => 'mail']);
		$userBackend->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$server = $this->getServerMock($userBackend, $this->getDefaultGroupBackendMock());

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals('mail', $ldapProvider->getLDAPEmailField('existing_user'));
	}


	public function testGetLDAPGroupMemberAssocUserIDNotFound() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Group id not found in LDAP');

		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->disableOriginalConstructor()
			->getMock();

		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->setMethods(['groupExists'])
			->disableOriginalConstructor()
			->getMock();

		$groupBackend->expects($this->any())->method('groupExists')->willReturn(false);

		$server = $this->getServerMock($userBackend, $groupBackend);

		$ldapProvider = $this->getLDAPProvider($server);
		$ldapProvider->getLDAPGroupMemberAssoc('nonexisting_group');
	}

	public function testgetLDAPGroupMemberAssoc() {
		$userBackend = $this->getMockBuilder('OCA\User_LDAP\User_LDAP')
			->disableOriginalConstructor()
			->getMock();

		$groupBackend = $this->getMockBuilder('OCA\User_LDAP\Group_LDAP')
			->setMethods(['groupExists', 'getLDAPAccess', 'getConnection', 'getConfiguration'])
			->disableOriginalConstructor()
			->getMock();

		$groupBackend->expects($this->at(0))
			->method('groupExists')
			->willReturn(true);
		$groupBackend->expects($this->any())
			->method('getConfiguration')
			->willReturn(['ldap_group_member_assoc_attribute' => 'assoc_type']);
		$groupBackend->expects($this->any())
			->method($this->anything())
			->willReturnSelf();

		$server = $this->getServerMock($userBackend, $groupBackend);

		$ldapProvider = $this->getLDAPProvider($server);
		$this->assertEquals('assoc_type', $ldapProvider->getLDAPGroupMemberAssoc('existing_group'));
	}
}
