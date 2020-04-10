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

namespace Test\AppFramework\Middleware;

use OC\AppFramework\Middleware\AdditionalScriptsMiddleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\PublicShareController;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AdditionalScriptsMiddlewareTest extends \Test\TestCase {

	/** @var EventDispatcherInterface|MockObject */
	private $dispatcher;
	/** @var IUserSession|MockObject */
	private $userSession;

	/** @var Controller */
	private $controller;

	/** @var AdditionalScriptsMiddleware */
	private $middleWare;

	protected function setUp(): void {
		parent::setUp();

		$this->dispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->middleWare = new AdditionalScriptsMiddleware(
			$this->dispatcher,
			$this->userSession
		);

		$this->controller = $this->createMock(Controller::class);
	}

	public function testNoTemplateResponse() {
		$this->dispatcher->expects($this->never())
			->method($this->anything());
		$this->userSession->expects($this->never())
			->method($this->anything());

		$this->middleWare->afterController($this->controller, 'myMethod', $this->createMock(Response::class));
	}

	public function testPublicShareController() {
		$this->dispatcher->expects($this->never())
			->method($this->anything());
		$this->userSession->expects($this->never())
			->method($this->anything());

		$this->middleWare->afterController($this->createMock(PublicShareController::class), 'myMethod', $this->createMock(Response::class));
	}

	public function testStandaloneTemplateResponse() {
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->willReturnCallback(function ($eventName) {
				if ($eventName === TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS) {
					return;
				}

				$this->fail('Wrong event dispatched');
			});
		$this->userSession->expects($this->never())
			->method($this->anything());

		$this->middleWare->afterController($this->controller, 'myMethod', $this->createMock(StandaloneTemplateResponse::class));
	}

	public function testTemplateResponseNotLoggedIn() {
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->willReturnCallback(function ($eventName) {
				if ($eventName === TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS) {
					return;
				}

				$this->fail('Wrong event dispatched');
			});
		$this->userSession->method('isLoggedIn')
			->willReturn(false);

		$this->middleWare->afterController($this->controller, 'myMethod', $this->createMock(TemplateResponse::class));
	}

	public function testTemplateResponseLoggedIn() {
		$events = [];

		$this->dispatcher->expects($this->exactly(2))
			->method('dispatch')
			->willReturnCallback(function ($eventName) use (&$events) {
				if ($eventName === TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS ||
					$eventName === TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS_LOGGEDIN) {
					$events[] = $eventName;
					return;
				}

				$this->fail('Wrong event dispatched');
			});
		$this->userSession->method('isLoggedIn')
			->willReturn(true);

		$this->middleWare->afterController($this->controller, 'myMethod', $this->createMock(TemplateResponse::class));

		$this->assertContains(TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS, $events);
		$this->assertContains(TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS_LOGGEDIN, $events);
	}
}
