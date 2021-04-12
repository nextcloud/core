<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\DAV\Tests\unit\CalDAV;

use OC\Calendar\Manager;
use OC\Calendar\ManagerV2;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\CalendarImpl;
use OCA\DAV\CalDAV\CalendarImplV2;
use OCA\DAV\CalDAV\CalendarManager;
use OCP\Calendar\IManager;
use OCP\Calendar\IManagerV2;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CalendarManagerTest extends TestCase {

	/** @var CalDavBackend | \PHPUnit\Framework\MockObject\MockObject */
	private $backend;

	/** @var IL10N | \PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;

	/** @var CalendarManager */
	private $manager;

	protected function setUp(): void {
		parent::setUp();
		$this->backend = $this->createMock(CalDavBackend::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->manager = new CalendarManager($this->backend,
			$this->l10n, $this->config);
	}

	public function testSetupCalendarProvider() {
		$this->backend->expects($this->once())
			->method('getCalendarsForUser')
			->with('principals/users/user123')
			->willReturn([
				['id' => 123, 'uri' => 'blablub1'],
				['id' => 456, 'uri' => 'blablub2'],
			]);

		/** @var IManager | \PHPUnit\Framework\MockObject\MockObject $calendarManager */
		$calendarManager = $this->createMock(Manager::class);
		$calendarManager->expects($this->at(0))
			->method('registerCalendar')
			->willReturnCallback(function () {
				$parameter = func_get_arg(0);
				$this->assertInstanceOf(CalendarImpl::class, $parameter);
				$this->assertEquals(123, $parameter->getKey());
			});

		$calendarManager->expects($this->at(1))
			->method('registerCalendar')
			->willReturnCallback(function () {
				$parameter = func_get_arg(0);
				$this->assertInstanceOf(CalendarImpl::class, $parameter);
				$this->assertEquals(456, $parameter->getKey());
			});

		$this->manager->setupCalendarProvider($calendarManager, 'user123');
	}

	public function testSetupCalendarProviderV2() {
		$this->backend->expects($this->once())
			->method('getCalendarsForUser')
			->with('principals/users/user123')
			->willReturn([
				['id' => 123, 'uri' => 'blablub1'],
				['id' => 456, 'uri' => 'blablub2'],
			]);

		/** @var IManagerV2 | MockObject $calendarManager */
		$calendarManager = $this->createMock(ManagerV2::class);
		$calendarManager->expects($this->at(0))
			->method('registerCalendar')
			->willReturnCallback(function () {
				$parameter = func_get_arg(0);
				$this->assertInstanceOf(CalendarImplV2::class, $parameter);
				$this->assertEquals(123, $parameter->getKey());
			});

		$calendarManager->expects($this->at(1))
			->method('registerCalendar')
			->willReturnCallback(function () {
				$parameter = func_get_arg(0);
				$this->assertInstanceOf(CalendarImplV2::class, $parameter);
				$this->assertEquals(456, $parameter->getKey());
			});

		$this->manager->setupCalendarProviderV2($calendarManager, 'user123');
	}
}
