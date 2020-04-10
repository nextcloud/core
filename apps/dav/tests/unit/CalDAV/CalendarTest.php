<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2020, Gary Kim <gary@garykim.dev>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Gary Kim <gary@garykim.dev>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OCA\DAV\CalDAV\BirthdayService;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;
use OCP\IConfig;
use OCP\IL10N;
use Sabre\DAV\PropPatch;
use Sabre\VObject\Reader;
use Test\TestCase;

class CalendarTest extends TestCase {

	/** @var IL10N */
	protected $l10n;

	/** @var IConfig */
	protected $config;

	protected function setUp(): void {
		parent::setUp();
		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()->getMock();
		$this->config = $this->createMock(IConfig::class);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
	}

	public function testDelete() {
		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->getMockBuilder(CalDavBackend::class)->disableOriginalConstructor()->getMock();
		$backend->expects($this->once())->method('updateShares');
		$backend->expects($this->any())->method('getShares')->willReturn([
			['href' => 'principal:user2']
		]);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];
		$c = new Calendar($backend, $calendarInfo, $this->l10n, $this->config);
		$c->delete();
	}


	public function testDeleteFromGroup() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->getMockBuilder(CalDavBackend::class)->disableOriginalConstructor()->getMock();
		$backend->expects($this->never())->method('updateShares');
		$backend->expects($this->any())->method('getShares')->willReturn([
			['href' => 'principal:group2']
		]);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];
		$c = new Calendar($backend, $calendarInfo, $this->l10n, $this->config);
		$c->delete();
	}

	public function testDeleteOwn() {
		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->createMock(CalDavBackend::class);
		$backend->expects($this->never())->method('updateShares');
		$backend->expects($this->never())->method('getShares');

		$this->config->expects($this->never())->method('setUserValue');

		$backend->expects($this->once())->method('deleteCalendar')
			->with(666);

		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user1',
			'id' => 666,
			'uri' => 'cal',
		];
		$c = new Calendar($backend, $calendarInfo, $this->l10n, $this->config);
		$c->delete();
	}

	public function testDeleteBirthdayCalendar() {
		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->createMock(CalDavBackend::class);
		$backend->expects($this->once())->method('deleteCalendar')
			->with(666);

		$this->config->expects($this->once())
			->method('setUserValue')
			->with('user1', 'dav', 'generateBirthdayCalendar', 'no');

		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'principals/users/user1',
			'principaluri' => 'principals/users/user1',
			'id' => 666,
			'uri' => 'contact_birthdays',
		];

		$c = new Calendar($backend, $calendarInfo, $this->l10n, $this->config);
		$c->delete();
	}

	public function dataPropPatch() {
		return [
			['user1', 'user2', [], true],
			['user1', 'user2', [
				'{http://owncloud.org/ns}calendar-enabled' => true,
			], true],
			['user1', 'user2', [
				'{DAV:}displayname' => true,
			], true],
			['user1', 'user2', [
				'{DAV:}displayname' => true,
				'{http://owncloud.org/ns}calendar-enabled' => true,
			], true],
			['user1', 'user1', [], false],
			['user1', 'user1', [
				'{http://owncloud.org/ns}calendar-enabled' => true,
			], false],
			['user1', 'user1', [
				'{DAV:}displayname' => true,
			], false],
			['user1', 'user1', [
				'{DAV:}displayname' => true,
				'{http://owncloud.org/ns}calendar-enabled' => true,
			], false],
		];
	}

	/**
	 * @dataProvider dataPropPatch
	 */
	public function testPropPatch($ownerPrincipal, $principalUri, $mutations, $shared) {
		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->getMockBuilder(CalDavBackend::class)->disableOriginalConstructor()->getMock();
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => $ownerPrincipal,
			'principaluri' => $principalUri,
			'id' => 666,
			'uri' => 'default'
		];
		$c = new Calendar($backend, $calendarInfo, $this->l10n, $this->config);
		$propPatch = new PropPatch($mutations);

		if (!$shared) {
			$backend->expects($this->once())
				->method('updateCalendar')
				->with(666, $propPatch);
		}
		$c->propPatch($propPatch);
		$this->addToAssertionCount(1);
	}

	/**
	 * @dataProvider providesReadOnlyInfo
	 */
	public function testAcl($expectsWrite, $readOnlyValue, $hasOwnerSet, $uri = 'default') {
		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->getMockBuilder(CalDavBackend::class)->disableOriginalConstructor()->getMock();
		$backend->expects($this->any())->method('applyShareAcl')->willReturnArgument(1);
		$calendarInfo = [
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => $uri
		];
		if (!is_null($readOnlyValue)) {
			$calendarInfo['{http://owncloud.org/ns}read-only'] = $readOnlyValue;
		}
		if ($hasOwnerSet) {
			$calendarInfo['{http://owncloud.org/ns}owner-principal'] = 'user1';
		}
		$c = new Calendar($backend, $calendarInfo, $this->l10n, $this->config);
		$acl = $c->getACL();
		$childAcl = $c->getChildACL();

		$expectedAcl = [[
			'privilege' => '{DAV:}read',
			'principal' => $hasOwnerSet ? 'user1' : 'user2',
			'protected' => true
		], [
			'privilege' => '{DAV:}read',
			'principal' => ($hasOwnerSet ? 'user1' : 'user2') . '/calendar-proxy-write',
			'protected' => true,
		], [
			'privilege' => '{DAV:}read',
			'principal' => ($hasOwnerSet ? 'user1' : 'user2') . '/calendar-proxy-read',
			'protected' => true,
		]];
		if ($uri === BirthdayService::BIRTHDAY_CALENDAR_URI) {
			$expectedAcl[] = [
				'privilege' => '{DAV:}write-properties',
				'principal' => $hasOwnerSet ? 'user1' : 'user2',
				'protected' => true
			];
			$expectedAcl[] = [
				'privilege' => '{DAV:}write-properties',
				'principal' => ($hasOwnerSet ? 'user1' : 'user2') . '/calendar-proxy-write',
				'protected' => true
			];
		} else {
			$expectedAcl[] = [
				'privilege' => '{DAV:}write',
				'principal' => $hasOwnerSet ? 'user1' : 'user2',
				'protected' => true
			];
			$expectedAcl[] = [
				'privilege' => '{DAV:}write',
				'principal' => ($hasOwnerSet ? 'user1' : 'user2') . '/calendar-proxy-write',
				'protected' => true
			];
		}

		$expectedAcl[] = [
			'privilege' => '{DAV:}write-properties',
			'principal' => ($hasOwnerSet ? 'user1' : 'user2') . '/calendar-proxy-read',
			'protected' => true
		];

		if ($hasOwnerSet) {
			$expectedAcl[] = [
				'privilege' => '{DAV:}read',
				'principal' => 'user2',
				'protected' => true
			];
			if ($expectsWrite) {
				$expectedAcl[] = [
					'privilege' => '{DAV:}write',
					'principal' => 'user2',
					'protected' => true
				];
			} else {
				$expectedAcl[] = [
					'privilege' => '{DAV:}write-properties',
					'principal' => 'user2',
					'protected' => true
				];
			}
		}
		$this->assertEquals($expectedAcl, $acl);
		$this->assertEquals($expectedAcl, $childAcl);
	}

	public function providesReadOnlyInfo() {
		return [
			'read-only property not set' => [true, null, true],
			'read-only property is false' => [true, false, true],
			'read-only property is true' => [false, true, true],
			'read-only property not set and no owner' => [true, null, false],
			'read-only property is false and no owner' => [true, false, false],
			'read-only property is true and no owner' => [false, true, false],
			'birthday calendar' => [false, false, false, BirthdayService::BIRTHDAY_CALENDAR_URI]
		];
	}

	/**
	 * @dataProvider providesConfidentialClassificationData
	 * @param int $expectedChildren
	 * @param bool $isShared
	 */
	public function testPrivateClassification($expectedChildren, $isShared) {

		$calObject0 = ['uri' => 'event-0', 'classification' => CalDavBackend::CLASSIFICATION_PUBLIC];
		$calObject1 = ['uri' => 'event-1', 'classification' => CalDavBackend::CLASSIFICATION_CONFIDENTIAL];
		$calObject2 = ['uri' => 'event-2', 'classification' => CalDavBackend::CLASSIFICATION_PRIVATE];

		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->getMockBuilder(CalDavBackend::class)->disableOriginalConstructor()->getMock();
		$backend->expects($this->any())->method('getCalendarObjects')->willReturn([
			$calObject0, $calObject1, $calObject2
		]);
		$backend->expects($this->any())->method('getMultipleCalendarObjects')
			->with(666, ['event-0', 'event-1', 'event-2'])
			->willReturn([
				$calObject0, $calObject1, $calObject2
			]);
		$backend->expects($this->any())->method('getCalendarObject')
			->willReturn($calObject2)->with(666, 'event-2');
		$backend->expects($this->any())->method('applyShareAcl')->willReturnArgument(1);

		$calendarInfo = [
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		if ($isShared) {
			$calendarInfo['{http://owncloud.org/ns}owner-principal'] = 'user1';

		}
		$c = new Calendar($backend, $calendarInfo, $this->l10n, $this->config);
		$children = $c->getChildren();
		$this->assertEquals($expectedChildren, count($children));
		$children = $c->getMultipleChildren(['event-0', 'event-1', 'event-2']);
		$this->assertEquals($expectedChildren, count($children));

		$this->assertEquals(!$isShared, $c->childExists('event-2'));
	}

	/**
	 * @dataProvider providesConfidentialClassificationData
	 * @param int $expectedChildren
	 * @param bool $isShared
	 */
	public function testConfidentialClassification($expectedChildren, $isShared) {
		$start = '20160609';
		$end = '20160610';

		$calData = <<<EOD
BEGIN:VCALENDAR
PRODID:-//ownCloud calendar v1.2.2
BEGIN:VEVENT
CREATED:20160602T133732
DTSTAMP:20160602T133732
LAST-MODIFIED:20160602T133732
UID:wej2z68l9h
SUMMARY:Test Event
LOCATION:Somewhere ...
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;CUTYPE=INDIVIDUAL;CN=de
 epdiver:MAILTO:thomas.mueller@tmit.eu
ORGANIZER;CN=deepdiver:MAILTO:thomas.mueller@tmit.eu
DESCRIPTION:maybe ....
DTSTART;TZID=Europe/Berlin;VALUE=DATE:$start
DTEND;TZID=Europe/Berlin;VALUE=DATE:$end
RRULE:FREQ=DAILY
BEGIN:VALARM
ACTION:AUDIO
TRIGGER:-PT15M
END:VALARM
END:VEVENT
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
DTSTART:19810329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
TZNAME:MESZ
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
END:DAYLIGHT
BEGIN:STANDARD
DTSTART:19961027T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
TZNAME:MEZ
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
END:STANDARD
END:VTIMEZONE
END:VCALENDAR
EOD;

		$calObject0 = ['uri' => 'event-0', 'classification' => CalDavBackend::CLASSIFICATION_PUBLIC];
		$calObject1 = ['uri' => 'event-1', 'classification' => CalDavBackend::CLASSIFICATION_CONFIDENTIAL, 'calendardata' => $calData];
		$calObject2 = ['uri' => 'event-2', 'classification' => CalDavBackend::CLASSIFICATION_PRIVATE];

		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->getMockBuilder(CalDavBackend::class)->disableOriginalConstructor()->getMock();
		$backend->expects($this->any())->method('getCalendarObjects')->willReturn([
			$calObject0, $calObject1, $calObject2
		]);
		$backend->expects($this->any())->method('getMultipleCalendarObjects')
			->with(666, ['event-0', 'event-1', 'event-2'])
			->willReturn([
				$calObject0, $calObject1, $calObject2
			]);
		$backend->expects($this->any())->method('getCalendarObject')
			->willReturn($calObject1)->with(666, 'event-1');
		$backend->expects($this->any())->method('applyShareAcl')->willReturnArgument(1);

		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => $isShared ? 'user1' : 'user2',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];
		$c = new Calendar($backend, $calendarInfo, $this->l10n, $this->config);

		$this->assertEquals(count($c->getChildren()), $expectedChildren);

		// test private event
		$privateEvent = $c->getChild('event-1');
		$calData = $privateEvent->get();
		$event = Reader::read($calData);

		$this->assertEquals($start, $event->VEVENT->DTSTART->getValue());
		$this->assertEquals($end, $event->VEVENT->DTEND->getValue());

		if ($isShared) {
			$this->assertEquals('Busy', $event->VEVENT->SUMMARY->getValue());
			$this->assertArrayNotHasKey('ATTENDEE', $event->VEVENT);
			$this->assertArrayNotHasKey('LOCATION', $event->VEVENT);
			$this->assertArrayNotHasKey('DESCRIPTION', $event->VEVENT);
			$this->assertArrayNotHasKey('ORGANIZER', $event->VEVENT);
		} else {
			$this->assertEquals('Test Event', $event->VEVENT->SUMMARY->getValue());
		}

		// Test l10n
		$l10n = $this->createMock(IL10N::class);
		if ($isShared) {
			$l10n->expects($this->once())
				->method('t')
				->with('Busy')
				->willReturn("Translated busy");
		} else {
			$l10n->expects($this->never());
		}
		$c = new Calendar($backend, $calendarInfo, $l10n, $this->config);

		$calData = $c->getChild('event-1')->get();
		$event = Reader::read($calData);

		if ($isShared) {
			$this->assertEquals('Translated busy', $event->VEVENT->SUMMARY->getValue());
		} else {
			$this->assertEquals('Test Event', $event->VEVENT->SUMMARY->getValue());
		}
	}

	public function providesConfidentialClassificationData() {
		return [
			[3, false],
			[2, true]
		];
	}

	public function testRemoveVAlarms() {
		$publicObjectData = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud calendar v1.5.6
CALSCALE:GREGORIAN
BEGIN:VEVENT
CREATED:20171022T125130
DTSTAMP:20171022T125130
LAST-MODIFIED:20171022T125130
UID:PPL24TH8UGOWE94XET87ER
SUMMARY:Foo bar blub
CLASS:PUBLIC
STATUS:CONFIRMED
DTSTART;VALUE=DATE:20171024
DTEND;VALUE=DATE:20171025
BEGIN:VALARM
ACTION:AUDIO
TRIGGER:-PT15M
END:VALARM
END:VEVENT
END:VCALENDAR

EOD;

		$confidentialObjectData = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud calendar v1.5.6
CALSCALE:GREGORIAN
BEGIN:VEVENT
CREATED:20171022T125130
DTSTAMP:20171022T125130
LAST-MODIFIED:20171022T125130
UID:PPL24TH8UGOWE94XET87ER
SUMMARY:Foo bar blub
CLASS:CONFIDENTIAL
STATUS:CONFIRMED
DTSTART;VALUE=DATE:20171024
DTEND;VALUE=DATE:20171025
BEGIN:VALARM
ACTION:AUDIO
TRIGGER:-PT15M
END:VALARM
END:VEVENT
END:VCALENDAR

EOD;

		$publicObjectDataWithoutVAlarm = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud calendar v1.5.6
CALSCALE:GREGORIAN
BEGIN:VEVENT
CREATED:20171022T125130
DTSTAMP:20171022T125130
LAST-MODIFIED:20171022T125130
UID:PPL24TH8UGOWE94XET87ER
SUMMARY:Foo bar blub
CLASS:PUBLIC
STATUS:CONFIRMED
DTSTART;VALUE=DATE:20171024
DTEND;VALUE=DATE:20171025
END:VEVENT
END:VCALENDAR

EOD;

		$confidentialObjectCleaned = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud calendar v1.5.6
CALSCALE:GREGORIAN
BEGIN:VEVENT
CREATED:20171022T125130
UID:PPL24TH8UGOWE94XET87ER
SUMMARY:Busy
CLASS:CONFIDENTIAL
DTSTART;VALUE=DATE:20171024
DTEND;VALUE=DATE:20171025
END:VEVENT
END:VCALENDAR

EOD;



		$publicObject = ['uri' => 'event-0',
			'classification' => CalDavBackend::CLASSIFICATION_PUBLIC,
			'calendardata' => $publicObjectData];

		$confidentialObject = ['uri' => 'event-1',
			'classification' => CalDavBackend::CLASSIFICATION_CONFIDENTIAL,
			'calendardata' => $confidentialObjectData];

		/** @var \PHPUnit_Framework_MockObject_MockObject | CalDavBackend $backend */
		$backend = $this->createMock(CalDavBackend::class);
		$backend->expects($this->any())
			->method('getCalendarObjects')
			->willReturn([$publicObject, $confidentialObject]);

		$backend->expects($this->any())
			->method('getMultipleCalendarObjects')
			->with(666, ['event-0', 'event-1'])
			->willReturn([$publicObject, $confidentialObject]);

		$backend->expects($this->any())
			->method('getCalendarObject')
			->willReturnCallback(function ($cId, $uri) use ($publicObject, $confidentialObject) {
				switch($uri) {
					case 'event-0':
						return $publicObject;

					case 'event-1':
						return $confidentialObject;

					default:
						throw new \Exception('unexpected uri');
				}
			});

		$backend->expects($this->any())
			->method('applyShareAcl')
			->willReturnArgument(1);

		$calendarInfoOwner = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user1',
			'id' => 666,
			'uri' => 'cal',
		];
		$calendarInfoSharedRW = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];
		$calendarInfoSharedRO = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'{http://owncloud.org/ns}read-only' => true,
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$ownerCalendar = new Calendar($backend, $calendarInfoOwner, $this->l10n, $this->config);
		$rwCalendar = new Calendar($backend, $calendarInfoSharedRW, $this->l10n, $this->config);
		$roCalendar = new Calendar($backend, $calendarInfoSharedRO, $this->l10n, $this->config);

		$this->assertEquals(count($ownerCalendar->getChildren()), 2);
		$this->assertEquals(count($rwCalendar->getChildren()), 2);
		$this->assertEquals(count($roCalendar->getChildren()), 2);

		// calendar data shall not be altered for the owner
		$this->assertEquals($ownerCalendar->getChild('event-0')->get(), $publicObjectData);
		$this->assertEquals($ownerCalendar->getChild('event-1')->get(), $confidentialObjectData);

		// valarms shall not be removed for read-write shares
		$this->assertEquals(
			$this->fixLinebreak($rwCalendar->getChild('event-0')->get()),
			$this->fixLinebreak($publicObjectData));
		$this->assertEquals(
			$this->fixLinebreak($rwCalendar->getChild('event-1')->get()),
			$this->fixLinebreak($confidentialObjectCleaned));

		// valarms shall be removed for read-only shares
		$this->assertEquals(
			$this->fixLinebreak($roCalendar->getChild('event-0')->get()),
			$this->fixLinebreak($publicObjectDataWithoutVAlarm));
		$this->assertEquals(
			$this->fixLinebreak($roCalendar->getChild('event-1')->get()),
			$this->fixLinebreak($confidentialObjectCleaned));

	}

	private function fixLinebreak($str) {
		return preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $str);
	}
}
