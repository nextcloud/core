<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace Test\AppFramework\Middleware\Security;

use OC\AppFramework\Middleware\Security\CSPMiddleware;
use OC\Security\CSP\ContentSecurityPolicy;
use OC\Security\CSP\ContentSecurityPolicyManager;
use OC\Security\CSP\ContentSecurityPolicyNonceManager;
use OC\Security\CSRF\CsrfToken;
use OC\Security\CSRF\CsrfTokenManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\AppFramework\Http\Response;
use PHPUnit\Framework\MockObject\MockObject;

class CSPMiddlewareTest extends \Test\TestCase {

	/** @var CSPMiddleware|MockObject */
	private $middleware;
	/** @var Controller|MockObject */
	private $controller;
	/** @var ContentSecurityPolicyManager|MockObject */
	private $contentSecurityPolicyManager;
	/** @var CsrfTokenManager|MockObject */
	private $csrfTokenManager;
	/** @var ContentSecurityPolicyNonceManager|MockObject */
	private $cspNonceManager;

	protected function setUp(): void {
		parent::setUp();

		$this->controller = $this->createMock(Controller::class);
		$this->contentSecurityPolicyManager = $this->createMock(ContentSecurityPolicyManager::class);
		$this->csrfTokenManager = $this->createMock(CsrfTokenManager::class);
		$this->cspNonceManager = $this->createMock(ContentSecurityPolicyNonceManager::class);
		$this->middleware = new CSPMiddleware(
			$this->contentSecurityPolicyManager,
			$this->cspNonceManager,
			$this->csrfTokenManager
		);
	}

	public function testAfterController() {
		$this->cspNonceManager
			->expects($this->once())
			->method('browserSupportsCspV3')
			->willReturn(false);
		$response = $this->createMock(Response::class);
		$defaultPolicy = new ContentSecurityPolicy();
		$defaultPolicy->addAllowedImageDomain('defaultpolicy');
		$currentPolicy = new ContentSecurityPolicy();
		$currentPolicy->addAllowedConnectDomain('currentPolicy');
		$mergedPolicy = new ContentSecurityPolicy();
		$mergedPolicy->addAllowedMediaDomain('mergedPolicy');
		$response
			->expects($this->exactly(2))
			->method('getContentSecurityPolicy')
			->willReturn($currentPolicy);
		$this->contentSecurityPolicyManager
			->expects($this->once())
			->method('getDefaultPolicy')
			->willReturn($defaultPolicy);
		$this->contentSecurityPolicyManager
			->expects($this->once())
			->method('mergePolicies')
			->with($defaultPolicy, $currentPolicy)
			->willReturn($mergedPolicy);
		$response->expects($this->once())
			->method('setContentSecurityPolicy')
			->with($mergedPolicy);

		$this->middleware->afterController($this->controller, 'test', $response);
	}

	public function testAfterControllerEmptyCSP() {
		$response = $this->createMock(Response::class);
		$emptyPolicy = new EmptyContentSecurityPolicy();
		$response->expects($this->any())
			->method('getContentSecurityPolicy')
			->willReturn($emptyPolicy);
		$response->expects($this->never())
			->method('setContentSecurityPolicy');

		$this->middleware->afterController($this->controller, 'test', $response);
	}

	public function testAfterControllerWithContentSecurityPolicy3Support() {
		$this->cspNonceManager
			->expects($this->once())
			->method('browserSupportsCspV3')
			->willReturn(true);
		$token = $this->createMock(CsrfToken::class);
		$token
			->expects($this->once())
			->method('getEncryptedValue')
			->willReturn('MyEncryptedToken');
		$this->csrfTokenManager
			->expects($this->once())
			->method('getToken')
			->willReturn($token);
		$response = $this->createMock(Response::class);
		$defaultPolicy = new ContentSecurityPolicy();
		$defaultPolicy->addAllowedImageDomain('defaultpolicy');
		$currentPolicy = new ContentSecurityPolicy();
		$currentPolicy->addAllowedConnectDomain('currentPolicy');
		$mergedPolicy = new ContentSecurityPolicy();
		$mergedPolicy->addAllowedMediaDomain('mergedPolicy');
		$response
			->expects($this->exactly(2))
			->method('getContentSecurityPolicy')
			->willReturn($currentPolicy);
		$this->contentSecurityPolicyManager
			->expects($this->once())
			->method('getDefaultPolicy')
			->willReturn($defaultPolicy);
		$this->contentSecurityPolicyManager
			->expects($this->once())
			->method('mergePolicies')
			->with($defaultPolicy, $currentPolicy)
			->willReturn($mergedPolicy);
		$response->expects($this->once())
			->method('setContentSecurityPolicy')
			->with($mergedPolicy);

		$this->assertEquals($response, $this->middleware->afterController($this->controller, 'test', $response));
	}
}
