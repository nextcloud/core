<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Federation\Tests\BackgroundJob;

use GuzzleHttp\Exception\ConnectException;
use OCA\Federation\BackgroundJob\GetSharedSecret;
use OCA\Federation\TrustedServers;
use OCA\Files_Sharing\Tests\TestCase;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\OCS\IDiscoveryService;

/**
 * Class GetSharedSecretTest
 *
 * @group DB
 *
 * @package OCA\Federation\Tests\BackgroundJob
 */
class GetSharedSecretTest extends TestCase {

	/** @var \PHPUnit\Framework\MockObject\MockObject|IClient */
	private $httpClient;

	/** @var  \PHPUnit\Framework\MockObject\MockObject|IClientService */
	private $httpClientService;

	/** @var \PHPUnit\Framework\MockObject\MockObject|IJobList */
	private $jobList;

	/** @var \PHPUnit\Framework\MockObject\MockObject|IURLGenerator */
	private $urlGenerator;

	/** @var \PHPUnit\Framework\MockObject\MockObject|TrustedServers  */
	private $trustedServers;

	/** @var \PHPUnit\Framework\MockObject\MockObject|ILogger */
	private $logger;

	/** @var \PHPUnit\Framework\MockObject\MockObject|IResponse */
	private $response;

	/** @var \PHPUnit\Framework\MockObject\MockObject|IDiscoveryService */
	private $discoverService;

	/** @var \PHPUnit\Framework\MockObject\MockObject|ITimeFactory */
	private $timeFactory;

	/** @var GetSharedSecret */
	private $getSharedSecret;

	protected function setUp(): void {
		parent::setUp();

		$this->httpClientService = $this->createMock(IClientService::class);
		$this->httpClient = $this->getMockBuilder(IClient::class)->getMock();
		$this->jobList = $this->getMockBuilder(IJobList::class)->getMock();
		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$this->trustedServers = $this->getMockBuilder(TrustedServers::class)
			->disableOriginalConstructor()->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();
		$this->response = $this->getMockBuilder(IResponse::class)->getMock();
		$this->discoverService = $this->getMockBuilder(IDiscoveryService::class)->getMock();
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$this->discoverService->expects($this->any())->method('discover')->willReturn([]);
		$this->httpClientService->expects($this->any())->method('newClient')->willReturn($this->httpClient);

		$this->getSharedSecret = new GetSharedSecret(
			$this->httpClientService,
			$this->urlGenerator,
			$this->jobList,
			$this->trustedServers,
			$this->logger,
			$this->discoverService,
			$this->timeFactory
		);
	}

	/**
	 * @dataProvider dataTestExecute
	 *
	 * @param bool $isTrustedServer
	 * @param bool $retainBackgroundJob
	 */
	public function testExecute($isTrustedServer, $retainBackgroundJob) {
		/** @var GetSharedSecret |\PHPUnit\Framework\MockObject\MockObject $getSharedSecret */
		$getSharedSecret = $this->getMockBuilder('OCA\Federation\BackgroundJob\GetSharedSecret')
			->setConstructorArgs(
				[
					$this->httpClientService,
					$this->urlGenerator,
					$this->jobList,
					$this->trustedServers,
					$this->logger,
					$this->discoverService,
					$this->timeFactory
				]
			)->setMethods(['parentExecute'])->getMock();
		$this->invokePrivate($getSharedSecret, 'argument', [['url' => 'url', 'token' => 'token']]);

		$this->trustedServers->expects($this->once())->method('isTrustedServer')
			->with('url')->willReturn($isTrustedServer);
		if ($isTrustedServer) {
			$getSharedSecret->expects($this->once())->method('parentExecute');
		} else {
			$getSharedSecret->expects($this->never())->method('parentExecute');
		}
		$this->invokePrivate($getSharedSecret, 'retainJob', [$retainBackgroundJob]);
		$this->jobList->expects($this->once())->method('remove');

		$this->timeFactory->method('getTime')->willReturn(42);

		if ($retainBackgroundJob) {
			$this->jobList->expects($this->once())
				->method('add')
				->with(
					GetSharedSecret::class,
					[
						'url' => 'url',
						'token' => 'token',
						'created' => 42,
					]
				);
		} else {
			$this->jobList->expects($this->never())->method('add');
		}

		$getSharedSecret->execute($this->jobList);
	}

