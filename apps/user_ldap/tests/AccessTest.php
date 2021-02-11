<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author root <root@localhost.localdomain>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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
use OCA\User_LDAP\Exceptions\ConstraintViolationException;
use OCA\User_LDAP\FilesystemHelper;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\ILDAPWrapper;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\LogWrapper;
use OCA\User_LDAP\Mapping\GroupMapping;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\Manager;
use OCA\User_LDAP\User\OfflineUser;
use OCA\User_LDAP\User\User;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\Image;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IManager;
use Test\TestCase;

/**
 * Class AccessTest
 *
 * @group DB
 *
 * @package OCA\User_LDAP\Tests
 */
class AccessTest extends TestCase {
	/** @var UserMapping|\PHPUnit\Framework\MockObject\MockObject */
	protected $userMapper;
	/** @var IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $shareManager;
	/** @var GroupMapping|\PHPUnit\Framework\MockObject\MockObject */
	protected $groupMapper;
	/** @var Connection|\PHPUnit\Framework\MockObject\MockObject */
	private $connection;
	/** @var LDAP|\PHPUnit\Framework\MockObject\MockObject */
	private $ldap;
	/** @var Manager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;
	/** @var Helper|\PHPUnit\Framework\MockObject\MockObject */
	private $helper;
	/** @var  IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $ncUserManager;
	/** @var Access */
	private $access;

