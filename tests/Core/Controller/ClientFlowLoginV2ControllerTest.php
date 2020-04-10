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

namespace Test\Core\Controller;

use OC\Core\Controller\ClientFlowLoginV2Controller;
use OC\Core\Data\LoginFlowV2Credentials;
use OC\Core\Db\LoginFlowV2;
use OC\Core\Exception\LoginFlowV2NotFoundException;
use OC\Core\Service\LoginFlowV2Service;
use OCP\AppFramework\Http;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ClientFlowLoginV2ControllerTest extends TestCase {

	/** @var IRequest|MockObject */
	private $request;
	/** @var LoginFlowV2Service|MockObject */
	private $loginFlowV2Service;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var ISession|MockObject */
	private $session;
	/** @var ISecureRandom|MockObject */
	private $random;
	/** @var Defaults|MockObject */
	private $defaults;
	/** @var IL10N|MockObject */
	private $l;
	/** @var ClientFlowLoginV2Controller */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->loginFlowV2Service = $this->createMock(LoginFlowV2Service::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->session = $this->createMock(ISession::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->defaults = $this->createMock(Defaults::class);
		$this->l = $this->createMock(IL10N::class);
		$this->controller = new ClientFlowLoginV2Controller(
			'core',
			$this->request,
			$this->loginFlowV2Service,
			$this->urlGenerator,
			$this->session,
			$this->random,
			$this->defaults,
			'user',
			$this->l
		);
	}

	public function testPollInvalid() {
		$this->loginFlowV2Service->method('poll')
			->with('token')
			->willThrowException(new LoginFlowV2NotFoundException());

		$result = $this->controller->poll('token');

		$this->assertSame([], $result->getData());
		$this->assertSame(Http::STATUS_NOT_FOUND, $result->getStatus());
	}

	public function testPollValid() {
		$creds = new LoginFlowV2Credentials('server', 'login', 'pass');
		$this->loginFlowV2Service->method('poll')
			->with('token')
			->willReturn($creds);

		$result = $this->controller->poll('token');

		$this->assertSame($creds, $result->getData());
		$this->assertSame(Http::STATUS_OK, $result->getStatus());
	}

	public function testLandingInvalid() {
		$this->session->expects($this->never())
			->method($this->anything());

		$this->loginFlowV2Service->method('startLoginFlow')
			->with('token')
			->willReturn(false);

		$result = $this->controller->landing('token');

		$this->assertSame(Http::STATUS_FORBIDDEN, $result->getStatus());
		$this->assertInstanceOf(Http\StandaloneTemplateResponse::class, $result);
	}

	public function testLandingValid() {
		$this->session->expects($this->once())
			->method('set')
			->with('client.flow.v2.login.token', 'token');

		$this->loginFlowV2Service->method('startLoginFlow')
			->with('token')
			->willReturn(true);

		$this->urlGenerator->method('linkToRouteAbsolute')
			->with('core.ClientFlowLoginV2.showAuthPickerPage')
			->willReturn('https://server/path');

		$result = $this->controller->landing('token');

		$this->assertInstanceOf(Http\RedirectResponse::class, $result);
		$this->assertSame(Http::STATUS_SEE_OTHER, $result->getStatus());
		$this->assertSame('https://server/path', $result->getRedirectURL());
	}

	public function testShowAuthPickerNoLoginToken() {
		$this->session->method('get')
			->willReturn(null);

		$result = $this->controller->showAuthPickerPage();

		$this->assertSame(Http::STATUS_FORBIDDEN, $result->getStatus());
	}

	public function testShowAuthPickerInvalidLoginToken() {
		$this->session->method('get')
			->with('client.flow.v2.login.token')
			->willReturn('loginToken');

		$this->loginFlowV2Service->method('getByLoginToken')
			->with('loginToken')
			->willThrowException(new LoginFlowV2NotFoundException());

		$result = $this->controller->showAuthPickerPage();

		$this->assertSame(Http::STATUS_FORBIDDEN, $result->getStatus());
	}

	public function testShowAuthPickerValidLoginToken() {
		$this->session->method('get')
			->with('client.flow.v2.login.token')
			->willReturn('loginToken');

		$flow = new LoginFlowV2();
		$this->loginFlowV2Service->method('getByLoginToken')
			->with('loginToken')
			->willReturn($flow);

		$this->random->method('generate')
			->with(64, ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_DIGITS)
			->willReturn('random');
		$this->session->expects($this->once())
			->method('set')
			->with('client.flow.v2.state.token', 'random');

		$this->controller->showAuthPickerPage();
	}

	public function testGrantPageInvalidStateToken() {
		$this->session->method('get')
			->willReturnCallback(function ($name) {
				return null;
			});

		$result = $this->controller->grantPage('stateToken');
		$this->assertSame(Http::STATUS_FORBIDDEN, $result->getStatus());
	}

	public function testGrantPageInvalidLoginToken() {
		$this->session->method('get')
			->willReturnCallback(function ($name) {
				if ($name === 'client.flow.v2.state.token') {
					return 'stateToken';
				}
				if ($name === 'client.flow.v2.login.token') {
					return 'loginToken';
				}
				return null;
			});

		$this->loginFlowV2Service->method('getByLoginToken')
			->with('loginToken')
			->willThrowException(new LoginFlowV2NotFoundException());

		$result = $this->controller->grantPage('stateToken');
		$this->assertSame(Http::STATUS_FORBIDDEN, $result->getStatus());
	}

	public function testGrantPageValid() {
		$this->session->method('get')
			->willReturnCallback(function ($name) {
				if ($name === 'client.flow.v2.state.token') {
					return 'stateToken';
				}
				if ($name === 'client.flow.v2.login.token') {
					return 'loginToken';
				}
				return null;
			});

		$flow = new LoginFlowV2();
		$this->loginFlowV2Service->method('getByLoginToken')
			->with('loginToken')
			->willReturn($flow);

		$result = $this->controller->grantPage('stateToken');
		$this->assertSame(Http::STATUS_OK, $result->getStatus());
	}


	public function testGenerateAppPasswordInvalidStateToken() {
		$this->session->method('get')
			->willReturnCallback(function ($name) {
				return null;
			});

		$result = $this->controller->generateAppPassword('stateToken');
		$this->assertSame(Http::STATUS_FORBIDDEN, $result->getStatus());
	}

	public function testGenerateAppPassworInvalidLoginToken() {
		$this->session->method('get')
			->willReturnCallback(function ($name) {
				if ($name === 'client.flow.v2.state.token') {
					return 'stateToken';
				}
				if ($name === 'client.flow.v2.login.token') {
					return 'loginToken';
				}
				return null;
			});

		$this->loginFlowV2Service->method('getByLoginToken')
			->with('loginToken')
			->willThrowException(new LoginFlowV2NotFoundException());

		$result = $this->controller->generateAppPassword('stateToken');
		$this->assertSame(Http::STATUS_FORBIDDEN, $result->getStatus());
	}

	public function testGenerateAppPassworValid() {
		$this->session->method('get')
			->willReturnCallback(function ($name) {
				if ($name === 'client.flow.v2.state.token') {
					return 'stateToken';
				}
				if ($name === 'client.flow.v2.login.token') {
					return 'loginToken';
				}
				return null;
			});

		$flow = new LoginFlowV2();
		$this->loginFlowV2Service->method('getByLoginToken')
			->with('loginToken')
			->willReturn($flow);

		$clearedState = false;
		$clearedLogin = false;
		$this->session->method('remove')
			->willReturnCallback(function ($name) use (&$clearedLogin, &$clearedState) {
				if ($name === 'client.flow.v2.state.token') {
					$clearedState = true;
				}
				if ($name === 'client.flow.v2.login.token') {
					$clearedLogin = true;
				}
			});

		$this->session->method('getId')
			->willReturn('sessionId');

		$this->loginFlowV2Service->expects($this->once())
			->method('flowDone')
			->with(
				'loginToken',
				'sessionId',
				'https://server',
				'user'
			)->willReturn(true);

		$this->request->method('getServerProtocol')
			->willReturn('https');
		$this->request->method('getRequestUri')
			->willReturn('/login/v2');
		$this->request->method('getServerHost')
			->willReturn('server');

		$result = $this->controller->generateAppPassword('stateToken');
		$this->assertSame(Http::STATUS_OK, $result->getStatus());

		$this->assertTrue($clearedLogin);
		$this->assertTrue($clearedState);
	}
}
