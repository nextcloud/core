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

namespace Core\Command\TwoFactorAuth;

use OC\Core\Command\TwoFactorAuth\State;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

class StateTest extends TestCase {

	/** @var IRegistry|MockObject */
	private $registry;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var CommandTester|MockObject */
	private $cmd;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = $this->createMock(IRegistry::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$cmd = new State($this->registry, $this->userManager);
		$this->cmd = new CommandTester($cmd);
	}

	public function testWrongUID() {
		$this->cmd->execute([
			'uid' => 'nope',
		]);

		$output = $this->cmd->getDisplay();
		$this->assertContains("Invalid UID", $output);
	}

	public function testStateNoProvidersActive() {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('eldora')
			->willReturn($user);
		$states = [
			'u2f' => false,
			'totp' => false,
		];
		$this->registry->expects($this->once())
			->method('getProviderStates')
			->with($user)
			->willReturn($states);

		$this->cmd->execute([
			'uid' => 'eldora',
		]);

		$output = $this->cmd->getDisplay();
		$this->assertContains("Two-factor authentication is not enabled for user eldora", $output);
	}

	public function testStateOneProviderActive() {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('mohamed')
			->willReturn($user);
		$states = [
			'u2f' => true,
			'totp' => false,
		];
		$this->registry->expects($this->once())
			->method('getProviderStates')
			->with($user)
			->willReturn($states);

		$this->cmd->execute([
			'uid' => 'mohamed',
		]);

		$output = $this->cmd->getDisplay();
		$this->assertContains("Two-factor authentication is enabled for user mohamed", $output);
	}

}
