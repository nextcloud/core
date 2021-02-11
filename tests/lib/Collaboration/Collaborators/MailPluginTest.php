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

use OC\Collaboration\Collaborators\MailPlugin;
use OC\Collaboration\Collaborators\SearchResult;
use OC\Federation\CloudIdManager;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\Contacts\IManager;
use OCP\Federation\ICloudIdManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IShare;
use Test\TestCase;

class MailPluginTest extends TestCase {
	/** @var  IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;

	/** @var  IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $contactsManager;

	/** @var  ICloudIdManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $cloudIdManager;

	/** @var  MailPlugin */
	protected $plugin;

	/** @var  SearchResult */
	protected $searchResult;

	/** @var  IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $groupManager;

	/** @var  IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	protected $userSession;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->contactsManager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->cloudIdManager = new CloudIdManager($this->contactsManager);

		$this->searchResult = new SearchResult();
	}

	public function instantiatePlugin() {
		$this->plugin = new MailPlugin($this->contactsManager, $this->cloudIdManager, $this->config, $this->groupManager, $this->userSession);
	}

	/**
	 * @dataProvider dataGetEmail
	 *
	 * @param string $searchTerm
	 * @param array $contacts
	 * @param bool $shareeEnumeration
	 * @param array $expected
	 * @param bool $reachedEnd
	 */
	public function testSearch($searchTerm, $contacts, $shareeEnumeration, $expected, $exactIdMatch, $reachedEnd) {
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

		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')
			->willReturn('current');
		$this->userSession->method('getUser')
			->willReturn($currentUser);

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

		$this->assertSame($exactIdMatch, $this->searchResult->hasExactIdMatch(new SearchResultType('emails')));
		$this->assertEquals($expected, $result);
		$this->assertSame($reachedEnd, $moreResults);
	}

