<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Federation\Tests;

use OCA\Federation\DbHandler;
use OCA\Federation\TrustedServers;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Security\ISecureRandom;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class TrustedServersTest extends TestCase {

	/** @var \PHPUnit\Framework\MockObject\MockObject | TrustedServers */
	private $trustedServers;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | DbHandler */
	private $dbHandler;

	/** @var \PHPUnit\Framework\MockObject\MockObject | IClientService */
	private $httpClientService;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | IClient */
	private $httpClient;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | IResponse */
	private $response;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | ILogger */
	private $logger;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | IJobList */
	private $jobList;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | ISecureRandom */
	private $secureRandom;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | IConfig */
	private $config;

	/** @var  \PHPUnit\Framework\MockObject\MockObject | EventDispatcherInterface */
	private $dispatcher;

	/** @var \PHPUnit\Framework\MockObject\MockObject|ITimeFactory */
	private $timeFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->dbHandler = $this->getMockBuilder(DbHandler::class)
			->disableOriginalConstructor()->getMock();
		$this->dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)
			->disableOriginalConstructor()->getMock();
		$this->httpClientService = $this->getMockBuilder(IClientService::class)->getMock();
		$this->httpClient = $this->getMockBuilder(IClient::class)->getMock();
		$this->response = $this->getMockBuilder(IResponse::class)->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();
		$this->jobList = $this->getMockBuilder(IJobList::class)->getMock();
		$this->secureRandom = $this->getMockBuilder(ISecureRandom::class)->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$this->trustedServers = new TrustedServers(
			$this->dbHandler,
			$this->httpClientService,
			$this->logger,
			$this->jobList,
			$this->secureRandom,
			$this->config,
			$this->dispatcher,
			$this->timeFactory
		);
	}

	/**
	 * @dataProvider dataTrueFalse
	 *
	 * @param bool $success
	 */
	public function testAddServer($success) {
		/** @var \PHPUnit\Framework\MockObject\MockObject|TrustedServers $trustedServers */
		$trustedServers = $this->getMockBuilder('OCA\Federation\TrustedServers')
			->setConstructorArgs(
				[
					$this->dbHandler,
					$this->httpClientService,
					$this->logger,
					$this->jobList,
					$this->secureRandom,
					$this->config,
					$this->dispatcher,
					$this->timeFactory
				]
			)
			->setMethods(['normalizeUrl', 'updateProtocol'])
			->getMock();
		$trustedServers->expects($this->once())->method('updateProtocol')
				->with('url')->willReturn('https://url');
		$this->timeFactory->method('getTime')
			->willReturn(1234567);
		$this->dbHandler->expects($this->once())->method('addServer')->with('https://url')
			->willReturn($success);

		if ($success) {
			$this->secureRandom->expects($this->once())->method('generate')
				->willReturn('token');
			$this->dbHandler->expects($this->once())->method('addToken')->with('https://url', 'token');
			$this->jobList->expects($this->once())->method('add')
				->with('OCA\Federation\BackgroundJob\RequestSharedSecret',
						['url' => 'https://url', 'token' => 'token', 'created' => 1234567]);
		} else {
			$this->jobList->expects($this->never())->method('add');
		}

		$this->assertSame($success,
			$trustedServers->addServer('url')
		);
	}

	public function dataTrueFalse() {
		return [
			[true],
			[false]
		];
	}

	/**
	 * @dataProvider dataTrueFalse
	 *
	 * @param bool $status
	 */
	public function testSetAutoAddServers($status) {
		if ($status) {
			$this->config->expects($this->once())->method('setAppValue')
				->with('federation', 'autoAddServers', '1');
		} else {
			$this->config->expects($this->once())->method('setAppValue')
				->with('federation', 'autoAddServers', '0');
		}

		$this->trustedServers->setAutoAddServers($status);
	}

	/**
	 * @dataProvider dataTestGetAutoAddServers
	 *
	 * @param string $status
	 * @param bool $expected
	 */
	public function testGetAutoAddServers($status, $expected) {
		$this->config->expects($this->once())->method('getAppValue')
			->with('federation', 'autoAddServers', '0')->willReturn($status);

		$this->assertSame($expected,
			$this->trustedServers->getAutoAddServers()
		);
	}

	public function dataTestGetAutoAddServers() {
		return [
			['1', true],
			['0', false]
		];
	}

	public function testAddSharedSecret() {
		$this->dbHandler->expects($this->once())->method('addSharedSecret')
			->with('url', 'secret');
		$this->trustedServers->addSharedSecret('url', 'secret');
	}

	public function testGetSharedSecret() {
		$this->dbHandler->expects($this->once())->method('getSharedSecret')
			->with('url')->willReturn(true);
		$this->assertTrue(
			$this->trustedServers->getSharedSecret('url')
		);
	}

	public function testRemoveServer() {
		$id = 42;
		$server = ['url_hash' => 'url_hash'];
		$this->dbHandler->expects($this->once())->method('removeServer')->with($id);
		$this->dbHandler->expects($this->once())->method('getServerById')->with($id)
			->willReturn($server);
		$this->dispatcher->expects($this->once())->method('dispatch')
			->willReturnCallback(
				function ($eventId, $event) {
					$this->assertSame($eventId, 'OCP\Federation\TrustedServerEvent::remove');
					$this->assertInstanceOf('Symfony\Component\EventDispatcher\GenericEvent', $event);
					/** @var \Symfony\Component\EventDispatcher\GenericEvent $event */
					$this->assertSame('url_hash', $event->getSubject());
				}
			);
		$this->trustedServers->removeServer($id);
	}

	public function testGetServers() {
		$this->dbHandler->expects($this->once())->method('getAllServer')->willReturn(['servers']);

		$this->assertEquals(
			['servers'],
			$this->trustedServers->getServers()
		);
	}


	public function testIsTrustedServer() {
		$this->dbHandler->expects($this->once())->method('serverExists')->with('url')
			->willReturn(true);

		$this->assertTrue(
			$this->trustedServers->isTrustedServer('url')
		);
	}

	public function testSetServerStatus() {
		$this->dbHandler->expects($this->once())->method('setServerStatus')
			->with('url', 'status');
		$this->trustedServers->setServerStatus('url', 'status');
	}

	public function testGetServerStatus() {
		$this->dbHandler->expects($this->once())->method('getServerStatus')
			->with('url')->willReturn(true);
		$this->assertTrue(
			$this->trustedServers->getServerStatus('url')
		);
	}

	/**
	 * @dataProvider dataTestIsOwnCloudServer
	 *
	 * @param int $statusCode
	 * @param bool $isValidOwnCloudVersion
	 * @param bool $expected
	 */
	public function testIsOwnCloudServer($statusCode, $isValidOwnCloudVersion, $expected) {
		$server = 'server1';

		/** @var \PHPUnit\Framework\MockObject\MockObject | TrustedServers $trustedServers */
		$trustedServers = $this->getMockBuilder('OCA\Federation\TrustedServers')
			->setConstructorArgs(
				[
					$this->dbHandler,
					$this->httpClientService,
					$this->logger,
					$this->jobList,
					$this->secureRandom,
					$this->config,
					$this->dispatcher,
					$this->timeFactory
				]
			)
			->setMethods(['checkOwnCloudVersion'])
			->getMock();

		$this->httpClientService->expects($this->once())->method('newClient')
			->willReturn($this->httpClient);

		$this->httpClient->expects($this->once())->method('get')->with($server . '/status.php')
			->willReturn($this->response);

		$this->response->expects($this->once())->method('getStatusCode')
			->willReturn($statusCode);

		if ($statusCode === 200) {
			$trustedServers->expects($this->once())->method('checkOwnCloudVersion')
				->willReturn($isValidOwnCloudVersion);
		} else {
			$trustedServers->expects($this->never())->method('checkOwnCloudVersion');
		}

		$this->assertSame($expected,
			$trustedServers->isOwnCloudServer($server)
		);
	}

	public function dataTestIsOwnCloudServer() {
		return [
			[200, true, true],
			[200, false, false],
			[404, true, false],
		];
	}

	/**
	 * @expectedExceptionMessage simulated exception
	 */
	public function testIsOwnCloudServerFail() {
		$server = 'server1';

		$this->httpClientService->expects($this->once())->method('newClient')
			->willReturn($this->httpClient);

		$this->httpClient->expects($this->once())->method('get')->with($server . '/status.php')
			->willReturnCallback(function () {
				throw new \Exception('simulated exception');
			});

		$this->assertFalse($this->trustedServers->isOwnCloudServer($server));
	}

	/**
	 * @dataProvider dataTestCheckOwnCloudVersion
	 */
	public function testCheckOwnCloudVersion($status) {
		$this->assertTrue($this->invokePrivate($this->trustedServers, 'checkOwnCloudVersion', [$status]));
	}

	public function dataTestCheckOwnCloudVersion() {
		return [
			['{"version":"9.0.0"}'],
			['{"version":"9.1.0"}']
		];
	}

	/**
	 * @dataProvider dataTestCheckOwnCloudVersionTooLow
	 */
	public function testCheckOwnCloudVersionTooLow($status) {
		$this->expectException(\OC\HintException::class);
		$this->expectExceptionMessage('Remote server version is too low. 9.0 is required.');

		$this->invokePrivate($this->trustedServers, 'checkOwnCloudVersion', [$status]);
	}

	public function dataTestCheckOwnCloudVersionTooLow() {
		return [
			['{"version":"8.2.3"}'],
		];
	}

	/**
	 * @dataProvider dataTestUpdateProtocol
	 * @param string $url
	 * @param string $expected
	 */
	public function testUpdateProtocol($url, $expected) {
		$this->assertSame($expected,
			$this->invokePrivate($this->trustedServers, 'updateProtocol', [$url])
		);
	}

	public function dataTestUpdateProtocol() {
		return [
			['http://owncloud.org', 'http://owncloud.org'],
			['https://owncloud.org', 'https://owncloud.org'],
			['owncloud.org', 'https://owncloud.org'],
			['httpserver', 'https://httpserver'],
		];
	}
}
