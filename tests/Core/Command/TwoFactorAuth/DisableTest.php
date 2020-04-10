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

namespace Test\Core\Command\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\ProviderManager;
use OC\Core\Command\TwoFactorAuth\Disable;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

class DisableTest extends TestCase {

	/** @var ProviderManager|MockObject */
	private $providerManager;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var CommandTester */
	private $command;

	protected function setUp(): void {
		parent::setUp();

		$this->providerManager = $this->createMock(ProviderManager::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$cmd = new Disable($this->providerManager, $this->userManager);
		$this->command = new CommandTester($cmd);
	}

	public function testInvalidUID() {
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn(null);

		$rc = $this->command->execute([
			'uid' => 'nope',
			'provider_id' => 'nope',
		]);

		$this->assertEquals(1, $rc);
		$this->assertContains("Invalid UID", $this->command->getDisplay());
	}

	public function testEnableNotSupported() {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('ricky')
			->willReturn($user);
		$this->providerManager->expects($this->once())
			->method('tryDisableProviderFor')
			->with('totp', $user)
			->willReturn(false);

		$rc = $this->command->execute([
			'uid' => 'ricky',
			'provider_id' => 'totp',
		]);

		$this->assertEquals(2, $rc);
		$this->assertContains("The provider does not support this operation", $this->command->getDisplay());
	}

	public function testEnabled() {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('ricky')
			->willReturn($user);
		$this->providerManager->expects($this->once())
			->method('tryDisableProviderFor')
			->with('totp', $user)
			->willReturn(true);

		$rc = $this->command->execute([
			'uid' => 'ricky',
			'provider_id' => 'totp',
		]);

		$this->assertEquals(0, $rc);
		$this->assertContains("Two-factor provider totp disabled for user ricky", $this->command->getDisplay());
	}
}
