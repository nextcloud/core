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

namespace OCA\TwoFactorBackupCodes\Tests\Unit\Listener;

use OCA\TwoFactorBackupCodes\BackgroundJob\RememberBackupCodesJob;
use OCA\TwoFactorBackupCodes\Listener\ProviderEnabled;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\Authentication\TwoFactorAuth\RegistryEvent;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use Test\TestCase;

class ProviderEnabledTest extends TestCase {

	/** @var IRegistry|\PHPUnit\Framework\MockObject\MockObject */
	private $registy;

	/** @var IJobList|\PHPUnit\Framework\MockObject\MockObject */
	private $jobList;

	/** @var ProviderEnabled */
	private $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->registy = $this->createMock(IRegistry::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->listener = new ProviderEnabled($this->registy, $this->jobList);
	}

	public function testHandleGenericEvent() {
		$event = $this->createMock(Event::class);
		$this->jobList->expects($this->never())
			->method($this->anything());

		$this->listener->handle($event);
	}

	public function testHandleCodesGeneratedEventAlraedyBackupcodes() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('myUID');
		$event = $this->createMock(RegistryEvent::class);
		$event->method('getUser')
			->willReturn($user);

		$this->registy->method('getProviderStates')
			->with($user)
			->willReturn([
				'backup_codes' => true,
			]);

		$this->jobList->expects($this->never())
			->method($this->anything());

		$this->listener->handle($event);
	}

	public function testHandleCodesGeneratedEventNoBackupcodes() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('myUID');
		$event = $this->createMock(RegistryEvent::class);
		$event->method('getUser')
			->willReturn($user);

		$this->registy->method('getProviderStates')
			->with($user)
			->willReturn([
				'backup_codes' => false,
			]);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				$this->equalTo(RememberBackupCodesJob::class),
				$this->equalTo(['uid' => 'myUID'])
			);

		$this->listener->handle($event);
	}
}