	public function dataTestExecute() {
		return [
			[true, true],
			[true, false],
			[false, false],
		];
	}

	/**
	 * @dataProvider dataTestRun
	 *
	 * @param int $statusCode
	 */
	public function testRun($statusCode) {
		$target = 'targetURL';
		$source = 'sourceURL';
		$token = 'token';

		$argument = ['url' => $target, 'token' => $token];

		$this->timeFactory->method('getTime')
			->willReturn(42);

		$this->urlGenerator->expects($this->once())->method('getAbsoluteURL')->with('/')
			->willReturn($source);
		$this->httpClient->expects($this->once())->method('get')
			->with(
				$target . '/ocs/v2.php/apps/federation/api/v1/shared-secret',
				[
					'query' =>
						[
							'url' => $source,
							'token' => $token,
							'format' => 'json',
						],
					'timeout' => 3,
					'connect_timeout' => 3,
				]
			)->willReturn($this->response);

		$this->response->expects($this->once())->method('getStatusCode')
			->willReturn($statusCode);

		if ($statusCode === Http::STATUS_OK) {
			$this->response->expects($this->once())->method('getBody')
				->willReturn('{"ocs":{"data":{"sharedSecret":"secret"}}}');
			$this->trustedServers->expects($this->once())->method('addSharedSecret')
				->with($target, 'secret');
		} else {
			$this->trustedServers->expects($this->never())->method('addSharedSecret');
		}

		$this->invokePrivate($this->getSharedSecret, 'run', [$argument]);
		if (
			$statusCode !== Http::STATUS_OK
			&& $statusCode !== Http::STATUS_FORBIDDEN
		) {
			$this->assertTrue($this->invokePrivate($this->getSharedSecret, 'retainJob'));
		} else {
			$this->assertFalse($this->invokePrivate($this->getSharedSecret, 'retainJob'));
		}
	}

	public function dataTestRun() {
		return [
			[Http::STATUS_OK],
			[Http::STATUS_FORBIDDEN],
			[Http::STATUS_CONFLICT],
		];
	}

	public function testRunExpired() {
		$target = 'targetURL';
		$source = 'sourceURL';
		$token = 'token';
		$created = 42;

		$argument = [
			'url' => $target,
			'token' => $token,
			'created' => $created,
		];

		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('/')
			->willReturn($source);

		$this->timeFactory->method('getTime')
			->willReturn($created + 2592000 + 1);

		$this->trustedServers->expects($this->once())
			->method('setServerStatus')
			->with(
				$target,
				TrustedServers::STATUS_FAILURE
			);

		$this->invokePrivate($this->getSharedSecret, 'run', [$argument]);
	}

	public function testRunConnectionError() {
		$target = 'targetURL';
		$source = 'sourceURL';
		$token = 'token';

		$argument = ['url' => $target, 'token' => $token];

		$this->timeFactory->method('getTime')
			->willReturn(42);

		$this->urlGenerator
			->expects($this->once())
			->method('getAbsoluteURL')
			->with('/')
			->willReturn($source);
		$this->httpClient->expects($this->once())->method('get')
			->with(
				$target . '/ocs/v2.php/apps/federation/api/v1/shared-secret',
				[
					'query' =>
						[
							'url' => $source,
							'token' => $token,
							'format' => 'json',
						],
					'timeout' => 3,
					'connect_timeout' => 3,
				]
			)->willThrowException($this->createMock(ConnectException::class));

		$this->trustedServers->expects($this->never())->method('addSharedSecret');

		$this->invokePrivate($this->getSharedSecret, 'run', [$argument]);

		$this->assertTrue($this->invokePrivate($this->getSharedSecret, 'retainJob'));
	}
}
