<?php

declare(strict_types=1);

/*
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace Tests\Core\Controller;

use OC\Core\Controller\WellKnownController;
use OC\Http\WellKnown\RequestManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\WellKnown\IResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class WellKnownControllerTest extends TestCase {

	/** @var IRequest|MockObject */
	private $request;

	/** @var RequestManager|MockObject */
	private $manager;

	/** @var WellKnownController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->manager = $this->createMock(RequestManager::class);

		$this->controller = new WellKnownController(
			$this->request,
			$this->manager,
		);
	}

	public function testHandleNotProcessed(): void {
		$httpResponse = $this->controller->handle("nodeinfo");

		self::assertInstanceOf(JSONResponse::class, $httpResponse);
		self::assertArrayHasKey('X-NEXTCLOUD-WELL-KNOWN', $httpResponse->getHeaders());
	}

	public function testHandle(): void {
		$response = $this->createMock(IResponse::class);
		$jsonResponse = $this->createMock(JSONResponse::class);
		$response->expects(self::once())
			->method('toHttpResponse')
			->willReturn($jsonResponse);
		$this->manager->expects(self::once())
			->method('process')
			->with(
				"nodeinfo",
				$this->request
			)->willReturn($response);
		$jsonResponse->expects(self::once())
			->method('addHeader')
			->willReturnSelf();

		$httpResponse = $this->controller->handle("nodeinfo");

		self::assertInstanceOf(JSONResponse::class, $httpResponse);
	}
}
