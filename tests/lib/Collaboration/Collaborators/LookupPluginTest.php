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

use OC\Collaboration\Collaborators\LookupPlugin;
use OC\Federation\CloudId;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share;
use Test\TestCase;

class LookupPluginTest extends TestCase {

	/** @var  IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var  IClientService|\PHPUnit_Framework_MockObject_MockObject */
	protected $clientService;
	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	protected $userSession;
	/** @var ICloudIdManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $cloudIdManager;
	/** @var  LookupPlugin */
	protected $plugin;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	protected $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->clientService = $this->createMock(IClientService::class);
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->expects($this->any())->method('getRemote')->willReturn('myNextcloud.net');
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())->method('getCloudId')->willReturn('user@myNextcloud.net');
		$this->userSession->expects($this->any())->method('getUser')
			->willReturn($user);
		$this->cloudIdManager->expects($this->any())->method('resolveCloudId')
			->willReturnCallback(function ($cloudId) {
				if ($cloudId === 'user@myNextcloud.net') {
					return new CloudId('user@myNextcloud.net', 'user', 'myNextcloud.net');
				}
				return new CloudId('user@someNextcloud.net', 'user', 'someNextcloud.net');
			});


		$this->plugin = new LookupPlugin(
			$this->config,
			$this->clientService,
			$this->userSession,
			$this->cloudIdManager,
			$this->logger
		);
	}

	public function testSearchNoLookupServerURI() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('files_sharing', 'lookupServerEnabled', 'yes')
			->willReturn('yes');
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('gs.enabled', false)
			->willReturn(false);

		$this->config->expects($this->at(2))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config->expects($this->at(3))
			->method('getSystemValue')
			->with('lookup_server', 'https://lookup.nextcloud.com')
			->willReturn('');

		$this->clientService->expects($this->never())
			->method('newClient');

		/** @var ISearchResult|\PHPUnit_Framework_MockObject_MockObject $searchResult */
		$searchResult = $this->createMock(ISearchResult::class);

		$this->plugin->search('foobar', 10, 0, $searchResult);
	}

	public function testSearchNoInternet() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('files_sharing', 'lookupServerEnabled', 'yes')
			->willReturn('yes');
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('gs.enabled', false)
			->willReturn(false);

		$this->config->expects($this->at(2))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(false);

		$this->clientService->expects($this->never())
			->method('newClient');

		/** @var ISearchResult|\PHPUnit_Framework_MockObject_MockObject $searchResult */
		$searchResult = $this->createMock(ISearchResult::class);

		$this->plugin->search('foobar', 10, 0, $searchResult);
	}

	/**
	 * @dataProvider searchDataProvider
	 * @param array $searchParams
	 */
	public function testSearch(array $searchParams) {
		$type = new SearchResultType('lookup');

		/** @var ISearchResult|\PHPUnit_Framework_MockObject_MockObject $searchResult */
		$searchResult = $this->createMock(ISearchResult::class);
		$searchResult->expects($this->once())
			->method('addResultSet')
			->with($type, $searchParams['expectedResult'], []);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('files_sharing', 'lookupServerEnabled', 'yes')
			->willReturn('yes');
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('gs.enabled', false)
			->willReturn(false);

		$this->config->expects($this->at(2))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config->expects($this->at(3))
			->method('getSystemValue')
			->with('lookup_server', 'https://lookup.nextcloud.com')
			->willReturn($searchParams['server']);

		$response = $this->createMock(IResponse::class);
		$response->expects($this->once())
			->method('getBody')
			->willReturn(json_encode($searchParams['resultBody']));

		$client = $this->createMock(IClient::class);
		$client->expects($this->once())
			->method('get')
			->willReturnCallback(function ($url) use ($searchParams, $response) {
				$this->assertSame(strpos($url, $searchParams['server'] . '/users?search='), 0);
				$this->assertNotFalse(strpos($url, urlencode($searchParams['search'])));
				return $response;
			});

		$this->clientService->expects($this->once())
			->method('newClient')
			->willReturn($client);

		$moreResults = $this->plugin->search(
			$searchParams['search'],
			$searchParams['limit'],
			$searchParams['offset'],
			$searchResult
		);

		$this->assertFalse($moreResults);
	}


	/**
	 * @dataProvider dataSearchEnableDisableLookupServer
	 * @param array $searchParams
	 * @param bool $GSEnabled
	 * @param bool $LookupEnabled
	 */
	public function testSearchEnableDisableLookupServer(array $searchParams, $GSEnabled, $LookupEnabled) {
		$type = new SearchResultType('lookup');

		/** @var ISearchResult|\PHPUnit_Framework_MockObject_MockObject $searchResult */
		$searchResult = $this->createMock(ISearchResult::class);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('files_sharing', 'lookupServerEnabled', 'yes')
			->willReturn($LookupEnabled ? 'yes' : 'no');
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('gs.enabled', false)
			->willReturn($GSEnabled);
		if ($GSEnabled || $LookupEnabled) {
			$searchResult->expects($this->once())
				->method('addResultSet')
				->with($type, $searchParams['expectedResult'], []);

			$this->config->expects($this->at(2))
				->method('getSystemValue')
				->with('has_internet_connection', true)
				->willReturn(true);
			$this->config->expects($this->at(3))
				->method('getSystemValue')
				->with('lookup_server', 'https://lookup.nextcloud.com')
				->willReturn($searchParams['server']);

			$response = $this->createMock(IResponse::class);
			$response->expects($this->once())
				->method('getBody')
				->willReturn(json_encode($searchParams['resultBody']));

			$client = $this->createMock(IClient::class);
			$client->expects($this->once())
				->method('get')
				->willReturnCallback(function ($url) use ($searchParams, $response) {
					$this->assertSame(strpos($url, $searchParams['server'] . '/users?search='), 0);
					$this->assertNotFalse(strpos($url, urlencode($searchParams['search'])));
					return $response;
				});

			$this->clientService->expects($this->once())
				->method('newClient')
				->willReturn($client);
		} else {
			$searchResult->expects($this->never())->method('addResultSet');
		}
		$moreResults = $this->plugin->search(
			$searchParams['search'],
			$searchParams['limit'],
			$searchParams['offset'],
			$searchResult
		);

		$this->assertFalse($moreResults);
	}


	public function testSearchLookupServerDisabled() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('files_sharing', 'lookupServerEnabled', 'yes')
			->willReturn('no');

		/** @var ISearchResult|\PHPUnit_Framework_MockObject_MockObject $searchResult */
		$searchResult = $this->createMock(ISearchResult::class);
		$searchResult->expects($this->never())
			->method('addResultSet');
		$searchResult->expects($this->never())
			->method('markExactIdMatch');

		$this->assertFalse($this->plugin->search('irr', 10, 0, $searchResult));
	}

	public function dataSearchEnableDisableLookupServer() {
		$fedIDs = [
			'foo@enceladus.moon',
			'foobar@enceladus.moon',
			'foongus@enceladus.moon',
		];

		return [
			[[
				'search' => 'foo',
				'limit' => 10,
				'offset' => 0,
				'server' => 'https://lookup.example.io',
				'resultBody' => [
					['federationId' => $fedIDs[0]],
					['federationId' => $fedIDs[1]],
					['federationId' => $fedIDs[2]],
				],
				'expectedResult' => [
					[
						'label' => $fedIDs[0],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[0]
						],
						'extra' => ['federationId' => $fedIDs[0]],
					],
					[
						'label' => $fedIDs[1],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[1]
						],
						'extra' => ['federationId' => $fedIDs[1]],
					],
					[
						'label' => $fedIDs[2],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[2]
						],
						'extra' => ['federationId' => $fedIDs[2]],
					],
				]
			],// GS , Lookup
				true, true
			],
			[[
				'search' => 'foo',
				'limit' => 10,
				'offset' => 0,
				'server' => 'https://lookup.example.io',
				'resultBody' => [
					['federationId' => $fedIDs[0]],
					['federationId' => $fedIDs[1]],
					['federationId' => $fedIDs[2]],
				],
				'expectedResult' => [
					[
						'label' => $fedIDs[0],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[0]
						],
						'extra' => ['federationId' => $fedIDs[0]],
					],
					[
						'label' => $fedIDs[1],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[1]
						],
						'extra' => ['federationId' => $fedIDs[1]],
					],
					[
						'label' => $fedIDs[2],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[2]
						],
						'extra' => ['federationId' => $fedIDs[2]],
					],
				]
			],// GS , Lookup
				true, false
			],
			[[
				'search' => 'foo',
				'limit' => 10,
				'offset' => 0,
				'server' => 'https://lookup.example.io',
				'resultBody' => [
					['federationId' => $fedIDs[0]],
					['federationId' => $fedIDs[1]],
					['federationId' => $fedIDs[2]],
				],
				'expectedResult' => [
					[
						'label' => $fedIDs[0],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[0]
						],
						'extra' => ['federationId' => $fedIDs[0]],
					],
					[
						'label' => $fedIDs[1],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[1]
						],
						'extra' => ['federationId' => $fedIDs[1]],
					],
					[
						'label' => $fedIDs[2],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[2]
						],
						'extra' => ['federationId' => $fedIDs[2]],
					],
				]
			],// GS , Lookup
				false, true
			],
			[[
				'search' => 'foo',
				'limit' => 10,
				'offset' => 0,
				'server' => 'https://lookup.example.io',
				'resultBody' => [
					['federationId' => $fedIDs[0]],
					['federationId' => $fedIDs[1]],
					['federationId' => $fedIDs[2]],
				],
				'expectedResult' => [
					[
						'label' => $fedIDs[0],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[0]
						],
						'extra' => ['federationId' => $fedIDs[0]],
					],
					[
						'label' => $fedIDs[1],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[1]
						],
						'extra' => ['federationId' => $fedIDs[1]],
					],
					[
						'label' => $fedIDs[2],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[2]
						],
						'extra' => ['federationId' => $fedIDs[2]],
					],
				]
			],// GS , Lookup
				false, false
			],
		];
	}

	public function searchDataProvider() {
		$fedIDs = [
			'foo@enceladus.moon',
			'foobar@enceladus.moon',
			'foongus@enceladus.moon',
		];

		return [
			// #0, standard search with results
			[[
				'search' => 'foo',
				'limit' => 10,
				'offset' => 0,
				'server' => 'https://lookup.example.io',
				'resultBody' => [
					['federationId' => $fedIDs[0]],
					['federationId' => $fedIDs[1]],
					['federationId' => $fedIDs[2]],
				],
				'expectedResult' => [
					[
						'label' => $fedIDs[0],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[0]
						],
						'extra' => ['federationId' => $fedIDs[0]],
					],
					[
						'label' => $fedIDs[1],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[1]
						],
						'extra' => ['federationId' => $fedIDs[1]],
					],
					[
						'label' => $fedIDs[2],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $fedIDs[2]
						],
						'extra' => ['federationId' => $fedIDs[2]],
					],
				]
			]],
			// #1, search without results
			[[
				'search' => 'foo',
				'limit' => 10,
				'offset' => 0,
				'server' => 'https://lookup.example.io',
				'resultBody' => [],
				'expectedResult' => [],
			]],
		];
	}
}
