<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\DAV\Tests\unit\CalDAV;

use OC\Calendar\Manager;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\CalendarImpl;
use OCA\DAV\CalDAV\CalendarManager;
use OCP\Calendar\IManager;
use OCP\IConfig;
use OCP\IL10N;

class CalendarManagerTest extends \Test\TestCase {

	/** @var CalDavBackend | \PHPUnit_Framework_MockObject_MockObject */
	private $backend;

	/** @var IL10N | \PHPUnit_Framework_MockObject_MockObject */
	private $l10n;

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
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

		/** @var IManager | \PHPUnit_Framework_MockObject_MockObject $calendarManager */
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
}
