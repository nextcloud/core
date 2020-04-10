<?php

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

declare(strict_types=1);

namespace lib\Authentication\Login;

use OC\Authentication\Login\TwoFactorCommand;
use OC\Authentication\TwoFactorAuth\Manager;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OC\Authentication\TwoFactorAuth\ProviderSet;
use OCP\Authentication\TwoFactorAuth\IActivatableAtLogin;
use OCP\Authentication\TwoFactorAuth\IProvider as ITwoFactorAuthProvider;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;

class TwoFactorCommandTest extends ALoginCommandTest {

	/** @var Manager|MockObject */
	private $twoFactorManager;

	/** @var MandatoryTwoFactor|MockObject */
	private $mandatoryTwoFactor;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	protected function setUp(): void {
		parent::setUp();

		$this->twoFactorManager = $this->createMock(Manager::class);
		$this->mandatoryTwoFactor = $this->createMock(MandatoryTwoFactor::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->cmd = new TwoFactorCommand(
			$this->twoFactorManager,
			$this->mandatoryTwoFactor,
			$this->urlGenerator
		);
	}

	public function testNotTwoFactorAuthenticated() {
		$data = $this->getLoggedInLoginData();
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->willReturn(false);
		$this->twoFactorManager->expects($this->never())
			->method('prepareTwoFactorLogin');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
	}

	public function testProcessOneActiveProvider() {
		$data = $this->getLoggedInLoginData();
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('prepareTwoFactorLogin')
			->with(
				$this->user,
				$data->isRememberLogin()
			);
		$provider = $this->createMock(ITwoFactorAuthProvider::class);
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->willReturn(new ProviderSet([
				$provider,
			], false));
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($this->user)
			->willReturn([]);
		$this->mandatoryTwoFactor->expects($this->any())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(false);
		$provider->expects($this->once())
			->method('getId')
			->willReturn('test');
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.TwoFactorChallenge.showChallenge',
				[
					'challengeProviderId' => 'test'
				]
			)
			->willReturn('two/factor/url');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertEquals('two/factor/url', $result->getRedirectUrl());
	}

	public function testProcessMissingProviders() {
		$data = $this->getLoggedInLoginData();
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('prepareTwoFactorLogin')
			->with(
				$this->user,
				$data->isRememberLogin()
			);
		$provider = $this->createMock(ITwoFactorAuthProvider::class);
		$provider->expects($this->once())
			->method('getId')
			->willReturn('test1');
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->willReturn(new ProviderSet([
				$provider,
			], true));
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($this->user)
			->willReturn([]);
		$this->mandatoryTwoFactor->expects($this->any())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(false);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.TwoFactorChallenge.selectChallenge'
			)
			->willReturn('two/factor/url');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertEquals('two/factor/url', $result->getRedirectUrl());
	}

	public function testProcessTwoActiveProviders() {
		$data = $this->getLoggedInLoginData();
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('prepareTwoFactorLogin')
			->with(
				$this->user,
				$data->isRememberLogin()
			);
		$provider1 = $this->createMock(ITwoFactorAuthProvider::class);
		$provider2 = $this->createMock(ITwoFactorAuthProvider::class);
		$provider1->expects($this->once())
			->method('getId')
			->willReturn('test1');
		$provider2->expects($this->once())
			->method('getId')
			->willReturn('test2');
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->willReturn(new ProviderSet([
				$provider1,
				$provider2,
			], false));
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($this->user)
			->willReturn([]);
		$this->mandatoryTwoFactor->expects($this->any())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(false);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.TwoFactorChallenge.selectChallenge'
			)
			->willReturn('two/factor/url');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertEquals('two/factor/url', $result->getRedirectUrl());
	}

	public function testProcessFailingProviderAndEnforcedButNoSetupProviders() {
		$data = $this->getLoggedInLoginData();
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('prepareTwoFactorLogin')
			->with(
				$this->user,
				$data->isRememberLogin()
			);
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->willReturn(new ProviderSet([], true));
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($this->user)
			->willReturn([]);
		$this->mandatoryTwoFactor->expects($this->any())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(true);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.TwoFactorChallenge.selectChallenge'
			)
			->willReturn('two/factor/url');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertEquals('two/factor/url', $result->getRedirectUrl());
	}

	public function testProcessFailingProviderAndEnforced() {
		$data = $this->getLoggedInLoginData();
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('prepareTwoFactorLogin')
			->with(
				$this->user,
				$data->isRememberLogin()
			);
		$provider = $this->createMock(IActivatableAtLogin::class);
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->willReturn(new ProviderSet([
				$provider,
			], true));
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($this->user)
			->willReturn([]);
		$this->mandatoryTwoFactor->expects($this->any())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(true);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.TwoFactorChallenge.selectChallenge'
			)
			->willReturn('two/factor/url');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertEquals('two/factor/url', $result->getRedirectUrl());
	}

	public function testProcessNoProvidersButEnforced() {
		$data = $this->getLoggedInLoginData();
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('prepareTwoFactorLogin')
			->with(
				$this->user,
				$data->isRememberLogin()
			);
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->willReturn(new ProviderSet([], false));
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($this->user)
			->willReturn([]);
		$this->mandatoryTwoFactor->expects($this->any())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(true);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.TwoFactorChallenge.selectChallenge'
			)
			->willReturn('two/factor/url');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertEquals('two/factor/url', $result->getRedirectUrl());
	}

	public function testProcessWithRedirectUrl() {
		$data = $this->getLoggedInLoginDataWithRedirectUrl();
		$this->twoFactorManager->expects($this->once())
			->method('isTwoFactorAuthenticated')
			->willReturn(true);
		$this->twoFactorManager->expects($this->once())
			->method('prepareTwoFactorLogin')
			->with(
				$this->user,
				$data->isRememberLogin()
			);
		$provider = $this->createMock(ITwoFactorAuthProvider::class);
		$this->twoFactorManager->expects($this->once())
			->method('getProviderSet')
			->willReturn(new ProviderSet([
				$provider,
			], false));
		$this->twoFactorManager->expects($this->once())
			->method('getLoginSetupProviders')
			->with($this->user)
			->willReturn([]);
		$this->mandatoryTwoFactor->expects($this->any())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(false);
		$provider->expects($this->once())
			->method('getId')
			->willReturn('test');
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with(
				'core.TwoFactorChallenge.showChallenge',
				[
					'challengeProviderId' => 'test',
					'redirect_url' => $this->redirectUrl,
				]
			)
			->willReturn('two/factor/url');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertEquals('two/factor/url', $result->getRedirectUrl());
	}

}
