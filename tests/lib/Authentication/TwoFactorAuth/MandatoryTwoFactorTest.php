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

namespace Tests\Authentication\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\EnforcementState;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MandatoryTwoFactorTest extends TestCase {

	/** @var IConfig|MockObject */
	private $config;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var MandatoryTwoFactor */
	private $mandatoryTwoFactor;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->mandatoryTwoFactor = new MandatoryTwoFactor($this->config, $this->groupManager);
	}

	public function testIsNotEnforced() {
		$this->config
			->method('getSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'false', 'false'],
				['twofactor_enforced_groups', [], []],
				['twofactor_enforced_excluded_groups', [], []],
			]);

		$state = $this->mandatoryTwoFactor->getState();

		$this->assertFalse($state->isEnforced());
	}

	public function testIsEnforced() {
		$this->config
			->method('getSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'false', 'true'],
				['twofactor_enforced_groups', [], []],
				['twofactor_enforced_excluded_groups', [], []],
			]);

		$state = $this->mandatoryTwoFactor->getState();

		$this->assertTrue($state->isEnforced());
	}

	public function testIsNotEnforcedForAnybody() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$this->config
			->method('getSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'false', 'false'],
				['twofactor_enforced_groups', [], []],
				['twofactor_enforced_excluded_groups', [], []],
			]);

		$isEnforced = $this->mandatoryTwoFactor->isEnforcedFor($user);

		$this->assertFalse($isEnforced);
	}

	public function testIsEnforcedForAGroupMember() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$this->config
			->method('getSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'false', 'true'],
				['twofactor_enforced_groups', [], ['twofactorers']],
				['twofactor_enforced_excluded_groups', [], []],
			]);
		$this->groupManager->method('isInGroup')
			->willReturnCallback(function ($user, $group) {
				return $user === 'user123' && $group === 'twofactorers';
			});

		$isEnforced = $this->mandatoryTwoFactor->isEnforcedFor($user);

		$this->assertTrue($isEnforced);
	}

	public function testIsEnforcedForOtherGroups() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$this->config
			->method('getSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'false', 'true'],
				['twofactor_enforced_groups', [], ['twofactorers']],
				['twofactor_enforced_excluded_groups', [], []],
			]);
		$this->groupManager->method('isInGroup')
			->willReturn(false);

		$isEnforced = $this->mandatoryTwoFactor->isEnforcedFor($user);

		$this->assertFalse($isEnforced);
	}

	public function testIsEnforcedButMemberOfExcludedGroup() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$this->config
			->method('getSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'false', 'true'],
				['twofactor_enforced_groups', [], []],
				['twofactor_enforced_excluded_groups', [], ['yoloers']],
			]);
		$this->groupManager->method('isInGroup')
			->willReturnCallback(function ($user, $group) {
				return $user === 'user123' && $group === 'yoloers';
			});

		$isEnforced = $this->mandatoryTwoFactor->isEnforcedFor($user);

		$this->assertFalse($isEnforced);
	}

	public function testSetEnforced() {
		$this->config
			->expects($this->exactly(3))
			->method('setSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'true'],
				['twofactor_enforced_groups', []],
				['twofactor_enforced_excluded_groups', []],
			]);

		$this->mandatoryTwoFactor->setState(new EnforcementState(true));
	}

	public function testSetEnforcedForGroups() {
		$this->config
			->expects($this->exactly(3))
			->method('setSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'true'],
				['twofactor_enforced_groups', ['twofactorers']],
				['twofactor_enforced_excluded_groups', ['yoloers']],
			]);

		$this->mandatoryTwoFactor->setState(new EnforcementState(true, ['twofactorers'], ['yoloers']));
	}

	public function testSetNotEnforced() {
		$this->config
			->expects($this->exactly(3))
			->method('setSystemValue')
			->willReturnMap([
				['twofactor_enforced', 'false'],
				['twofactor_enforced_groups', []],
				['twofactor_enforced_excluded_groups', []],
			]);

		$this->mandatoryTwoFactor->setState(new EnforcementState(false));
	}
}
