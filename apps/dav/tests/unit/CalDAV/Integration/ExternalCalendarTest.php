<?php
/**
 * @copyright 2020, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Tests\unit\CalDAV\Integration;

use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use Test\TestCase;

class ExternalCalendarTest extends TestCase {

	private $abstractExternalCalendar;

	protected function setUp(): void {
		parent::setUp();

		$this->abstractExternalCalendar =
			$this->getMockForAbstractClass(ExternalCalendar::class, ['example-app-id', 'calendar-uri-in-backend']);
	}

	public function testGetName():void {
		// Check that the correct name is returned
		$this->assertEquals('app-generated--example-app-id--calendar-uri-in-backend',
			$this->abstractExternalCalendar->getName());

		// Check that the method is final and can't be overriden by other classes
		$reflectionMethod = new \ReflectionMethod(ExternalCalendar::class, 'getName');
		$this->assertTrue($reflectionMethod->isFinal());
	}

	public function testSetName():void {
		// Check that the method is final and can't be overriden by other classes
		$reflectionMethod = new \ReflectionMethod(ExternalCalendar::class, 'setName');
		$this->assertTrue($reflectionMethod->isFinal());

		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);
		$this->expectExceptionMessage('Renaming calendars is not yet supported');

		$this->abstractExternalCalendar->setName('other-name');
	}

	public function createDirectory():void {
		// Check that the method is final and can't be overriden by other classes
		$reflectionMethod = new \ReflectionMethod(ExternalCalendar::class, 'createDirectory');
		$this->assertTrue($reflectionMethod->isFinal());

		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);
		$this->expectExceptionMessage('Creating collections in calendar objects is not allowed');

		$this->abstractExternalCalendar->createDirectory('other-name');
	}

	public function testIsAppGeneratedCalendar():void {
		$this->assertFalse(ExternalCalendar::isAppGeneratedCalendar('personal'));
		$this->assertFalse(ExternalCalendar::isAppGeneratedCalendar('work'));
		$this->assertFalse(ExternalCalendar::isAppGeneratedCalendar('contact_birthdays'));
		$this->assertFalse(ExternalCalendar::isAppGeneratedCalendar('company'));
		$this->assertFalse(ExternalCalendar::isAppGeneratedCalendar('app-generated'));
		$this->assertFalse(ExternalCalendar::isAppGeneratedCalendar('app-generated--example'));

		$this->assertTrue(ExternalCalendar::isAppGeneratedCalendar('app-generated--deck--board-1'));
		$this->assertTrue(ExternalCalendar::isAppGeneratedCalendar('app-generated--example--foo-2'));
		$this->assertTrue(ExternalCalendar::isAppGeneratedCalendar('app-generated--example--foo--2'));
	}

	/**
	 * @dataProvider splitAppGeneratedCalendarUriDataProvider
	 */
	public function testSplitAppGeneratedCalendarUriInvalid(string $name):void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Provided calendar uri was not app-generated');

		ExternalCalendar::splitAppGeneratedCalendarUri($name);
	}

	public function splitAppGeneratedCalendarUriDataProvider():array {
		return [
			['personal'],
			['foo_shared_by_admin'],
			['contact_birthdays'],
		];
	}

	public function testSplitAppGeneratedCalendarUri():void {
		$this->assertEquals(['deck', 'board-1'], ExternalCalendar::splitAppGeneratedCalendarUri('app-generated--deck--board-1'));
		$this->assertEquals(['example', 'foo-2'], ExternalCalendar::splitAppGeneratedCalendarUri('app-generated--example--foo-2'));
		$this->assertEquals(['example', 'foo--2'], ExternalCalendar::splitAppGeneratedCalendarUri('app-generated--example--foo--2'));
	}

	public function testDoesViolateReservedName():void {
		$this->assertFalse(ExternalCalendar::doesViolateReservedName('personal'));
		$this->assertFalse(ExternalCalendar::doesViolateReservedName('work'));
		$this->assertFalse(ExternalCalendar::doesViolateReservedName('contact_birthdays'));
		$this->assertFalse(ExternalCalendar::doesViolateReservedName('company'));

		$this->assertTrue(ExternalCalendar::doesViolateReservedName('app-generated'));
		$this->assertTrue(ExternalCalendar::doesViolateReservedName('app-generated-calendar'));
		$this->assertTrue(ExternalCalendar::doesViolateReservedName('app-generated--deck-123'));
	}
}
