<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TwoFactorBackupCodes\Tests\Unit\BackgroundJob;

use OC\Authentication\TwoFactorAuth\Manager;
use OCA\TwoFactorBackupCodes\BackgroundJob\CheckBackupCodes;
use OCA\TwoFactorBackupCodes\BackgroundJob\RememberBackupCodesJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CheckBackupCodeTest extends TestCase {

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var IJobList|MockObject */
	private $jobList;

	/** @var IRegistry|MockObject */
	private $registry;

	/** @var Manager|MockObject */
	private $manager;

	/** @var IUser|MockObject */
	private $user;

	/** @var CheckBackupCodes */
	private $checkBackupCodes;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->registry = $this->createMock(IRegistry::class);
		$this->manager = $this->createMock(Manager::class);

		$this->user = $this->createMock(IUser::class);

		$this->userManager->method('callForSeenUsers')
			->willReturnCallback(function (\Closure $e) {
				$e($this->user);
			});

		$this->checkBackupCodes = new CheckBackupCodes(
			$this->createMock(ITimeFactory::class),
			$this->userManager,
			$this->jobList,
			$this->manager,
			$this->registry
		);
	}

	public function testRunAlreadyGenerated() {
		$this->registry->method('getProviderStates')
			->with($this->user)
			->willReturn(['backup_codes' => true]);
		$this->manager->method('isTwoFactorAuthenticated')
			->with($this->user)
			->willReturn(true);
		$this->jobList->expects($this->never())
			->method($this->anything());

		$this->invokePrivate($this->checkBackupCodes, 'run', [[]]);
	}

	public function testRun() {
		$this->user->method('getUID')
			->willReturn('myUID');

		$this->registry->expects($this->once())
			->method('getProviderStates')
			->with($this->user)
			->willReturn([
				'backup_codes' => false,
			]);
		$this->jobList->expects($this->once())
			->method('add')
			->with(
				$this->equalTo(RememberBackupCodesJob::class),
				['uid' => 'myUID']
			);
		$this->manager->method('isTwoFactorAuthenticated')
			->with($this->user)
			->willReturn(true);

		$this->invokePrivate($this->checkBackupCodes, 'run', [[]]);
	}

	public function testRunNoProviders() {
		$this->registry->expects($this->once())
			->method('getProviderStates')
			->with($this->user)
			->willReturn([
				'backup_codes' => false,
			]);
		$this->jobList->expects($this->never())
			->method($this->anything());
		$this->manager->method('isTwoFactorAuthenticated')
			->with($this->user)
			->willReturn(false);

		$this->invokePrivate($this->checkBackupCodes, 'run', [[]]);
	}


}
