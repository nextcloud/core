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
use OCA\DAV\CalDAV\Calendar;
use OCA\DAV\CalDAV\CalendarImplV2;
use OCA\DAV\CalDAV\CalendarObjectImplV2;
use PHPUnit\Framework\MockObject\MockObject;
use Sabre\VObject\Component\VCalendar;
use Test\TestCase;

class CalendarImplV2Test extends TestCase {

	/** @var CalendarImplV2 */
	private $calendarImpl;

	/** @var Calendar | MockObject */
	private $calendar;

	/** @var array */
	private $calendarInfo;

	/** @var CalDavBackend | MockObject */
	private $backend;

	protected function setUp(): void {
		parent::setUp();

		$this->calendar = $this->createMock(Calendar::class);
		$this->calendarInfo = [
			'id' => '4',
			'{DAV:}displayname' => 'user readable name 123',
			'{http://apple.com/ns/ical/}calendar-color' => '#AABBCC',
		];
		$this->backend = $this->createMock(CalDavBackend::class);

		$this->calendarImpl = new CalendarImplV2($this->calendar,
			$this->calendarInfo, $this->backend);
	}


	public function testGetKey() {
		$this->assertEquals($this->calendarImpl->getKey(), '4');
	}

	public function testGetDisplayname() {
		$this->assertEquals($this->calendarImpl->getDisplayName(),'user readable name 123');
	}

	public function testGetDisplayColor() {
		$this->assertEquals($this->calendarImpl->getDisplayColor(), '#AABBCC');
	}

	public function testSearch() {
		$this->backend->expects($this->once())
			->method('search')
			->with($this->calendarInfo, 'abc', ['def'], ['ghi'], 42, 1337)
			->willReturn(['SEARCHRESULTS']);

		$result = $this->calendarImpl->search('abc', ['def'], ['ghi'], 42, 1337);
		$this->assertEquals($result, ['SEARCHRESULTS']);
	}

	/**
	 * @dataProvider dataTestIsWritable
	 */
	public function testGetIsWritable(array $privileges, bool $result) {
		$this->calendar->expects($this->once())
			->method('getACL')
			->with()
			->willReturn($privileges);

		$this->assertEquals($result, $this->calendarImpl->isWriteable());
	}

	public function dataTestIsWritable(): array {
		return [
			[
				[
					['privilege' => '{DAV:}read']
				],
				false
			],
			[
				[
					['privilege' => '{DAV:}write']
				],
				true
			],
			[
				[
					['privilege' => '{DAV:}read'],
					['privilege' => '{DAV:}write']
				],
				true
			],
			[
				[
					['privilege' => '{DAV:}all']
				],
				true
			]
		];
	}

	public function testGetByUri() {
		$vcalendar = new VCalendar();
		$calendarObjectData = ['id' => 'someid', 'calendarid' => '4', 'uri' => 'someuri.ics', 'calendardata' => $vcalendar->serialize()];
		$calendarObject = new CalendarObjectImplV2(
			$calendarObjectData['calendarid'],
			$calendarObjectData['uri'],
			$vcalendar,
			$this->backend);

		$this->backend->expects($this->once())
			->method('getCalendarObject')
			->with($calendarObjectData['calendarid'], $calendarObjectData['uri'])
			->willReturn($calendarObjectData);
		$foundObject = $this->calendarImpl->getByUri($calendarObjectData['uri']);
		$this->assertEquals($calendarObject->getCalendarKey(), $foundObject->getCalendarKey());
		$this->assertEquals($calendarObject->getUri(), $foundObject->getUri());
		// Asserting equality for VObject PHP objects fails on parent references, so we serialize them
		$this->assertEquals($calendarObject->getVObject()->serialize(), $foundObject->getVObject()->serialize());
	}

	public function testGetByUriNotFound() {
		$this->backend->expects($this->once())
			->method('getCalendarObject')
			->willReturn(null);
		$this->assertEquals(null, $this->calendarImpl->getByUri('uri'));
	}

	public function testCreateCalendarObject() {
		$calendarObjectDataObject = new VCalendar([
			'VEVENT' => [
				'SUMMARY' => 'Birthday party!',
				'DTSTART' => new \DateTime('2016-07-04 21:00:00'),
				'DTEND'   => new \DateTime('2016-07-05 03:00:00')
			]
		]);
		$calendarObjectData = ['id' => 'someid', 'calendarid' => '4', 'uri' => 'someuri.ics', 'calendardata' => $calendarObjectDataObject->serialize()];
		$calendarObject = new CalendarObjectImplV2($calendarObjectData['calendarid'], $calendarObjectData['uri'], $calendarObjectDataObject, $this->backend);
		$this->backend->expects($this->once())
			->method('createCalendarObject')
			->with(
				$calendarObjectData['calendarid'],
				$this->anything(),
				$calendarObjectDataObject->serialize()
			);
		$this->backend->expects($this->once())
			->method('getCalendarObject')
			->willReturn($calendarObjectData);
		$createdCalendarObject = $this->calendarImpl->create($calendarObjectDataObject);
		$this->assertEquals($calendarObject->getCalendarKey(), $createdCalendarObject->getCalendarKey());
		$this->assertEquals($calendarObject->getUri(), $createdCalendarObject->getUri());
		// Asserting equality for VObject PHP objects fails on parent references (and therefore on the whole CalendarImplV2 object), so we serialize them
		$this->assertEquals($calendarObject->getVObject()->serialize(), $createdCalendarObject->getVObject()->serialize());
	}

	public function testCreateCalendarObjectWithBadCalendarData() {
		$vobject = new VCalendar([]);
		$this->expectException(InvalidArgumentException::class);
		$this->calendarImpl->create($vobject);
	}
}
