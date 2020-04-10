<?php

declare(strict_types=1);

/**
 * @copyright 2018, Thomas Citharel <tcit@tcit.fr>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\Tests\unit\BackgroundJob;

use OCA\DAV\BackgroundJob\EventReminderJob;
use OCA\DAV\CalDAV\Reminder\ReminderService;
use OCP\IConfig;
use Test\TestCase;

class EventReminderJobTest extends TestCase {

	/** @var ReminderService|\PHPUnit\Framework\MockObject\MockObject */
	private $reminderService;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;

	/** @var EventReminderJob|\PHPUnit\Framework\MockObject\MockObject */
	private $backgroundJob;

	protected function setUp(): void {
		parent::setUp();

		$this->reminderService = $this->createMock(ReminderService::class);
		$this->config = $this->createMock(IConfig::class);

		$this->backgroundJob = new EventReminderJob($this->reminderService, $this->config);
	}

	public function data(): array
	{
		return [
			[true, true, true],
			[true, false, false],
			[false, true, false],
			[false, false, false],
		];
	}

	/**
	 * @dataProvider data
	 *
	 * @param bool $sendEventReminders
	 * @param bool $sendEventRemindersMode
	 * @param bool $expectCall
	 */
	public function testRun(bool $sendEventReminders, bool $sendEventRemindersMode, bool $expectCall): void {
		$this->config->expects($this->at(0))
			->method('getAppValue')
			->with('dav', 'sendEventReminders', 'yes')
			->willReturn($sendEventReminders ? 'yes' : 'no');

		if ($sendEventReminders) {
			$this->config->expects($this->at(1))
				->method('getAppValue')
				->with('dav', 'sendEventRemindersMode', 'backgroundjob')
				->willReturn($sendEventRemindersMode ? 'backgroundjob' : 'cron');

		}

		if ($expectCall) {
			$this->reminderService->expects($this->once())
				->method('processReminders');
		} else {
			$this->reminderService->expects($this->never())
				->method('processReminders');
		}

		$this->backgroundJob->run([]);
	}
}
