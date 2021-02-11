<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\FederatedFileSharing\Tests;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Notifications;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Http\Client\IClientService;
use OCP\OCS\IDiscoveryService;

class NotificationsTest extends \Test\TestCase {

	/** @var  AddressHandler | \PHPUnit\Framework\MockObject\MockObject */
	private $addressHandler;

	/** @var  IClientService | \PHPUnit\Framework\MockObject\MockObject*/
	private $httpClientService;

	/** @var  IDiscoveryService | \PHPUnit\Framework\MockObject\MockObject */
	private $discoveryService;

	/** @var  IJobList | \PHPUnit\Framework\MockObject\MockObject */
	private $jobList;

	/** @var ICloudFederationProviderManager|\PHPUnit\Framework\MockObject\MockObject */
	private $cloudFederationProviderManager;

	/** @var ICloudFederationFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $cloudFederationFactory;

	/** @var IEventDispatcher|\PHPUnit\Framework\MockObject\MockObject */
	private $eventDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->jobList = $this->getMockBuilder('OCP\BackgroundJob\IJobList')->getMock();
		$this->discoveryService = $this->getMockBuilder(IDiscoveryService::class)->getMock();
		$this->httpClientService = $this->getMockBuilder('OCP\Http\Client\IClientService')->getMock();
		$this->addressHandler = $this->getMockBuilder('OCA\FederatedFileSharing\AddressHandler')
			->disableOriginalConstructor()->getMock();
		$this->cloudFederationProviderManager = $this->createMock(ICloudFederationProviderManager::class);
		$this->cloudFederationFactory = $this->createMock(ICloudFederationFactory::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
	}

	/**
	 * get instance of Notifications class
	 *
	 * @param array $mockedMethods methods which should be mocked
	 * @return Notifications | \PHPUnit\Framework\MockObject\MockObject
	 */
	private function getInstance(array $mockedMethods = []) {
		if (empty($mockedMethods)) {
			$instance = new Notifications(
				$this->addressHandler,
				$this->httpClientService,
				$this->discoveryService,
				$this->jobList,
				$this->cloudFederationProviderManager,
				$this->cloudFederationFactory,
				$this->eventDispatcher
			);
		} else {
			$instance = $this->getMockBuilder('OCA\FederatedFileSharing\Notifications')
				->setConstructorArgs(
					[
						$this->addressHandler,
						$this->httpClientService,
						$this->discoveryService,
						$this->jobList,
						$this->cloudFederationProviderManager,
						$this->cloudFederationFactory,
						$this->eventDispatcher
					]
				)->setMethods($mockedMethods)->getMock();
		}

		return $instance;
	}


	/**
	 * @dataProvider dataTestSendUpdateToRemote
	 *
	 * @param int $try
	 * @param array $httpRequestResult
	 * @param bool $expected
	 */
	public function testSendUpdateToRemote($try, $httpRequestResult, $expected) {
		$remote = 'http://remote';
		$id = 42;
		$timestamp = 63576;
		$token = 'token';
		$action = 'unshare';
		$instance = $this->getInstance(['tryHttpPostToShareEndpoint', 'getTimestamp']);

		$instance->expects($this->any())->method('getTimestamp')->willReturn($timestamp);

		$instance->expects($this->once())->method('tryHttpPostToShareEndpoint')
			->with($remote, '/'.$id.'/unshare', ['token' => $token, 'data1Key' => 'data1Value', 'remoteId' => $id], $action)
			->willReturn($httpRequestResult);

		// only add background job on first try
		if ($try === 0 && $expected === false) {
			$this->jobList->expects($this->once())->method('add')
				->with(
					'OCA\FederatedFileSharing\BackgroundJob\RetryJob',
					[
						'remote' => $remote,
						'remoteId' => $id,
						'action' => 'unshare',
						'data' => json_encode(['data1Key' => 'data1Value']),
						'token' => $token,
						'try' => $try,
						'lastRun' => $timestamp
					]
				);
		} else {
			$this->jobList->expects($this->never())->method('add');
		}

		$this->assertSame($expected,
			$instance->sendUpdateToRemote($remote, $id, $token, $action, ['data1Key' => 'data1Value'], $try)
		);
	}


	public function dataTestSendUpdateToRemote() {
		return [
			// test if background job is added correctly
			[0, ['success' => true, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 200]]])], true],
			[1, ['success' => true, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 200]]])], true],
			[0, ['success' => false, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 200]]])], false],
			[1, ['success' => false, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 200]]])], false],
			// test all combinations of 'statuscode' and 'success'
			[0, ['success' => true, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 200]]])], true],
			[0, ['success' => true, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 100]]])], true],
			[0, ['success' => true, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 400]]])], false],
			[0, ['success' => false, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 200]]])], false],
			[0, ['success' => false, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 100]]])], false],
			[0, ['success' => false, 'result' => json_encode(['ocs' => ['meta' => ['statuscode' => 400]]])], false],
		];
	}
}
