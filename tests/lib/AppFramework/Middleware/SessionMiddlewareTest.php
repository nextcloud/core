<?php
/**
 * ownCloud - App Framework
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Thomas Müller <deepdiver@owncloud.com>
 * @copyright Thomas Müller 2014
 */

namespace Test\AppFramework\Middleware;

use OC\AppFramework\Middleware\SessionMiddleware;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;

class SessionMiddlewareTest extends \Test\TestCase {

	/** @var ControllerMethodReflector */
	private $reflector;

	/** @var Controller */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->reflector = new ControllerMethodReflector();
		$this->controller = $this->createMock(Controller::class);
	}

	/**
	 * @UseSession
	 */
	public function testSessionNotClosedOnBeforeController() {
		$session = $this->getSessionMock(0);

		$this->reflector->reflect($this, __FUNCTION__);
		$middleware = new SessionMiddleware($this->reflector, $session);
		$middleware->beforeController($this->controller, __FUNCTION__);
	}

	/**
	 * @UseSession
	 */
	public function testSessionClosedOnAfterController() {
		$session = $this->getSessionMock(1);

		$this->reflector->reflect($this, __FUNCTION__);
		$middleware = new SessionMiddleware($this->reflector, $session);
		$middleware->afterController($this->controller, __FUNCTION__, new Response());
	}

	public function testSessionClosedOnBeforeController() {
		$session = $this->getSessionMock(1);

		$this->reflector->reflect($this, __FUNCTION__);
		$middleware = new SessionMiddleware($this->reflector, $session);
		$middleware->beforeController($this->controller, __FUNCTION__);
	}

	public function testSessionNotClosedOnAfterController() {
		$session = $this->getSessionMock(0);

		$this->reflector->reflect($this, __FUNCTION__);
		$middleware = new SessionMiddleware($this->reflector, $session);
		$middleware->afterController($this->controller, __FUNCTION__, new Response());
	}

	/**
	 * @return mixed
	 */
	private function getSessionMock($expectedCloseCount) {
		$session = $this->getMockBuilder('\OC\Session\Memory')
			->disableOriginalConstructor()
			->getMock();

		$session->expects($this->exactly($expectedCloseCount))
			->method('close');
		return $session;
	}

}
