<?php

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Tests\Core\Controller;

use OC\Core\Controller\CSRFTokenController;
use OC\Security\CSRF\CsrfToken;
use OC\Security\CSRF\CsrfTokenManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit_Framework_MockObject_MockObject;
use Test\TestCase;

class CSRFTokenControllerTest extends TestCase {

	/** @var CSRFTokenController */
	private $controller;

	/** @var IRequest|PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var CsrfTokenManager|PHPUnit_Framework_MockObject_MockObject */
	private $tokenManager;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->tokenManager = $this->createMock(CsrfTokenManager::class);

		$this->controller = new CSRFTokenController('core', $this->request,
			$this->tokenManager);
	}

	public function testGetToken(): void {
		$this->request->method('passesStrictCookieCheck')->willReturn(true);

		$token = $this->createMock(CsrfToken::class);
		$this->tokenManager->method('getToken')->willReturn($token);
		$token->method('getEncryptedValue')->willReturn('toktok123');

		$response = $this->controller->index();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals([
			'token' => 'toktok123'
		], $response->getData());
	}

	public function testGetTokenNoStrictSameSiteCookie(): void {
		$this->request->method('passesStrictCookieCheck')->willReturn(false);

		$response = $this->controller->index();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

}
