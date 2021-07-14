<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
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

namespace Tests\Core\Controller;

use OC\Authentication\Login\Chain as LoginChain;
use OC\Authentication\Login\LoginData;
use OC\Authentication\Login\LoginResult;
use OC\Authentication\TwoFactorAuth\Manager;
use OC\Core\Controller\LoginController;
use OC\Security\Bruteforce\Throttler;
use OC\User\Session;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class LoginControllerTest extends TestCase {

	/** @var LoginController */
	private $loginController;

	/** @var IRequest|MockObject */
	private $request;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var IConfig|MockObject */
	private $config;

	/** @var ISession|MockObject */
	private $session;

	/** @var Session|MockObject */
	private $userSession;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var ILogger|MockObject */
	private $logger;

	/** @var Manager|MockObject */
	private $twoFactorManager;

	/** @var Defaults|MockObject */
	private $defaults;

	/** @var Throttler|MockObject */
	private $throttler;

	/** @var LoginChain|MockObject */
	private $chain;

	/** @var IInitialStateService|MockObject */
	private $initialStateService;

	/** @var \OC\Authentication\WebAuthn\Manager|MockObject */
	private $webAuthnManager;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->userManager = $this->createMock(\OC\User\Manager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->session = $this->createMock(ISession::class);
		$this->userSession = $this->createMock(Session::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->twoFactorManager = $this->createMock(Manager::class);
		$this->defaults = $this->createMock(Defaults::class);
		$this->throttler = $this->createMock(Throttler::class);
		$this->chain = $this->createMock(LoginChain::class);
		$this->initialStateService = $this->createMock(IInitialStateService::class);
		$this->webAuthnManager = $this->createMock(\OC\Authentication\WebAuthn\Manager::class);


		$this->request->method('getRemoteAddress')
			->willReturn('1.2.3.4');
		$this->throttler->method('getDelay')
			->with(
				$this->equalTo('1.2.3.4'),
				$this->equalTo('')
			)->willReturn(1000);

		$this->loginController = new LoginController(
			'core',
			$this->request,
			$this->userManager,
			$this->config,
			$this->session,
			$this->userSession,
			$this->urlGenerator,
			$this->logger,
			$this->defaults,
			$this->throttler,
			$this->chain,
			$this->initialStateService,
			$this->webAuthnManager
		);
	}

	public function testLogoutWithoutToken() {
		$this->request
			->expects($this->once())
			->method('getCookie')
			->with('nc_token')
			->willReturn(null);
		$this->request
			->expects($this->once())
			->method('isUserAgent')
			->willReturn(false);
		$this->config
			->expects($this->never())
			->method('deleteUserValue');
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('core.login.showLoginForm')
			->willReturn('/login');

		$expected = new RedirectResponse('/login');
		$expected->addHeader('Clear-Site-Data', '"cache", "storage"');
		$this->assertEquals($expected, $this->loginController->logout());
	}

	public function testLogoutNoClearSiteData() {
		$this->request
			->expects($this->once())
			->method('getCookie')
			->with('nc_token')
			->willReturn(null);
		$this->request
			->expects($this->once())
			->method('isUserAgent')
			->willReturn(true);
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('core.login.showLoginForm')
			->willReturn('/login');

		$expected = new RedirectResponse('/login');
		$this->assertEquals($expected, $this->loginController->logout());
	}

	public function testLogoutWithToken() {
		$this->request
			->expects($this->once())
			->method('getCookie')
			->with('nc_token')
			->willReturn('MyLoginToken');
		$this->request
			->expects($this->once())
			->method('isUserAgent')
			->willReturn(false);
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('JohnDoe');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->config
			->expects($this->once())
			->method('deleteUserValue')
			->with('JohnDoe', 'login_token', 'MyLoginToken');
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('core.login.showLoginForm')
			->willReturn('/login');

		$expected = new RedirectResponse('/login');
		$expected->addHeader('Clear-Site-Data', '"cache", "storage"');
		$this->assertEquals($expected, $this->loginController->logout());
	}

	public function testShowLoginFormForLoggedInUsers() {
		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(true);
		$this->urlGenerator
			->expects($this->once())
			->method('linkToDefaultPageUrl')
			->willReturn('/default/foo');

		$expectedResponse = new RedirectResponse('/default/foo');
		$this->assertEquals($expectedResponse, $this->loginController->showLoginForm('', '', ''));
	}

	public function testShowLoginFormWithErrorsInSession() {
		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);
		$this->session
			->expects($this->once())
			->method('get')
			->with('loginMessages')
			->willReturn(
				[
					[
						'ErrorArray1',
						'ErrorArray2',
					],
					[
						'MessageArray1',
						'MessageArray2',
					],
				]
			);
		$this->initialStateService->expects($this->at(0))
			->method('provideInitialState')
			->with(
				'core',
				'loginMessages',
				[
					'MessageArray1',
					'MessageArray2',
				]
			);
		$this->initialStateService->expects($this->at(1))
			->method('provideInitialState')
			->with(
				'core',
				'loginErrors',
				[
					'ErrorArray1',
					'ErrorArray2',
				]
			);

		$expectedResponse = new TemplateResponse(
			'core',
			'login',
			[
				'alt_login' => [],
			],
			'guest'
		);
		$this->assertEquals($expectedResponse, $this->loginController->showLoginForm('', '', ''));
	}

	public function testShowLoginFormForFlowAuth() {
		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);
		$this->initialStateService->expects($this->at(2))
			->method('provideInitialState')
			->with(
				'core',
				'loginRedirectUrl',
				'login/flow'
			);

		$expectedResponse = new TemplateResponse(
			'core',
			'login',
			[
				'alt_login' => [],
			],
			'guest'
		);
		$this->assertEquals($expectedResponse, $this->loginController->showLoginForm('', 'login/flow', ''));
	}

	/**
	 * @return array
	 */
	public function passwordResetDataProvider() {
		return [
			[
				true,
				true,
			],
			[
				false,
				false,
			],
		];
	}

	/**
	 * @dataProvider passwordResetDataProvider
	 */
	public function testShowLoginFormWithPasswordResetOption($canChangePassword,
															 $expectedResult) {
		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);
		$this->config
			->expects($this->exactly(2))
			->method('getSystemValue')
			->willReturnMap([
				['login_form_autocomplete', true, true],
				['lost_password_link', '', false],
			]);
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('canChangePassword')
			->willReturn($canChangePassword);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('LdapUser')
			->willReturn($user);
		$this->initialStateService->expects($this->at(0))
			->method('provideInitialState')
			->with(
				'core',
				'loginUsername',
				'LdapUser'
			);
		$this->initialStateService->expects($this->at(4))
			->method('provideInitialState')
			->with(
				'core',
				'loginCanResetPassword',
				$expectedResult
			);

		$expectedResponse = new TemplateResponse(
			'core',
			'login',
			[
				'alt_login' => [],
			],
			'guest'
		);
		$this->assertEquals($expectedResponse, $this->loginController->showLoginForm('LdapUser', '', ''));
	}

	public function testShowLoginFormForUserNamed0() {
		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);
		$this->config
			->expects($this->exactly(2))
			->method('getSystemValue')
			->willReturnMap([
				['login_form_autocomplete', true, true],
				['lost_password_link', '', false],
			]);
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('canChangePassword')
			->willReturn(false);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('0')
			->willReturn($user);
		$this->initialStateService->expects($this->at(1))
			->method('provideInitialState')
			->with(
				'core',
				'loginAutocomplete',
				true
			);
		$this->initialStateService->expects($this->at(3))
			->method('provideInitialState')
			->with(
				'core',
				'loginResetPasswordLink',
				false
			);
		$this->initialStateService->expects($this->at(4))
			->method('provideInitialState')
			->with(
				'core',
				'loginCanResetPassword',
				false
			);

		$expectedResponse = new TemplateResponse(
			'core',
			'login',
			[
				'alt_login' => [],
			],
			'guest'
		);
		$this->assertEquals($expectedResponse, $this->loginController->showLoginForm('0', '', ''));
	}

	public function testLoginWithInvalidCredentials() {
		$user = 'MyUserName';
		$password = 'secret';
		$loginPageUrl = '/login?redirect_url=/apps/files';

		$this->request
			->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(true);
		$loginData = new LoginData(
			$this->request,
			$user,
			$password,
			'/apps/files'
		);
		$loginResult = LoginResult::failure($loginData, LoginController::LOGIN_MSG_INVALIDPASSWORD);
		$this->chain->expects($this->once())
			->method('process')
			->with($this->equalTo($loginData))
			->willReturn($loginResult);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.login.showLoginForm', [
				'user' => $user,
				'redirect_url' => '/apps/files',
				'direct' => 1,
			])
			->willReturn($loginPageUrl);
		$expected = new RedirectResponse($loginPageUrl);
		$expected->throttle(['user' => 'MyUserName']);

		$response = $this->loginController->tryLogin($user, $password, '/apps/files');

		$this->assertEquals($expected, $response);
	}

	public function testLoginWithValidCredentials() {
		$user = 'MyUserName';
		$password = 'secret';

		$this->request
			->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(true);
		$loginData = new LoginData(
			$this->request,
			$user,
			$password
		);
		$loginResult = LoginResult::success($loginData);
		$this->chain->expects($this->once())
			->method('process')
			->with($this->equalTo($loginData))
			->willReturn($loginResult);
		$this->urlGenerator
			->expects($this->once())
			->method('linkToDefaultPageUrl')
			->willReturn('/default/foo');

		$expected = new RedirectResponse('/default/foo');
		$this->assertEquals($expected, $this->loginController->tryLogin($user, $password));
	}

	public function testLoginWithoutPassedCsrfCheckAndNotLoggedIn() {
		/** @var IUser|MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('jane');
		$password = 'secret';
		$originalUrl = 'another%20url';

		$this->request
			->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(false);
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->with()
			->willReturn(false);
		$this->config->expects($this->never())
			->method('deleteUserValue');
		$this->userSession->expects($this->never())
			->method('createRememberMeToken');
		$this->urlGenerator
			->expects($this->once())
			->method('linkToDefaultPageUrl')
			->willReturn('/default/foo');

		$expected = new RedirectResponse('/default/foo');
		$this->assertEquals($expected, $this->loginController->tryLogin('Jane', $password, $originalUrl));
	}

	public function testLoginWithoutPassedCsrfCheckAndLoggedIn() {
		/** @var IUser|MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('jane');
		$password = 'secret';
		$originalUrl = 'another url';
		$redirectUrl = 'http://localhost/another url';

		$this->request
			->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(false);
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->with()
			->willReturn(true);
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with(urldecode($originalUrl))
			->willReturn($redirectUrl);
		$this->config->expects($this->never())
			->method('deleteUserValue');
		$this->userSession->expects($this->never())
			->method('createRememberMeToken');
		$this->config
			->method('getSystemValue')
			->with('remember_login_cookie_lifetime')
			->willReturn(1234);

		$expected = new RedirectResponse($redirectUrl);
		$this->assertEquals($expected, $this->loginController->tryLogin('Jane', $password, $originalUrl));
	}

	public function testLoginWithValidCredentialsAndRedirectUrl() {
		$user = 'MyUserName';
		$password = 'secret';
		$redirectUrl = 'https://next.cloud/apps/mail';

		$this->request
			->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(true);
		$loginData = new LoginData(
			$this->request,
			$user,
			$password,
			'/apps/mail'
		);
		$loginResult = LoginResult::success($loginData);
		$this->chain->expects($this->once())
			->method('process')
			->with($this->equalTo($loginData))
			->willReturn($loginResult);
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->willReturn(true);
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('/apps/mail')
			->willReturn($redirectUrl);
		$expected = new RedirectResponse($redirectUrl);

		$response = $this->loginController->tryLogin($user, $password, '/apps/mail');

		$this->assertEquals($expected, $response);
	}

	public function testToNotLeakLoginName() {
		$this->request
			->expects($this->once())
			->method('passesCSRFCheck')
			->willReturn(true);
		$loginPageUrl = '/login?redirect_url=/apps/files';
		$loginData = new LoginData(
			$this->request,
			'john@doe.com',
			'just wrong',
			'/apps/files'
		);
		$loginResult = LoginResult::failure($loginData, LoginController::LOGIN_MSG_INVALIDPASSWORD);
		$this->chain->expects($this->once())
			->method('process')
			->with($this->equalTo($loginData))
			->willReturnCallback(function (LoginData $data) use ($loginResult) {
				$data->setUsername('john');
				return $loginResult;
			});
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.login.showLoginForm', [
				'user' => 'john@doe.com',
				'redirect_url' => '/apps/files',
				'direct' => 1,
			])
			->willReturn($loginPageUrl);
		$expected = new RedirectResponse($loginPageUrl);
		$expected->throttle(['user' => 'john']);

		$response = $this->loginController->tryLogin(
			'john@doe.com',
			'just wrong',
			'/apps/files'
		);

		$this->assertEquals($expected, $response);
	}
}