	protected function setUp(): void {
		$this->connection = $this->createMock(Connection::class);
		$this->ldap = $this->createMock(LDAP::class);
		$this->userManager = $this->createMock(Manager::class);
		$this->helper = $this->createMock(Helper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userMapper = $this->createMock(UserMapping::class);
		$this->groupMapper = $this->createMock(GroupMapping::class);
		$this->ncUserManager = $this->createMock(IUserManager::class);
		$this->shareManager = $this->createMock(IManager::class);

		$this->access = new Access(
			$this->connection,
			$this->ldap,
			$this->userManager,
			$this->helper,
			$this->config,
			$this->ncUserManager
		);
		$this->access->setUserMapper($this->userMapper);
		$this->access->setGroupMapper($this->groupMapper);
	}

	private function getConnectorAndLdapMock() {
		$lw = $this->createMock(ILDAPWrapper::class);
		$connector = $this->getMockBuilder(Connection::class)
			->setConstructorArgs([$lw, null, null])
			->getMock();
		$um = $this->getMockBuilder(Manager::class)
			->setConstructorArgs([
				$this->createMock(IConfig::class),
				$this->createMock(FilesystemHelper::class),
				$this->createMock(LogWrapper::class),
				$this->createMock(IAvatarManager::class),
				$this->createMock(Image::class),
				$this->createMock(IUserManager::class),
				$this->createMock(INotificationManager::class),
				$this->shareManager])
			->getMock();
		$helper = new Helper(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection());

		return [$lw, $connector, $um, $helper];
	}

	public function testEscapeFilterPartValidChars() {
		$input = 'okay';
		$this->assertTrue($input === $this->access->escapeFilterPart($input));
	}

	public function testEscapeFilterPartEscapeWildcard() {
		$input = '*';
		$expected = '\\\\*';
		$this->assertTrue($expected === $this->access->escapeFilterPart($input));
	}

	public function testEscapeFilterPartEscapeWildcard2() {
		$input = 'foo*bar';
		$expected = 'foo\\\\*bar';
		$this->assertTrue($expected === $this->access->escapeFilterPart($input));
	}

	/**
	 * @dataProvider convertSID2StrSuccessData
	 * @param array $sidArray
	 * @param $sidExpected
	 */
	public function testConvertSID2StrSuccess(array $sidArray, $sidExpected) {
		$sidBinary = implode('', $sidArray);
		$this->assertSame($sidExpected, $this->access->convertSID2Str($sidBinary));
	}

	public function convertSID2StrSuccessData() {
		return [
			[
				[
					"\x01",
					"\x04",
					"\x00\x00\x00\x00\x00\x05",
					"\x15\x00\x00\x00",
					"\xa6\x81\xe5\x0e",
					"\x4d\x6c\x6c\x2b",
					"\xca\x32\x05\x5f",
				],
				'S-1-5-21-249921958-728525901-1594176202',
			],
			[
				[
					"\x01",
					"\x02",
					"\xFF\xFF\xFF\xFF\xFF\xFF",
					"\xFF\xFF\xFF\xFF",
					"\xFF\xFF\xFF\xFF",
				],
				'S-1-281474976710655-4294967295-4294967295',
			],
		];
	}

	public function testConvertSID2StrInputError() {
		$sidIllegal = 'foobar';
		$sidExpected = '';

		$this->assertSame($sidExpected, $this->access->convertSID2Str($sidIllegal));
	}

	public function testGetDomainDNFromDNSuccess() {
		$inputDN = 'uid=zaphod,cn=foobar,dc=my,dc=server,dc=com';
		$domainDN = 'dc=my,dc=server,dc=com';

		$this->ldap->expects($this->once())
			->method('explodeDN')
			->with($inputDN, 0)
			->willReturn(explode(',', $inputDN));

		$this->assertSame($domainDN, $this->access->getDomainDNFromDN($inputDN));
	}

	public function testGetDomainDNFromDNError() {
		$inputDN = 'foobar';
		$expected = '';

		$this->ldap->expects($this->once())
			->method('explodeDN')
			->with($inputDN, 0)
			->willReturn(false);

		$this->assertSame($expected, $this->access->getDomainDNFromDN($inputDN));
	}

	public function dnInputDataProvider() {
		return  [[
			[
				'input' => 'foo=bar,bar=foo,dc=foobar',
				'interResult' => [
					'count' => 3,
					0 => 'foo=bar',
					1 => 'bar=foo',
					2 => 'dc=foobar'
				],
				'expectedResult' => true
			],
			[
				'input' => 'foobarbarfoodcfoobar',
				'interResult' => false,
				'expectedResult' => false
			]
		]];
	}

	/**
	 * @dataProvider dnInputDataProvider
	 * @param array $case
	 */
	public function testStringResemblesDN($case) {
		list($lw, $con, $um, $helper) = $this->getConnectorAndLdapMock();
		/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject $config */
		$config = $this->createMock(IConfig::class);
		$access = new Access($con, $lw, $um, $helper, $config, $this->ncUserManager);

		$lw->expects($this->exactly(1))
			->method('explodeDN')
			->willReturnCallback(function ($dn) use ($case) {
				if ($dn === $case['input']) {
					return $case['interResult'];
				}
				return null;
			});

		$this->assertSame($case['expectedResult'], $access->stringResemblesDN($case['input']));
	}

	/**
	 * @dataProvider dnInputDataProvider
	 * @param $case
	 */
	public function testStringResemblesDNLDAPmod($case) {
		list(, $con, $um, $helper) = $this->getConnectorAndLdapMock();
		/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject $config */
		$config = $this->createMock(IConfig::class);
		$lw = new LDAP();
		$access = new Access($con, $lw, $um, $helper, $config, $this->ncUserManager);

		if (!function_exists('ldap_explode_dn')) {
			$this->markTestSkipped('LDAP Module not available');
		}

		$this->assertSame($case['expectedResult'], $access->stringResemblesDN($case['input']));
	}

	public function testCacheUserHome() {
		$this->connection->expects($this->once())
			->method('writeToCache');

		$this->access->cacheUserHome('foobar', '/foobars/path');
	}

	public function testBatchApplyUserAttributes() {
		$this->ldap->expects($this->any())
			->method('isResource')
			->willReturn(true);

		$this->ldap->expects($this->any())
			->method('getAttributes')
			->willReturn(['displayname' => ['bar', 'count' => 1]]);

		/** @var UserMapping|\PHPUnit\Framework\MockObject\MockObject $mapperMock */
		$mapperMock = $this->createMock(UserMapping::class);
		$mapperMock->expects($this->any())
			->method('getNameByDN')
			->willReturn(false);
		$mapperMock->expects($this->any())
			->method('map')
			->willReturn(true);

		$userMock = $this->createMock(User::class);

		// also returns for userUuidAttribute
		$this->access->connection->expects($this->any())
			->method('__get')
			->willReturn('displayName');

		$this->access->setUserMapper($mapperMock);

		$displayNameAttribute = strtolower($this->access->connection->ldapUserDisplayName);
		$data = [
			[
				'dn' => ['foobar'],
				$displayNameAttribute => 'barfoo'
			],
			[
				'dn' => ['foo'],
				$displayNameAttribute => 'bar'
			],
			[
				'dn' => ['raboof'],
				$displayNameAttribute => 'oofrab'
			]
		];

		$userMock->expects($this->exactly(count($data)))
			->method('processAttributes');

		$this->userManager->expects($this->exactly(count($data) * 2))
			->method('get')
			->willReturn($userMock);

		$this->access->batchApplyUserAttributes($data);
	}

	public function testBatchApplyUserAttributesSkipped() {
		/** @var UserMapping|\PHPUnit\Framework\MockObject\MockObject $mapperMock */
		$mapperMock = $this->createMock(UserMapping::class);
		$mapperMock->expects($this->any())
			->method('getNameByDN')
			->willReturn('a_username');

		$userMock = $this->createMock(User::class);

		$this->access->connection->expects($this->any())
			->method('__get')
			->willReturn('displayName');

		$this->access->setUserMapper($mapperMock);

		$displayNameAttribute = strtolower($this->access->connection->ldapUserDisplayName);
		$data = [
			[
				'dn' => ['foobar'],
				$displayNameAttribute => 'barfoo'
			],
			[
				'dn' => ['foo'],
				$displayNameAttribute => 'bar'
			],
			[
				'dn' => ['raboof'],
				$displayNameAttribute => 'oofrab'
			]
		];

		$userMock->expects($this->never())
			->method('processAttributes');

		$this->userManager->expects($this->any())
			->method('get')
			->willReturn($this->createMock(User::class));

		$this->access->batchApplyUserAttributes($data);
	}

	public function testBatchApplyUserAttributesDontSkip() {
		/** @var UserMapping|\PHPUnit\Framework\MockObject\MockObject $mapperMock */
		$mapperMock = $this->createMock(UserMapping::class);
		$mapperMock->expects($this->any())
			->method('getNameByDN')
			->willReturn('a_username');

		$userMock = $this->createMock(User::class);

		$this->access->connection->expects($this->any())
			->method('__get')
			->willReturn('displayName');

		$this->access->setUserMapper($mapperMock);

		$displayNameAttribute = strtolower($this->access->connection->ldapUserDisplayName);
		$data = [
			[
				'dn' => ['foobar'],
				$displayNameAttribute => 'barfoo'
			],
			[
				'dn' => ['foo'],
				$displayNameAttribute => 'bar'
			],
			[
				'dn' => ['raboof'],
				$displayNameAttribute => 'oofrab'
			]
		];

		$userMock->expects($this->exactly(count($data)))
			->method('processAttributes');

		$this->userManager->expects($this->exactly(count($data) * 2))
			->method('get')
			->willReturn($userMock);

		$this->access->batchApplyUserAttributes($data);
	}

	public function dNAttributeProvider() {
		// corresponds to Access::resemblesDN()
		return [
			'dn' => ['dn'],
			'uniqueMember' => ['uniquemember'],
			'member' => ['member'],
			'memberOf' => ['memberof']
		];
	}

	/**
	 * @dataProvider dNAttributeProvider
	 * @param $attribute
	 */
	public function testSanitizeDN($attribute) {
		list($lw, $con, $um, $helper) = $this->getConnectorAndLdapMock();
		/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject $config */
		$config = $this->createMock(IConfig::class);

		$dnFromServer = 'cn=Mixed Cases,ou=Are Sufficient To,ou=Test,dc=example,dc=org';

		$lw->expects($this->any())
			->method('isResource')
			->willReturn(true);
		$lw->expects($this->any())
			->method('getAttributes')
			->willReturn([
				$attribute => ['count' => 1, $dnFromServer]
			]);

		$access = new Access($con, $lw, $um, $helper, $config, $this->ncUserManager);
		$values = $access->readAttribute('uid=whoever,dc=example,dc=org', $attribute);
		$this->assertSame($values[0], strtolower($dnFromServer));
	}


	public function testSetPasswordWithDisabledChanges() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('LDAP password changes are disabled');

		$this->connection
			->method('__get')
			->willReturn(false);

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->access->setPassword('CN=foo', 'MyPassword');
	}

