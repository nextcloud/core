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

namespace Test\Core\Controller;

use OC\Authentication\TwoFactorAuth\Manager;
use OC\Authentication\TwoFactorAuth\ProviderSet;
use OC\Core\Controller\TwoFactorChallengeController;
use OC_Util;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\Authentication\TwoFactorAuth\IActivatableAtLogin;
use OCP\Authentication\TwoFactorAuth\ILoginSetupProvider;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\TwoFactorException;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Template;
use Test\TestCase;

class TwoFactorChallengeControllerTest extends TestCase {

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;

	/** @var Manager|\PHPUnit\Framework\MockObject\MockObject */
	private $twoFactorManager;

	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;

	/** @var ISession|\PHPUnit\Framework\MockObject\MockObject */
	private $session;

	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;

	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;

	/** @var TwoFactorChallengeController|\PHPUnit\Framework\MockObject\MockObject */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->twoFactorManager = $this->createMock(Manager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->session = $this->createMock(ISession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->controller = $this->getMockBuilder(TwoFactorChallengeController::class)
			->setConstructorArgs([
				'core',
				$this->request,
				$this->twoFactorManager,
				$this->userSession,
				$this->session,
				$this->urlGenerator,
				$this->logger,
			])
			->setMethods(['getLogoutUrl'])
			->getMock();
		$this->controller->expects($this->any())
			->method('getLogoutUrl')
			->willReturn('logoutAttribute');
	}

	public function testSelectChallenge() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$p1 = $this->createMock(IActivatableAtLogin::class);
		$p1->method('getId')->willReturn('p1');
		$backupProvider = $this->createMock(IProvider::class);
		$backupProvider->method('getId')->willReturn('backup_codes');
		$providerSet = new ProviderSet([$p1, $backupProvider], true);
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($user)
			->willReturn([$p1]);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->with($user)
			->willReturn($providerSet);

		$expected = new StandaloneTemplateResponse('core', 'twofactorselectchallenge', [
			'providers' => [
				$p1,
			],
			'providerMissing' => true,
			'backupProvider' => $backupProvider,
			'redirect_url' => '/some/url',
			'logout_url' => 'logoutAttribute',
			'hasSetupProviders' => true,
		], 'guest');

		$this->assertEquals($expected, $this->controller->selectChallenge('/some/url'));
	}

