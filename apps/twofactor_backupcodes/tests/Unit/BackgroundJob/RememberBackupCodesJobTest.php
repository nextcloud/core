<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

use OCA\TwoFactorBackupCodes\BackgroundJob\RememberBackupCodesJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Test\TestCase;

class RememberBackupCodesJobTest extends TestCase {

	/** @var IRegistry|\PHPUnit\Framework\MockObject\MockObject  */
	private $registry;

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject  */
	private $userManager;

	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $time;

	/** @var IManager|\PHPUnit\Framework\MockObject\MockObject */
	private $notificationManager;

	/** @var IJobList|\PHPUnit\Framework\MockObject\MockObject */
	private $jobList;

	/** @var RememberBackupCodesJob */
	private $job;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = $this->createMock(IRegistry::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->time = $this->createMock(ITimeFactory::class);
		$this->time->method('getTime')
			->willReturn(10000000);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->job = new RememberBackupCodesJob(
			$this->registry,
			$this->userManager,
			$this->time,
			$this->notificationManager,
			$this->jobList
		);
	}

	public function testInvalidUID() {
		$this->userManager->method('get')
			->with('invalidUID')
			->willReturn(null);

		$this->notificationManager->expects($this->never())
			->method($this->anything());
		$this->jobList->expects($this->never())
			->method($this->anything());

		$this->invokePrivate($this->job, 'run', [['uid' => 'invalidUID']]);
	}

	public function testBackupCodesGenerated() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('validUID');
		$this->userManager->method('get')
			->with('validUID')
			->willReturn($user);

		$this->registry->method('getProviderStates')
			->with($user)
			->willReturn([
				'backup_codes' => true
			]);

		$this->jobList->expects($this->once())
			->method('remove')
			->with(
				RememberBackupCodesJob::class,
				['uid' => 'validUID']
			);

		$this->notificationManager->expects($this->never())
			->method($this->anything());

		$this->invokePrivate($this->job, 'run', [['uid' => 'validUID']]);
	}

	public function testNoActiveProvider() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('validUID');
		$this->userManager->method('get')
			->with('validUID')
			->willReturn($user);

		$this->registry->method('getProviderStates')
			->with($user)
			->willReturn([
				'backup_codes' => false,
				'foo' => false,
			]);

		$this->jobList->expects($this->once())
			->method('remove')
			->with(
				RememberBackupCodesJob::class,
				['uid' => 'validUID']
			);

		$this->notificationManager->expects($this->never())
			->method($this->anything());

		$this->invokePrivate($this->job, 'run', [['uid' => 'validUID']]);
	}

	public function testNotificationSend() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('validUID');
		$this->userManager->method('get')
			->with('validUID')
			->willReturn($user);

		$this->registry->method('getProviderStates')
			->with($user)
			->willReturn([
				'backup_codes' => false,
				'foo' => true,
			]);

		$this->jobList->expects($this->never())
			->method($this->anything());

		$date = new \DateTime();
		$date->setTimestamp($this->time->getTime());

		$this->notificationManager->method('createNotification')
			->willReturn(\OC::$server->query(IManager::class)->createNotification());

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($this->callback(function (INotification $n) {
				return $n->getApp() === 'twofactor_backupcodes' &&
					$n->getUser() === 'validUID' &&
					$n->getDateTime()->getTimestamp() === 10000000 &&
					$n->getObjectType() === 'create' &&
					$n->getObjectId() === 'codes' &&
					$n->getSubject() === 'create_backupcodes';
			}));

		$this->invokePrivate($this->job, 'run', [['uid' => 'validUID']]);
	}
}
