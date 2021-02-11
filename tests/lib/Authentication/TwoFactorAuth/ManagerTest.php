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

namespace Test\Authentication\TwoFactorAuth;

use OC;
use OC\Authentication\Token\IProvider as TokenProvider;
use OC\Authentication\TwoFactorAuth\Manager;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OC\Authentication\TwoFactorAuth\ProviderLoader;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\TwoFactorAuth\IActivatableAtLogin;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IConfig;
use OCP\ISession;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use function reset;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class ManagerTest extends TestCase {

	/** @var IUser|MockObject */
	private $user;

	/** @var ProviderLoader|MockObject */
	private $providerLoader;

	/** @var IRegistry|MockObject */
	private $providerRegistry;

	/** @var MandatoryTwoFactor|MockObject */
	private $mandatoryTwoFactor;

	/** @var ISession|MockObject */
	private $session;

	/** @var Manager */
	private $manager;

	/** @var IConfig|MockObject */
	private $config;

	/** @var IManager|MockObject */
	private $activityManager;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IProvider|MockObject */
	private $fakeProvider;

	/** @var IProvider|MockObject */
	private $backupProvider;

	/** @var TokenProvider|MockObject */
	private $tokenProvider;

	/** @var ITimeFactory|MockObject */
	private $timeFactory;

	/** @var EventDispatcherInterface|MockObject */
	private $eventDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->providerLoader = $this->createMock(ProviderLoader::class);
		$this->providerRegistry = $this->createMock(IRegistry::class);
		$this->mandatoryTwoFactor = $this->createMock(MandatoryTwoFactor::class);
		$this->session = $this->createMock(ISession::class);
		$this->config = $this->createMock(IConfig::class);
		$this->activityManager = $this->createMock(IManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->tokenProvider = $this->createMock(TokenProvider::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

		$this->manager = new Manager(
			$this->providerLoader,
			$this->providerRegistry,
			$this->mandatoryTwoFactor,
			$this->session,
			$this->config,
			$this->activityManager,
			$this->logger,
			$this->tokenProvider,
			$this->timeFactory,
			$this->eventDispatcher
		);

		$this->fakeProvider = $this->createMock(IProvider::class);
		$this->fakeProvider->method('getId')->willReturn('email');

		$this->backupProvider = $this->getMockBuilder('\OCP\Authentication\TwoFactorAuth\IProvider')->getMock();
		$this->backupProvider->method('getId')->willReturn('backup_codes');
		$this->backupProvider->method('isTwoFactorAuthEnabledForUser')->willReturn(true);
	}

	private function prepareNoProviders() {
		$this->providerLoader->method('getProviders')
			->with($this->user)
			->willReturn([]);
	}

	private function prepareProviders() {
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->with($this->user)
			->willReturn([
				$this->fakeProvider->getId() => true,
			]);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($this->user)
			->willReturn([$this->fakeProvider]);
	}

	private function prepareProvidersWitBackupProvider() {
		$this->providerLoader->method('getProviders')
			->with($this->user)
			->willReturn([
				$this->fakeProvider,
				$this->backupProvider,
			]);
	}

	public function testIsTwoFactorAuthenticatedEnforced() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(true);

		$enabled = $this->manager->isTwoFactorAuthenticated($this->user);

		$this->assertTrue($enabled);
	}

	public function testIsTwoFactorAuthenticatedNoProviders() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(false);
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->willReturn([]); // No providers registered
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->willReturn([]); // No providers loadable

		$this->assertFalse($this->manager->isTwoFactorAuthenticated($this->user));
	}

	public function testIsTwoFactorAuthenticatedOnlyBackupCodes() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(false);
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->willReturn([
				'backup_codes' => true,
			]);
		$backupCodesProvider = $this->createMock(IProvider::class);
		$backupCodesProvider
			->method('getId')
			->willReturn('backup_codes');
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->willReturn([
				$backupCodesProvider,
			]);

		$this->assertFalse($this->manager->isTwoFactorAuthenticated($this->user));
	}

	public function testIsTwoFactorAuthenticatedFailingProviders() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('isEnforcedFor')
			->with($this->user)
			->willReturn(false);
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->willReturn([
				'twofactor_totp' => true,
				'twofactor_u2f' => false,
			]); // Two providers registered, but …
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->willReturn([]); // … none of them is able to load, however …

		// … 2FA is still enforced
		$this->assertTrue($this->manager->isTwoFactorAuthenticated($this->user));
	}

	public function providerStatesFixData(): array {
		return [
			[false, false],
			[true, true],
		];
	}

	/**
	 * If the 2FA registry has not been populated when a user logs in,
	 * the 2FA manager has to first fix the state before it checks for
	 * enabled providers.
	 *
	 * If any of these providers is active, 2FA is enabled
	 *
	 * @dataProvider providerStatesFixData
	 */
	public function testIsTwoFactorAuthenticatedFixesProviderStates(bool $providerEnabled, bool $expected) {
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->willReturn([]); // Nothing registered yet
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->willReturn([
				$this->fakeProvider
			]);
		$this->fakeProvider->expects($this->once())
			->method('isTwoFactorAuthEnabledForUser')
			->with($this->user)
			->willReturn($providerEnabled);
		if ($providerEnabled) {
			$this->providerRegistry->expects($this->once())
				->method('enableProviderFor')
				->with(
					$this->fakeProvider,
					$this->user
				);
		} else {
			$this->providerRegistry->expects($this->once())
				->method('disableProviderFor')
				->with(
					$this->fakeProvider,
					$this->user
				);
		}

		$this->assertEquals($expected, $this->manager->isTwoFactorAuthenticated($this->user));
	}

	public function testGetProvider() {
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->with($this->user)
			->willReturn([
				$this->fakeProvider->getId() => true,
			]);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($this->user)
			->willReturn([$this->fakeProvider]);

		$provider = $this->manager->getProvider($this->user, $this->fakeProvider->getId());

		$this->assertSame($this->fakeProvider, $provider);
	}

	public function testGetInvalidProvider() {
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->with($this->user)
			->willReturn([]);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($this->user)
			->willReturn([]);

		$provider = $this->manager->getProvider($this->user, 'nonexistent');

		$this->assertNull($provider);
	}

	public function testGetLoginSetupProviders() {
		$provider1 = $this->createMock(IProvider::class);
		$provider2 = $this->createMock(IActivatableAtLogin::class);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($this->user)
			->willReturn([
				$provider1,
				$provider2,
			]);

		$providers = $this->manager->getLoginSetupProviders($this->user);

		$this->assertCount(1, $providers);
		$this->assertSame($provider2, reset($providers));
	}

	public function testGetProviders() {
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->with($this->user)
			->willReturn([
				$this->fakeProvider->getId() => true,
			]);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($this->user)
			->willReturn([$this->fakeProvider]);
		$expectedProviders = [
			'email' => $this->fakeProvider,
		];

		$providerSet = $this->manager->getProviderSet($this->user);
		$providers = $providerSet->getProviders();

		$this->assertEquals($expectedProviders, $providers);
		$this->assertFalse($providerSet->isProviderMissing());
	}

	public function testGetProvidersOneMissing() {
		$this->providerRegistry->expects($this->once())
			->method('getProviderStates')
			->with($this->user)
			->willReturn([
				$this->fakeProvider->getId() => true,
			]);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($this->user)
			->willReturn([]);
		$expectedProviders = [
			'email' => $this->fakeProvider,
		];

		$providerSet = $this->manager->getProviderSet($this->user);

		$this->assertTrue($providerSet->isProviderMissing());
	}

	public function testVerifyChallenge() {
		$this->prepareProviders();

		$challenge = 'passme';
		$event = $this->createMock(IEvent::class);
		$this->fakeProvider->expects($this->once())
			->method('verifyChallenge')
			->with($this->user, $challenge)
			->willReturn(true);
		$this->session->expects($this->once())
			->method('get')
			->with('two_factor_remember_login')
			->willReturn(false);
		$this->session->expects($this->at(1))
			->method('remove')
			->with('two_factor_auth_uid');
		$this->session->expects($this->at(2))
			->method('remove')
			->with('two_factor_remember_login');
		$this->session->expects($this->at(3))
			->method('set')
			->with(Manager::SESSION_UID_DONE, 'jos');
		$this->session->method('getId')
			->willReturn('mysessionid');
		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('jos');
		$event->expects($this->once())
			->method('setApp')
			->with($this->equalTo('core'))
			->willReturnSelf();
		$event->expects($this->once())
			->method('setType')
			->with($this->equalTo('security'))
			->willReturnSelf();
		$event->expects($this->once())
			->method('setAuthor')
			->with($this->equalTo('jos'))
			->willReturnSelf();
		$event->expects($this->once())
			->method('setAffectedUser')
			->with($this->equalTo('jos'))
			->willReturnSelf();
		$this->fakeProvider
			->method('getDisplayName')
			->willReturn('Fake 2FA');
		$event->expects($this->once())
			->method('setSubject')
			->with($this->equalTo('twofactor_success'), $this->equalTo([
				'provider' => 'Fake 2FA',
			]))
			->willReturnSelf();
		$token = $this->createMock(OC\Authentication\Token\IToken::class);
		$this->tokenProvider->method('getToken')
			->with('mysessionid')
			->willReturn($token);
		$token->method('getId')
			->willReturn(42);
		$this->config->expects($this->once())
			->method('deleteUserValue')
			->with('jos', 'login_token_2fa', '42');

		$result = $this->manager->verifyChallenge('email', $this->user, $challenge);

		$this->assertTrue($result);
	}

	public function testVerifyChallengeInvalidProviderId() {
		$this->prepareProviders();

		$challenge = 'passme';
		$this->fakeProvider->expects($this->never())
			->method('verifyChallenge')
			->with($this->user, $challenge);
		$this->session->expects($this->never())
			->method('remove');

		$this->assertFalse($this->manager->verifyChallenge('dontexist', $this->user, $challenge));
	}

	public function testVerifyInvalidChallenge() {
		$this->prepareProviders();

		$challenge = 'dontpassme';
		$event = $this->createMock(IEvent::class);
		$this->fakeProvider->expects($this->once())
			->method('verifyChallenge')
			->with($this->user, $challenge)
			->willReturn(false);
		$this->session->expects($this->never())
			->method('remove');
		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('jos');
		$event->expects($this->once())
			->method('setApp')
			->with($this->equalTo('core'))
			->willReturnSelf();
		$event->expects($this->once())
			->method('setType')
			->with($this->equalTo('security'))
			->willReturnSelf();
		$event->expects($this->once())
			->method('setAuthor')
			->with($this->equalTo('jos'))
			->willReturnSelf();
		$event->expects($this->once())
			->method('setAffectedUser')
			->with($this->equalTo('jos'))
			->willReturnSelf();
		$this->fakeProvider
			->method('getDisplayName')
			->willReturn('Fake 2FA');
		$event->expects($this->once())
			->method('setSubject')
			->with($this->equalTo('twofactor_failed'), $this->equalTo([
				'provider' => 'Fake 2FA',
			]))
			->willReturnSelf();

		$this->assertFalse($this->manager->verifyChallenge('email', $this->user, $challenge));
	}

	public function testNeedsSecondFactor() {
		$user = $this->createMock(IUser::class);
		$this->session->expects($this->at(0))
			->method('exists')
			->with('app_password')
			->willReturn(false);
		$this->session->expects($this->at(1))
			->method('exists')
			->with('two_factor_auth_uid')
			->willReturn(false);
		$this->session->expects($this->at(2))
			->method('exists')
			->with(Manager::SESSION_UID_DONE)
			->willReturn(false);

		$this->session->method('getId')
			->willReturn('mysessionid');
		$token = $this->createMock(OC\Authentication\Token\IToken::class);
		$this->tokenProvider->method('getToken')
			->with('mysessionid')
			->willReturn($token);
		$token->method('getId')
			->willReturn(42);

		$user->method('getUID')
			->willReturn('user');
		$this->config->method('getUserKeys')
			->with('user', 'login_token_2fa')
			->willReturn([
				'42'
			]);

		$manager = $this->getMockBuilder(Manager::class)
			->setConstructorArgs([
				$this->providerLoader,
				$this->providerRegistry,
				$this->mandatoryTwoFactor,
				$this->session,
				$this->config,
				$this->activityManager,
				$this->logger,
				$this->tokenProvider,
				$this->timeFactory,
				$this->eventDispatcher
			])
			->setMethods(['loadTwoFactorApp', 'isTwoFactorAuthenticated'])// Do not actually load the apps
			->getMock();

		$manager->method('isTwoFactorAuthenticated')
			->with($user)
			->willReturn(true);

		$this->assertTrue($manager->needsSecondFactor($user));
	}

	public function testNeedsSecondFactorUserIsNull() {
		$user = null;
		$this->session->expects($this->never())
			->method('exists');

		$this->assertFalse($this->manager->needsSecondFactor($user));
	}

	public function testNeedsSecondFactorWithNoProviderAvailableAnymore() {
		$this->prepareNoProviders();

		$user = null;
		$this->session->expects($this->never())
			->method('exists')
			->with('two_factor_auth_uid')
			->willReturn(true);
		$this->session->expects($this->never())
			->method('remove')
			->with('two_factor_auth_uid');

		$this->assertFalse($this->manager->needsSecondFactor($user));
	}

	public function testPrepareTwoFactorLogin() {
		$this->user->method('getUID')
			->willReturn('ferdinand');

		$this->session->expects($this->at(0))
			->method('set')
			->with('two_factor_auth_uid', 'ferdinand');
		$this->session->expects($this->at(1))
			->method('set')
			->with('two_factor_remember_login', true);

		$this->session->method('getId')
			->willReturn('mysessionid');
		$token = $this->createMock(OC\Authentication\Token\IToken::class);
		$this->tokenProvider->method('getToken')
			->with('mysessionid')
			->willReturn($token);
		$token->method('getId')
			->willReturn(42);

		$this->timeFactory->method('getTime')
			->willReturn(1337);

		$this->config->method('setUserValue')
			->with('ferdinand', 'login_token_2fa', '42', '1337');


		$this->manager->prepareTwoFactorLogin($this->user, true);
	}

	public function testPrepareTwoFactorLoginDontRemember() {
		$this->user->method('getUID')
			->willReturn('ferdinand');

		$this->session->expects($this->at(0))
			->method('set')
			->with('two_factor_auth_uid', 'ferdinand');
		$this->session->expects($this->at(1))
			->method('set')
			->with('two_factor_remember_login', false);

		$this->session->method('getId')
			->willReturn('mysessionid');
		$token = $this->createMock(OC\Authentication\Token\IToken::class);
		$this->tokenProvider->method('getToken')
			->with('mysessionid')
			->willReturn($token);
		$token->method('getId')
			->willReturn(42);

		$this->timeFactory->method('getTime')
			->willReturn(1337);

		$this->config->method('setUserValue')
			->with('ferdinand', 'login_token_2fa', '42', '1337');

		$this->manager->prepareTwoFactorLogin($this->user, false);
	}

	public function testNeedsSecondFactorSessionAuth() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('user');

		$this->session->method('exists')
			->willReturnCallback(function ($var) {
				if ($var === Manager::SESSION_UID_KEY) {
					return false;
				} elseif ($var === 'app_password') {
					return false;
				}
				return true;
			});
		$this->session->expects($this->once())
			->method('get')
			->with(Manager::SESSION_UID_DONE)
			->willReturn('user');

		$this->assertFalse($this->manager->needsSecondFactor($user));
	}

	public function testNeedsSecondFactorSessionAuthFailDBPass() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('user');

		$this->session->method('exists')
			->willReturn(false);
		$this->session->method('getId')
			->willReturn('mysessionid');

		$token = $this->createMock(OC\Authentication\Token\IToken::class);
		$token->method('getId')
			->willReturn(40);

		$this->tokenProvider->method('getToken')
			->with('mysessionid')
			->willReturn($token);

		$this->config->method('getUserKeys')
			->with('user', 'login_token_2fa')
			->willReturn([
				'42', '43', '44'
			]);

		$this->session->expects($this->once())
			->method('set')
			->with(Manager::SESSION_UID_DONE, 'user');

		$this->assertFalse($this->manager->needsSecondFactor($user));
	}

	public function testNeedsSecondFactorInvalidToken() {
		$this->prepareNoProviders();

		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('user');

		$this->session->method('exists')
			->willReturn(false);
		$this->session->method('getId')
			->willReturn('mysessionid');

		$this->tokenProvider->method('getToken')
			->with('mysessionid')
			->willThrowException(new OC\Authentication\Exceptions\InvalidTokenException());

		$this->config->method('getUserKeys')->willReturn([]);

		$this->assertFalse($this->manager->needsSecondFactor($user));
	}

	public function testNeedsSecondFactorAppPassword() {
		$user = $this->createMock(IUser::class);
		$this->session->method('exists')
			->with('app_password')
			->willReturn(true);

		$this->assertFalse($this->manager->needsSecondFactor($user));
	}
}
