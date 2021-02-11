<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2017 Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author dartcafe <github@dartcafe.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author leith abdulla <online-nextcloud@eleith.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

use DateTime;
use DateTimeZone;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;
use OCP\IConfig;
use OCP\IL10N;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Xml\Property\Href;
use Sabre\DAVACL\IACL;

/**
 * Class CalDavBackendTest
 *
 * @group DB
 *
 * @package OCA\DAV\Tests\unit\CalDAV
 */
class CalDavBackendTest extends AbstractCalDavBackend {
	public function testCalendarOperations() {
		$calendarId = $this->createTestCalendar();

		// update it's display name
		$patch = new PropPatch([
			'{DAV:}displayname' => 'Unit test',
			'{urn:ietf:params:xml:ns:caldav}calendar-description' => 'Calendar used for unit testing'
		]);
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::updateCalendar');
		$this->backend->updateCalendar($calendarId, $patch);
		$patch->commit();
		$this->assertEquals(1, $this->backend->getCalendarsForUserCount(self::UNIT_TEST_USER));
		$calendars = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER);
		$this->assertCount(1, $calendars);
		$this->assertEquals('Unit test', $calendars[0]['{DAV:}displayname']);
		$this->assertEquals('Calendar used for unit testing', $calendars[0]['{urn:ietf:params:xml:ns:caldav}calendar-description']);
		$this->assertEquals('User\'s displayname', $calendars[0]['{http://nextcloud.com/ns}owner-displayname']);