	public function testShowChallenge() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);
		$provider->method('getId')->willReturn('myprovider');
		$backupProvider = $this->createMock(IProvider::class);
		$backupProvider->method('getId')->willReturn('backup_codes');
		$tmpl = $this->createMock(Template::class);
		$providerSet = new ProviderSet([$provider, $backupProvider], true);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->with($user)
			->willReturn($providerSet);
		$provider->expects($this->once())
			->method('getId')
			->willReturn('u2f');
		$backupProvider->expects($this->once())
			->method('getId')
			->willReturn('backup_codes');

		$this->session->expects($this->once())
			->method('exists')
			->with('two_factor_auth_error')
			->willReturn(true);
		$this->session->expects($this->exactly(2))
			->method('remove')
			->with($this->logicalOr($this->equalTo('two_factor_auth_error'), $this->equalTo('two_factor_auth_error_message')));
		$provider->expects($this->once())
			->method('getTemplate')
			->with($user)
			->willReturn($tmpl);
		$tmpl->expects($this->once())
			->method('fetchPage')
			->willReturn('<html/>');

		$expected = new StandaloneTemplateResponse('core', 'twofactorshowchallenge', [
			'error' => true,
			'provider' => $provider,
			'backupProvider' => $backupProvider,
			'logout_url' => 'logoutAttribute',
			'template' => '<html/>',
			'redirect_url' => '/re/dir/ect/url',
			'error_message' => null,
		], 'guest');

		$this->assertEquals($expected, $this->controller->showChallenge('myprovider', '/re/dir/ect/url'));
	}

	public function testShowInvalidChallenge() {
		$user = $this->createMock(IUser::class);
		$providerSet = new ProviderSet([], false);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->with($user)
			->willReturn($providerSet);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.TwoFactorChallenge.selectChallenge')
			->willReturn('select/challenge/url');

		$expected = new RedirectResponse('select/challenge/url');

		$this->assertEquals($expected, $this->controller->showChallenge('myprovider', 'redirect/url'));
	}

	public function testSolveChallenge() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('getProvider')
			->with($user, 'myprovider')
			->willReturn($provider);

		$this->twoFactorManager->expects($this->once())
			->method('verifyChallenge')
			->with('myprovider', $user, 'token')
			->willReturn(true);

		$expected = new RedirectResponse(OC_Util::getDefaultPageUrl());
		$this->assertEquals($expected, $this->controller->solveChallenge('myprovider', 'token'));
	}

	public function testSolveValidChallengeAndRedirect() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('getProvider')
			->with($user, 'myprovider')
			->willReturn($provider);

		$this->twoFactorManager->expects($this->once())
			->method('verifyChallenge')
			->with('myprovider', $user, 'token')
			->willReturn(true);
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('redirect url')
			->willReturn('redirect/url');

		$expected = new RedirectResponse('redirect/url');
		$this->assertEquals($expected, $this->controller->solveChallenge('myprovider', 'token', 'redirect%20url'));
	}

	public function testSolveChallengeInvalidProvider() {
		$user = $this->getMockBuilder(IUser::class)->getMock();

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('getProvider')
			->with($user, 'myprovider')
			->willReturn(null);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.TwoFactorChallenge.selectChallenge')
			->willReturn('select/challenge/url');

		$expected = new RedirectResponse('select/challenge/url');

		$this->assertEquals($expected, $this->controller->solveChallenge('myprovider', 'token'));
	}

	public function testSolveInvalidChallenge() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('getProvider')
			->with($user, 'myprovider')
			->willReturn($provider);

		$this->twoFactorManager->expects($this->once())
			->method('verifyChallenge')
			->with('myprovider', $user, 'token')
			->willReturn(false);
		$this->session->expects($this->once())
			->method('set')
			->with('two_factor_auth_error', true);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.TwoFactorChallenge.showChallenge', [
				'challengeProviderId' => 'myprovider',
				'redirect_url' => '/url',
			])
			->willReturn('files/index/url');
		$provider->expects($this->once())
			->method('getId')
			->willReturn('myprovider');

		$expected = new RedirectResponse('files/index/url');
		$this->assertEquals($expected, $this->controller->solveChallenge('myprovider', 'token', '/url'));
	}

	public function testSolveChallengeTwoFactorException() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);
		$exception = new TwoFactorException("2FA failed");

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->twoFactorManager->expects($this->once())
			->method('getProvider')
			->with($user, 'myprovider')
			->willReturn($provider);

		$this->twoFactorManager->expects($this->once())
			->method('verifyChallenge')
			->with('myprovider', $user, 'token')
			->will($this->throwException($exception));
		$this->session->expects($this->at(0))
			->method('set')
			->with('two_factor_auth_error_message', "2FA failed");
		$this->session->expects($this->at(1))
			->method('set')
			->with('two_factor_auth_error', true);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.TwoFactorChallenge.showChallenge', [
				'challengeProviderId' => 'myprovider',
				'redirect_url' => '/url',
			])
			->willReturn('files/index/url');
		$provider->expects($this->once())
			->method('getId')
			->willReturn('myprovider');

		$expected = new RedirectResponse('files/index/url');
		$this->assertEquals($expected, $this->controller->solveChallenge('myprovider', 'token', '/url'));
	}

	public function testSetUpProviders() {
		$user = $this->createMock(IUser::class);
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$provider = $this->createMock(IActivatableAtLogin::class);
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($user)
			->willReturn([
				$provider,
			]);
		$expected = new StandaloneTemplateResponse(
			'core',
			'twofactorsetupselection',
			[
				'providers' => [
					$provider,
				],
				'logout_url' => 'logoutAttribute',
			],
			'guest'
		);

		$response = $this->controller->setupProviders();

		$this->assertEquals($expected, $response);
	}

	public function testSetUpInvalidProvider() {
		$user = $this->createMock(IUser::class);
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$provider = $this->createMock(IActivatableAtLogin::class);
		$provider->expects($this->any())
			->method('getId')
			->willReturn('prov1');
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($user)
			->willReturn([
				$provider,
			]);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.TwoFactorChallenge.selectChallenge')
			->willReturn('2fa/select/page');
		$expected = new RedirectResponse('2fa/select/page');

		$response = $this->controller->setupProvider('prov2');

		$this->assertEquals($expected, $response);
	}

	public function testSetUpProvider() {
		$user = $this->createMock(IUser::class);
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$provider = $this->createMock(IActivatableAtLogin::class);
		$provider->expects($this->any())
			->method('getId')
			->willReturn('prov1');
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($user)
			->willReturn([
				$provider,
			]);
		$loginSetup = $this->createMock(ILoginSetupProvider::class);
		$provider->expects($this->any())
			->method('getLoginSetup')
			->with($user)
			->willReturn($loginSetup);
		$tmpl = $this->createMock(Template::class);
		$loginSetup->expects($this->once())
			->method('getBody')
			->willReturn($tmpl);
		$tmpl->expects($this->once())
			->method('fetchPage')
			->willReturn('tmpl');
		$expected = new StandaloneTemplateResponse(
			'core',
			'twofactorsetupchallenge',
			[
				'provider' => $provider,
				'logout_url' => 'logoutAttribute',
				'template' => 'tmpl',
			],
			'guest'
		);

		$response = $this->controller->setupProvider('prov1');

		$this->assertEquals($expected, $response);
	}

	public function testConfirmProviderSetup() {
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.TwoFactorChallenge.showChallenge',
				[
					'challengeProviderId' => 'totp',
				])
			->willReturn('2fa/select/page');
		$expected = new RedirectResponse('2fa/select/page');

		$response = $this->controller->confirmProviderSetup('totp');

		$this->assertEquals($expected, $response);
	}
}
