<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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

use OC\AppFramework\Middleware\Security\BruteForceMiddleware;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OC\Security\Bruteforce\Throttler;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Test\TestCase;

class BruteForceMiddlewareTest extends TestCase {
	/** @var ControllerMethodReflector|\PHPUnit_Framework_MockObject_MockObject */
	private $reflector;
	/** @var Throttler|\PHPUnit_Framework_MockObject_MockObject */
	private $throttler;
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var BruteForceMiddleware */
	private $bruteForceMiddleware;

	protected function setUp(): void {
		parent::setUp();

		$this->reflector = $this->createMock(ControllerMethodReflector::class);
		$this->throttler = $this->createMock(Throttler::class);
		$this->request = $this->createMock(IRequest::class);

		$this->bruteForceMiddleware = new BruteForceMiddleware(
			$this->reflector,
			$this->throttler,
			$this->request
		);
	}

	public function testBeforeControllerWithAnnotation() {
		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('BruteForceProtection')
			->willReturn(true);
		$this->reflector
			->expects($this->once())
			->method('getAnnotationParameter')
			->with('BruteForceProtection', 'action')
			->willReturn('login');
		$this->request
			->expects($this->once())
			->method('getRemoteAddress')
			->willReturn('127.0.0.1');
		$this->throttler
			->expects($this->once())
			->method('sleepDelay')
			->with('127.0.0.1', 'login');

		/** @var Controller|\PHPUnit_Framework_MockObject_MockObject $controller */
		$controller = $this->createMock(Controller::class);
		$this->bruteForceMiddleware->beforeController($controller, 'testMethod');
	}

	public function testBeforeControllerWithoutAnnotation() {
		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('BruteForceProtection')
			->willReturn(false);
		$this->reflector
			->expects($this->never())
			->method('getAnnotationParameter');
		$this->request
			->expects($this->never())
			->method('getRemoteAddress');
		$this->throttler
			->expects($this->never())
			->method('sleepDelay');

		/** @var Controller|\PHPUnit_Framework_MockObject_MockObject $controller */
		$controller = $this->createMock(Controller::class);
		$this->bruteForceMiddleware->beforeController($controller, 'testMethod');
	}

	public function testAfterControllerWithAnnotationAndThrottledRequest() {
		/** @var Response|\PHPUnit_Framework_MockObject_MockObject $response */
		$response = $this->createMock(Response::class);
		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('BruteForceProtection')
			->willReturn(true);
		$response
			->expects($this->once())
			->method('isThrottled')
			->willReturn(true);
		$response
			->expects($this->once())
			->method('getThrottleMetadata')
			->willReturn([]);
		$this->reflector
			->expects($this->once())
			->method('getAnnotationParameter')
			->with('BruteForceProtection', 'action')
			->willReturn('login');
		$this->request
			->expects($this->once())
			->method('getRemoteAddress')
			->willReturn('127.0.0.1');
		$this->throttler
			->expects($this->once())
			->method('sleepDelay')
			->with('127.0.0.1', 'login');
		$this->throttler
			->expects($this->once())
			->method('registerAttempt')
			->with('login', '127.0.0.1');

		/** @var Controller|\PHPUnit_Framework_MockObject_MockObject $controller */
		$controller = $this->createMock(Controller::class);
		$this->bruteForceMiddleware->afterController($controller, 'testMethod' ,$response);
	}

	public function testAfterControllerWithAnnotationAndNotThrottledRequest() {
		/** @var Response|\PHPUnit_Framework_MockObject_MockObject $response */
		$response = $this->createMock(Response::class);
		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('BruteForceProtection')
			->willReturn(true);
		$response
			->expects($this->once())
			->method('isThrottled')
			->willReturn(false);
		$this->reflector
			->expects($this->never())
			->method('getAnnotationParameter');
		$this->request
			->expects($this->never())
			->method('getRemoteAddress');
		$this->throttler
			->expects($this->never())
			->method('sleepDelay');
		$this->throttler
			->expects($this->never())
			->method('registerAttempt');

		/** @var Controller|\PHPUnit_Framework_MockObject_MockObject $controller */
		$controller = $this->createMock(Controller::class);
		$this->bruteForceMiddleware->afterController($controller, 'testMethod' ,$response);
	}

	public function testAfterControllerWithoutAnnotation() {
		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('BruteForceProtection')
			->willReturn(false);
		$this->reflector
			->expects($this->never())
			->method('getAnnotationParameter');
		$this->request
			->expects($this->never())
			->method('getRemoteAddress');
		$this->throttler
			->expects($this->never())
			->method('sleepDelay');

		/** @var Controller|\PHPUnit_Framework_MockObject_MockObject $controller */
		$controller = $this->createMock(Controller::class);
		/** @var Response|\PHPUnit_Framework_MockObject_MockObject $response */
		$response = $this->createMock(Response::class);
		$this->bruteForceMiddleware->afterController($controller, 'testMethod' ,$response);
	}
}
