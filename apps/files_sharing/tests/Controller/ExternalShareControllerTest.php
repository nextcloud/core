<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Files_Sharing\Tests\Controllers;

use OCA\Files_Sharing\Controller\ExternalSharesController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\Client\IClientService;
use OCP\IRequest;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\IClient;
use OCA\Files_Sharing\External\Manager;

/**
 * Class ExternalShareControllerTest
 *
 * @package OCA\Files_Sharing\Controllers
 */
class ExternalShareControllerTest extends \Test\TestCase {
	/** @var IRequest */
	private $request;
	/** @var \OCA\Files_Sharing\External\Manager */
	private $externalManager;
	/** @var IClientService */
	private $clientService;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->externalManager = $this->createMock(Manager::class);
		$this->clientService = $this->createMock(IClientService::class);
	}

	/**
	 * @return ExternalSharesController
	 */
	public function getExternalShareController() {
		return new ExternalSharesController(
			'files_sharing',
			$this->request,
			$this->externalManager,
			$this->clientService
		);
	}

	public function testIndex() {
		$this->externalManager
			->expects($this->once())
			->method('getOpenShares')
			->willReturn(['MyDummyArray']);

		$this->assertEquals(new JSONResponse(['MyDummyArray']), $this->getExternalShareController()->index());
	}

	public function testCreate() {
		$this->externalManager
			->expects($this->once())
			->method('acceptShare')
			->with(4);

		$this->assertEquals(new JSONResponse(), $this->getExternalShareController()->create(4));
	}

	public function testDestroy() {
		$this->externalManager
			->expects($this->once())
			->method('declineShare')
			->with(4);

		$this->assertEquals(new JSONResponse(), $this->getExternalShareController()->destroy(4));
	}

	public function testRemoteWithValidHttps() {
		$client = $this->createMock(IClient::class);
		$response = $this->createMock(IResponse::class);
		$response
			->expects($this->exactly(2))
			->method('getBody')
			->willReturnOnConsecutiveCalls(
				'Certainly not a JSON string',
				'{"installed":true,"maintenance":false,"version":"8.1.0.8","versionstring":"8.1.0","edition":""}'
			);
		$client
			->expects($this->any())
			->method('get')
			->willReturn($response);

		$this->clientService
			->expects($this->exactly(2))
			->method('newClient')
			->willReturn($client);

		$this->assertEquals(new DataResponse('https'), $this->getExternalShareController()->testRemote('nextcloud.com'));
	}

	public function testRemoteWithWorkingHttp() {
		$client = $this->createMock(IClient::class);
		$response = $this->createMock(IResponse::class);
		$client
			->method('get')
			->willReturn($response);
		$response
			->expects($this->exactly(5))
			->method('getBody')
			->willReturnOnConsecutiveCalls(
				'Certainly not a JSON string',
				'Certainly not a JSON string',
				'Certainly not a JSON string',
				'Certainly not a JSON string',
				'{"installed":true,"maintenance":false,"version":"8.1.0.8","versionstring":"8.1.0","edition":""}'
			);
		$this->clientService
			->expects($this->exactly(5))
			->method('newClient')
			->willReturn($client);

		$this->assertEquals(new DataResponse('http'), $this->getExternalShareController()->testRemote('nextcloud.com'));
	}

	public function testRemoteWithInvalidRemote() {
		$client = $this->createMock(IClient::class);
		$response = $this->createMock(IResponse::class);
		$client
			->expects($this->exactly(6))
			->method('get')
			->willReturn($response);
		$response
			->expects($this->exactly(6))
			->method('getBody')
			->willReturn('Certainly not a JSON string');
		$this->clientService
			->expects($this->exactly(6))
			->method('newClient')
			->willReturn($client);

		$this->assertEquals(new DataResponse(false), $this->getExternalShareController()->testRemote('nextcloud.com'));
	}

	public function dataRemoteWithInvalidRemoteURLs(): array {
		return [
			['nextcloud.com?query'],
			['nextcloud.com/#anchor'],
		];
	}

	/**
	 * @dataProvider dataRemoteWithInvalidRemoteURLs
	 * @param string $remote
	 */
	public function testRemoteWithInvalidRemoteURLs(string $remote) {
		$this->clientService
			->expects($this->never())
			->method('newClient');

		$this->assertEquals(new DataResponse(false), $this->getExternalShareController()->testRemote($remote));
	}
}
