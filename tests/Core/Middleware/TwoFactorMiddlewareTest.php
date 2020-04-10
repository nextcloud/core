<?php

/**
 * @author Christoph Wurst <christoph@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace Test\Core\Middleware;

use OC\AppFramework\Http\Request;
use OC\Authentication\Exceptions\TwoFactorAuthRequiredException;
use OC\Authentication\Exceptions\UserAlreadyLoggedInException;
use OC\Authentication\TwoFactorAuth\Manager;
use OC\Authentication\TwoFactorAuth\ProviderSet;
use OC\Core\Controller\TwoFactorChallengeController;
use OC\Core\Middleware\TwoFactorMiddleware;
use OC\User\Session;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TwoFactorMiddlewareTest extends TestCase {

	/** @var Manager|MockObject */
	private $twoFactorManager;

	/** @var IUserSession|MockObject */
	private $userSession;

	/** @var ISession|MockObject */
	private $session;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var IControllerMethodReflector|MockObject */
	private $reflector;

	/** @var IRequest|MockObject */
	private $request;

	/** @var TwoFactorMiddleware */
	private $middleware;

	/** @var Controller */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->twoFactorManager = $this->getMockBuilder(Manager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession = $this->getMockBuilder(Session::class)
			->disableOriginalConstructor()
			->getMock();
		$this->session = $this->createMock(ISession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->reflector = $this->createMock(IControllerMethodReflector::class);
		$this->request = new Request(
			[
				'server' => [
					'REQUEST_URI' => 'test/url'
				]
			],
			$this->createMock(ISecureRandom::class),
			$this->createMock(IConfig::class)
		);

		$this->middleware = new TwoFactorMiddleware($this->twoFactorManager, $this->userSession, $this->session, $this->urlGenerator, $this->reflector, $this->request);
		$this->controller = $this->createMock(Controller::class);
	}

	public function testBeforeControllerNotLoggedIn() {
		$this->reflector->expects($this->once())
			->method('hasAnnotation')
			->with('PublicPage')
			->willReturn(false);
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);

		$this->userSession->expects($this->never())
			->method('getUser');

		$this->middleware->beforeController($this->controller, 'index');
	}

	public function testBeforeControllerPublicPage() {
		$this->reflector->expects($this->once())
			->method('hasAnnotation')
			->with('PublicPage')
			->willReturn(true);
		$this->userSession->expects($this->never())
			->method('isLoggedIn');

		$this->middleware->beforeController($this->controller, 'create');
	}

	public function testBeforeSetupController() {
		$user = $this->createMock(IUser::class);
		$controller = $this->createMock(ALoginSetupController::class);
		$this->reflector->expects($this->once())
			->method('hasAnnotation')
			->with('PublicPage')
			->willReturn(false);
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('needsSecondFactor')
			->willReturn(true);
		$this->userSession->expects($this->never())
			->method('isLoggedIn');

		$this->middleware->beforeController($controller, 'create');
	}

	public function testBeforeControllerNoTwoFactorCheckNeeded() {
		$user = $this->createMock(IUser::class);

		$this->reflector->expects($this->once())
			->method('hasAnnotation')
			->with('PublicPage')
			->willReturn(false);
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->willReturn(true);
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->with($user)
			->willReturn(false);

		$this->middleware->beforeController($this->controller, 'index');
	}

	
	public function testBeforeControllerTwoFactorAuthRequired() {
		$this->expectException(\OC\Authentication\Exceptions\TwoFactorAuthRequiredException::class);

		$user = $this->createMock(IUser::class);

		$this->reflector->expects($this->once())
			->method('hasAnnotation')
			->with('PublicPage')
			->willReturn(false);
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->willReturn(true);
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->with($user)
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('needsSecondFactor')
			->with($user)
			->willReturn(true);

		$this->middleware->beforeController($this->controller, 'index');
	}

	
	public function testBeforeControllerUserAlreadyLoggedIn() {
		$this->expectException(\OC\Authentication\Exceptions\UserAlreadyLoggedInException::class);

		$user = $this->createMock(IUser::class);

		$this->reflector
			->method('hasAnnotation')
			->willReturn(false);
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->willReturn(true);
		$this->userSession
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->with($user)
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('needsSecondFactor')
			->with($user)
			->willReturn(false);

		$twoFactorChallengeController = $this->getMockBuilder('\OC\Core\Controller\TwoFactorChallengeController')
			->disableOriginalConstructor()
			->getMock();
		$this->middleware->beforeController($twoFactorChallengeController, 'index');
	}

	public function testAfterExceptionTwoFactorAuthRequired() {
		$ex = new \OC\Authentication\Exceptions\TwoFactorAuthRequiredException();

		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.TwoFactorChallenge.selectChallenge')
			->willReturn('test/url');
		$expected = new \OCP\AppFramework\Http\RedirectResponse('test/url');

		$this->assertEquals($expected, $this->middleware->afterException($this->controller, 'index', $ex));
	}

	public function testAfterException() {
		$ex = new \OC\Authentication\Exceptions\UserAlreadyLoggedInException();

		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('files.view.index')
			->willReturn('redirect/url');
		$expected = new \OCP\AppFramework\Http\RedirectResponse('redirect/url');

		$this->assertEquals($expected, $this->middleware->afterException($this->controller, 'index', $ex));
	}

	public function testRequires2FASetupDoneAnnotated() {
		$user = $this->createMock(IUser::class);

		$this->reflector
			->method('hasAnnotation')
			->willReturnCallback(function (string $annotation) {
				return $annotation === 'TwoFactorSetUpDoneRequired';
			});
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->willReturn(true);
		$this->userSession
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->with($user)
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('needsSecondFactor')
			->with($user)
			->willReturn(false);

		$this->expectException(UserAlreadyLoggedInException::class);

		$twoFactorChallengeController = $this->getMockBuilder(TwoFactorChallengeController::class)
			->disableOriginalConstructor()
			->getMock();
		$this->middleware->beforeController($twoFactorChallengeController, 'index');
	}

	public function dataRequires2FASetupDone() {
		$provider = $this->createMock(IProvider::class);
		$provider->method('getId')
			->willReturn('2FAftw');

		return [
			[[], false, false],
			[[],  true,  true],
			[[$provider], false, true],
			[[$provider], true,  true],
		];
	}

	/**
	 * @dataProvider dataRequires2FASetupDone
	 */
	public function testRequires2FASetupDone(array $providers, bool $missingProviders, bool $expectEception) {
		$user = $this->createMock(IUser::class);

		$this->reflector
			->method('hasAnnotation')
			->willReturn(false);
		$this->userSession
			->method('getUser')
			->willReturn($user);
		$providerSet = new ProviderSet($providers, $missingProviders);
		$this->twoFactorManager->method('getProviderSet')
			->with($user)
			->willReturn($providerSet);
		$this->userSession
			->method('isLoggedIn')
			->willReturn(false);

		if ($expectEception) {
			$this->expectException(TwoFactorAuthRequiredException::class);
		} else {
			// hack to make phpunit shut up. Since we don't expect an exception here...
			$this->assertTrue(true);
		}

		$twoFactorChallengeController = $this->getMockBuilder(TwoFactorChallengeController::class)
			->disableOriginalConstructor()
			->getMock();
		$this->middleware->beforeController($twoFactorChallengeController, 'index');
	}

}
