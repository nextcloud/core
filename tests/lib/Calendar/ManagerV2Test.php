<?php
/**
 * @copyright 2020, Thomas Citharel <nextcloud@tcit.fr>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Calendar;

use OC\Calendar\ManagerV2;
use OCP\Calendar\ICalendarV2;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ManagerV2Test extends TestCase {

	/** @var ManagerV2 */
	private $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->manager = new ManagerV2();
	}

	/**
	 * @dataProvider searchProvider
	 */
	public function testSearch($search1, $search2, $expected) {
		/** @var ICalendarV2 | MockObject $calendar1 */
		$calendar1 = $this->createMock(ICalendarV2::class);
		$calendar1->method('getKey')->willReturn('simple:1');
		$calendar1->expects($this->once())
			->method('search')
			->with('', [], [], null, null)
			->willReturn($search1);

		/** @var ICalendarV2 | MockObject $calendar2 */
		$calendar2 = $this->createMock(ICalendarV2::class);
		$calendar2->method('getKey')->willReturn('simple:2');
		$calendar2->expects($this->once())
			->method('search')
			->with('', [], [], null, null)
			->willReturn($search2);

		$this->manager->registerCalendar($calendar1);
		$this->manager->registerCalendar($calendar2);

		$result = $this->manager->search('');
		$this->assertEquals($expected, $result);
	}

	/**
	 * @dataProvider searchProvider
	 */
	public function testSearchOptions($search1, $search2, $expected) {
		/** @var ICalendarV2 | MockObject $calendar1 */
		$calendar1 = $this->createMock(ICalendarV2::class);
		$calendar1->method('getKey')->willReturn('simple:1');
		$calendar1->expects($this->once())
			->method('search')
			->with('searchTerm', ['SUMMARY', 'DESCRIPTION'],
				['timerange' => ['start' => null, 'end' => null]], 5, 20)
			->willReturn($search1);

		/** @var ICalendarV2 | MockObject $calendar2 */
		$calendar2 = $this->createMock(ICalendarV2::class);
		$calendar2->method('getKey')->willReturn('simple:2');
		$calendar2->expects($this->once())
			->method('search')
			->with('searchTerm', ['SUMMARY', 'DESCRIPTION'],
				['timerange' => ['start' => null, 'end' => null]], 5, 20)
			->willReturn($search2);

		$this->manager->registerCalendar($calendar1);
		$this->manager->registerCalendar($calendar2);

		$result = $this->manager->search('searchTerm', ['SUMMARY', 'DESCRIPTION'],
			['timerange' => ['start' => null, 'end' => null]], 5, 20);
		$this->assertEquals($expected, $result);
	}

	public function searchProvider() {
		$search1 = [
			[
				'id' => 1,
				'data' => 'foobar',
			],
			[
				'id' => 2,
				'data' => 'barfoo',
			]
		];
		$search2 = [
			[
				'id' => 3,
				'data' => 'blablub',
			],
			[
				'id' => 4,
				'data' => 'blubbla',
			]
		];

		$expected = [
			[
				'id' => 1,
				'data' => 'foobar',
				'calendar-key' => 'simple:1',
			],
			[
				'id' => 2,
				'data' => 'barfoo',
				'calendar-key' => 'simple:1',
			],
			[
				'id' => 3,
				'data' => 'blablub',
				'calendar-key' => 'simple:2',
			],
			[
				'id' => 4,
				'data' => 'blubbla',
				'calendar-key' => 'simple:2',
			]
		];

		return [
			[
				$search1,
				$search2,
				$expected
			]
		];
	}

	public function testRegisterUnregister() {
		/** @var ICalendarV2 | MockObject $calendar1 */
		$calendar1 = $this->createMock(ICalendarV2::class);
		$calendar1->method('getKey')->willReturn('key1');

		/** @var ICalendarV2 | MockObject $calendar2 */
		$calendar2 = $this->createMock(ICalendarV2::class);
		$calendar2->method('getKey')->willReturn('key2');

		$this->manager->registerCalendar($calendar1);
		$this->manager->registerCalendar($calendar2);

		$result = $this->manager->getCalendars();
		$this->assertCount(2, $result);
		$this->assertContains($calendar1, $result);
		$this->assertContains($calendar2, $result);

		$this->manager->unregisterCalendar($calendar1);

		$result = $this->manager->getCalendars();
		$this->assertCount(1, $result);
		$this->assertContains($calendar2, $result);
	}

	public function testGetCalendars() {
		/** @var ICalendarV2 | MockObject $calendar1 */
		$calendar1 = $this->createMock(ICalendarV2::class);
		$calendar1->method('getKey')->willReturn('key1');

		/** @var ICalendarV2 | MockObject $calendar2 */
		$calendar2 = $this->createMock(ICalendarV2::class);
		$calendar2->method('getKey')->willReturn('key2');

		$this->manager->registerCalendar($calendar1);
		$this->manager->registerCalendar($calendar2);

		$result = $this->manager->getCalendars();
		$this->assertCount(2, $result);
		$this->assertContainsOnlyInstancesOf(ICalendarV2::class, $result);
		$this->assertContains($calendar1, $result);
		$this->assertContains($calendar2, $result);

		$this->manager->clear();

		$result = $this->manager->getCalendars();

		$this->assertCount(0, $result);
	}

	public function testGetCalendar() {
		/** @var ICalendarV2 | MockObject $calendar1 */
		$calendar1 = $this->createMock(ICalendarV2::class);
		$calendar1->method('getKey')->willReturn('key1');

		/** @var ICalendarV2 | MockObject $calendar2 */
		$calendar2 = $this->createMock(ICalendarV2::class);
		$calendar2->method('getKey')->willReturn('key2');

		$this->manager->registerCalendar($calendar1);
		$this->manager->registerCalendar($calendar2);

		$result = $this->manager->getCalendar('key1');
		$this->assertEquals($calendar1, $result);
		$this->assertInstanceOf(ICalendarV2::class, $result);

		$result = $this->manager->getCalendar('key2');
		$this->assertEquals($calendar1, $result);
		$this->assertInstanceOf(ICalendarV2::class, $result);

		$result = $this->manager->getCalendar('key3');
		$this->assertNull($result);

		$this->manager->clear();

		$result = $this->manager->getCalendar('key1');

		$this->assertNull($result);
	}

	public function testEnabledIfNot() {
		$isEnabled = $this->manager->isEnabled();
		$this->assertFalse($isEnabled);
	}

	public function testIfEnabledIfSo() {
		/** @var ICalendarV2 | MockObject $calendar */
		$calendar = $this->createMock(ICalendarV2::class);
		$this->manager->registerCalendar($calendar);

		$isEnabled = $this->manager->isEnabled();
		$this->assertTrue($isEnabled);
	}
}
