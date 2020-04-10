<?php
/**
 * @copyright 2019 Georg Ehrke <oc.list@georgehrke.com>
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
use OCA\DAV\BackgroundJob\RegisterRegenerateBirthdayCalendars;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

class RegisterRegenerateBirthdayCalendarsTest extends TestCase {

	/** @var ITimeFactory | \PHPUnit_Framework_MockObject_MockObject */
	private $time;

	/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var IJobList | \PHPUnit_Framework_MockObject_MockObject */
	private $jobList;

	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var RegisterRegenerateBirthdayCalendars */
	private $backgroundJob;

	protected function setUp(): void {
		parent::setUp();

		$this->time = $this->createMock(ITimeFactory::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->config = $this->createMock(IConfig::class);

		$this->backgroundJob = new RegisterRegenerateBirthdayCalendars($this->time,
			$this->userManager, $this->jobList);
	}

	public function testRun() {
		$this->userManager->expects($this->once())
			->method('callForSeenUsers')
			->willReturnCallback(function ($closure) {
				$user1 = $this->createMock(IUser::class);
				$user1->method('getUID')->willReturn('uid1');
				$user2 = $this->createMock(IUser::class);
				$user2->method('getUID')->willReturn('uid2');
				$user3 = $this->createMock(IUser::class);
				$user3->method('getUID')->willReturn('uid3');

				$closure($user1);
				$closure($user2);
				$closure($user3);
			});

		$this->jobList->expects($this->at(0))
			->method('add')
			->with(GenerateBirthdayCalendarBackgroundJob::class, [
				'userId' => 'uid1',
				'purgeBeforeGenerating' => true
			]);
		$this->jobList->expects($this->at(1))
			->method('add')
			->with(GenerateBirthdayCalendarBackgroundJob::class, [
				'userId' => 'uid2',
				'purgeBeforeGenerating' => true
			]);
		$this->jobList->expects($this->at(2))
			->method('add')
			->with(GenerateBirthdayCalendarBackgroundJob::class, [
				'userId' => 'uid3',
				'purgeBeforeGenerating' => true
			]);

		$this->backgroundJob->run([]);
	}
}
