<?php
/**
 * ownCloud
 *
 * @author Bjoern Schiessle
 * @copyright 2014 Bjoern Schiessle <schiessle@owncloud.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Test\Share;

/**
 * @group DB
 * Class Helper
 */
class HelperTest extends \Test\TestCase {

	public function expireDateProvider() {
		return [
			// no default expire date, we take the users expire date
			[['defaultExpireDateSet' => false], 2000000000, 2000010000, 2000010000],
			// no default expire date and no user defined expire date, return false
			[['defaultExpireDateSet' => false], 2000000000, null, false],
			// unenforced expire data and no user defined expire date, return false (because the default is not enforced)
			[['defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => false], 2000000000, null, false],
			// enforced expire date and no user defined expire date, take default expire date
			[['defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => true], 2000000000, null, 2000086400],
			// unenforced expire date and user defined date > default expire date, take users expire date
			[['defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => false], 2000000000, 2000100000, 2000100000],
			// unenforced expire date and user expire date < default expire date, take users expire date
			[['defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => false], 2000000000, 2000010000, 2000010000],
			// enforced expire date and user expire date < default expire date, take users expire date
			[['defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => true], 2000000000, 2000010000, 2000010000],
			// enforced expire date and users expire date > default expire date, take default expire date
			[['defaultExpireDateSet' => true, 'expireAfterDays' => 1, 'enforceExpireDate' => true], 2000000000, 2000100000, 2000086400],
		];
	}

	/**
	 * @dataProvider expireDateProvider
	 */
	public function testCalculateExpireDate($defaultExpireSettings, $creationTime, $userExpireDate, $expected) {
		$result = \OC\Share\Helper::calculateExpireDate($defaultExpireSettings, $creationTime, $userExpireDate);
		$this->assertSame($expected, $result);
	}

	public function dataTestSplitUserRemote() {
		$userPrefix = ['user@name', 'username'];
		$protocols = ['', 'http://', 'https://'];
		$remotes = [
			'localhost',
			'local.host',
			'dev.local.host',
			'dev.local.host/path',
			'dev.local.host/at@inpath',
			'127.0.0.1',
			'::1',
			'::192.0.2.128',
			'::192.0.2.128/at@inpath',
		];

		$testCases = [];
		foreach ($userPrefix as $user) {
			foreach ($remotes as $remote) {
				foreach ($protocols as $protocol) {
					$baseUrl = $user . '@' . $protocol . $remote;

					$testCases[] = [$baseUrl, $user, $protocol . $remote];
					$testCases[] = [$baseUrl . '/', $user, $protocol . $remote];
					$testCases[] = [$baseUrl . '/index.php', $user, $protocol . $remote];
					$testCases[] = [$baseUrl . '/index.php/s/token', $user, $protocol . $remote];
				}
			}
		}
		return $testCases;
	}

	/**
	 * @dataProvider dataTestSplitUserRemote
	 *
	 * @param string $remote
	 * @param string $expectedUser
	 * @param string $expectedUrl
	 */
	public function testSplitUserRemote($remote, $expectedUser, $expectedUrl) {
		list($remoteUser, $remoteUrl) = \OC\Share\Helper::splitUserRemote($remote);
		$this->assertSame($expectedUser, $remoteUser);
		$this->assertSame($expectedUrl, $remoteUrl);
	}

	public function dataTestSplitUserRemoteError() {
		return [
			// Invalid path
			['user@'],

			// Invalid user
			['@server'],
			['us/er@server'],
			['us:er@server'],

			// Invalid splitting
			['user'],
			[''],
			['us/erserver'],
			['us:erserver'],
		];
	}

	/**
	 * @dataProvider dataTestSplitUserRemoteError
	 *
	 * @param string $id
	 */
	public function testSplitUserRemoteError($id) {
		$this->expectException(\OC\HintException::class);

		\OC\Share\Helper::splitUserRemote($id);
	}

	/**
	 * @dataProvider dataTestCompareServerAddresses
	 *
	 * @param string $server1
	 * @param string $server2
	 * @param bool $expected
	 */
	public function testIsSameUserOnSameServer($user1, $server1, $user2, $server2, $expected) {
		$this->assertSame($expected,
			\OC\Share\Helper::isSameUserOnSameServer($user1, $server1, $user2, $server2)
		);
	}

	public function dataTestCompareServerAddresses() {
		return [
			['user1', 'http://server1', 'user1', 'http://server1', true],
			['user1', 'https://server1', 'user1', 'http://server1', true],
			['user1', 'http://serVer1', 'user1', 'http://server1', true],
			['user1', 'http://server1/',  'user1', 'http://server1', true],
			['user1', 'server1', 'user1', 'http://server1', true],
			['user1', 'http://server1', 'user1', 'http://server2', false],
			['user1', 'https://server1', 'user1', 'http://server2', false],
			['user1', 'http://serVer1', 'user1', 'http://serer2', false],
			['user1', 'http://server1/', 'user1', 'http://server2', false],
			['user1', 'server1', 'user1', 'http://server2', false],
			['user1', 'http://server1', 'user2', 'http://server1', false],
			['user1', 'https://server1', 'user2', 'http://server1', false],
			['user1', 'http://serVer1', 'user2', 'http://server1', false],
			['user1', 'http://server1/',  'user2', 'http://server1', false],
			['user1', 'server1', 'user2', 'http://server1', false],
		];
	}
}
