<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Collaboration\Collaborators;

use OC\Collaboration\Collaborators\RemotePlugin;
use OC\Collaboration\Collaborators\SearchResult;
use OC\Federation\CloudIdManager;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\Contacts\IManager;
use OCP\Federation\ICloudIdManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IShare;
use Test\TestCase;

class RemotePluginTest extends TestCase {

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $userManager;

	/** @var  IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;

	/** @var  IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $contactsManager;

	/** @var  ICloudIdManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $cloudIdManager;

	/** @var  RemotePlugin */
	protected $plugin;

	/** @var  SearchResult */
	protected $searchResult;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->contactsManager = $this->createMock(IManager::class);
		$this->cloudIdManager = new CloudIdManager($this->contactsManager);
		$this->searchResult = new SearchResult();
	}

	public function instantiatePlugin() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);
		$this->plugin = new RemotePlugin($this->contactsManager, $this->cloudIdManager, $this->config, $this->userManager, $userSession);
	}

	/**
	 * @dataProvider dataGetRemote
	 *
	 * @param string $searchTerm
	 * @param array $contacts
	 * @param bool $shareeEnumeration
	 * @param array $expected
	 * @param bool $exactIdMatch
	 * @param bool $reachedEnd
	 */
	public function testSearch($searchTerm, array $contacts, $shareeEnumeration, array $expected, $exactIdMatch, $reachedEnd) {
		$this->config->expects($this->any())
			->method('getAppValue')
			->willReturnCallback(
				function ($appName, $key, $default) use ($shareeEnumeration) {
					if ($appName === 'core' && $key === 'shareapi_allow_share_dialog_user_enumeration') {
						return $shareeEnumeration ? 'yes' : 'no';
					}
					return $default;
				}
			);

		$this->instantiatePlugin();

		$this->contactsManager->expects($this->any())
			->method('search')
			->willReturnCallback(function ($search, $searchAttributes) use ($searchTerm, $contacts) {
				if ($search === $searchTerm) {
					return $contacts;
				}
				return [];
			});

		$moreResults = $this->plugin->search($searchTerm, 2, 0, $this->searchResult);
		$result = $this->searchResult->asArray();

		$this->assertSame($exactIdMatch, $this->searchResult->hasExactIdMatch(new SearchResultType('remotes')));
		$this->assertEquals($expected, $result);
		$this->assertSame($reachedEnd, $moreResults);
	}

	/**
	 * @dataProvider dataTestSplitUserRemote
	 *
	 * @param string $remote
	 * @param string $expectedUser
	 * @param string $expectedUrl
	 */
	public function testSplitUserRemote($remote, $expectedUser, $expectedUrl) {
		$this->instantiatePlugin();

		$this->contactsManager->expects($this->any())
			->method('search')
			->willReturn([]);

		list($remoteUser, $remoteUrl) = $this->plugin->splitUserRemote($remote);
		$this->assertSame($expectedUser, $remoteUser);
		$this->assertSame($expectedUrl, $remoteUrl);
	}

	/**
	 * @dataProvider dataTestSplitUserRemoteError
	 *
	 * @param string $id
	 */
	public function testSplitUserRemoteError($id) {
		$this->expectException(\Exception::class);

		$this->instantiatePlugin();
		$this->plugin->splitUserRemote($id);
	}

	public function dataGetRemote() {
		return [
			['test', [], true, ['remotes' => [], 'exact' => ['remotes' => []]], false, true],
			['test', [], false, ['remotes' => [], 'exact' => ['remotes' => []]], false, true],
			[
				'test@remote',
				[],
				true,
				['remotes' => [], 'exact' => ['remotes' => [['label' => 'test (remote)', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'test@remote', 'server' => 'remote'], 'uuid' => 'test', 'name' => 'test']]]],
				false,
				true,
			],
			[
				'test@remote',
				[],
				false,
				['remotes' => [], 'exact' => ['remotes' => [['label' => 'test (remote)', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'test@remote', 'server' => 'remote'], 'uuid' => 'test', 'name' => 'test']]]],
				false,
				true,
			],
			[
				'test',
				[
					[
						'UID' => 'uid',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid',
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				true,
				['remotes' => [['name' => 'User @ Localhost', 'label' => 'User @ Localhost (username@localhost)', 'uuid' => 'uid1', 'type' => '', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'username@localhost', 'server' => 'localhost']]], 'exact' => ['remotes' => []]],
				false,
				true,
			],
			[
				'test',
				[
					[
						'UID' => 'uid',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid',
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'UID' => 'uid',
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				false,
				['remotes' => [], 'exact' => ['remotes' => []]],
				false,
				true,
			],
			[
				'test@remote',
				[
					[
						'UID' => 'uid',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid',
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'UID' => 'uid',
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				true,
				['remotes' => [['name' => 'User @ Localhost', 'label' => 'User @ Localhost (username@localhost)', 'uuid' => 'uid', 'type' => '', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'username@localhost', 'server' => 'localhost']]], 'exact' => ['remotes' => [['label' => 'test (remote)', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'test@remote', 'server' => 'remote'], 'uuid' => 'test', 'name' => 'test']]]],
				false,
				true,
			],
			[
				'test@remote',
				[
					[
						'UID' => 'uid',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid',
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'UID' => 'uid',
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				false,
				['remotes' => [], 'exact' => ['remotes' => [['label' => 'test (remote)', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'test@remote', 'server' => 'remote'], 'uuid' => 'test', 'name' => 'test']]]],
				false,
				true,
			],
			[
				'username@localhost',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => '2',
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				true,
				['remotes' => [], 'exact' => ['remotes' => [['name' => 'User @ Localhost', 'label' => 'User @ Localhost (username@localhost)', 'uuid' => 'uid1', 'type' => '', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'username@localhost', 'server' => 'localhost']]]]],
				true,
				true,
			],
			[
				'username@localhost',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				false,
				['remotes' => [], 'exact' => ['remotes' => [['name' => 'User @ Localhost', 'label' => 'User @ Localhost (username@localhost)', 'uuid' => 'uid1', 'type' => '', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'username@localhost', 'server' => 'localhost']]]]],
				true,
				true,
			],
			// contact with space
			[
				'user name@localhost',
				[
					[
						'UID' => 'uid1',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'UID' => 'uid3',
						'FN' => 'User Name @ Localhost',
						'CLOUD' => [
							'user name@localhost',
						],
					],
				],
				false,
				['remotes' => [], 'exact' => ['remotes' => [['name' => 'User Name @ Localhost', 'label' => 'User Name @ Localhost (user name@localhost)', 'uuid' => 'uid3', 'type' => '', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'user name@localhost', 'server' => 'localhost']]]]],
				true,
				true,
			],
			// remote with space, no contact
			[
				'user space@remote',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				false,
				['remotes' => [], 'exact' => ['remotes' => [['label' => 'user space (remote)', 'value' => ['shareType' => IShare::TYPE_REMOTE, 'shareWith' => 'user space@remote', 'server' => 'remote'], 'uuid' => 'user space', 'name' => 'user space']]]],
				false,
				true,
			],
		];
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
}
