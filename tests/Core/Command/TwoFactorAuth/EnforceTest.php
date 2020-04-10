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

namespace Tests\Core\Command\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\EnforcementState;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OC\Core\Command\TwoFactorAuth\Enforce;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

class EnforceTest extends TestCase {

	/** @var MandatoryTwoFactor|MockObject */
	private $mandatoryTwoFactor;

	/** @var CommandTester */
	private $command;

	protected function setUp(): void {
		parent::setUp();

		$this->mandatoryTwoFactor = $this->createMock(MandatoryTwoFactor::class);
		$command = new Enforce($this->mandatoryTwoFactor);

		$this->command = new CommandTester($command);
	}

	public function testEnforce() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('setState')
			->with($this->equalTo(new EnforcementState(true)));
		$this->mandatoryTwoFactor->expects($this->once())
			->method('getState')
			->willReturn(new EnforcementState(true));

		$rc = $this->command->execute([
			'--on' => true,
		]);

		$this->assertEquals(0, $rc);
		$display = $this->command->getDisplay();
		$this->assertContains("Two-factor authentication is enforced for all users", $display);
	}

	public function testEnforceForOneGroup() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('setState')
			->with($this->equalTo(new EnforcementState(true, ['twofactorers'])));
		$this->mandatoryTwoFactor->expects($this->once())
			->method('getState')
			->willReturn(new EnforcementState(true, ['twofactorers']));

		$rc = $this->command->execute([
			'--on' => true,
			'--group' => ['twofactorers'],
		]);

		$this->assertEquals(0, $rc);
		$display = $this->command->getDisplay();
		$this->assertContains("Two-factor authentication is enforced for members of the group(s) twofactorers", $display);
	}

	public function testEnforceForAllExceptOneGroup() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('setState')
			->with($this->equalTo(new EnforcementState(true, [], ['yoloers'])));
		$this->mandatoryTwoFactor->expects($this->once())
			->method('getState')
			->willReturn(new EnforcementState(true, [], ['yoloers']));

		$rc = $this->command->execute([
			'--on' => true,
			'--exclude' => ['yoloers'],
		]);

		$this->assertEquals(0, $rc);
		$display = $this->command->getDisplay();
		$this->assertContains("Two-factor authentication is enforced for all users, except members of yoloers", $display);
	}

	public function testDisableEnforced() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('setState')
			->with(new EnforcementState(false));
		$this->mandatoryTwoFactor->expects($this->once())
			->method('getState')
			->willReturn(new EnforcementState(false));

		$rc = $this->command->execute([
			'--off' => true,
		]);

		$this->assertEquals(0, $rc);
		$display = $this->command->getDisplay();
		$this->assertContains("Two-factor authentication is not enforced", $display);
	}

	public function testCurrentStateEnabled() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('getState')
			->willReturn(new EnforcementState(true));

		$rc = $this->command->execute([]);

		$this->assertEquals(0, $rc);
		$display = $this->command->getDisplay();
		$this->assertContains("Two-factor authentication is enforced for all users", $display);
	}

	public function testCurrentStateDisabled() {
		$this->mandatoryTwoFactor->expects($this->once())
			->method('getState')
			->willReturn(new EnforcementState(false));

		$rc = $this->command->execute([]);

		$this->assertEquals(0, $rc);
		$display = $this->command->getDisplay();
		$this->assertContains("Two-factor authentication is not enforced", $display);
	}

}
