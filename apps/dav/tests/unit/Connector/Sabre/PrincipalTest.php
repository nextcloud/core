<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2018, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OC\User\User;
use OCA\DAV\CalDAV\Proxy\Proxy;
use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use Sabre\DAV\PropPatch;
use Test\TestCase;

class PrincipalTest extends TestCase {

	/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var \OCA\DAV\Connector\Sabre\Principal */
	private $connector;

	/** @var IGroupManager | \PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;

	/** @var IManager | \PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	/** @var IUserSession | \PHPUnit_Framework_MockObject_MockObject */
	private $userSession;

	/** @var IAppManager | \PHPUnit_Framework_MockObject_MockObject  */
	private $appManager;

	/** @var ProxyMapper | \PHPUnit_Framework_MockObject_MockObject */
	private $proxyMapper;

	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;

	protected function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->proxyMapper = $this->createMock(ProxyMapper::class);
		$this->config = $this->createMock(IConfig::class);

		$this->connector = new \OCA\DAV\Connector\Sabre\Principal(
			$this->userManager,
			$this->groupManager,
			$this->shareManager,
			$this->userSession,
			$this->appManager,
			$this->proxyMapper,
			$this->config
		);
		parent::setUp();
	}

	public function testGetPrincipalsByPrefixWithoutPrefix() {
		$response = $this->connector->getPrincipalsByPrefix('');
		$this->assertSame([], $response);
	}

	public function testGetPrincipalsByPrefixWithUsers() {
		$fooUser = $this->createMock(User::class);
		$fooUser
				->expects($this->exactly(1))
				->method('getUID')
				->willReturn('foo');
		$fooUser
				->expects($this->exactly(1))
				->method('getDisplayName')
				->willReturn('Dr. Foo-Bar');
		$fooUser
				->expects($this->exactly(1))
				->method('getEMailAddress')
				->willReturn('');
		$barUser = $this->createMock(User::class);
		$barUser
			->expects($this->exactly(1))
			->method('getUID')
			->willReturn('bar');
		$barUser
				->expects($this->exactly(1))
				->method('getEMailAddress')
				->willReturn('bar@nextcloud.com');
		$this->userManager
			->expects($this->once())
			->method('search')
			->with('')
			->willReturn([$fooUser, $barUser]);

		$expectedResponse = [
			0 => [
				'uri' => 'principals/users/foo',
				'{DAV:}displayname' => 'Dr. Foo-Bar',
				'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'INDIVIDUAL',
			],
			1 => [
				'uri' => 'principals/users/bar',
				'{DAV:}displayname' => 'bar',
				'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'INDIVIDUAL',
				'{http://sabredav.org/ns}email-address' => 'bar@nextcloud.com',
			]
		];
		$response = $this->connector->getPrincipalsByPrefix('principals/users');
		$this->assertSame($expectedResponse, $response);
	}

	public function testGetPrincipalsByPrefixEmpty() {
		$this->userManager
			->expects($this->once())
			->method('search')
			->with('')
			->willReturn([]);

		$response = $this->connector->getPrincipalsByPrefix('principals/users');
		$this->assertSame([], $response);
	}

	public function testGetPrincipalsByPathWithoutMail() {
		$fooUser = $this->createMock(User::class);
		$fooUser
			->expects($this->exactly(1))
			->method('getUID')
			->willReturn('foo');
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($fooUser);

		$expectedResponse = [
			'uri' => 'principals/users/foo',
			'{DAV:}displayname' => 'foo',
			'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'INDIVIDUAL',
		];
		$response = $this->connector->getPrincipalByPath('principals/users/foo');
		$this->assertSame($expectedResponse, $response);
	}

	public function testGetPrincipalsByPathWithMail() {
		$fooUser = $this->createMock(User::class);
		$fooUser
				->expects($this->exactly(1))
				->method('getEMailAddress')
				->willReturn('foo@nextcloud.com');
		$fooUser
				->expects($this->exactly(1))
				->method('getUID')
				->willReturn('foo');
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($fooUser);

		$expectedResponse = [
			'uri' => 'principals/users/foo',
			'{DAV:}displayname' => 'foo',
			'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'INDIVIDUAL',
			'{http://sabredav.org/ns}email-address' => 'foo@nextcloud.com',
		];
		$response = $this->connector->getPrincipalByPath('principals/users/foo');
		$this->assertSame($expectedResponse, $response);
	}

	public function testGetPrincipalsByPathEmpty() {
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn(null);

		$response = $this->connector->getPrincipalByPath('principals/users/foo');
		$this->assertSame(null, $response);
	}

	public function testGetGroupMemberSet() {
		$response = $this->connector->getGroupMemberSet('principals/users/foo');
		$this->assertSame([], $response);
	}


	public function testGetGroupMemberSetEmpty() {
		$this->expectException(\Sabre\DAV\Exception::class);
		$this->expectExceptionMessage('Principal not found');

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn(null);

		$this->connector->getGroupMemberSet('principals/users/foo/calendar-proxy-read');
	}

	public function testGetGroupMemberSetProxyRead() {
		$fooUser = $this->createMock(User::class);
		$fooUser
			->expects($this->exactly(1))
			->method('getUID')
			->willReturn('foo');
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($fooUser);

		$proxy1 = new Proxy();
		$proxy1->setProxyId('proxyId1');
		$proxy1->setPermissions(1);

		$proxy2 = new Proxy();
		$proxy2->setProxyId('proxyId2');
		$proxy2->setPermissions(3);

		$proxy3 = new Proxy();
		$proxy3->setProxyId('proxyId3');
		$proxy3->setPermissions(3);

		$this->proxyMapper->expects($this->once())
			->method('getProxiesOf')
			->with('principals/users/foo')
			->willReturn([$proxy1, $proxy2, $proxy3]);

		$this->assertEquals(['proxyId1'], $this->connector->getGroupMemberSet('principals/users/foo/calendar-proxy-read'));
	}

	public function testGetGroupMemberSetProxyWrite() {
		$fooUser = $this->createMock(User::class);
		$fooUser
			->expects($this->exactly(1))
			->method('getUID')
			->willReturn('foo');
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($fooUser);

		$proxy1 = new Proxy();
		$proxy1->setProxyId('proxyId1');
		$proxy1->setPermissions(1);

		$proxy2 = new Proxy();
		$proxy2->setProxyId('proxyId2');
		$proxy2->setPermissions(3);

		$proxy3 = new Proxy();
		$proxy3->setProxyId('proxyId3');
		$proxy3->setPermissions(3);

		$this->proxyMapper->expects($this->once())
			->method('getProxiesOf')
			->with('principals/users/foo')
			->willReturn([$proxy1, $proxy2, $proxy3]);

		$this->assertEquals(['proxyId2', 'proxyId3'], $this->connector->getGroupMemberSet('principals/users/foo/calendar-proxy-write'));
	}

	public function testGetGroupMembership() {
		$fooUser = $this->createMock(User::class);
		$group1 = $this->createMock(IGroup::class);
		$group1->expects($this->once())
			->method('getGID')
			->willReturn('group1');
		$group2 = $this->createMock(IGroup::class);
		$group2->expects($this->once())
			->method('getGID')
			->willReturn('foo/bar');
		$this->userManager
			->expects($this->exactly(2))
			->method('get')
			->with('foo')
			->willReturn($fooUser);
		$this->groupManager
			->expects($this->once())
			->method('getUserGroups')
			->with($fooUser)
			->willReturn([
				$group1,
				$group2,
			]);

		$proxy1 = new Proxy();
		$proxy1->setOwnerId('proxyId1');
		$proxy1->setPermissions(1);

		$proxy2 = new Proxy();
		$proxy2->setOwnerId('proxyId2');
		$proxy2->setPermissions(3);

		$this->proxyMapper->expects($this->once())
			->method('getProxiesFor')
			->with('principals/users/foo')
			->willReturn([$proxy1, $proxy2]);

		$expectedResponse = [
			'principals/groups/group1',
			'principals/groups/foo%2Fbar',
			'proxyId1/calendar-proxy-read',
			'proxyId2/calendar-proxy-write',
		];
		$response = $this->connector->getGroupMembership('principals/users/foo');
		$this->assertSame($expectedResponse, $response);
	}


	public function testGetGroupMembershipEmpty() {
		$this->expectException(\Sabre\DAV\Exception::class);
		$this->expectExceptionMessage('Principal not found');

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn(null);

		$this->connector->getGroupMembership('principals/users/foo');
	}


	public function testSetGroupMembership() {
		$this->expectException(\Sabre\DAV\Exception::class);
		$this->expectExceptionMessage('Setting members of the group is not supported yet');

		$this->connector->setGroupMemberSet('principals/users/foo', ['foo']);
	}

	public function testSetGroupMembershipProxy() {
		$fooUser = $this->createMock(User::class);
		$fooUser
			->expects($this->exactly(1))
			->method('getUID')
			->willReturn('foo');
		$barUser = $this->createMock(User::class);
		$barUser
			->expects($this->exactly(1))
			->method('getUID')
			->willReturn('bar');
		$this->userManager
			->expects($this->at(0))
			->method('get')
			->with('foo')
			->willReturn($fooUser);
		$this->userManager
			->expects($this->at(1))
			->method('get')
			->with('bar')
			->willReturn($barUser);

		$this->proxyMapper->expects($this->at(0))
			->method('getProxiesOf')
			->with('principals/users/foo')
			->willReturn([]);

		$this->proxyMapper->expects($this->at(1))
			->method('insert')
			->with($this->callback(function ($proxy) {
				/** @var Proxy $proxy */
				if ($proxy->getOwnerId() !== 'principals/users/foo') {
					return false;
				}
				if ($proxy->getProxyId() !== 'principals/users/bar') {
					return false;
				}
				if ($proxy->getPermissions() !== 3) {
					return false;
				}

				return true;
			}));

		$this->connector->setGroupMemberSet('principals/users/foo/calendar-proxy-write', ['principals/users/bar']);
	}

	public function testUpdatePrincipal() {
		$this->assertSame(0, $this->connector->updatePrincipal('foo', new PropPatch([])));
	}

	public function testSearchPrincipalsWithEmptySearchProperties() {
		$this->assertSame([], $this->connector->searchPrincipals('principals/users', []));
	}

	public function testSearchPrincipalsWithWrongPrefixPath() {
		$this->assertSame([], $this->connector->searchPrincipals('principals/groups',
			['{http://sabredav.org/ns}email-address' => 'foo']));
	}

	/**
	 * @dataProvider searchPrincipalsDataProvider
	 */
	public function testSearchPrincipals($sharingEnabled, $groupsOnly, $test, $result) {
		$this->shareManager->expects($this->once())
			->method('shareAPIEnabled')
			->willReturn($sharingEnabled);

		if ($sharingEnabled) {
			$this->shareManager->expects($this->once())
				->method('allowEnumeration')
				->willReturn(true);

			$this->shareManager->expects($this->once())
				->method('shareWithGroupMembersOnly')
				->willReturn($groupsOnly);

			if ($groupsOnly) {
				$user = $this->createMock(IUser::class);
				$this->userSession->expects($this->once())
					->method('getUser')
					->willReturn($user);

				$this->groupManager->expects($this->at(0))
					->method('getUserGroupIds')
					->with($user)
					->willReturn(['group1', 'group2', 'group5']);
			}
		} else {
			$this->config->expects($this->never())
				->method('getAppValue');
			$this->shareManager->expects($this->never())
				->method('shareWithGroupMembersOnly');
			$this->groupManager->expects($this->never())
				->method($this->anything());
		}

		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')->willReturn('user3');
		$user4 = $this->createMock(IUser::class);
		$user4->method('getUID')->willReturn('user4');

		if ($sharingEnabled) {
			$this->userManager->expects($this->at(0))
				->method('getByEmail')
				->with('user@example.com')
				->willReturn([$user2, $user3]);

			$this->userManager->expects($this->at(1))
				->method('searchDisplayName')
				->with('User 12')
				->willReturn([$user3, $user4]);
		} else {
			$this->userManager->expects($this->never())
				->method('getByEmail');

			$this->userManager->expects($this->never())
				->method('searchDisplayName');
		}

		if ($sharingEnabled && $groupsOnly) {
			$this->groupManager->expects($this->at(1))
				->method('getUserGroupIds')
				->with($user2)
				->willReturn(['group1', 'group3']);
			$this->groupManager->expects($this->at(2))
				->method('getUserGroupIds')
				->with($user3)
				->willReturn(['group3', 'group4']);
			$this->groupManager->expects($this->at(3))
				->method('getUserGroupIds')
				->with($user3)
				->willReturn(['group3', 'group4']);
			$this->groupManager->expects($this->at(4))
				->method('getUserGroupIds')
				->with($user4)
				->willReturn(['group4', 'group5']);
		}


		$this->assertEquals($result, $this->connector->searchPrincipals('principals/users',
			['{http://sabredav.org/ns}email-address' => 'user@example.com',
				'{DAV:}displayname' => 'User 12'], $test));
	}

	public function searchPrincipalsDataProvider() {
		return [
			[true, false, 'allof', ['principals/users/user3']],
			[true, false, 'anyof', ['principals/users/user2', 'principals/users/user3', 'principals/users/user4']],
			[true, true, 'allof', []],
			[true, true, 'anyof', ['principals/users/user2', 'principals/users/user4']],
			[false, false, 'allof', []],
			[false, false, 'anyof', []],
		];
	}

	public function testSearchPrincipalByCalendarUserAddressSet() {
		$this->shareManager->expects($this->exactly(2))
			->method('shareAPIEnabled')
			->willReturn(true);

		$this->shareManager->expects($this->exactly(2))
			->method('allowEnumeration')
			->willReturn(true);

		$this->shareManager->expects($this->exactly(2))
			->method('shareWithGroupMembersOnly')
			->willReturn(false);

		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')->willReturn('user3');

		$this->userManager->expects($this->at(0))
			->method('getByEmail')
			->with('user@example.com')
			->willReturn([$user2, $user3]);

		$this->assertEquals([
			'principals/users/user2',
			'principals/users/user3',
		], $this->connector->searchPrincipals('principals/users',
			['{urn:ietf:params:xml:ns:caldav}calendar-user-address-set' => 'user@example.com']));
	}

	public function testSearchPrincipalWithEnumerationDisabledDisplayname() {
		$this->shareManager->expects($this->once())
			->method('shareAPIEnabled')
			->willReturn(true);

		$this->shareManager->expects($this->once())
			->method('allowEnumeration')
			->willReturn(false);

		$this->shareManager->expects($this->once())
			->method('shareWithGroupMembersOnly')
			->willReturn(false);

		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('getDisplayName')->willReturn('User 2');
		$user2->method('getEMailAddress')->willReturn('user2@foo.bar');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')->willReturn('user3');
		$user2->method('getDisplayName')->willReturn('User 22');
		$user2->method('getEMailAddress')->willReturn('user2@foo.bar123');
		$user4 = $this->createMock(IUser::class);
		$user4->method('getUID')->willReturn('user4');
		$user2->method('getDisplayName')->willReturn('User 222');
		$user2->method('getEMailAddress')->willReturn('user2@foo.bar456');

		$this->userManager->expects($this->at(0))
			->method('searchDisplayName')
			->with('User 2')
			->willReturn([$user2, $user3, $user4]);

		$this->assertEquals(['principals/users/user2'], $this->connector->searchPrincipals('principals/users',
			['{DAV:}displayname' => 'User 2']));
	}

	public function testSearchPrincipalWithEnumerationDisabledEmail() {
		$this->shareManager->expects($this->once())
			->method('shareAPIEnabled')
			->willReturn(true);

		$this->shareManager->expects($this->once())
			->method('allowEnumeration')
			->willReturn(false);

		$this->shareManager->expects($this->once())
			->method('shareWithGroupMembersOnly')
			->willReturn(false);

		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('getDisplayName')->willReturn('User 2');
		$user2->method('getEMailAddress')->willReturn('user2@foo.bar');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')->willReturn('user3');
		$user2->method('getDisplayName')->willReturn('User 22');
		$user2->method('getEMailAddress')->willReturn('user2@foo.bar123');
		$user4 = $this->createMock(IUser::class);
		$user4->method('getUID')->willReturn('user4');
		$user2->method('getDisplayName')->willReturn('User 222');
		$user2->method('getEMailAddress')->willReturn('user2@foo.bar456');

		$this->userManager->expects($this->at(0))
			->method('getByEmail')
			->with('user2@foo.bar')
			->willReturn([$user2, $user3, $user4]);

		$this->assertEquals(['principals/users/user2'], $this->connector->searchPrincipals('principals/users',
			['{http://sabredav.org/ns}email-address' => 'user2@foo.bar']));
	}

	public function testSearchPrincipalWithEnumerationLimitedDisplayname() {
		$this->shareManager->expects($this->at(0))
			->method('shareAPIEnabled')
			->willReturn(true);

		$this->shareManager->expects($this->at(1))
			->method('allowEnumeration')
			->willReturn(true);

		$this->shareManager->expects($this->at(2))
			->method('limitEnumerationToGroups')
			->willReturn(true);

		$this->shareManager->expects($this->once())
			->method('shareWithGroupMembersOnly')
			->willReturn(false);

		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('getDisplayName')->willReturn('User 2');
		$user2->method('getEMailAddress')->willReturn('user2@foo.bar');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')->willReturn('user3');
		$user3->method('getDisplayName')->willReturn('User 22');
		$user3->method('getEMailAddress')->willReturn('user2@foo.bar123');
		$user4 = $this->createMock(IUser::class);
		$user4->method('getUID')->willReturn('user4');
		$user4->method('getDisplayName')->willReturn('User 222');
		$user4->method('getEMailAddress')->willReturn('user2@foo.bar456');


		$this->userSession->expects($this->at(0))
			->method('getUser')
			->willReturn($user2);

		$this->groupManager->expects($this->at(0))
			->method('getUserGroupIds')
			->willReturn(['group1']);
		$this->groupManager->expects($this->at(1))
			->method('getUserGroupIds')
			->willReturn(['group1']);
		$this->groupManager->expects($this->at(2))
			->method('getUserGroupIds')
			->willReturn(['group1']);
		$this->groupManager->expects($this->at(3))
			->method('getUserGroupIds')
			->willReturn(['group2']);

		$this->userManager->expects($this->at(0))
			->method('searchDisplayName')
			->with('User')
			->willReturn([$user2, $user3, $user4]);


		$this->assertEquals([
			'principals/users/user2',
			'principals/users/user3',
		], $this->connector->searchPrincipals('principals/users',
			['{DAV:}displayname' => 'User']));
	}

	public function testSearchPrincipalWithEnumerationLimitedMail() {
		$this->shareManager->expects($this->at(0))
			->method('shareAPIEnabled')
			->willReturn(true);

		$this->shareManager->expects($this->at(1))
			->method('allowEnumeration')
			->willReturn(true);

		$this->shareManager->expects($this->at(2))
			->method('limitEnumerationToGroups')
			->willReturn(true);

		$this->shareManager->expects($this->once())
			->method('shareWithGroupMembersOnly')
			->willReturn(false);

		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('getDisplayName')->willReturn('User 2');
		$user2->method('getEMailAddress')->willReturn('user2@foo.bar');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')->willReturn('user3');
		$user3->method('getDisplayName')->willReturn('User 22');
		$user3->method('getEMailAddress')->willReturn('user2@foo.bar123');
		$user4 = $this->createMock(IUser::class);
		$user4->method('getUID')->willReturn('user4');
		$user4->method('getDisplayName')->willReturn('User 222');
		$user4->method('getEMailAddress')->willReturn('user2@foo.bar456');


		$this->userSession->expects($this->at(0))
			->method('getUser')
			->willReturn($user2);

		$this->groupManager->expects($this->at(0))
			->method('getUserGroupIds')
			->willReturn(['group1']);
		$this->groupManager->expects($this->at(1))
			->method('getUserGroupIds')
			->willReturn(['group1']);
		$this->groupManager->expects($this->at(2))
			->method('getUserGroupIds')
			->willReturn(['group1']);
		$this->groupManager->expects($this->at(3))
			->method('getUserGroupIds')
			->willReturn(['group2']);

		$this->userManager->expects($this->at(0))
			->method('getByEmail')
			->with('user')
			->willReturn([$user2, $user3, $user4]);


		$this->assertEquals([
			'principals/users/user2',
			'principals/users/user3'
		], $this->connector->searchPrincipals('principals/users',
			['{http://sabredav.org/ns}email-address' => 'user']));
	}

	public function testFindByUriSharingApiDisabled() {
		$this->shareManager->expects($this->once())
			->method('shareApiEnabled')
			->willReturn(false);

		$this->assertEquals(null, $this->connector->findByUri('mailto:user@foo.com', 'principals/users'));
	}

	/**
	 * @dataProvider findByUriWithGroupRestrictionDataProvider
	 */
	public function testFindByUriWithGroupRestriction($uri, $email, $expects) {
		$this->shareManager->expects($this->once())
			->method('shareApiEnabled')
			->willReturn(true);

			$this->shareManager->expects($this->once())
				->method('shareWithGroupMembersOnly')
				->willReturn(true);

			$user = $this->createMock(IUser::class);
			$this->userSession->expects($this->once())
				->method('getUser')
				->willReturn($user);

			$this->groupManager->expects($this->at(0))
				->method('getUserGroupIds')
				->with($user)
				->willReturn(['group1', 'group2']);

			$user2 = $this->createMock(IUser::class);
			$user2->method('getUID')->willReturn('user2');
			$user3 = $this->createMock(IUser::class);
			$user3->method('getUID')->willReturn('user3');

			$this->userManager->expects($this->once())
				->method('getByEmail')
				->with($email)
				->willReturn([$email === 'user2@foo.bar' ? $user2 : $user3]);

			if ($email === 'user2@foo.bar') {
				$this->groupManager->expects($this->at(1))
					->method('getUserGroupIds')
					->with($user2)
					->willReturn(['group1', 'group3']);
			} else {
				$this->groupManager->expects($this->at(1))
					->method('getUserGroupIds')
					->with($user3)
					->willReturn(['group3', 'group3']);
			}

		$this->assertEquals($expects, $this->connector->findByUri($uri, 'principals/users'));
	}

	public function findByUriWithGroupRestrictionDataProvider() {
		return [
			['mailto:user2@foo.bar', 'user2@foo.bar', 'principals/users/user2'],
			['mailto:user3@foo.bar', 'user3@foo.bar', null],
		];
	}

	/**
	 * @dataProvider findByUriWithoutGroupRestrictionDataProvider
	 */
	public function testFindByUriWithoutGroupRestriction($uri, $email, $expects) {
		$this->shareManager->expects($this->once())
			->method('shareApiEnabled')
			->willReturn(true);

			$this->shareManager->expects($this->once())
				->method('shareWithGroupMembersOnly')
				->willReturn(false);

			$user2 = $this->createMock(IUser::class);
			$user2->method('getUID')->willReturn('user2');
			$user3 = $this->createMock(IUser::class);
			$user3->method('getUID')->willReturn('user3');

			$this->userManager->expects($this->once())
				->method('getByEmail')
				->with($email)
				->willReturn([$email === 'user2@foo.bar' ? $user2 : $user3]);

		$this->assertEquals($expects, $this->connector->findByUri($uri, 'principals/users'));
	}

	public function findByUriWithoutGroupRestrictionDataProvider() {
		return [
			['mailto:user2@foo.bar', 'user2@foo.bar', 'principals/users/user2'],
			['mailto:user3@foo.bar', 'user3@foo.bar', 'principals/users/user3'],
		];
	}
}
