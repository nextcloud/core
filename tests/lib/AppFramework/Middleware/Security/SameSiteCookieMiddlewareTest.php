<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
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

use OC\AppFramework\Http\Request;
use OC\AppFramework\Middleware\Security\Exceptions\LaxSameSiteCookieFailedException;
use OC\AppFramework\Middleware\Security\Exceptions\SecurityException;
use OC\AppFramework\Middleware\Security\SameSiteCookieMiddleware;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use Test\TestCase;

class SameSiteCookieMiddlewareTest extends TestCase {

	/** @var SameSiteCookieMiddleware */
	private $middleware;

	/** @var Request|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var ControllerMethodReflector|\PHPUnit_Framework_MockObject_MockObject */
	private $reflector;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(Request::class);
		$this->reflector = $this->createMock(ControllerMethodReflector::class);
		$this->middleware = new SameSiteCookieMiddleware($this->request, $this->reflector);
	}

	public function testBeforeControllerNoIndex() {
		$this->request->method('getScriptName')
			->willReturn('/ocs/v2.php');

		$this->middleware->beforeController($this->createMock(Controller::class), 'foo');
		$this->addToAssertionCount(1);
	}

	public function testBeforeControllerIndexHasAnnotation() {
		$this->request->method('getScriptName')
			->willReturn('/index.php');

		$this->reflector->method('hasAnnotation')
			->with('NoSameSiteCookieRequired')
			->willReturn(true);

		$this->middleware->beforeController($this->createMock(Controller::class), 'foo');
		$this->addToAssertionCount(1);
	}

	public function testBeforeControllerIndexNoAnnotationPassingCheck() {
		$this->request->method('getScriptName')
			->willReturn('/index.php');

		$this->reflector->method('hasAnnotation')
			->with('NoSameSiteCookieRequired')
			->willReturn(false);

		$this->request->method('passesLaxCookieCheck')
			->willReturn(true);

		$this->middleware->beforeController($this->createMock(Controller::class), 'foo');
		$this->addToAssertionCount(1);
	}

	public function testBeforeControllerIndexNoAnnotationFailingCheck() {
		$this->expectException(LaxSameSiteCookieFailedException::class);

		$this->request->method('getScriptName')
			->willReturn('/index.php');

		$this->reflector->method('hasAnnotation')
			->with('NoSameSiteCookieRequired')
			->willReturn(false);

		$this->request->method('passesLaxCookieCheck')
			->willReturn(false);

		$this->middleware->beforeController($this->createMock(Controller::class), 'foo');
	}

	public function testAfterExceptionNoLaxCookie() {
		$ex = new SecurityException();

		try {
			$this->middleware->afterException($this->createMock(Controller::class), 'foo', $ex);
			$this->fail();
		} catch (\Exception $e) {
			$this->assertSame($ex, $e);
		}
	}

	public function testAfterExceptionLaxCookie() {
		$ex = new LaxSameSiteCookieFailedException();

		$this->request->method('getRequestUri')
			->willReturn('/myrequri');

		$middleware = $this->getMockBuilder(SameSiteCookieMiddleware::class)
			->setConstructorArgs([$this->request, $this->reflector])
			->setMethods(['setSameSiteCookie'])
			->getMock();

		$middleware->expects($this->once())
			->method('setSameSiteCookie');

		$resp = $middleware->afterException($this->createMock(Controller::class), 'foo', $ex);

		$this->assertSame(Http::STATUS_FOUND, $resp->getStatus());

		$headers = $resp->getHeaders();
		$this->assertSame('/myrequri', $headers['Location']);
	}
}