	public function dataGetEmail() {
		return [
			// data set 0
			['test', [], true, ['emails' => [], 'exact' => ['emails' => []]], false, false],
			// data set 1
			['test', [], false, ['emails' => [], 'exact' => ['emails' => []]], false, false],
			// data set 2
			[
				'test@remote.com',
				[],
				true,
				['emails' => [], 'exact' => ['emails' => [['uuid' => 'test@remote.com', 'label' => 'test@remote.com', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'test@remote.com']]]]],
				false,
				false,
			],
			// data set 3
			[ // no valid email address
				'test@remote',
				[],
				true,
				['emails' => [], 'exact' => ['emails' => []]],
				false,
				false,
			],
			// data set 4
			[
				'test@remote.com',
				[],
				false,
				['emails' => [], 'exact' => ['emails' => [['uuid' => 'test@remote.com', 'label' => 'test@remote.com', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'test@remote.com']]]]],
				false,
				false,
			],
			// data set 5
			[
				'test',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'EMAIL' => [
							'username@localhost',
						],
					],
				],
				true,
				['emails' => [['uuid' => 'uid1', 'name' => 'User @ Localhost', 'type' => '', 'label' => 'User @ Localhost (username@localhost)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'username@localhost']]], 'exact' => ['emails' => []]],
				false,
				false,
			],
			// data set 6
			[
				'test',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'EMAIL' => [
							'username@localhost',
						],
					],
				],
				false,
				['emails' => [], 'exact' => ['emails' => []]],
				false,
				false,
			],
			// data set 7
			[
				'test@remote.com',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'EMAIL' => [
							'username@localhost',
						],
					],
				],
				true,
				['emails' => [['uuid' => 'uid1', 'name' => 'User @ Localhost', 'type' => '', 'label' => 'User @ Localhost (username@localhost)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'username@localhost']]], 'exact' => ['emails' => [['label' => 'test@remote.com', 'uuid' => 'test@remote.com', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'test@remote.com']]]]],
				false,
				false,
			],
			// data set 8
			[
				'test@remote.com',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'EMAIL' => [
							'username@localhost',
						],
					],
				],
				false,
				['emails' => [], 'exact' => ['emails' => [['label' => 'test@remote.com', 'uuid' => 'test@remote.com', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'test@remote.com']]]]],
				false,
				false,
			],
			// data set 9
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
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'EMAIL' => [
							'username@localhost',
						],
					],
				],
				true,
				['emails' => [], 'exact' => ['emails' => [['name' => 'User @ Localhost', 'uuid' => 'uid1', 'type' => '', 'label' => 'User @ Localhost (username@localhost)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'username@localhost']]]]],
				true,
				false,
			],
			// data set 10
			[
				'username@localhost',
				[
					[
						'UID' => 'uid1',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'EMAIL' => [
							'username@localhost',
						],
					],
				],
				false,
				['emails' => [], 'exact' => ['emails' => [['name' => 'User @ Localhost', 'uuid' => 'uid1', 'type' => '', 'label' => 'User @ Localhost (username@localhost)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'username@localhost']]]]],
				true,
				false,
			],
			// data set 11
			// contact with space
			[
				'user name@localhost',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User Name @ Localhost',
						'EMAIL' => [
							'user name@localhost',
						],
					],
				],
				false,
				['emails' => [], 'exact' => ['emails' => [['name' => 'User Name @ Localhost', 'uuid' => 'uid1', 'type' => '', 'label' => 'User Name @ Localhost (user name@localhost)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'user name@localhost']]]]],
				true,
				false,
			],
			// data set 12
			// remote with space, no contact
			[
				'user space@remote.com',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3 @ Localhost',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2 @ Localhost',
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User @ Localhost',
						'EMAIL' => [
							'username@localhost',
						],
					],
				],
				false,
				['emails' => [], 'exact' => ['emails' => []]],
				false,
				false,
			],
			// data set 13
			// Local user found by email
			[
				'test@example.com',
				[
					[
						'UID' => 'uid1',
						'FN' => 'User',
						'EMAIL' => ['test@example.com'],
						'CLOUD' => ['test@localhost'],
						'isLocalSystemBook' => true,
					]
				],
				false,
				['users' => [], 'exact' => ['users' => [['uuid' => 'uid1', 'name' => 'User', 'label' => 'User (test@example.com)','value' => ['shareType' => IShare::TYPE_USER, 'shareWith' => 'test'], 'shareWithDisplayNameUnique' => 'test@example.com']]]],
				true,
				false,
			],
			// data set 14
			// Current local user found by email => no result
			[
				'test@example.com',
				[
					[
						'UID' => 'uid1',
						'FN' => 'User',
						'EMAIL' => ['test@example.com'],
						'CLOUD' => ['current@localhost'],
						'isLocalSystemBook' => true,
					]
				],
				true,
				['exact' => []],
				false,
				false,
			],
			// data set 15
			// Pagination and "more results" for user matches byyyyyyy emails
			[
				'test@example',
				[
					[
						'UID' => 'uid1',
						'FN' => 'User1',
						'EMAIL' => ['test@example.com'],
						'CLOUD' => ['test1@localhost'],
						'isLocalSystemBook' => true,
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2',
						'EMAIL' => ['test@example.de'],
						'CLOUD' => ['test2@localhost'],
						'isLocalSystemBook' => true,
					],
					[
						'UID' => 'uid3',
						'FN' => 'User3',
						'EMAIL' => ['test@example.org'],
						'CLOUD' => ['test3@localhost'],
						'isLocalSystemBook' => true,
					],
					[
						'UID' => 'uid4',
						'FN' => 'User4',
						'EMAIL' => ['test@example.net'],
						'CLOUD' => ['test4@localhost'],
						'isLocalSystemBook' => true,
					],
				],
				true,
				['users' => [
					['uuid' => 'uid1', 'name' => 'User1', 'label' => 'User1 (test@example.com)', 'value' => ['shareType' => IShare::TYPE_USER, 'shareWith' => 'test1'], 'shareWithDisplayNameUnique' => 'test@example.com'],
					['uuid' => 'uid2', 'name' => 'User2', 'label' => 'User2 (test@example.de)', 'value' => ['shareType' => IShare::TYPE_USER, 'shareWith' => 'test2'], 'shareWithDisplayNameUnique' => 'test@example.de'],
				], 'emails' => [], 'exact' => ['users' => [], 'emails' => []]],
				false,
				true,
			],
			// data set 16
			// Pagination and "more results" for normal emails
			[
				'test@example',
				[
					[
						'UID' => 'uid1',
						'FN' => 'User1',
						'EMAIL' => ['test@example.com'],
						'CLOUD' => ['test1@localhost'],
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2',
						'EMAIL' => ['test@example.de'],
						'CLOUD' => ['test2@localhost'],
					],
					[
						'UID' => 'uid3',
						'FN' => 'User3',
						'EMAIL' => ['test@example.org'],
						'CLOUD' => ['test3@localhost'],
					],
					[
						'UID' => 'uid4',
						'FN' => 'User4',
						'EMAIL' => ['test@example.net'],
						'CLOUD' => ['test4@localhost'],
					],
				],
				true,
				['emails' => [
					['uuid' => 'uid1', 'name' => 'User1', 'type' => '', 'label' => 'User1 (test@example.com)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'test@example.com']],
					['uuid' => 'uid2', 'name' => 'User2', 'type' => '', 'label' => 'User2 (test@example.de)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'test@example.de']],
				], 'exact' => ['emails' => []]],
				false,
				true,
			],
			// data set 17
			// multiple email addresses with type
			[
				'User Name',
				[
					[
						'UID' => 'uid3',
						'FN' => 'User3',
					],
					[
						'UID' => 'uid2',
						'FN' => 'User2',
						'EMAIL' => [
						],
					],
					[
						'UID' => 'uid1',
						'FN' => 'User Name',
						'EMAIL' => [
							['type' => 'HOME', 'value' => 'username@localhost'],
							['type' => 'WORK', 'value' => 'username@other'],
						],
					],
				],
				false,
				['emails' => [
				], 'exact' => ['emails' => [
					['name' => 'User Name', 'uuid' => 'uid1', 'type' => 'HOME', 'label' => 'User Name (username@localhost)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'username@localhost']],
					['name' => 'User Name', 'uuid' => 'uid1', 'type' => 'WORK', 'label' => 'User Name (username@other)', 'value' => ['shareType' => IShare::TYPE_EMAIL, 'shareWith' => 'username@other']]
				]]],
				false,
				false,
			],
		];
	}

	/**
	 * @dataProvider dataGetEmailGroupsOnly
	 *
	 * @param string $searchTerm
	 * @param array $contacts
	 * @param array $expected
	 * @param bool $exactIdMatch
	 * @param bool $reachedEnd
	 * @param array groups
	 */
	public function testSearchGroupsOnly($searchTerm, $contacts, $expected, $exactIdMatch, $reachedEnd, $userToGroupMapping) {
		$this->config->expects($this->any())
			->method('getAppValue')
			->willReturnCallback(
				function ($appName, $key, $default) {
					if ($appName === 'core' && $key === 'shareapi_allow_share_dialog_user_enumeration') {
						return 'yes';
					} elseif ($appName === 'core' && $key === 'shareapi_only_share_with_group_members') {
						return 'yes';
					}
					return $default;
				}
			);

		$this->instantiatePlugin();

		/** @var \OCP\IUser | \PHPUnit\Framework\MockObject\MockObject */
		$currentUser = $this->createMock('\OCP\IUser');

		$currentUser->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');

		$this->contactsManager->expects($this->any())
			->method('search')
			->willReturnCallback(function ($search, $searchAttributes) use ($searchTerm, $contacts) {
				if ($search === $searchTerm) {
					return $contacts;
				}
				return [];
			});

		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($currentUser);

		$this->groupManager->expects($this->any())
			->method('getUserGroupIds')
			->willReturnCallback(function (\OCP\IUser $user) use ($userToGroupMapping) {
				return $userToGroupMapping[$user->getUID()];
			});

		$this->groupManager->expects($this->any())
			->method('isInGroup')
			->willReturnCallback(function ($userId, $group) use ($userToGroupMapping) {
				return in_array($group, $userToGroupMapping[$userId]);
			});

		$moreResults = $this->plugin->search($searchTerm, 2, 0, $this->searchResult);
		$result = $this->searchResult->asArray();

		$this->assertSame($exactIdMatch, $this->searchResult->hasExactIdMatch(new SearchResultType('emails')));
		$this->assertEquals($expected, $result);
		$this->assertSame($reachedEnd, $moreResults);
	}

	public function dataGetEmailGroupsOnly() {
		return [
			// The user `User` can share with the current user
			[
				'test',
				[
					[
						'FN' => 'User',
						'EMAIL' => ['test@example.com'],
						'CLOUD' => ['test@localhost'],
						'isLocalSystemBook' => true,
						'UID' => 'User'
					]
				],
				['users' => [['label' => 'User (test@example.com)', 'uuid' => 'User', 'name' => 'User', 'value' => ['shareType' => 0, 'shareWith' => 'test'],'shareWithDisplayNameUnique' => 'test@example.com',]], 'emails' => [], 'exact' => ['emails' => [], 'users' => []]],
				false,
				false,
				[
					"currentUser" => ["group1"],
					"User" => ["group1"]
				]
			],
			// The user `User` cannot share with the current user
			[
				'test',
				[
					[
						'FN' => 'User',
						'EMAIL' => ['test@example.com'],
						'CLOUD' => ['test@localhost'],
						'isLocalSystemBook' => true,
						'UID' => 'User'
					]
				],
				['emails' => [], 'exact' => ['emails' => []]],
				false,
				false,
				[
					"currentUser" => ["group1"],
					"User" => ["group2"]
				]
			],
			// The user `User` cannot share with the current user, but there is an exact match on the e-mail address -> share by e-mail
			[
				'test@example.com',
				[
					[
						'FN' => 'User',
						'EMAIL' => ['test@example.com'],
						'CLOUD' => ['test@localhost'],
						'isLocalSystemBook' => true,
						'UID' => 'User'
					]
				],
				['emails' => [], 'exact' => ['emails' => [['label' => 'test@example.com', 'uuid' => 'test@example.com', 'value' => ['shareType' => 4,'shareWith' => 'test@example.com']]]]],
				false,
				false,
				[
					"currentUser" => ["group1"],
					"User" => ["group2"]
				]
			]
		];
	}
}
