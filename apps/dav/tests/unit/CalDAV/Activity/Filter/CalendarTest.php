<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\DAV\Tests\unit\CalDAV\Activity\Filter;

use OCA\DAV\CalDAV\Activity\Filter\Calendar;
use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;
use Test\TestCase;

class CalendarTest extends TestCase {

	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $url;

	/** @var IFilter|\PHPUnit_Framework_MockObject_MockObject */
	protected $filter;

	protected function setUp(): void {
		parent::setUp();
		$this->url = $this->createMock(IURLGenerator::class);
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});

		$this->filter = new Calendar(
			$l, $this->url
		);
	}

	public function testGetIcon() {
		$this->url->expects($this->once())
			->method('imagePath')
			->with('core', 'places/calendar.svg')
			->willReturn('path-to-icon');

		$this->url->expects($this->once())
			->method('getAbsoluteURL')
			->with('path-to-icon')
			->willReturn('absolute-path-to-icon');

		$this->assertEquals('absolute-path-to-icon', $this->filter->getIcon());
	}

	public function dataFilterTypes() {
		return [
			[[], []],
			[['calendar', 'calendar_event'], ['calendar', 'calendar_event']],
			[['calendar', 'calendar_event', 'calendar_todo'], ['calendar', 'calendar_event']],
			[['calendar', 'calendar_event', 'files'], ['calendar', 'calendar_event']],
		];
	}

	/**
	 * @dataProvider dataFilterTypes
	 * @param string[] $types
	 * @param string[] $expected
	 */
	public function testFilterTypes($types, $expected) {
		$this->assertEquals($expected, $this->filter->filterTypes($types));
	}
}