	public function testSetPasswordWithLdapNotAvailable() {
		$this->connection
			->method('__get')
			->willReturn(true);
		$connection = $this->createMock(LDAP::class);
		$this->connection
			->expects($this->once())
			->method('getConnectionResource')
			->willReturn($connection);
		$this->ldap
			->expects($this->once())
			->method('isResource')
			->with($connection)
			->willReturn(false);

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->assertFalse($this->access->setPassword('CN=foo', 'MyPassword'));
	}


	public function testSetPasswordWithRejectedChange() {
		$this->expectException(\OC\HintException::class);
		$this->expectExceptionMessage('Password change rejected.');

		$this->connection
			->method('__get')
			->willReturn(true);
		$connection = $this->createMock(LDAP::class);
		$this->connection
			->expects($this->once())
			->method('getConnectionResource')
			->willReturn($connection);
		$this->ldap
			->expects($this->once())
			->method('isResource')
			->with($connection)
			->willReturn(true);
		$this->ldap
			->expects($this->once())
			->method('modReplace')
			->with($connection, 'CN=foo', 'MyPassword')
			->willThrowException(new ConstraintViolationException());

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->access->setPassword('CN=foo', 'MyPassword');
	}

	public function testSetPassword() {
		$this->connection
			->method('__get')
			->willReturn(true);
		$connection = $this->createMock(LDAP::class);
		$this->connection
			->expects($this->once())
			->method('getConnectionResource')
			->willReturn($connection);
		$this->ldap
			->expects($this->once())
			->method('isResource')
			->with($connection)
			->willReturn(true);
		$this->ldap
			->expects($this->once())
			->method('modReplace')
			->with($connection, 'CN=foo', 'MyPassword')
			->willReturn(true);

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->assertTrue($this->access->setPassword('CN=foo', 'MyPassword'));
	}

