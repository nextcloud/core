<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace lib\Authentication\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\ProviderLoader;
use OC\Authentication\TwoFactorAuth\ProviderManager;
use OCP\Authentication\TwoFactorAuth\IActivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\IDeactivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ProviderManagerTest extends TestCase {

	/** @var ProviderLoader|MockObject */
	private $providerLoader;

	/** @var IRegistry|MockObject */
	private $registry;

	/** @var ProviderManager */
	private $providerManager;

	protected function setUp(): void {
		parent::setUp();

		$this->providerLoader = $this->createMock(ProviderLoader::class);
		$this->registry = $this->createMock(IRegistry::class);

		$this->providerManager = new ProviderManager(
			$this->providerLoader,
			$this->registry
		);
	}

	
	public function testTryEnableInvalidProvider() {
		$this->expectException(\OC\Authentication\Exceptions\InvalidProviderException::class);

		$user = $this->createMock(IUser::class);
		$this->providerManager->tryEnableProviderFor('none', $user);
	}

	public function testTryEnableUnsupportedProvider() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($user)
			->willReturn([
				'u2f' => $provider,
			]);
		$this->registry->expects($this->never())
			->method('enableProviderFor');

		$res = $this->providerManager->tryEnableProviderFor('u2f', $user);

		$this->assertFalse($res);
	}

	public function testTryEnableProvider() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IActivatableByAdmin::class);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($user)
			->willReturn([
				'u2f' => $provider,
			]);
		$provider->expects($this->once())
			->method('enableFor')
			->with($user);
		$this->registry->expects($this->once())
			->method('enableProviderFor')
			->with($provider, $user);

		$res = $this->providerManager->tryEnableProviderFor('u2f', $user);

		$this->assertTrue($res);
	}

	
	public function testTryDisableInvalidProvider() {
		$this->expectException(\OC\Authentication\Exceptions\InvalidProviderException::class);

		$user = $this->createMock(IUser::class);
		$this->providerManager->tryDisableProviderFor('none', $user);
	}

	public function testTryDisableUnsupportedProvider() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($user)
			->willReturn([
				'u2f' => $provider,
			]);
		$this->registry->expects($this->never())
			->method('disableProviderFor');

		$res = $this->providerManager->tryDisableProviderFor('u2f', $user);

		$this->assertFalse($res);
	}

	public function testTryDisableProvider() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IDeactivatableByAdmin::class);
		$this->providerLoader->expects($this->once())
			->method('getProviders')
			->with($user)
			->willReturn([
				'u2f' => $provider,
			]);
		$provider->expects($this->once())
			->method('disableFor')
			->with($user);
		$this->registry->expects($this->once())
			->method('disableProviderFor')
			->with($provider, $user);

		$res = $this->providerManager->tryDisableProviderFor('u2f', $user);

		$this->assertTrue($res);
	}

}
