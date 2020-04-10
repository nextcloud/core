<?php
/**
 * @copyright Copyright (c) 2018, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OCA\DAV\BackgroundJob\RefreshWebcalJob;
use OCA\DAV\CalDAV\WebcalCaching\RefreshWebcalService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\ILogger;
use PHPUnit\Framework\MockObject\MockObject;

use Test\TestCase;

class RefreshWebcalJobTest extends TestCase {

	/** @var RefreshWebcalService | MockObject */
	private $refreshWebcalService;

	/** @var IConfig | MockObject */
	private $config;

	/** @var ILogger | MockObject */
	private $logger;

	/** @var ITimeFactory | MockObject */
	private $timeFactory;

	/** @var IJobList | MockObject */
	private $jobList;

	protected function setUp(): void {
		parent::setUp();

		$this->refreshWebcalService = $this->createMock(RefreshWebcalService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$this->jobList = $this->createMock(IJobList::class);
	}

	/**
	 *
	 * @param int $lastRun
	 * @param int $time
	 * @param bool $process
	 *
	 * @dataProvider runDataProvider
	 */
	public function testRun(int $lastRun, int $time, bool $process) {
		$backgroundJob = new RefreshWebcalJob($this->refreshWebcalService, $this->config, $this->logger, $this->timeFactory);

		$backgroundJob->setArgument([
			'principaluri' => 'principals/users/testuser',
			'uri' => 'sub123',
		]);
		$backgroundJob->setLastRun($lastRun);

		$this->refreshWebcalService->expects($this->once())
			->method('getSubscription')
			->with('principals/users/testuser', 'sub123')
			->willReturn([
				'id' => '99',
				'uri' => 'sub456',
				'{http://apple.com/ns/ical/}refreshrate' => 'P1D',
				'{http://calendarserver.org/ns/}subscribed-strip-todos' => '1',
				'{http://calendarserver.org/ns/}subscribed-strip-alarms' => '1',
				'{http://calendarserver.org/ns/}subscribed-strip-attachments' => '1',
				'source' => 'webcal://foo.bar/bla'
			]);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'calendarSubscriptionRefreshRate', 'P1W')
			->willReturn('P1W');

		$this->timeFactory->expects($this->once())
			->method('getTime')
			->willReturn($time);

		if ($process) {
			$this->refreshWebcalService->expects($this->once())
				->method('refreshSubscription')
				->with('principals/users/testuser', 'sub123');
		} else {
			$this->refreshWebcalService->expects($this->never())
				->method('refreshSubscription')
				->with('principals/users/testuser', 'sub123');
		}

		$backgroundJob->execute($this->jobList, $this->logger);
	}

	/**
	 * @return array
	 */
	public function runDataProvider():array {
		return [
			[0, 100000, true],
			[100000, 100000, false]
		];
	}
}