	protected function prepareMocksForSearchTests(
		$base,
		$fakeConnection,
		$fakeSearchResultResource,
		$fakeLdapEntries
	) {
		$this->connection
			->expects($this->any())
			->method('getConnectionResource')
			->willReturn($fakeConnection);
		$this->connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($key) use ($base) {
				if (stripos($key, 'base') !== false) {
					return [$base];
				}
				return null;
			});

		$this->ldap
			->expects($this->any())
			->method('isResource')
			->willReturnCallback(function ($resource) {
				return is_resource($resource);
			});
		$this->ldap
			->expects($this->any())
			->method('errno')
			->willReturn(0);
		$this->ldap
			->expects($this->once())
			->method('search')
			->willReturn($fakeSearchResultResource);
		$this->ldap
			->expects($this->exactly(1))
			->method('getEntries')
			->willReturn($fakeLdapEntries);

		$this->helper->expects($this->any())
			->method('sanitizeDN')
			->willReturnArgument(0);
	}

	public function testSearchNoPagedSearch() {
		// scenario: no pages search, 1 search base
		$filter = 'objectClass=nextcloudUser';
		$base = 'ou=zombies,dc=foobar,dc=nextcloud,dc=com';

		$fakeConnection = ldap_connect();
		$fakeSearchResultResource = ldap_connect();
		$fakeLdapEntries = [
			'count' => 2,
			[
				'dn' => 'uid=sgarth,' . $base,
			],
			[
				'dn' => 'uid=wwilson,' . $base,
			]
		];

		$expected = $fakeLdapEntries;
		unset($expected['count']);

		$this->prepareMocksForSearchTests($base, $fakeConnection, $fakeSearchResultResource, $fakeLdapEntries);

		/** @noinspection PhpUnhandledExceptionInspection */
		$result = $this->access->search($filter, $base);
		$this->assertSame($expected, $result);
	}

	public function testFetchListOfUsers() {
		$filter = 'objectClass=nextcloudUser';
		$base = 'ou=zombies,dc=foobar,dc=nextcloud,dc=com';
		$attrs = ['dn', 'uid'];

		$fakeConnection = ldap_connect();
		$fakeSearchResultResource = ldap_connect();
		$fakeLdapEntries = [
			'count' => 2,
			[
				'dn' => 'uid=sgarth,' . $base,
				'uid' => [ 'sgarth' ],
			],
			[
				'dn' => 'uid=wwilson,' . $base,
				'uid' => [ 'wwilson' ],
			]
		];
		$expected = $fakeLdapEntries;
		unset($expected['count']);
		array_walk($expected, function (&$v) {
			$v['dn'] = [$v['dn']];	// dn is translated into an array internally for consistency
		});

		$this->prepareMocksForSearchTests($base, $fakeConnection, $fakeSearchResultResource, $fakeLdapEntries);

		$this->connection->expects($this->exactly($fakeLdapEntries['count']))
			->method('writeToCache')
			->with($this->stringStartsWith('userExists'), true);

		$this->userMapper->expects($this->exactly($fakeLdapEntries['count']))
			->method('getNameByDN')
			->willReturnCallback(function ($fdn) {
				$parts = ldap_explode_dn($fdn, false);
				return $parts[0];
			});

		/** @noinspection PhpUnhandledExceptionInspection */
		$list = $this->access->fetchListOfUsers($filter, $attrs);
		$this->assertSame($expected, $list);
	}

	public function testFetchListOfGroupsKnown() {
		$filter  = 'objectClass=nextcloudGroup';
		$attributes = ['cn', 'gidNumber', 'dn'];
		$base = 'ou=SomeGroups,dc=my,dc=directory';

		$fakeConnection = ldap_connect();
		$fakeSearchResultResource = ldap_connect();
		$fakeLdapEntries = [
			'count' => 2,
			[
				'dn' => 'cn=Good Team,' . $base,
				'cn' => ['Good Team'],
			],
			[
				'dn' => 'cn=Another Good Team,' . $base,
				'cn' => ['Another Good Team'],
			]
		];

		$this->prepareMocksForSearchTests($base, $fakeConnection, $fakeSearchResultResource, $fakeLdapEntries);

		$this->groupMapper->expects($this->any())
			->method('getListOfIdsByDn')
			->willReturn([
				'cn=Good Team,' . $base => 'Good_Team',
				'cn=Another Good Team,' . $base => 'Another_Good_Team',
			]);
		$this->groupMapper->expects($this->never())
			->method('getNameByDN');

		$this->connection->expects($this->exactly(2))
			->method('writeToCache');

		$groups = $this->access->fetchListOfGroups($filter, $attributes);
		$this->assertSame(2, count($groups));
		$this->assertSame('Good Team', $groups[0]['cn'][0]);
		$this->assertSame('Another Good Team', $groups[1]['cn'][0]);
	}

	public function intUsernameProvider() {
		// system dependent :-/
		$translitExpected = @iconv('UTF-8', 'ASCII//TRANSLIT', 'fränk') ? 'frank' : 'frnk';

		return [
			['alice', 'alice'],
			['b/ob', 'bob'],
			['charly🐬', 'charly'],
			['debo rah', 'debo_rah'],
			['epost@poste.test', 'epost@poste.test'],
			['fränk', $translitExpected],
			[' gerda ', 'gerda'],
			['🕱🐵🐘🐑', null]
		];
	}

	/**
	 * @dataProvider intUsernameProvider
	 *
	 * @param $name
	 * @param $expected
	 */
	public function testSanitizeUsername($name, $expected) {
		if ($name === 'fränk' && PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Special chars do boom still on CI in php8');
		}
		if ($expected === null) {
			$this->expectException(\InvalidArgumentException::class);
		}
		$sanitizedName = $this->access->sanitizeUsername($name);
		$this->assertSame($expected, $sanitizedName);
	}

	public function testUserStateUpdate() {
		$this->connection->expects($this->any())
			->method('__get')
			->willReturnMap([
				[ 'ldapUserDisplayName', 'displayName' ],
				[ 'ldapUserDisplayName2', null],
			]);

		$offlineUserMock = $this->createMock(OfflineUser::class);
		$offlineUserMock->expects($this->once())
			->method('unmark');

		$regularUserMock = $this->createMock(User::class);

		$this->userManager->expects($this->atLeastOnce())
			->method('get')
			->with('detta')
			->willReturnOnConsecutiveCalls($offlineUserMock, $regularUserMock);

		/** @var UserMapping|\PHPUnit\Framework\MockObject\MockObject $mapperMock */
		$mapperMock = $this->createMock(UserMapping::class);
		$mapperMock->expects($this->any())
			->method('getNameByDN')
			->with('uid=detta,ou=users,dc=hex,dc=ample')
			->willReturn('detta');
		$this->access->setUserMapper($mapperMock);

		$records = [
			[
				'dn' => ['uid=detta,ou=users,dc=hex,dc=ample'],
				'displayName' => ['Detta Detkova'],
			]
		];
		$this->access->nextcloudUserNames($records);
	}
}
