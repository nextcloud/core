<?php
/**
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\AppFramework\Middleware\Security;

use OC\AppFramework\Http;
use OC\AppFramework\Http\Request;
use OC\AppFramework\Middleware\Security\Exceptions\AppNotEnabledException;
use OC\AppFramework\Middleware\Security\Exceptions\CrossSiteRequestForgeryException;
use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;
use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OC\AppFramework\Middleware\Security\Exceptions\SecurityException;
use OC\Appframework\Middleware\Security\Exceptions\StrictCookieMissingException;
use OC\AppFramework\Middleware\Security\SecurityMiddleware;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class SecurityMiddlewareTest extends \Test\TestCase {

	/** @var SecurityMiddleware|\PHPUnit\Framework\MockObject\MockObject */
	private $middleware;
	/** @var Controller|\PHPUnit\Framework\MockObject\MockObject */
	private $controller;
	/** @var SecurityException */
	private $secException;
	/** @var SecurityException */
	private $secAjaxException;
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var ControllerMethodReflector */
	private $reader;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var INavigationManager|\PHPUnit\Framework\MockObject\MockObject */
	private $navigationManager;
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var IAppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	protected function setUp(): void {
		parent::setUp();

		$this->controller = $this->createMock(Controller::class);
		$this->reader = new ControllerMethodReflector();
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->navigationManager = $this->createMock(INavigationManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->middleware = $this->getMiddleware(true, true, false);
		$this->secException = new SecurityException('hey', false);
		$this->secAjaxException = new SecurityException('hey', true);
	}

	private function getMiddleware(bool $isLoggedIn, bool $isAdminUser, bool $isSubAdmin, bool $isAppEnabledForUser = true): SecurityMiddleware {
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->expects($this->any())
			->method('isEnabledForUser')
			->willReturn($isAppEnabledForUser);

		return new SecurityMiddleware(
			$this->request,
			$this->reader,
			$this->navigationManager,
			$this->urlGenerator,
			$this->logger,
			'files',
			$isLoggedIn,
			$isAdminUser,
			$isSubAdmin,
			$this->appManager,
			$this->l10n
		);
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function testSetNavigationEntry() {
		$this->navigationManager->expects($this->once())
			->method('setActiveEntry')
			->with($this->equalTo('files'));

		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController($this->controller, __FUNCTION__);
	}


	/**
	 * @param string $method
	 * @param string $test
	 */
	private function ajaxExceptionStatus($method, $test, $status) {
		$isLoggedIn = false;
		$isAdminUser = false;

		// isAdminUser requires isLoggedIn call to return true
		if ($test === 'isAdminUser') {
			$isLoggedIn = true;
		}

		$sec = $this->getMiddleware($isLoggedIn, $isAdminUser, false);

		try {
			$this->reader->reflect(__CLASS__, $method);
			$sec->beforeController($this->controller, $method);
		} catch (SecurityException $ex) {
			$this->assertEquals($status, $ex->getCode());
		}

		// add assertion if everything should work fine otherwise phpunit will
		// complain
		if ($status === 0) {
			$this->addToAssertionCount(1);
		}
	}

	public function testAjaxStatusLoggedInCheck() {
		$this->ajaxExceptionStatus(
			__FUNCTION__,
			'isLoggedIn',
			Http::STATUS_UNAUTHORIZED
		);
	}

	/**
	 * @NoCSRFRequired
	 */
	public function testAjaxNotAdminCheck() {
		$this->ajaxExceptionStatus(
			__FUNCTION__,
			'isAdminUser',
			Http::STATUS_FORBIDDEN
		);
	}

	/**
	 * @PublicPage
	 */
	public function testAjaxStatusCSRFCheck() {
		$this->ajaxExceptionStatus(
			__FUNCTION__,
			'passesCSRFCheck',
			Http::STATUS_PRECONDITION_FAILED
		);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function testAjaxStatusAllGood() {
		$this->ajaxExceptionStatus(
			__FUNCTION__,
			'isLoggedIn',
			0
		);
		$this->ajaxExceptionStatus(
			__FUNCTION__,
			'isAdminUser',
			0
		);
		$this->ajaxExceptionStatus(
			__FUNCTION__,
			'passesCSRFCheck',
			0
		);
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function testNoChecks() {
		$this->request->expects($this->never())
			->method('passesCSRFCheck')
			->willReturn(false);

		$sec = $this->getMiddleware(false, false, false);

		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$sec->beforeController($this->controller, __FUNCTION__);
	}


	/**
	 * @param string $method
	 * @param string $expects
	 */
	private function securityCheck($method, $expects, $shouldFail = false) {
		// admin check requires login
		if ($expects === 'isAdminUser') {
			$isLoggedIn = true;
			$isAdminUser = !$shouldFail;
		} else {
			$isLoggedIn = !$shouldFail;
			$isAdminUser = false;
		}

		$sec = $this->getMiddleware($isLoggedIn, $isAdminUser, false);

		if ($shouldFail) {
			$this->expectException(SecurityException::class);
		} else {
			$this->addToAssertionCount(1);
		}

		$this->reader->reflect(__CLASS__, $method);
		$sec->beforeController($this->controller, $method);
	}


	/**
	 * @PublicPage
	 */
	public function testCsrfCheck() {
		$this->expectException(\OC\AppFramework\Middleware\Security\Exceptions\CrossSiteRequestForgeryException::class);

		$this->request->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(false);
		$this->request->expects($this->once())
			->method('passesStrictCookieCheck')
			->willReturn(true);
		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController($this->controller, __FUNCTION__);
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function testNoCsrfCheck() {
		$this->request->expects($this->never())
			->method('passesCSRFCheck')
			->willReturn(false);

		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController($this->controller, __FUNCTION__);
	}

	/**
	 * @PublicPage
	 */
	public function testPassesCsrfCheck() {
		$this->request->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(true);
		$this->request->expects($this->once())
			->method('passesStrictCookieCheck')
			->willReturn(true);

		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController($this->controller, __FUNCTION__);
	}

	/**
	 * @PublicPage
	 */
	public function testFailCsrfCheck() {
		$this->expectException(\OC\AppFramework\Middleware\Security\Exceptions\CrossSiteRequestForgeryException::class);

		$this->request->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(false);
		$this->request->expects($this->once())
			->method('passesStrictCookieCheck')
			->willReturn(true);

		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController($this->controller, __FUNCTION__);
	}

	/**
	 * @PublicPage
	 * @StrictCookieRequired
	 */
	public function testStrictCookieRequiredCheck() {
		$this->expectException(\OC\Appframework\Middleware\Security\Exceptions\StrictCookieMissingException::class);

		$this->request->expects($this->never())
			->method('passesCSRFCheck');
		$this->request->expects($this->once())
			->method('passesStrictCookieCheck')
			->willReturn(false);

		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController($this->controller, __FUNCTION__);
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function testNoStrictCookieRequiredCheck() {
		$this->request->expects($this->never())
			->method('passesStrictCookieCheck')
			->willReturn(false);

		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController($this->controller, __FUNCTION__);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @StrictCookieRequired
	 */
	public function testPassesStrictCookieRequiredCheck() {
		$this->request
			->expects($this->once())
			->method('passesStrictCookieCheck')
			->willReturn(true);

		$this->reader->reflect(__CLASS__, __FUNCTION__);
		$this->middleware->beforeController($this->controller, __FUNCTION__);
	}

	public function dataCsrfOcsController() {
		$controller = $this->getMockBuilder('OCP\AppFramework\Controller')
			->disableOriginalConstructor()
			->getMock();
		$ocsController = $this->getMockBuilder('OCP\AppFramework\OCSController')
			->disableOriginalConstructor()
			->getMock();

		return [
			[$controller, false, false, true],
			[$controller, false,  true, true],
			[$controller,  true, false, true],
			[$controller,  true,  true, true],

			[$ocsController, false, false,  true],
			[$ocsController, false,  true, false],
			[$ocsController,  true, false, false],
			[$ocsController,  true,  true, false],
		];
	}

	/**
	 * @dataProvider dataCsrfOcsController
	 * @param Controller $controller
	 * @param bool $hasOcsApiHeader
	 * @param bool $hasBearerAuth
	 * @param bool $exception
	 */
	public function testCsrfOcsController(Controller $controller, bool $hasOcsApiHeader, bool $hasBearerAuth, bool $exception) {
		$this->request
			->method('getHeader')
			->willReturnCallback(function ($header) use ($hasOcsApiHeader, $hasBearerAuth) {
				if ($header === 'OCS-APIREQUEST' && $hasOcsApiHeader) {
					return 'true';
				}
				if ($header === 'Authorization' && $hasBearerAuth) {
					return 'Bearer TOKEN!';
				}
				return '';
			});
		$this->request->expects($this->once())
			->method('passesStrictCookieCheck')
			->willReturn(true);

		try {
			$this->middleware->beforeController($controller, 'foo');
			$this->assertFalse($exception);
		} catch (CrossSiteRequestForgeryException $e) {
			$this->assertTrue($exception);
		}
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function testLoggedInCheck() {
		$this->securityCheck(__FUNCTION__, 'isLoggedIn');
	}


	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function testFailLoggedInCheck() {
		$this->securityCheck(__FUNCTION__, 'isLoggedIn', true);
	}


	/**
	 * @NoCSRFRequired
	 */
	public function testIsAdminCheck() {
		$this->securityCheck(__FUNCTION__, 'isAdminUser');
	}

	/**
	 * @NoCSRFRequired
	 * @SubAdminRequired
	 */
	public function testIsNotSubAdminCheck() {
		$this->reader->reflect(__CLASS__,__FUNCTION__);
		$sec = $this->getMiddleware(true, false, false);

		$this->expectException(SecurityException::class);
		$sec->beforeController($this, __METHOD__);
	}

	/**
	 * @NoCSRFRequired
	 * @SubAdminRequired
	 */
	public function testIsSubAdminCheck() {
		$this->reader->reflect(__CLASS__,__FUNCTION__);
		$sec = $this->getMiddleware(true, false, true);

		$sec->beforeController($this, __METHOD__);
		$this->addToAssertionCount(1);
	}

	/**
	 * @NoCSRFRequired
	 * @SubAdminRequired
	 */
	public function testIsSubAdminAndAdminCheck() {
		$this->reader->reflect(__CLASS__,__FUNCTION__);
		$sec = $this->getMiddleware(true, true, true);

		$sec->beforeController($this, __METHOD__);
		$this->addToAssertionCount(1);
	}

	/**
	 * @NoCSRFRequired
	 */
	public function testFailIsAdminCheck() {
		$this->securityCheck(__FUNCTION__, 'isAdminUser', true);
	}


	public function testAfterExceptionNotCaughtThrowsItAgain() {
		$ex = new \Exception();
		$this->expectException(\Exception::class);
		$this->middleware->afterException($this->controller, 'test', $ex);
	}

	public function testAfterExceptionReturnsRedirectForNotLoggedInUser() {
		$this->request = new Request(
			[
				'server' =>
					[
						'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
						'REQUEST_URI' => 'nextcloud/index.php/apps/specialapp'
					]
			],
			$this->createMock(ISecureRandom::class),
			$this->createMock(IConfig::class)
		);
		$this->middleware = $this->getMiddleware(false, false, false);
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.login.showLoginForm',
				[
					'redirect_url' => 'nextcloud/index.php/apps/specialapp',
				]
			)
			->willReturn('http://localhost/nextcloud/index.php/login?redirect_url=nextcloud/index.php/apps/specialapp');
		$this->logger
			->expects($this->once())
			->method('debug');
		$response = $this->middleware->afterException(
			$this->controller,
			'test',
			new NotLoggedInException()
		);
		$expected = new RedirectResponse('http://localhost/nextcloud/index.php/login?redirect_url=nextcloud/index.php/apps/specialapp');
		$this->assertEquals($expected , $response);
	}

	public function testAfterExceptionRedirectsToWebRootAfterStrictCookieFail() {
		$this->request = new Request(
			[
				'server' => [
					'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'REQUEST_URI' => 'nextcloud/index.php/apps/specialapp',
				],
			],
			$this->createMock(ISecureRandom::class),
			$this->createMock(IConfig::class)
		);

		$this->middleware = $this->getMiddleware(false, false, false);
		$response = $this->middleware->afterException(
			$this->controller,
			'test',
			new StrictCookieMissingException()
		);

		$expected = new RedirectResponse(\OC::$WEBROOT . '/');
		$this->assertEquals($expected , $response);
	}


	/**
	 * @return array
	 */
	public function exceptionProvider() {
		return [
			[
				new AppNotEnabledException(),
			],
			[
				new CrossSiteRequestForgeryException(),
			],
			[
				new NotAdminException(''),
			],
		];
	}

	/**
	 * @dataProvider exceptionProvider
	 * @param SecurityException $exception
	 */
	public function testAfterExceptionReturnsTemplateResponse(SecurityException $exception) {
		$this->request = new Request(
			[
				'server' =>
					[
						'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
						'REQUEST_URI' => 'nextcloud/index.php/apps/specialapp'
					]
			],
			$this->createMock(ISecureRandom::class),
			$this->createMock(IConfig::class)
		);
		$this->middleware = $this->getMiddleware(false, false, false);
		$this->logger
			->expects($this->once())
			->method('debug');
		$response = $this->middleware->afterException(
			$this->controller,
			'test',
			$exception
		);
		$expected = new TemplateResponse('core', '403', ['message' => $exception->getMessage()], 'guest');
		$expected->setStatus($exception->getCode());
		$this->assertEquals($expected , $response);
	}

	public function testAfterAjaxExceptionReturnsJSONError() {
		$response = $this->middleware->afterException($this->controller, 'test',
			$this->secAjaxException);

		$this->assertTrue($response instanceof JSONResponse);
	}

	public function dataRestrictedApp() {
		return [
			[false, false, false,],
			[false, false,  true,],
			[false,  true, false,],
			[false,  true,  true,],
			[ true, false, false,],
			[ true, false,  true,],
			[ true,  true, false,],
			[ true,  true,  true,],
		];
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function testRestrictedAppLoggedInPublicPage() {
		$middleware = $this->getMiddleware(true, false, false);
		$this->reader->reflect(__CLASS__,__FUNCTION__);

		$this->appManager->method('getAppPath')
			->with('files')
			->willReturn('foo');

		$this->appManager->method('isEnabledForUser')
			->with('files')
			->willReturn(false);

		$middleware->beforeController($this->controller, __FUNCTION__);
		$this->addToAssertionCount(1);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function testRestrictedAppNotLoggedInPublicPage() {
		$middleware = $this->getMiddleware(false, false, false);
		$this->reader->reflect(__CLASS__,__FUNCTION__);

		$this->appManager->method('getAppPath')
			->with('files')
			->willReturn('foo');

		$this->appManager->method('isEnabledForUser')
			->with('files')
			->willReturn(false);

		$middleware->beforeController($this->controller, __FUNCTION__);
		$this->addToAssertionCount(1);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function testRestrictedAppLoggedIn() {
		$middleware = $this->getMiddleware(true, false, false, false);
		$this->reader->reflect(__CLASS__,__FUNCTION__);

		$this->appManager->method('getAppPath')
			->with('files')
			->willReturn('foo');

		$this->expectException(AppNotEnabledException::class);
		$middleware->beforeController($this->controller, __FUNCTION__);
	}
}
