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
use OCP\App\IAppManager;
use OCP\Authentication\TwoFactorAuth\IProvider;
use PHPUnit_Framework_MockObject_MockObject;
use Test\TestCase;

class ProviderLoaderTest extends TestCase {

	/** @var IAppManager|PHPUnit_Framework_MockObject_MockObject */
	private $appManager;

	/** @var IUser|PHPUnit_Framework_MockObject_MockObject */
	private $user;

	/** @var ProviderLoader */
	private $loader;

	protected function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(IAppManager::class);
		$this->user = $this->createMock(\OCP\IUser::class);

		$this->loader = new ProviderLoader($this->appManager);
	}

	
	public function testFailHardIfProviderCanNotBeLoaded() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Could not load two-factor auth provider \\OCA\\MyFaulty2faApp\\DoesNotExist');

		$this->appManager->expects($this->once())
			->method('getEnabledAppsForUser')
			->with($this->user)
			->willReturn(['mail', 'twofactor_totp']);
		$this->appManager
			->method('getAppInfo')
			->willReturnMap([
				['mail', false, null, []],
				['twofactor_totp', false, null, [
					'two-factor-providers' => [
						'\\OCA\\MyFaulty2faApp\\DoesNotExist',
					],
				]],
			]);

		$this->loader->getProviders($this->user);
	}

	public function testGetProviders() {
		$provider = $this->createMock(IProvider::class);
		$provider->method('getId')->willReturn('test');
		\OC::$server->registerService('\\OCA\\TwoFactorTest\\Provider', function () use ($provider) {
			return $provider;
		});
		$this->appManager->expects($this->once())
			->method('getEnabledAppsForUser')
			->with($this->user)
			->willReturn(['twofactor_test']);
		$this->appManager
			->method('getAppInfo')
			->with('twofactor_test')
			->willReturn(['two-factor-providers' => [
				'\\OCA\\TwoFactorTest\\Provider',
			]]);

		$providers = $this->loader->getProviders($this->user);

		$this->assertCount(1, $providers);
		$this->assertArrayHasKey('test', $providers);
		$this->assertSame($provider, $providers['test']);
	}

}
