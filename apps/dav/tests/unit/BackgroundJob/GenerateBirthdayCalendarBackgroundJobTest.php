<?php
/**
 * @copyright Copyright (c) 2017, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Tests\unit\BackgroundJob;

use OCA\DAV\BackgroundJob\GenerateBirthdayCalendarBackgroundJob;
use OCA\DAV\CalDAV\BirthdayService;
use OCP\IConfig;
use Test\TestCase;

class GenerateBirthdayCalendarBackgroundJobTest extends TestCase {

	/** @var BirthdayService | \PHPUnit_Framework_MockObject_MockObject */
	private $birthdayService;

	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var \OCA\DAV\BackgroundJob\GenerateBirthdayCalendarBackgroundJob */
	private $backgroundJob;

	protected function setUp(): void {
		parent::setUp();

		$this->birthdayService = $this->createMock(BirthdayService::class);
		$this->config = $this->createMock(IConfig::class);

		$this->backgroundJob = new GenerateBirthdayCalendarBackgroundJob(
			$this->birthdayService, $this->config);
	}

	public function testRun() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user123', 'dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->birthdayService->expects($this->never())
			->method('resetForUser')
			->with('user123');

		$this->birthdayService->expects($this->once())
			->method('syncUser')
			->with('user123');

		$this->backgroundJob->run(['userId' => 'user123']);
	}

	public function testRunAndReset() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user123', 'dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->birthdayService->expects($this->once())
			->method('resetForUser')
			->with('user123');

		$this->birthdayService->expects($this->once())
			->method('syncUser')
			->with('user123');

		$this->backgroundJob->run(['userId' => 'user123', 'purgeBeforeGenerating' => true]);
	}

	public function testRunGloballyDisabled() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('no');

		$this->config->expects($this->never())
			->method('getUserValue');

		$this->birthdayService->expects($this->never())
			->method('syncUser');

		$this->backgroundJob->run(['userId' => 'user123']);
	}

	public function testRunUserDisabled() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user123', 'dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('no');

		$this->birthdayService->expects($this->never())
			->method('syncUser');

		$this->backgroundJob->run(['userId' => 'user123']);
	}
}
