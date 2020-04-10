<?php
/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace Test\AppFramework\Middleware\PublicShare;

use OC\AppFramework\Middleware\PublicShare\Exceptions\NeedAuthenticationException;
use OC\AppFramework\Middleware\PublicShare\PublicShareMiddleware;
use OCP\AppFramework\AuthPublicShareController;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\PublicShareController;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;

class PublicShareMiddlewareTest extends \Test\TestCase {

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var ISession|\PHPUnit_Framework_MockObject_MockObject */
	private $session;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var PublicShareMiddleware */
	private $middleware;


	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->session = $this->createMock(ISession::class);
		$this->config = $this->createMock(IConfig::class);

		$this->middleware = new PublicShareMiddleware(
			$this->request,
			$this->session,
			$this->config
		);
	}

	public function testBeforeControllerNoPublicShareController() {
		$controller = $this->createMock(Controller::class);

		$this->middleware->beforeController($controller, 'method');
		$this->assertTrue(true);
	}

	public function dataShareApi() {
		return [
			['no', 'no',],
			['no', 'yes',],
			['yes', 'no',],
		];
	}

	/**
	 * @dataProvider dataShareApi
	 */
	public function testBeforeControllerShareApiDisabled(string $shareApi, string $shareLinks) {
		$controller = $this->createMock(PublicShareController::class);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enabled', 'yes', $shareApi],
				['core', 'shareapi_allow_links', 'yes', $shareLinks],
			]);

		$this->expectException(NotFoundException::class);
		$this->middleware->beforeController($controller, 'mehod');
	}

	public function testBeforeControllerNoTokenParam() {
		$controller = $this->createMock(PublicShareController::class);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enabled', 'yes', 'yes'],
				['core', 'shareapi_allow_links', 'yes', 'yes'],
			]);

		$this->expectException(NotFoundException::class);
		$this->middleware->beforeController($controller, 'mehod');
	}

	public function testBeforeControllerInvalidToken() {
		$controller = $this->createMock(PublicShareController::class);

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enabled', 'yes', 'yes'],
				['core', 'shareapi_allow_links', 'yes', 'yes'],
			]);

		$this->request->method('getParam')
			->with('token', null)
			->willReturn('myToken');

		$controller->method('isValidToken')
			->willReturn(false);
		$controller->expects($this->once())
			->method('shareNotFound');

		$this->expectException(NotFoundException::class);
		$this->middleware->beforeController($controller, 'mehod');
	}

	public function testBeforeControllerValidTokenNotAuthenticated() {
		$controller = $this->getMockBuilder(PublicShareController::class)
			->setConstructorArgs(['app', $this->request, $this->session])
			->getMock();

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enabled', 'yes', 'yes'],
				['core', 'shareapi_allow_links', 'yes', 'yes'],
			]);

		$this->request->method('getParam')
			->with('token', null)
			->willReturn('myToken');

		$controller->method('isValidToken')
			->willReturn(true);

		$controller->method('isPasswordProtected')
			->willReturn(true);

		$this->expectException(NotFoundException::class);
		$this->middleware->beforeController($controller, 'mehod');
	}

	public function testBeforeControllerValidTokenAuthenticateMethod() {
		$controller = $this->getMockBuilder(PublicShareController::class)
			->setConstructorArgs(['app', $this->request, $this->session])
			->getMock();

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enabled', 'yes', 'yes'],
				['core', 'shareapi_allow_links', 'yes', 'yes'],
			]);

		$this->request->method('getParam')
			->with('token', null)
			->willReturn('myToken');

		$controller->method('isValidToken')
			->willReturn(true);

		$controller->method('isPasswordProtected')
			->willReturn(true);

		$this->middleware->beforeController($controller, 'authenticate');
		$this->assertTrue(true);
	}

	public function testBeforeControllerValidTokenShowAuthenticateMethod() {
		$controller = $this->getMockBuilder(PublicShareController::class)
			->setConstructorArgs(['app', $this->request, $this->session])
			->getMock();

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enabled', 'yes', 'yes'],
				['core', 'shareapi_allow_links', 'yes', 'yes'],
			]);

		$this->request->method('getParam')
			->with('token', null)
			->willReturn('myToken');

		$controller->method('isValidToken')
			->willReturn(true);

		$controller->method('isPasswordProtected')
			->willReturn(true);

		$this->middleware->beforeController($controller, 'showAuthenticate');
		$this->assertTrue(true);
	}

	public function testBeforeControllerAuthPublicShareController() {
		$controller = $this->getMockBuilder(AuthPublicShareController::class)
			->setConstructorArgs(['app', $this->request, $this->session, $this->createMock(IURLGenerator::class)])
			->getMock();

		$this->config->method('getAppValue')
			->willReturnMap([
				['core', 'shareapi_enabled', 'yes', 'yes'],
				['core', 'shareapi_allow_links', 'yes', 'yes'],
			]);

		$this->request->method('getParam')
			->with('token', null)
			->willReturn('myToken');

		$controller->method('isValidToken')
			->willReturn(true);

		$controller->method('isPasswordProtected')
			->willReturn(true);

		$this->session->expects($this->once())
			->method('set')
			->with('public_link_authenticate_redirect', '[]');

		$this->expectException(NeedAuthenticationException::class);
		$this->middleware->beforeController($controller, 'method');
	}

	public function testAfterExceptionNoPublicShareController() {
		$controller = $this->createMock(Controller::class);
		$exception = new \Exception();

		try {
			$this->middleware->afterException($controller, 'method', $exception);
		} catch (\Exception $e) {
			$this->assertEquals($exception, $e);
		}
	}

	public function testAfterExceptionPublicShareControllerNotFoundException() {
		$controller = $this->createMock(PublicShareController::class);
		$exception = new NotFoundException();

		$result = $this->middleware->afterException($controller, 'method', $exception);
		$this->assertInstanceOf(NotFoundResponse::class, $result);
	}

	public function testAfterExceptionPublicShareController() {
		$controller = $this->createMock(PublicShareController::class);
		$exception = new \Exception();

		try {
			$this->middleware->afterException($controller, 'method', $exception);
		} catch (\Exception $e) {
			$this->assertEquals($exception, $e);
		}
	}

	public function testAfterExceptionAuthPublicShareController() {
		$controller = $this->getMockBuilder(AuthPublicShareController::class)
			->setConstructorArgs([
				'app',
				$this->request,
				$this->session,
				$this->createMock(IURLGenerator::class),
			])->getMock();
		$controller->setToken('token');

		$exception = new NeedAuthenticationException();

		$this->request->method('getParam')
			->with('_route')
			->willReturn('my.route');

		$result = $this->middleware->afterException($controller, 'method', $exception);
		$this->assertInstanceOf(RedirectResponse::class, $result);
	}


}
