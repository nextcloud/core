<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 * @author Vincent Petry <vincent@nextcloud.com>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
 * @author Xuanwo <xuanwo@yunify.com>
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

use OCA\User_LDAP\Access;
use OCA\User_LDAP\Connection;
use OCA\User_LDAP\Group_LDAP as GroupLDAP;
use OCA\User_LDAP\GroupPluginManager;
use OCA\User_LDAP\ILDAPWrapper;
use OCA\User_LDAP\Mapping\GroupMapping;
use OCA\User_LDAP\User\Manager;
use OCP\GroupInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * Class GroupLDAPTest
 *
 * @group DB
 *
 * @package OCA\User_LDAP\Tests
 */
class Group_LDAPTest extends TestCase {
	public function testCountEmptySearchString() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();
		$groupDN = 'cn=group,dc=foo,dc=bar';

		$this->enableGroups($access);

		$access->expects($this->any())
			->method('groupname2dn')
			->willReturn($groupDN);
		$access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn) use ($groupDN) {
				if ($dn === $groupDN) {
					return [
						'uid=u11,ou=users,dc=foo,dc=bar',
						'uid=u22,ou=users,dc=foo,dc=bar',
						'uid=u33,ou=users,dc=foo,dc=bar',
						'uid=u34,ou=users,dc=foo,dc=bar'
					];
				}
				return [];
			});
		$access->expects($this->any())
			->method('isDNPartOfBase')
			->willReturn(true);
		// for primary groups
		$access->expects($this->once())
			->method('countUsers')
			->willReturn(2);

		$access->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['displayName', 'mail']);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$users = $groupBackend->countUsersInGroup('group');

		$this->assertSame(6, $users);
	}

	/**
	 * @return MockObject|Access
	 */
	private function getAccessMock() {
		static $conMethods;
		static $accMethods;

		if (is_null($conMethods) || is_null($accMethods)) {
			$conMethods = get_class_methods(Connection::class);
			$accMethods = get_class_methods(Access::class);
		}
		$lw = $this->createMock(ILDAPWrapper::class);
		$connector = $this->getMockBuilder(Connection::class)
			->setMethods($conMethods)
			->setConstructorArgs([$lw, null, null])
			->getMock();

		$access = $this->createMock(Access::class);

		$access->expects($this->any())
			->method('getConnection')
			->willReturn($connector);

		$access->userManager = $this->createMock(Manager::class);

		return $access;
	}

	/**
	 * @return MockObject|GroupPluginManager
	 */
	private function getPluginManagerMock() {
		return $this->createMock(GroupPluginManager::class);
	}

	private function enableGroups(Access $access) {
		$access->connection = $this->createMock(Connection::class);

		$access->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapDynamicGroupMemberURL') {
					return '';
				}
				return 1;
			});
	}

	public function testCountWithSearchString() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$access->expects($this->any())
			->method('groupname2dn')
			->willReturn('cn=group,dc=foo,dc=bar');
		$access->expects($this->any())
			->method('fetchListOfUsers')
			->willReturn([]);
		$access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($name) {
				//the search operation will call readAttribute, thus we need
				//to anaylze the "dn". All other times we just need to return
				//something that is neither null or false, but once an array
				//with the users in the group – so we do so all other times for
				//simplicicity.
				if (strpos($name, 'u') === 0) {
					return strpos($name, '3');
				}
				return ['u11', 'u22', 'u33', 'u34'];
			});
		$access->expects($this->any())
			->method('dn2username')
			->willReturnCallback(function () {
				return 'foobar' . \OC::$server->getSecureRandom()->generate(7);
			});
		$access->expects($this->any())
			->method('isDNPartOfBase')
			->willReturn(true);
		$access->expects($this->any())
			->method('escapeFilterPart')
			->willReturnArgument(0);

		$access->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['displayName', 'mail']);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$users = $groupBackend->countUsersInGroup('group', '3');

		$this->assertSame(2, $users);
	}

	public function testCountUsersWithPlugin() {
		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'countUsersInGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::COUNT_USERS)
			->willReturn(true);

		$pluginManager->expects($this->once())
			->method('countUsersInGroup')
			->with('gid', 'search')
			->willReturn(42);

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$this->assertEquals($ldap->countUsersInGroup('gid', 'search'), 42);
	}

	public function testGidNumber2NameSuccess() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('searchGroups')
			->willReturn([['dn' => ['cn=foo,dc=barfoo,dc=bar']]]);

		$access->expects($this->once())
			->method('dn2groupname')
			->with('cn=foo,dc=barfoo,dc=bar')
			->willReturn('MyGroup');

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$group = $groupBackend->gidNumber2Name('3117', $userDN);

		$this->assertSame('MyGroup', $group);
	}

	public function testGidNumberID2NameNoGroup() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('searchGroups')
			->willReturn([]);

		$access->expects($this->never())
			->method('dn2groupname');

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$group = $groupBackend->gidNumber2Name('3117', $userDN);

		$this->assertSame(false, $group);
	}

	public function testGidNumberID2NameNoName() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('searchGroups')
			->willReturn([['dn' => ['cn=foo,dc=barfoo,dc=bar']]]);

		$access->expects($this->once())
			->method('dn2groupname')
			->willReturn(false);

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$group = $groupBackend->gidNumber2Name('3117', $userDN);

		$this->assertSame(false, $group);
	}

	public function testGetEntryGidNumberValue() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$dn = 'cn=foobar,cn=foo,dc=barfoo,dc=bar';
		$attr = 'gidNumber';

		$access->expects($this->once())
			->method('readAttribute')
			->with($dn, $attr)
			->willReturn(['3117']);

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$gid = $groupBackend->getGroupGidNumber($dn);

		$this->assertSame('3117', $gid);
	}

	public function testGetEntryGidNumberNoValue() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$dn = 'cn=foobar,cn=foo,dc=barfoo,dc=bar';
		$attr = 'gidNumber';

		$access->expects($this->once())
			->method('readAttribute')
			->with($dn, $attr)
			->willReturn(false);

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$gid = $groupBackend->getGroupGidNumber($dn);

		$this->assertSame(false, $gid);
	}

	public function testPrimaryGroupID2NameSuccess() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('getSID')
			->with($userDN)
			->willReturn('S-1-5-21-249921958-728525901-1594176202');

		$access->expects($this->once())
			->method('searchGroups')
			->willReturn([['dn' => ['cn=foo,dc=barfoo,dc=bar']]]);

		$access->expects($this->once())
			->method('dn2groupname')
			->with('cn=foo,dc=barfoo,dc=bar')
			->willReturn('MyGroup');

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$group = $groupBackend->primaryGroupID2Name('3117', $userDN);

		$this->assertSame('MyGroup', $group);
	}

	public function testPrimaryGroupID2NameNoSID() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('getSID')
			->with($userDN)
			->willReturn(false);

		$access->expects($this->never())
			->method('searchGroups');

		$access->expects($this->never())
			->method('dn2groupname');

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$group = $groupBackend->primaryGroupID2Name('3117', $userDN);

		$this->assertSame(false, $group);
	}

	public function testPrimaryGroupID2NameNoGroup() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('getSID')
			->with($userDN)
			->willReturn('S-1-5-21-249921958-728525901-1594176202');

		$access->expects($this->once())
			->method('searchGroups')
			->willReturn([]);

		$access->expects($this->never())
			->method('dn2groupname');

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$group = $groupBackend->primaryGroupID2Name('3117', $userDN);

		$this->assertSame(false, $group);
	}

	public function testPrimaryGroupID2NameNoName() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('getSID')
			->with($userDN)
			->willReturn('S-1-5-21-249921958-728525901-1594176202');

		$access->expects($this->once())
			->method('searchGroups')
			->willReturn([['dn' => ['cn=foo,dc=barfoo,dc=bar']]]);

		$access->expects($this->once())
			->method('dn2groupname')
			->willReturn(false);

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$group = $groupBackend->primaryGroupID2Name('3117', $userDN);

		$this->assertSame(false, $group);
	}

	public function testGetEntryGroupIDValue() {
		//tests getEntryGroupID via getGroupPrimaryGroupID
		//which is basically identical to getUserPrimaryGroupIDs
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$dn = 'cn=foobar,cn=foo,dc=barfoo,dc=bar';
		$attr = 'primaryGroupToken';

		$access->expects($this->once())
			->method('readAttribute')
			->with($dn, $attr)
			->willReturn(['3117']);

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$gid = $groupBackend->getGroupPrimaryGroupID($dn);

		$this->assertSame('3117', $gid);
	}

	public function testGetEntryGroupIDNoValue() {
		//tests getEntryGroupID via getGroupPrimaryGroupID
		//which is basically identical to getUserPrimaryGroupIDs
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$dn = 'cn=foobar,cn=foo,dc=barfoo,dc=bar';
		$attr = 'primaryGroupToken';

		$access->expects($this->once())
			->method('readAttribute')
			->with($dn, $attr)
			->willReturn(false);

		$groupBackend = new GroupLDAP($access, $pluginManager);

		$gid = $groupBackend->getGroupPrimaryGroupID($dn);

		$this->assertSame(false, $gid);
	}

	/**
	 * tests whether Group Backend behaves correctly when cache with uid and gid
	 * is hit
	 */
	public function testInGroupHitsUidGidCache() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$uid = 'someUser';
		$gid = 'someGroup';
		$cacheKey = 'inGroup' . $uid . ':' . $gid;

		$access->connection->expects($this->once())
			->method('getFromCache')
			->with($cacheKey)
			->willReturn(true);

		$access->expects($this->never())
			->method('username2dn');

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$groupBackend->inGroup($uid, $gid);
	}

	public function groupWithMembersProvider() {
		return [
			[
				'someGroup',
				'cn=someGroup,ou=allTheGroups,ou=someDepartment,dc=someDomain,dc=someTld',
				[
					'uid=oneUser,ou=someTeam,ou=someDepartment,dc=someDomain,dc=someTld',
					'uid=someUser,ou=someTeam,ou=someDepartment,dc=someDomain,dc=someTld',
					'uid=anotherUser,ou=someTeam,ou=someDepartment,dc=someDomain,dc=someTld',
					'uid=differentUser,ou=someTeam,ou=someDepartment,dc=someDomain,dc=someTld',
				],
			],
		];
	}

	/**
	 * @dataProvider groupWithMembersProvider
	 */
	public function testInGroupMember(string $gid, string $groupDn, array $memberDNs) {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$access->connection = $this->createMock(Connection::class);

		$uid = 'someUser';
		$userDn = $memberDNs[0];

		$access->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				switch ($name) {
					case 'ldapGroupMemberAssocAttr':
						return 'member';
					case 'ldapDynamicGroupMemberURL':
						return '';
					case 'hasPrimaryGroups':
					case 'ldapNestedGroups':
						return 0;
					default:
						return 1;
				}
			});
		$access->connection->expects($this->any())
			->method('getFromCache')
			->willReturn(null);

		$access->expects($this->once())
			->method('username2dn')
			->with($uid)
			->willReturn($userDn);
		$access->expects($this->once())
			->method('groupname2dn')
			->willReturn($groupDn);
		$access->expects($this->any())
			->method('readAttribute')
			->willReturn($memberDNs);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$this->assertTrue($groupBackend->inGroup($uid, $gid));
	}

	/**
	 * @dataProvider groupWithMembersProvider
	 */
	public function testInGroupMemberNot(string $gid, string $groupDn, array $memberDNs) {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$access->connection = $this->createMock(Connection::class);

		$uid = 'unelatedUser';
		$userDn = 'uid=unrelatedUser,ou=unrelatedTeam,ou=unrelatedDepartment,dc=someDomain,dc=someTld';

		$access->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				switch ($name) {
					case 'ldapGroupMemberAssocAttr':
						return 'member';
					case 'ldapDynamicGroupMemberURL':
						return '';
					case 'hasPrimaryGroups':
					case 'ldapNestedGroups':
						return 0;
					default:
						return 1;
				}
			});
		$access->connection->expects($this->any())
			->method('getFromCache')
			->willReturn(null);

		$access->expects($this->once())
			->method('username2dn')
			->with($uid)
			->willReturn($userDn);
		$access->expects($this->once())
			->method('groupname2dn')
			->willReturn($groupDn);
		$access->expects($this->any())
			->method('readAttribute')
			->willReturn($memberDNs);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$this->assertFalse($groupBackend->inGroup($uid, $gid));
	}

	/**
	 * @dataProvider groupWithMembersProvider
	 */
	public function testInGroupMemberUid(string $gid, string $groupDn, array $memberDNs) {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$memberUids = [];
		$userRecords = [];
		foreach ($memberDNs as $dn) {
			$memberUids[] = ldap_explode_dn($dn, false)[0];
			$userRecords[] = ['dn' => [$dn]];
		}


		$access->connection = $this->createMock(Connection::class);

		$uid = 'someUser';
		$userDn = $memberDNs[0];

		$access->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				switch ($name) {
					case 'ldapGroupMemberAssocAttr':
						return 'memberUid';
					case 'ldapDynamicGroupMemberURL':
						return '';
					case 'ldapLoginFilter':
						return 'uid=%uid';
					case 'hasPrimaryGroups':
					case 'ldapNestedGroups':
						return 0;
					default:
						return 1;
				}
			});
		$access->connection->expects($this->any())
			->method('getFromCache')
			->willReturn(null);

		$access->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['uid', 'mail', 'displayname']);

		$access->expects($this->once())
			->method('username2dn')
			->with($uid)
			->willReturn($userDn);
		$access->expects($this->once())
			->method('groupname2dn')
			->willReturn($groupDn);
		$access->expects($this->any())
			->method('readAttribute')
			->willReturn($memberUids);
		$access->expects($this->any())
			->method('fetchListOfUsers')
			->willReturn($userRecords);
		$access->expects($this->any())
			->method('combineFilterWithOr')
			->willReturn('(|(pseudo=filter)(filter=pseudo))');

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$this->assertTrue($groupBackend->inGroup($uid, $gid));
	}

	public function testGetGroupsWithOffset() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$access->expects($this->once())
			->method('nextcloudGroupNames')
			->willReturn(['group1', 'group2']);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$groups = $groupBackend->getGroups('', 2, 2);

		$this->assertSame(2, count($groups));
	}

	/**
	 * tests that a user listing is complete, if all it's members have the group
	 * as their primary.
	 */
	public function testUsersInGroupPrimaryMembersOnly() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$access->connection->expects($this->any())
			->method('getFromCache')
			->willReturn(null);
		$access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn, $attr) {
				if ($attr === 'primaryGroupToken') {
					return [1337];
				} elseif ($attr === 'gidNumber') {
					return [4211];
				}
				return [];
			});
		$access->expects($this->any())
			->method('groupname2dn')
			->willReturn('cn=foobar,dc=foo,dc=bar');
		$access->expects($this->exactly(2))
			->method('nextcloudUserNames')
			->willReturnOnConsecutiveCalls(['lisa', 'bart', 'kira', 'brad'], ['walle', 'dino', 'xenia']);
		$access->expects($this->any())
			->method('isDNPartOfBase')
			->willReturn(true);
		$access->expects($this->any())
			->method('combineFilterWithAnd')
			->willReturn('pseudo=filter');

		$access->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['displayName', 'mail']);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$users = $groupBackend->usersInGroup('foobar');

		$this->assertSame(7, count($users));
	}

	/**
	 * tests that a user listing is complete, if all it's members have the group
	 * as their primary.
	 */
	public function testUsersInGroupPrimaryAndUnixMembers() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$access->connection->expects($this->any())
			->method('getFromCache')
			->willReturn(null);
		$access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn, $attr) {
				if ($attr === 'primaryGroupToken') {
					return [1337];
				}
				return [];
			});
		$access->expects($this->any())
			->method('groupname2dn')
			->willReturn('cn=foobar,dc=foo,dc=bar');
		$access->expects($this->once())
			->method('nextcloudUserNames')
			->willReturn(['lisa', 'bart', 'kira', 'brad']);
		$access->expects($this->any())
			->method('isDNPartOfBase')
			->willReturn(true);
		$access->expects($this->any())
			->method('combineFilterWithAnd')
			->willReturn('pseudo=filter');

		$access->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['displayName', 'mail']);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$users = $groupBackend->usersInGroup('foobar');

		$this->assertSame(4, count($users));
	}

	/**
	 * tests that a user counting is complete, if all it's members have the group
	 * as their primary.
	 */
	public function testCountUsersInGroupPrimaryMembersOnly() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$access->connection->expects($this->any())
			->method('getFromCache')
			->willReturn(null);

		$access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($dn, $attr) {
				if ($attr === 'primaryGroupToken') {
					return [1337];
				}
				return [];
			});
		$access->expects($this->any())
			->method('groupname2dn')
			->willReturn('cn=foobar,dc=foo,dc=bar');
		$access->expects($this->once())
			->method('countUsers')
			->willReturn(4);
		$access->expects($this->any())
			->method('isDNPartOfBase')
			->willReturn(true);

		$access->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['displayName', 'mail']);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$users = $groupBackend->countUsersInGroup('foobar');

		$this->assertSame(4, $users);
	}

	public function testGetUserGroupsMemberOf() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$this->enableGroups($access);

		$dn = 'cn=userX,dc=foobar';

		$access->connection->hasPrimaryGroups = false;
		$access->connection->hasGidNumber = false;

		$access->expects($this->any())
			->method('username2dn')
			->willReturn($dn);
		$access->expects($this->exactly(5))
			->method('readAttribute')
			->will($this->onConsecutiveCalls(['cn=groupA,dc=foobar', 'cn=groupB,dc=foobar'], [], [], [], []));
		$access->expects($this->any())
			->method('dn2groupname')
			->willReturnArgument(0);
		$access->expects($this->any())
			->method('groupname2dn')
			->willReturnArgument(0);
		$access->expects($this->any())
			->method('isDNPartOfBase')
			->willReturn(true);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$groups = $groupBackend->getUserGroups('userX');

		$this->assertSame(2, count($groups));
	}

	public function testGetUserGroupsMemberOfDisabled() {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$access->connection = $this->createMock(Connection::class);
		$access->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'useMemberOfToDetectMembership') {
					return 0;
				} elseif ($name === 'ldapDynamicGroupMemberURL') {
					return '';
				}
				return 1;
			});

		$dn = 'cn=userX,dc=foobar';

		$access->connection->hasPrimaryGroups = false;
		$access->connection->hasGidNumber = false;

		$access->expects($this->once())
			->method('username2dn')
			->willReturn($dn);
		$access->expects($this->never())
			->method('readAttribute')
			->with($dn, 'memberOf');
		$access->expects($this->once())
			->method('nextcloudGroupNames')
			->willReturn([]);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$groupBackend->getUserGroups('userX');
	}

	public function nestedGroupsProvider(): array {
		return [
			[true],
			[false],
		];
	}

	/**
	 * @dataProvider nestedGroupsProvider
	 */
	public function testGetGroupsByMember(bool $nestedGroups) {
		$access = $this->getAccessMock();
		$pluginManager = $this->getPluginManagerMock();

		$groupFilter = '(&(objectclass=nextcloudGroup)(nextcloudEnabled=TRUE))';
		$access->connection = $this->createMock(Connection::class);
		$access->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) use ($nestedGroups, $groupFilter) {
				switch ($name) {
					case 'useMemberOfToDetectMembership':
						return 0;
					case 'ldapDynamicGroupMemberURL':
						return '';
					case 'ldapNestedGroups':
						return $nestedGroups;
					case 'ldapGroupMemberAssocAttr':
						return 'member';
					case 'ldapGroupFilter':
						return $groupFilter;
				}
				return 1;
			});

		$dn = 'cn=userX,dc=foobar';

		$access->connection->hasPrimaryGroups = false;
		$access->connection->hasGidNumber = false;

		$access->expects($this->exactly(2))
			->method('username2dn')
			->willReturn($dn);
		$access->expects($this->any())
			->method('readAttribute')
			->willReturn([]);
		$access->expects($this->any())
			->method('combineFilterWithAnd')
			->willReturnCallback(function (array $filterParts) {
				// ⚠ returns a pseudo-filter only, not real LDAP Filter syntax
				return implode('&', $filterParts);
			});

		$group1 = [
			'cn' => 'group1',
			'dn' => ['cn=group1,ou=groups,dc=domain,dc=com'],
		];
		$group2 = [
			'cn' => 'group2',
			'dn' => ['cn=group2,ou=groups,dc=domain,dc=com'],
		];

		$access->expects($this->once())
			->method('nextcloudGroupNames')
			->with([$group1, $group2])
			->willReturn(['group1', 'group2']);
		$access->expects($nestedGroups ? $this->atLeastOnce() : $this->once())
			->method('fetchListOfGroups')
			->willReturnCallback(function ($filter, $attr, $limit, $offset) use ($nestedGroups, $groupFilter, $group1, $group2) {
				static $firstRun = true;
				if (!$nestedGroups) {
					// When nested groups are enabled, groups cannot be filtered early as it would
					// exclude intermediate groups. But we can, and should, when working with flat groups.
					$this->assertTrue(strpos($filter, $groupFilter) !== false);
				}
				if ($firstRun) {
					$firstRun = false;
					return [$group1, $group2];
				}
				return [];
			});
		$access->expects($this->any())
			->method('dn2groupname')
			->willReturnCallback(function (string $dn) {
				return ldap_explode_dn($dn, 1)[0];
			});
		$access->expects($this->any())
			->method('groupname2dn')
			->willReturnCallback(function (string $gid) use ($group1, $group2) {
				if ($gid === $group1['cn']) {
					return $group1['dn'][0];
				}
				if ($gid === $group2['cn']) {
					return $group2['dn'][0];
				}
			});
		$access->expects($this->any())
			->method('isDNPartOfBase')
			->willReturn(true);

		$groupBackend = new GroupLDAP($access, $pluginManager);
		$groups = $groupBackend->getUserGroups('userX');
		$this->assertEquals(['group1', 'group2'], $groups);

		$groupsAgain = $groupBackend->getUserGroups('userX');
		$this->assertEquals(['group1', 'group2'], $groupsAgain);
	}

	public function testCreateGroupWithPlugin() {
		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'createGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::CREATE_GROUP)
			->willReturn(true);

		$pluginManager->expects($this->once())
			->method('createGroup')
			->with('gid')
			->willReturn('result');

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$this->assertEquals($ldap->createGroup('gid'), true);
	}


	public function testCreateGroupFailing() {
		$this->expectException(\Exception::class);

		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'createGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::CREATE_GROUP)
			->willReturn(false);

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$ldap->createGroup('gid');
	}

	public function testDeleteGroupWithPlugin() {
		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'deleteGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::DELETE_GROUP)
			->willReturn(true);

		$pluginManager->expects($this->once())
			->method('deleteGroup')
			->with('gid')
			->willReturn('result');

		$mapper = $this->getMockBuilder(GroupMapping::class)
			->setMethods(['unmap'])
			->disableOriginalConstructor()
			->getMock();

		$access = $this->getAccessMock();
		$access->expects($this->any())
			->method('getGroupMapper')
			->willReturn($mapper);

		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$this->assertEquals($ldap->deleteGroup('gid'), 'result');
	}


	public function testDeleteGroupFailing() {
		$this->expectException(\Exception::class);

		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'deleteGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::DELETE_GROUP)
			->willReturn(false);

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$ldap->deleteGroup('gid');
	}

	public function testAddToGroupWithPlugin() {
		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'addToGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::ADD_TO_GROUP)
			->willReturn(true);

		$pluginManager->expects($this->once())
			->method('addToGroup')
			->with('uid', 'gid')
			->willReturn('result');

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$this->assertEquals($ldap->addToGroup('uid', 'gid'), 'result');
	}


	public function testAddToGroupFailing() {
		$this->expectException(\Exception::class);

		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'addToGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::ADD_TO_GROUP)
			->willReturn(false);

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$ldap->addToGroup('uid', 'gid');
	}

	public function testRemoveFromGroupWithPlugin() {
		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'removeFromGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::REMOVE_FROM_GROUP)
			->willReturn(true);

		$pluginManager->expects($this->once())
			->method('removeFromGroup')
			->with('uid', 'gid')
			->willReturn('result');

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$this->assertEquals($ldap->removeFromGroup('uid', 'gid'), 'result');
	}


	public function testRemoveFromGroupFailing() {
		$this->expectException(\Exception::class);

		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'removeFromGroup'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::REMOVE_FROM_GROUP)
			->willReturn(false);

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$ldap->removeFromGroup('uid', 'gid');
	}

	public function testGetGroupDetailsWithPlugin() {
		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'getGroupDetails'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::GROUP_DETAILS)
			->willReturn(true);

		$pluginManager->expects($this->once())
			->method('getGroupDetails')
			->with('gid')
			->willReturn('result');

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$this->assertEquals($ldap->getGroupDetails('gid'), 'result');
	}


	public function testGetGroupDetailsFailing() {
		$this->expectException(\Exception::class);

		/** @var GroupPluginManager|MockObject $pluginManager */
		$pluginManager = $this->getMockBuilder(GroupPluginManager::class)
			->setMethods(['implementsActions', 'getGroupDetails'])
			->getMock();

		$pluginManager->expects($this->once())
			->method('implementsActions')
			->with(GroupInterface::GROUP_DETAILS)
			->willReturn(false);

		$access = $this->getAccessMock();
		$access->connection = $this->createMock(Connection::class);

		$ldap = new GroupLDAP($access, $pluginManager);

		$ldap->getGroupDetails('gid');
	}

	public function groupMemberProvider() {
		$base = 'dc=species,dc=earth';

		$groups0 = [
			'uid=3723,' . $base,
			'uid=8372,' . $base,
			'uid=8427,' . $base,
			'uid=2333,' . $base,
			'uid=4754,' . $base,
		];
		$groups1 = [
			'3723',
			'8372',
			'8427',
			'2333',
			'4754',
		];
		$groups2Nested = ['6642', '1424'];
		$expGroups2 = array_merge($groups1, $groups2Nested);

		return [
			[ #0 – test DNs
				'cn=Birds,' . $base,
				$groups0,
				['cn=Birds,' . $base => $groups0]
			],
			[ #1 – test uids
				'cn=Birds,' . $base,
				$groups1,
				['cn=Birds,' . $base => $groups1]
			],
		];
	}

	/**
	 * @param string $groupDN
	 * @param string[] $expectedMembers
	 * @param array $groupsInfo
	 * @dataProvider groupMemberProvider
	 */
	public function testGroupMembers($groupDN, $expectedMembers, $groupsInfo = null) {
		$access = $this->getAccessMock();
		$access->expects($this->any())
			->method('readAttribute')
			->willReturnCallback(function ($group) use ($groupDN, $expectedMembers, $groupsInfo) {
				if (isset($groupsInfo[$group])) {
					return $groupsInfo[$group];
				}
				return [];
			});

		$access->connection = $this->createMock(Connection::class);
		if (count($groupsInfo) > 1) {
			$access->connection->expects($this->any())
				->method('__get')
				->willReturnCallback(function ($name) {
					if ($name === 'ldapNestedGroups') {
						return 1;
					}
					return null;
				});
		}

		/** @var GroupPluginManager $pluginManager */
		$pluginManager = $this->createMock(GroupPluginManager::class);

		$ldap = new GroupLDAP($access, $pluginManager);
		$resultingMembers = $this->invokePrivate($ldap, '_groupMembers', [$groupDN]);

		$this->assertEqualsCanonicalizing($expectedMembers, $resultingMembers);
	}

	public function displayNameProvider() {
		return [
			['Graphic Novelists', ['Graphic Novelists']],
			['', false],
		];
	}

	/**
	 * @dataProvider displayNameProvider
	 */
	public function testGetDisplayName(string $expected, $ldapResult) {
		$gid = 'graphic_novelists';

		$access = $this->getAccessMock();
		$access->expects($this->atLeastOnce())
			->method('readAttribute')
			->willReturn($ldapResult);

		$access->connection = $this->createMock(Connection::class);
		$access->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($name) {
				if ($name === 'ldapGroupMemberAssocAttr') {
					return 'member';
				} elseif ($name === 'ldapGroupFilter') {
					return 'objectclass=nextcloudGroup';
				} elseif ($name === 'ldapGroupDisplayName') {
					return 'cn';
				}
				return null;
			});

		/** @var GroupPluginManager $pluginManager */
		$pluginManager = $this->createMock(GroupPluginManager::class);

		$ldap = new GroupLDAP($access, $pluginManager);
		$this->assertSame($expected, $ldap->getDisplayName($gid));
	}
}