		// delete the address book
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendar');
		$this->backend->deleteCalendar($calendars[0]['id']);
		$calendars = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER);
		$this->assertCount(0, $calendars);
	}

	public function providesSharingData() {
		return [
			[true, true, true, false, [
				[
					'href' => 'principal:' . self::UNIT_TEST_USER1,
					'readOnly' => false
				],
				[
					'href' => 'principal:' . self::UNIT_TEST_GROUP,
					'readOnly' => true
				]
			]],
			[true, true, true, false, [
				[
					'href' => 'principal:' . self::UNIT_TEST_GROUP,
					'readOnly' => true,
				],
				[
					'href' => 'principal:' . self::UNIT_TEST_GROUP2,
					'readOnly' => false,
				],
			]],
			[true, true, true, true, [
				[
					'href' => 'principal:' . self::UNIT_TEST_GROUP,
					'readOnly' => false,
				],
				[
					'href' => 'principal:' . self::UNIT_TEST_GROUP2,
					'readOnly' => true,
				],
			]],
			[true, false, false, false, [
				[
					'href' => 'principal:' . self::UNIT_TEST_USER1,
					'readOnly' => true
				],
			]],

		];
	}

	/**
	 * @dataProvider providesSharingData
	 */
	public function testCalendarSharing($userCanRead, $userCanWrite, $groupCanRead, $groupCanWrite, $add) {

		/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject $l10n */
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		$config = $this->createMock(IConfig::class);

		$this->userManager->expects($this->any())
			->method('userExists')
			->willReturn(true);

		$this->groupManager->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$calendarId = $this->createTestCalendar();
		$calendars = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER);
		$this->assertCount(1, $calendars);
		$calendar = new Calendar($this->backend, $calendars[0], $l10n, $config);
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::updateShares');
		$this->backend->updateShares($calendar, $add, []);
		$calendars = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER1);
		$this->assertCount(1, $calendars);
		$calendar = new Calendar($this->backend, $calendars[0], $l10n, $config);
		$acl = $calendar->getACL();
		$this->assertAcl(self::UNIT_TEST_USER, '{DAV:}read', $acl);
		$this->assertAcl(self::UNIT_TEST_USER, '{DAV:}write', $acl);
		$this->assertAccess($userCanRead, self::UNIT_TEST_USER1, '{DAV:}read', $acl);
		$this->assertAccess($userCanWrite, self::UNIT_TEST_USER1, '{DAV:}write', $acl);
		$this->assertEquals(self::UNIT_TEST_USER, $calendar->getOwner());

		// test acls on the child
		$uri = static::getUniqueID('calobj');
		$calData = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject');
		$this->backend->createCalendarObject($calendarId, $uri, $calData);

		/** @var IACL $child */
		$child = $calendar->getChild($uri);
		$acl = $child->getACL();
		$this->assertAcl(self::UNIT_TEST_USER, '{DAV:}read', $acl);
		$this->assertAcl(self::UNIT_TEST_USER, '{DAV:}write', $acl);
		$this->assertAccess($userCanRead, self::UNIT_TEST_USER1, '{DAV:}read', $acl);
		$this->assertAccess($userCanWrite, self::UNIT_TEST_USER1, '{DAV:}write', $acl);

		// delete the address book
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendar');
		$this->backend->deleteCalendar($calendars[0]['id']);
		$calendars = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER);
		$this->assertCount(0, $calendars);
	}

	public function testCalendarObjectsOperations() {
		$calendarId = $this->createTestCalendar();

		// create a card
		$uri = static::getUniqueID('calobj');
		$calData = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject');
		$this->backend->createCalendarObject($calendarId, $uri, $calData);

		// get all the cards
		$calendarObjects = $this->backend->getCalendarObjects($calendarId);
		$this->assertCount(1, $calendarObjects);
		$this->assertEquals($calendarId, $calendarObjects[0]['calendarid']);
		$this->assertArrayHasKey('classification', $calendarObjects[0]);

		// get the cards
		$calendarObject = $this->backend->getCalendarObject($calendarId, $uri);
		$this->assertNotNull($calendarObject);
		$this->assertArrayHasKey('id', $calendarObject);
		$this->assertArrayHasKey('uri', $calendarObject);
		$this->assertArrayHasKey('lastmodified', $calendarObject);
		$this->assertArrayHasKey('etag', $calendarObject);
		$this->assertArrayHasKey('size', $calendarObject);
		$this->assertArrayHasKey('classification', $calendarObject);
		$this->assertEquals($calData, $calendarObject['calendardata']);

		// update the card
		$calData = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
END:VEVENT
END:VCALENDAR
EOD;
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::updateCalendarObject');
		$this->backend->updateCalendarObject($calendarId, $uri, $calData);
		$calendarObject = $this->backend->getCalendarObject($calendarId, $uri);
		$this->assertEquals($calData, $calendarObject['calendardata']);

		// delete the card
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject');
		$this->backend->deleteCalendarObject($calendarId, $uri);
		$calendarObjects = $this->backend->getCalendarObjects($calendarId);
		$this->assertCount(0, $calendarObjects);
	}


	public function testMultipleCalendarObjectsWithSameUID() {
		$this->expectException(\Sabre\DAV\Exception\BadRequest::class);
		$this->expectExceptionMessage('Calendar object with uid already exists in this calendar collection.');

		$calendarId = $this->createTestCalendar();

		$calData = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-1
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$uri0 = static::getUniqueID('event');
		$uri1 = static::getUniqueID('event');
		$this->backend->createCalendarObject($calendarId, $uri0, $calData);
		$this->backend->createCalendarObject($calendarId, $uri1, $calData);
	}

	public function testMultiCalendarObjects() {
		$calendarId = $this->createTestCalendar();

		// create an event
		$calData = [];
		$calData[] = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-1
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$calData[] = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-2
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$calData[] = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-3
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$uri0 = static::getUniqueID('card');
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject');
		$this->backend->createCalendarObject($calendarId, $uri0, $calData[0]);
		$uri1 = static::getUniqueID('card');
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject');
		$this->backend->createCalendarObject($calendarId, $uri1, $calData[1]);
		$uri2 = static::getUniqueID('card');
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject');
		$this->backend->createCalendarObject($calendarId, $uri2, $calData[2]);

		// get all the cards
		$calendarObjects = $this->backend->getCalendarObjects($calendarId);
		$this->assertCount(3, $calendarObjects);

		// get the cards
		$calendarObjects = $this->backend->getMultipleCalendarObjects($calendarId, [$uri1, $uri2]);
		$this->assertCount(2, $calendarObjects);
		foreach ($calendarObjects as $card) {
			$this->assertArrayHasKey('id', $card);
			$this->assertArrayHasKey('uri', $card);
			$this->assertArrayHasKey('lastmodified', $card);
			$this->assertArrayHasKey('etag', $card);
			$this->assertArrayHasKey('size', $card);
			$this->assertArrayHasKey('classification', $card);
		}

		usort($calendarObjects, function ($a, $b) {
			return $a['id'] - $b['id'];
		});

		$this->assertEquals($calData[1], $calendarObjects[0]['calendardata']);
		$this->assertEquals($calData[2], $calendarObjects[1]['calendardata']);

		// delete the card
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject');
		$this->backend->deleteCalendarObject($calendarId, $uri0);
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject');
		$this->backend->deleteCalendarObject($calendarId, $uri1);
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject');
		$this->backend->deleteCalendarObject($calendarId, $uri2);
		$calendarObjects = $this->backend->getCalendarObjects($calendarId);
		$this->assertCount(0, $calendarObjects);
	}

	/**
	 * @dataProvider providesCalendarQueryParameters
	 */
	public function testCalendarQuery($expectedEventsInResult, $propFilters, $compFilter) {
		$calendarId = $this->createTestCalendar();
		$events = [];
		$events[0] = $this->createEvent($calendarId, '20130912T130000Z', '20130912T140000Z');
		$events[1] = $this->createEvent($calendarId, '20130912T150000Z', '20130912T170000Z');
		$events[2] = $this->createEvent($calendarId, '20130912T173000Z', '20130912T220000Z');
		$events[3] = $this->createEvent($calendarId, '21130912T130000Z', '22130912T130000Z');

		$result = $this->backend->calendarQuery($calendarId, [
			'name' => '',
			'prop-filters' => $propFilters,
			'comp-filters' => $compFilter
		]);

		$expectedEventsInResult = array_map(function ($index) use ($events) {
			return $events[$index];
		}, $expectedEventsInResult);
		$this->assertEqualsCanonicalizing($expectedEventsInResult, $result);
	}

	public function testGetCalendarObjectByUID() {
		$calendarId = $this->createTestCalendar();
		$uri = static::getUniqueID('calobj');
		$calData = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;


		$this->backend->createCalendarObject($calendarId, $uri, $calData);

		$co = $this->backend->getCalendarObjectByUID(self::UNIT_TEST_USER, '47d15e3ec8');
		$this->assertNotNull($co);
	}

	public function providesCalendarQueryParameters() {
		return [
			'all' => [[0, 1, 2, 3], [], []],
			'only-todos' => [[], ['name' => 'VTODO'], []],
			'only-events' => [[0, 1, 2, 3], [], [['name' => 'VEVENT', 'is-not-defined' => false, 'comp-filters' => [], 'time-range' => ['start' => null, 'end' => null], 'prop-filters' => []]],],
			'start' => [[1, 2, 3], [], [['name' => 'VEVENT', 'is-not-defined' => false, 'comp-filters' => [], 'time-range' => ['start' => new DateTime('2013-09-12 14:00:00', new DateTimeZone('UTC')), 'end' => null], 'prop-filters' => []]],],
			'end' => [[0], [], [['name' => 'VEVENT', 'is-not-defined' => false, 'comp-filters' => [], 'time-range' => ['start' => null, 'end' => new DateTime('2013-09-12 14:00:00', new DateTimeZone('UTC'))], 'prop-filters' => []]],],
			'future' => [[3], [], [['name' => 'VEVENT', 'is-not-defined' => false, 'comp-filters' => [], 'time-range' => ['start' => new DateTime('2099-09-12 14:00:00', new DateTimeZone('UTC')), 'end' => null], 'prop-filters' => []]],],
		];
	}

	public function testSyncSupport() {
		$calendarId = $this->createTestCalendar();

		// fist call without synctoken
		$changes = $this->backend->getChangesForCalendar($calendarId, '', 1);
		$syncToken = $changes['syncToken'];

		// add a change
		$event = $this->createEvent($calendarId, '20130912T130000Z', '20130912T140000Z');

		// look for changes
		$changes = $this->backend->getChangesForCalendar($calendarId, $syncToken, 1);
		$this->assertEquals($event, $changes['added'][0]);
	}

	public function testPublications() {
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CalDAV\CalDavBackend::createCalendar');

		$this->backend->createCalendar(self::UNIT_TEST_USER, 'Example', []);

		$calendarInfo = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER)[0];

		/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject $l10n */
		$l10n = $this->createMock(IL10N::class);
		$config = $this->createMock(IConfig::class);

		$calendar = new Calendar($this->backend, $calendarInfo, $l10n, $config);
		$calendar->setPublishStatus(true);
		$this->assertNotEquals(false, $calendar->getPublishStatus());

		$publicCalendars = $this->backend->getPublicCalendars();
		$this->assertCount(1, $publicCalendars);
		$this->assertEquals(true, $publicCalendars[0]['{http://owncloud.org/ns}public']);
		$this->assertEquals('User\'s displayname', $publicCalendars[0]['{http://nextcloud.com/ns}owner-displayname']);

		$publicCalendarURI = $publicCalendars[0]['uri'];
		$publicCalendar = $this->backend->getPublicCalendar($publicCalendarURI);
		$this->assertEquals(true, $publicCalendar['{http://owncloud.org/ns}public']);

		$calendar->setPublishStatus(false);
		$this->assertEquals(false, $calendar->getPublishStatus());

		$this->expectException(NotFound::class);
		$this->backend->getPublicCalendar($publicCalendarURI);
	}

	public function testSubscriptions() {
		$id = $this->backend->createSubscription(self::UNIT_TEST_USER, 'Subscription', [
			'{http://calendarserver.org/ns/}source' => new Href('test-source'),
			'{http://apple.com/ns/ical/}calendar-color' => '#1C4587',
			'{http://calendarserver.org/ns/}subscribed-strip-todos' => ''
		]);

		$subscriptions = $this->backend->getSubscriptionsForUser(self::UNIT_TEST_USER);
		$this->assertCount(1, $subscriptions);
		$this->assertEquals('#1C4587', $subscriptions[0]['{http://apple.com/ns/ical/}calendar-color']);
		$this->assertEquals(true, $subscriptions[0]['{http://calendarserver.org/ns/}subscribed-strip-todos']);
		$this->assertEquals($id, $subscriptions[0]['id']);

		$patch = new PropPatch([
			'{DAV:}displayname' => 'Unit test',
			'{http://apple.com/ns/ical/}calendar-color' => '#ac0606',
		]);
		$this->backend->updateSubscription($id, $patch);
		$patch->commit();

		$subscriptions = $this->backend->getSubscriptionsForUser(self::UNIT_TEST_USER);
		$this->assertCount(1, $subscriptions);
		$this->assertEquals($id, $subscriptions[0]['id']);
		$this->assertEquals('Unit test', $subscriptions[0]['{DAV:}displayname']);
		$this->assertEquals('#ac0606', $subscriptions[0]['{http://apple.com/ns/ical/}calendar-color']);

		$this->backend->deleteSubscription($id);
		$subscriptions = $this->backend->getSubscriptionsForUser(self::UNIT_TEST_USER);
		$this->assertCount(0, $subscriptions);
	}

	public function providesSchedulingData() {
		$data = <<<EOS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 3.5.0//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VTIMEZONE
TZID:Europe/Warsaw
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20170320T131655Z
LAST-MODIFIED:20170320T135019Z
DTSTAMP:20170320T135019Z
UID:7e908a6d-4c4e-48d7-bd62-59ab80fbf1a3
SUMMARY:TEST Z pg_escape_bytea
ORGANIZER;RSVP=TRUE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:k.klimczak@gromar.e
 u
ATTENDEE;RSVP=TRUE;CN=Zuzanna Leszek;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICI
 PANT:mailto:z.leszek@gromar.eu
ATTENDEE;RSVP=TRUE;CN=Marcin Pisarski;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTIC
 IPANT:mailto:m.pisarski@gromar.eu
ATTENDEE;RSVP=TRUE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:klimcz
 ak.k@gmail.com
ATTENDEE;RSVP=TRUE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:k_klim
 czak@tlen.pl
DTSTART;TZID=Europe/Warsaw:20170325T150000
DTEND;TZID=Europe/Warsaw:20170325T160000
TRANSP:OPAQUE
DESCRIPTION:Magiczna treść uzyskana za pomocą magicznego proszku.\n\nę
 żźćńłóÓŻŹĆŁĘ€śśśŚŚ\n               \,\,))))))))\;\,\n
           __))))))))))))))\,\n \\|/       -\\(((((''''((((((((.\n -*-==///
 ///((''  .     `))))))\,\n /|\\      ))| o    \;-.    '(((((
                     \,(\,\n          ( `|    /  )    \;))))'
                  \,_))^\;(~\n             |   |   |   \,))((((_     _____-
 -----~~~-.        %\,\;(\;(>'\;'~\n             o_)\;   \;    )))(((` ~---
 ~  `::           \\      %%~~)(v\;(`('~\n                   \;    ''''````
          `:       `:::|\\\,__\,%%    )\;`'\; ~\n                  |   _
              )     /      `:|`----'     `-'\n            ______/\\/~    |
                 /        /\n          /~\;\;.____/\;\;'  /          ___--\
 ,-(   `\;\;\;/\n         / //  _\;______\;'------~~~~~    /\;\;/\\    /\n
        //  | |                        / \;   \\\;\;\,\\\n       (<_  | \;
                      /'\,/-----'  _>\n        \\_| ||_
  //~\;~~~~~~~~~\n            `\\_|                   (\,~~  -Tua Xiong\n
                                   \\~\\\n
     ~~\n\n
SEQUENCE:1
X-MOZ-GENERATION:1
END:VEVENT
END:VCALENDAR
EOS;

		return [
			'no data' => [''],
			'failing on postgres' => [$data]
		];
	}

	/**
	 * @dataProvider providesSchedulingData
	 * @param $objectData
	 */
	public function testScheduling($objectData) {
		$this->backend->createSchedulingObject(self::UNIT_TEST_USER, 'Sample Schedule', $objectData);

		$sos = $this->backend->getSchedulingObjects(self::UNIT_TEST_USER);
		$this->assertCount(1, $sos);

		$so = $this->backend->getSchedulingObject(self::UNIT_TEST_USER, 'Sample Schedule');
		$this->assertNotNull($so);

		$this->backend->deleteSchedulingObject(self::UNIT_TEST_USER, 'Sample Schedule');

		$sos = $this->backend->getSchedulingObjects(self::UNIT_TEST_USER);
		$this->assertCount(0, $sos);
	}

	/**
	 * @dataProvider providesCalDataForGetDenormalizedData
	 */
	public function testGetDenormalizedData($expected, $key, $calData) {
		$actual = $this->backend->getDenormalizedData($calData);
		$this->assertEquals($expected, $actual[$key]);
	}

	public function providesCalDataForGetDenormalizedData() {
		return [
			'first occurrence before unix epoch starts' => [0, 'firstOccurence', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:413F269B-B51B-46B1-AFB6-40055C53A4DC\r\nDTSTAMP:20160309T095056Z\r\nDTSTART;VALUE=DATE:16040222\r\nDTEND;VALUE=DATE:16040223\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:SUMMARY\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"],
			'no first occurrence because yearly' => [null, 'firstOccurence', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:413F269B-B51B-46B1-AFB6-40055C53A4DC\r\nDTSTAMP:20160309T095056Z\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:SUMMARY\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"],
			'last occurrence is max when only last VEVENT in group is weekly' => [(new DateTime(CalDavBackend::MAX_DATE))->getTimestamp(), 'lastOccurence', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.3.0//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nDTSTART;TZID=America/Los_Angeles:20200812T103000\r\nDTEND;TZID=America/Los_Angeles:20200812T110000\r\nDTSTAMP:20200927T180638Z\r\nUID:asdfasdfasdf@google.com\r\nRECURRENCE-ID;TZID=America/Los_Angeles:20200811T123000\r\nCREATED:20200626T181848Z\r\nLAST-MODIFIED:20200922T192707Z\r\nSUMMARY:Weekly 1:1\r\nTRANSP:OPAQUE\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nDTSTART;TZID=America/Los_Angeles:20200728T123000\r\nDTEND;TZID=America/Los_Angeles:20200728T130000\r\nEXDATE;TZID=America/Los_Angeles:20200818T123000\r\nRRULE:FREQ=WEEKLY;BYDAY=TU\r\nDTSTAMP:20200927T180638Z\r\nUID:asdfasdfasdf@google.com\r\nCREATED:20200626T181848Z\r\nDESCRIPTION:Setting up recurring time on our calendars\r\nLAST-MODIFIED:20200922T192707Z\r\nSUMMARY:Weekly 1:1\r\nTRANSP:OPAQUE\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"],
			'first occurrence is found when not first VEVENT in group' => [(new DateTime('2020-09-01T110000', new DateTimeZone("America/Los_Angeles")))->getTimestamp(), 'firstOccurence', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.3.0//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nDTSTART;TZID=America/Los_Angeles:20201013T110000\r\nDTEND;TZID=America/Los_Angeles:20201013T120000\r\nDTSTAMP:20200927T180638Z\r\nUID:asdf0000@google.com\r\nRECURRENCE-ID;TZID=America/Los_Angeles:20201013T110000\r\nCREATED:20160330T034726Z\r\nLAST-MODIFIED:20200925T042014Z\r\nSTATUS:CONFIRMED\r\nTRANSP:OPAQUE\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nDTSTART;TZID=America/Los_Angeles:20200901T110000\r\nDTEND;TZID=America/Los_Angeles:20200901T120000\r\nRRULE:FREQ=WEEKLY;BYDAY=TU\r\nEXDATE;TZID=America/Los_Angeles:20200922T110000\r\nEXDATE;TZID=America/Los_Angeles:20200915T110000\r\nEXDATE;TZID=America/Los_Angeles:20200908T110000\r\nDTSTAMP:20200927T180638Z\r\nUID:asdf0000@google.com\r\nCREATED:20160330T034726Z\r\nLAST-MODIFIED:20200915T162810Z\r\nSTATUS:CONFIRMED\r\nTRANSP:OPAQUE\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"],
			'CLASS:PRIVATE' => [CalDavBackend::CLASSIFICATION_PRIVATE, 'classification', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//dmfs.org//mimedir.icalendar//EN\r\nBEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\nX-LIC-LOCATION:Europe/Berlin\r\nBEGIN:DAYLIGHT\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nDTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\nEND:DAYLIGHT\r\nBEGIN:STANDARD\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:20160419T130000\r\nSUMMARY:Test\r\nCLASS:PRIVATE\r\nTRANSP:OPAQUE\r\nSTATUS:CONFIRMED\r\nDTEND;TZID=Europe/Berlin:20160419T140000\r\nLAST-MODIFIED:20160419T074202Z\r\nDTSTAMP:20160419T074202Z\r\nCREATED:20160419T074202Z\r\nUID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310\r\nEND:VEVENT\r\nEND:VCALENDAR"],
			'CLASS:PUBLIC' => [CalDavBackend::CLASSIFICATION_PUBLIC, 'classification', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//dmfs.org//mimedir.icalendar//EN\r\nBEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\nX-LIC-LOCATION:Europe/Berlin\r\nBEGIN:DAYLIGHT\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nDTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\nEND:DAYLIGHT\r\nBEGIN:STANDARD\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:20160419T130000\r\nSUMMARY:Test\r\nCLASS:PUBLIC\r\nTRANSP:OPAQUE\r\nSTATUS:CONFIRMED\r\nDTEND;TZID=Europe/Berlin:20160419T140000\r\nLAST-MODIFIED:20160419T074202Z\r\nDTSTAMP:20160419T074202Z\r\nCREATED:20160419T074202Z\r\nUID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310\r\nEND:VEVENT\r\nEND:VCALENDAR"],
			'CLASS:CONFIDENTIAL' => [CalDavBackend::CLASSIFICATION_CONFIDENTIAL, 'classification', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//dmfs.org//mimedir.icalendar//EN\r\nBEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\nX-LIC-LOCATION:Europe/Berlin\r\nBEGIN:DAYLIGHT\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nDTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\nEND:DAYLIGHT\r\nBEGIN:STANDARD\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:20160419T130000\r\nSUMMARY:Test\r\nCLASS:CONFIDENTIAL\r\nTRANSP:OPAQUE\r\nSTATUS:CONFIRMED\r\nDTEND;TZID=Europe/Berlin:20160419T140000\r\nLAST-MODIFIED:20160419T074202Z\r\nDTSTAMP:20160419T074202Z\r\nCREATED:20160419T074202Z\r\nUID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310\r\nEND:VEVENT\r\nEND:VCALENDAR"],
			'no class set -> public' => [CalDavBackend::CLASSIFICATION_PUBLIC, 'classification', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//dmfs.org//mimedir.icalendar//EN\r\nBEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\nX-LIC-LOCATION:Europe/Berlin\r\nBEGIN:DAYLIGHT\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nDTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\nEND:DAYLIGHT\r\nBEGIN:STANDARD\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:20160419T130000\r\nSUMMARY:Test\r\nTRANSP:OPAQUE\r\nDTEND;TZID=Europe/Berlin:20160419T140000\r\nLAST-MODIFIED:20160419T074202Z\r\nDTSTAMP:20160419T074202Z\r\nCREATED:20160419T074202Z\r\nUID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310\r\nEND:VEVENT\r\nEND:VCALENDAR"],
			'unknown class -> private' => [CalDavBackend::CLASSIFICATION_PRIVATE, 'classification', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//dmfs.org//mimedir.icalendar//EN\r\nBEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\nX-LIC-LOCATION:Europe/Berlin\r\nBEGIN:DAYLIGHT\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nDTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\nEND:DAYLIGHT\r\nBEGIN:STANDARD\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:20160419T130000\r\nSUMMARY:Test\r\nCLASS:VERTRAULICH\r\nTRANSP:OPAQUE\r\nSTATUS:CONFIRMED\r\nDTEND;TZID=Europe/Berlin:20160419T140000\r\nLAST-MODIFIED:20160419T074202Z\r\nDTSTAMP:20160419T074202Z\r\nCREATED:20160419T074202Z\r\nUID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310\r\nEND:VEVENT\r\nEND:VCALENDAR"],
		];
	}

	public function testCalendarSearch() {
		$calendarId = $this->createTestCalendar();

		$uri = static::getUniqueID('calobj');
		$calData = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$this->backend->createCalendarObject($calendarId, $uri, $calData);

		$search1 = $this->backend->calendarSearch(self::UNIT_TEST_USER, [
			'comps' => [
				'VEVENT',
				'VTODO'
			],
			'props' => [
				'SUMMARY',
				'LOCATION'
			],
			'search-term' => 'Test',
		]);
		$this->assertEquals(count($search1), 1);


		// update the card
		$calData = <<<'EOD'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:123 Event 🙈
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
ATTENDEE;CN=test:mailto:foo@bar.com
END:VEVENT
END:VCALENDAR
EOD;
		$this->backend->updateCalendarObject($calendarId, $uri, $calData);

		$search2 = $this->backend->calendarSearch(self::UNIT_TEST_USER, [
			'comps' => [
				'VEVENT',
				'VTODO'
			],
			'props' => [
				'SUMMARY',
				'LOCATION'
			],
			'search-term' => 'Test',
		]);
		$this->assertEquals(count($search2), 0);

		$search3 = $this->backend->calendarSearch(self::UNIT_TEST_USER, [
			'comps' => [
				'VEVENT',
				'VTODO'
			],
			'props' => [
				'SUMMARY',
				'LOCATION'
			],
			'params' => [
				[
					'property' => 'ATTENDEE',
					'parameter' => 'CN'
				]
			],
			'search-term' => 'Test',
		]);
		$this->assertEquals(count($search3), 1);

		// t matches both summary and attendee's CN, but we want unique results
		$search4 = $this->backend->calendarSearch(self::UNIT_TEST_USER, [
			'comps' => [
				'VEVENT',
				'VTODO'
			],
			'props' => [
				'SUMMARY',
				'LOCATION'
			],
			'params' => [
				[
					'property' => 'ATTENDEE',
					'parameter' => 'CN'
				]
			],
			'search-term' => 't',
		]);
		$this->assertEquals(count($search4), 1);

		$this->backend->deleteCalendarObject($calendarId, $uri);

		$search5 = $this->backend->calendarSearch(self::UNIT_TEST_USER, [
			'comps' => [
				'VEVENT',
				'VTODO'
			],
			'props' => [
				'SUMMARY',
				'LOCATION'
			],
			'params' => [
				[
					'property' => 'ATTENDEE',
					'parameter' => 'CN'
				]
			],
			'search-term' => 't',
		]);
		$this->assertEquals(count($search5), 0);
	}

	/**
	 * @dataProvider searchDataProvider
	 */
	public function testSearch(bool $isShared, array $searchOptions, int $count) {
		$calendarId = $this->createTestCalendar();

		$uris = [];
		$calData = [];

		$uris[] = static::getUniqueID('calobj');
		$calData[] = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:Nextcloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-1
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$uris[] = static::getUniqueID('calobj');
		$calData[] = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:Nextcloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-2
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:123
LOCATION:Test
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$uris[] = static::getUniqueID('calobj');
		$calData[] = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:Nextcloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-3
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:123
ATTENDEE;CN=test:mailto:foo@bar.com
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PRIVATE
END:VEVENT
END:VCALENDAR
EOD;

		$uris[] = static::getUniqueID('calobj');
		$calData[] = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:Nextcloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8-4
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:123
ATTENDEE;CN=foobar:mailto:test@bar.com
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:CONFIDENTIAL
END:VEVENT
END:VCALENDAR
EOD;

		$uriCount = count($uris);
		for ($i = 0; $i < $uriCount; $i++) {
			$this->backend->createCalendarObject($calendarId,
				$uris[$i], $calData[$i]);
		}

		$calendarInfo = [
			'id' => $calendarId,
			'principaluri' => 'user1',
			'{http://owncloud.org/ns}owner-principal' => $isShared ? 'user2' : 'user1',
		];

		$result = $this->backend->search($calendarInfo, 'Test',
			['SUMMARY', 'LOCATION', 'ATTENDEE'], $searchOptions, null, null);

		$this->assertCount($count, $result);
	}

	public function searchDataProvider() {
		return [
			[false, [], 4],
			[true, ['timerange' => ['start' => new DateTime('2013-09-12 13:00:00'), 'end' => new DateTime('2013-09-12 14:00:00')]], 2],
			[true, ['timerange' => ['start' => new DateTime('2013-09-12 15:00:00'), 'end' => new DateTime('2013-09-12 16:00:00')]], 0],
		];
	}

	public function testSameUriSameIdForDifferentCalendarTypes() {
		$calendarId = $this->createTestCalendar();
		$subscriptionId = $this->createTestSubscription();

		$uri = static::getUniqueID('calobj');
		$calData = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$calData2 = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event 123
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$this->backend->createCalendarObject($calendarId, $uri, $calData);
		$this->backend->createCalendarObject($subscriptionId, $uri, $calData2, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);

		$this->assertEquals($calData, $this->backend->getCalendarObject($calendarId, $uri, CalDavBackend::CALENDAR_TYPE_CALENDAR)['calendardata']);
		$this->assertEquals($calData2, $this->backend->getCalendarObject($subscriptionId, $uri, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION)['calendardata']);
	}

	public function testPurgeAllCachedEventsForSubscription() {
		$subscriptionId = $this->createTestSubscription();
		$uri = static::getUniqueID('calobj');
		$calData = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:ownCloud Calendar
BEGIN:VEVENT
CREATED;VALUE=DATE-TIME:20130910T125139Z
UID:47d15e3ec8
LAST-MODIFIED;VALUE=DATE-TIME:20130910T125139Z
DTSTAMP;VALUE=DATE-TIME:20130910T125139Z
SUMMARY:Test Event
DTSTART;VALUE=DATE-TIME:20130912T130000Z
DTEND;VALUE=DATE-TIME:20130912T140000Z
CLASS:PUBLIC
END:VEVENT
END:VCALENDAR
EOD;

		$this->backend->createCalendarObject($subscriptionId, $uri, $calData, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);
		$this->backend->purgeAllCachedEventsForSubscription($subscriptionId);

		$this->assertEquals(null, $this->backend->getCalendarObject($subscriptionId, $uri, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION));
	}

	public function testCalendarMovement() {
		$this->backend->createCalendar(self::UNIT_TEST_USER, 'Example', []);

		$this->assertCount(1, $this->backend->getCalendarsForUser(self::UNIT_TEST_USER));

		$calendarInfoUser = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER)[0];

		$this->backend->moveCalendar('Example', self::UNIT_TEST_USER, self::UNIT_TEST_USER1);
		$this->assertCount(0, $this->backend->getCalendarsForUser(self::UNIT_TEST_USER));
		$this->assertCount(1, $this->backend->getCalendarsForUser(self::UNIT_TEST_USER1));

		$calendarInfoUser1 = $this->backend->getCalendarsForUser(self::UNIT_TEST_USER1)[0];
		$this->assertEquals($calendarInfoUser['id'], $calendarInfoUser1['id']);
		$this->assertEquals($calendarInfoUser['uri'], $calendarInfoUser1['uri']);
	}

	public function testSearchPrincipal(): void {
		$myPublic = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//dmfs.org//mimedir.icalendar//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20160419T130000
SUMMARY:My Test (public)
CLASS:PUBLIC
TRANSP:OPAQUE
STATUS:CONFIRMED
DTEND;TZID=Europe/Berlin:20160419T140000
LAST-MODIFIED:20160419T074202Z
DTSTAMP:20160419T074202Z
CREATED:20160419T074202Z
UID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310-1
END:VEVENT
END:VCALENDAR
EOD;
		$myPrivate = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//dmfs.org//mimedir.icalendar//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20160419T130000
SUMMARY:My Test (private)
CLASS:PRIVATE
TRANSP:OPAQUE
STATUS:CONFIRMED
DTEND;TZID=Europe/Berlin:20160419T140000
LAST-MODIFIED:20160419T074202Z
DTSTAMP:20160419T074202Z
CREATED:20160419T074202Z
UID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310-2
END:VEVENT
END:VCALENDAR
EOD;
		$myConfidential = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//dmfs.org//mimedir.icalendar//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20160419T130000
SUMMARY:My Test (confidential)
CLASS:CONFIDENTIAL
TRANSP:OPAQUE
STATUS:CONFIRMED
DTEND;TZID=Europe/Berlin:20160419T140000
LAST-MODIFIED:20160419T074202Z
DTSTAMP:20160419T074202Z
CREATED:20160419T074202Z
UID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310-3
END:VEVENT
END:VCALENDAR
EOD;

		$sharerPublic = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//dmfs.org//mimedir.icalendar//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20160419T130000
SUMMARY:Sharer Test (public)
CLASS:PUBLIC
TRANSP:OPAQUE
STATUS:CONFIRMED
DTEND;TZID=Europe/Berlin:20160419T140000
LAST-MODIFIED:20160419T074202Z
DTSTAMP:20160419T074202Z
CREATED:20160419T074202Z
UID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310-4
END:VEVENT
END:VCALENDAR
EOD;
		$sharerPrivate = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//dmfs.org//mimedir.icalendar//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20160419T130000
SUMMARY:Sharer Test (private)
CLASS:PRIVATE
TRANSP:OPAQUE
STATUS:CONFIRMED
DTEND;TZID=Europe/Berlin:20160419T140000
LAST-MODIFIED:20160419T074202Z
DTSTAMP:20160419T074202Z
CREATED:20160419T074202Z
UID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310-5
END:VEVENT
END:VCALENDAR
EOD;
		$sharerConfidential = <<<EOD
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//dmfs.org//mimedir.icalendar//EN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20160419T130000
SUMMARY:Sharer Test (confidential)
CLASS:CONFIDENTIAL
TRANSP:OPAQUE
STATUS:CONFIRMED
DTEND;TZID=Europe/Berlin:20160419T140000
LAST-MODIFIED:20160419T074202Z
DTSTAMP:20160419T074202Z
CREATED:20160419T074202Z
UID:2e468c48-7860-492e-bc52-92fa0daeeccf.1461051722310-6
END:VEVENT
END:VCALENDAR
EOD;

		$l10n = $this->createMock(IL10N::class);
		$l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
		$config = $this->createMock(IConfig::class);
		$this->userManager->expects($this->any())
			->method('userExists')
			->willReturn(true);
		$this->groupManager->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$me = self::UNIT_TEST_USER;
		$sharer = self::UNIT_TEST_USER1;
		$this->backend->createCalendar($me, 'calendar-uri-me', []);
		$this->backend->createCalendar($sharer, 'calendar-uri-sharer', []);

		$myCalendars = $this->backend->getCalendarsForUser($me);
		$this->assertCount(1, $myCalendars);

		$sharerCalendars = $this->backend->getCalendarsForUser($sharer);
		$this->assertCount(1, $sharerCalendars);
		$sharerCalendar = new Calendar($this->backend, $sharerCalendars[0], $l10n, $config);
		$this->backend->updateShares($sharerCalendar, [
			[
				'href' => 'principal:' . $me,
				'readOnly' => false,
			],
		], []);

		$this->assertCount(2, $this->backend->getCalendarsForUser($me));

		$this->backend->createCalendarObject($myCalendars[0]['id'], 'event0.ics', $myPublic);
		$this->backend->createCalendarObject($myCalendars[0]['id'], 'event1.ics', $myPrivate);
		$this->backend->createCalendarObject($myCalendars[0]['id'], 'event2.ics', $myConfidential);

		$this->backend->createCalendarObject($sharerCalendars[0]['id'], 'event3.ics', $sharerPublic);
		$this->backend->createCalendarObject($sharerCalendars[0]['id'], 'event4.ics', $sharerPrivate);
		$this->backend->createCalendarObject($sharerCalendars[0]['id'], 'event5.ics', $sharerConfidential);

		$mySearchResults = $this->backend->searchPrincipalUri($me, 'Test', ['VEVENT'], ['SUMMARY'], []);
		$sharerSearchResults = $this->backend->searchPrincipalUri($sharer, 'Test', ['VEVENT'], ['SUMMARY'], []);

		$this->assertCount(4, $mySearchResults);
		$this->assertCount(3, $sharerSearchResults);

		$this->assertEquals($myPublic, $mySearchResults[0]['calendardata']);
		$this->assertEquals($myPrivate, $mySearchResults[1]['calendardata']);
		$this->assertEquals($myConfidential, $mySearchResults[2]['calendardata']);
		$this->assertEquals($sharerPublic, $mySearchResults[3]['calendardata']);

		$this->assertEquals($sharerPublic, $sharerSearchResults[0]['calendardata']);
		$this->assertEquals($sharerPrivate, $sharerSearchResults[1]['calendardata']);
		$this->assertEquals($sharerConfidential, $sharerSearchResults[2]['calendardata']);
	}
}
