<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2017, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\unit\CalDAV;

use OCA\DAV\AppInfo\PluginManager;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\CalendarHome;
use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider;
use OCA\DAV\CalDAV\Outbox;
use Sabre\CalDAV\Schedule\Inbox;
use Sabre\DAV\MkCol;
use Test\TestCase;

class CalendarHomeTest extends TestCase {

	/** @var CalDavBackend | \PHPUnit_Framework_MockObject_MockObject */
	private $backend;

	/** @var array */
	private $principalInfo = [];

	/** @var PluginManager */
	private $pluginManager;

	/** @var CalendarHome */
	private $calendarHome;

	protected function setUp(): void {
		parent::setUp();

		$this->backend = $this->createMock(CalDavBackend::class);
		$this->principalInfo = [
			'uri' => 'user-principal-123',
		];
		$this->pluginManager = $this->createMock(PluginManager::class);

		$this->calendarHome = new CalendarHome($this->backend,
			$this->principalInfo);

		// Replace PluginManager with our mock
		$reflection = new \ReflectionClass($this->calendarHome);
		$reflectionProperty = $reflection->getProperty('pluginManager');
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($this->calendarHome, $this->pluginManager);
	}

	public function testCreateCalendarValidName() {
		/** @var MkCol | \PHPUnit_Framework_MockObject_MockObject $mkCol */
		$mkCol = $this->createMock(MkCol::class);

		$mkCol->method('getResourceType')
			->willReturn(['{DAV:}collection',
				'{urn:ietf:params:xml:ns:caldav}calendar']);
		$mkCol->method('getRemainingValues')
			->willReturn(['... properties ...']);

		$this->backend->expects($this->once())
			->method('createCalendar')
			->with('user-principal-123', 'name123', ['... properties ...']);

		$this->calendarHome->createExtendedCollection('name123', $mkCol);
	}

	public function testCreateCalendarReservedName() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);
		$this->expectExceptionMessage('The resource you tried to create has a reserved name');

		/** @var MkCol | \PHPUnit_Framework_MockObject_MockObject $mkCol */
		$mkCol = $this->createMock(MkCol::class);

		$this->calendarHome->createExtendedCollection('contact_birthdays', $mkCol);
	}

	public function testCreateCalendarReservedNameAppGenerated() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);
		$this->expectExceptionMessage('The resource you tried to create has a reserved name');

		/** @var MkCol | \PHPUnit_Framework_MockObject_MockObject $mkCol */
		$mkCol = $this->createMock(MkCol::class);

		$this->calendarHome->createExtendedCollection('app-generated--example--foo-1', $mkCol);
	}

	public function testGetChildren():void {
		$this->backend
			->expects($this->at(0))
			->method('getCalendarsForUser')
			->with('user-principal-123')
			->willReturn([]);

		$this->backend
			->expects($this->at(1))
			->method('getSubscriptionsForUser')
			->with('user-principal-123')
			->willReturn([]);

		$calendarPlugin1 = $this->createMock(ICalendarProvider::class);
		$calendarPlugin1
			->expects($this->once())
			->method('fetchAllForCalendarHome')
			->with('user-principal-123')
			->willReturn(['plugin1calendar1', 'plugin1calendar2']);

		$calendarPlugin2 = $this->createMock(ICalendarProvider::class);
		$calendarPlugin2
			->expects($this->once())
			->method('fetchAllForCalendarHome')
			->with('user-principal-123')
			->willReturn(['plugin2calendar1', 'plugin2calendar2']);

		$this->pluginManager
			->expects($this->once())
			->method('getCalendarPlugins')
			->with()
			->willReturn([$calendarPlugin1, $calendarPlugin2]);

		$actual = $this->calendarHome->getChildren();

		$this->assertCount(6, $actual);
		$this->assertInstanceOf(Inbox::class, $actual[0]);
		$this->assertInstanceOf(Outbox::class, $actual[1]);
		$this->assertEquals('plugin1calendar1', $actual[2]);
		$this->assertEquals('plugin1calendar2', $actual[3]);
		$this->assertEquals('plugin2calendar1', $actual[4]);
		$this->assertEquals('plugin2calendar2', $actual[5]);
	}

	public function testGetChildNonAppGenerated():void {
		$this->backend
			->expects($this->at(0))
			->method('getCalendarsForUser')
			->with('user-principal-123')
			->willReturn([]);

		$this->backend
			->expects($this->at(1))
			->method('getSubscriptionsForUser')
			->with('user-principal-123')
			->willReturn([]);

		$this->pluginManager
			->expects($this->never())
			->method('getCalendarPlugins');

		$this->expectException(\Sabre\DAV\Exception\NotFound::class);
		$this->expectExceptionMessage('Node with name \'personal\' could not be found');

		$this->calendarHome->getChild('personal');
	}

	public function testGetChildAppGenerated():void {
		$this->backend
			->expects($this->at(0))
			->method('getCalendarsForUser')
			->with('user-principal-123')
			->willReturn([]);

		$this->backend
			->expects($this->at(1))
			->method('getSubscriptionsForUser')
			->with('user-principal-123')
			->willReturn([]);

		$calendarPlugin1 = $this->createMock(ICalendarProvider::class);
		$calendarPlugin1
			->expects($this->once())
			->method('getAppId')
			->with()
			->willReturn('calendar_plugin_1');
		$calendarPlugin1
			->expects($this->never())
			->method('hasCalendarInCalendarHome');
		$calendarPlugin1
			->expects($this->never())
			->method('getCalendarInCalendarHome');

		$externalCalendarMock = $this->createMock(ExternalCalendar::class);

		$calendarPlugin2 = $this->createMock(ICalendarProvider::class);
		$calendarPlugin2
			->expects($this->once())
			->method('getAppId')
			->with()
			->willReturn('calendar_plugin_2');
		$calendarPlugin2
			->expects($this->once())
			->method('hasCalendarInCalendarHome')
			->with('user-principal-123', 'calendar-uri-from-backend')
			->willReturn(true);
		$calendarPlugin2
			->expects($this->once())
			->method('getCalendarInCalendarHome')
			->with('user-principal-123', 'calendar-uri-from-backend')
			->willReturn($externalCalendarMock);

		$this->pluginManager
			->expects($this->once())
			->method('getCalendarPlugins')
			->with()
			->willReturn([$calendarPlugin1, $calendarPlugin2]);

		$actual = $this->calendarHome->getChild('app-generated--calendar_plugin_2--calendar-uri-from-backend');
		$this->assertEquals($externalCalendarMock, $actual);
	}
}
