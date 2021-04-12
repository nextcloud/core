<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
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

namespace OCA\DAV\Tests\unit\CalDAV;

use InvalidArgumentException;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\CalendarObjectImplV2;
use PHPUnit\Framework\MockObject\MockObject;
use Sabre\VObject\Component\VCalendar;
use Test\TestCase;

class CalendarObjectImplV2Test extends TestCase {

	/** @var array */
	private $calendarObject;

	/** @var CalDavBackend | MockObject */
	private $backend;
	/**
	 * @var CalendarObjectImplV2
	 */
	private $calendarObjectImpl;

	protected function setUp(): void {
		parent::setUp();

		$calendarObjectData = new VCalendar([
			'VEVENT' => [
				'SUMMARY' => 'Birthday party!',
				'DTSTART' => new \DateTime('2016-07-04 21:00:00'),
				'DTEND'   => new \DateTime('2016-07-05 03:00:00')
			]
		]);

		$this->calendarObject = [
			'calendarid' => '4',
			'uri' => 'something.ics',
			'calendardata' => $calendarObjectData->serialize()
		];
		$this->backend = $this->createMock(CalDavBackend::class);

		$this->calendarObjectImpl = new CalendarObjectImplV2($this->calendarObject['calendarid'], $this->calendarObject['uri'], $calendarObjectData, $this->backend);
	}


	public function testGetCalendarKey() {
		$this->assertEquals($this->calendarObjectImpl->getCalendarKey(), $this->calendarObject['calendarid']);
	}

	public function testGetUri() {
		$this->assertEquals($this->calendarObjectImpl->getUri(),$this->calendarObject['uri']);
	}

	public function testGetVObject() {
		$this->assertEquals($this->calendarObjectImpl->getVObject()->serialize(), $this->calendarObject['calendardata']);
	}

	public function testUpdate() {
		$vobject = new VCalendar([
			'VEVENT' => [
				'SUMMARY' => 'Updated birthday party!',
				'DTSTART' => new \DateTime('2020-07-04 21:00:00'),
				'DTEND'   => new \DateTime('2020-07-05 03:00:00')
			]
		]);

		$this->backend->expects($this->once())
			->method('updateCalendarObject')
			->with($this->calendarObject['calendarid'], $this->calendarObject['uri'], $vobject->serialize());

		$this->calendarObjectImpl->update($vobject);
		$this->assertEquals($this->calendarObjectImpl->getVObject()->serialize(), $vobject->serialize());
	}

	public function testUpdateWithBadCalendarData() {
		$vobject = new VCalendar([]);
		$this->expectException(InvalidArgumentException::class);
		$this->calendarObjectImpl->update($vobject);
	}

	public function testDelete() {
		$this->backend->expects($this->once())
			->method('deleteCalendarObject')
			->with($this->calendarObject['calendarid'], $this->calendarObject['uri']);

		$this->calendarObjectImpl->delete();
	}
}
