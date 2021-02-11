<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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
namespace OCA\DAV\Tests\unit\Search;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\Search\EventsSearchProvider;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Sabre\VObject\Reader;
use Test\TestCase;

class EventsSearchProviderTest extends TestCase {

	/** @var IAppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;

	/** @var CalDavBackend|\PHPUnit\Framework\MockObject\MockObject */
	private $backend;

	/** @var EventsSearchProvider */
	private $provider;

	// NO SUMMARY
	private $vEvent0 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'PRODID:-//Apple Inc.//Mac OS X 10.11.6//EN'.PHP_EOL.
		'CALSCALE:GREGORIAN'.PHP_EOL.
		'BEGIN:VEVENT'.PHP_EOL.
		'CREATED:20161004T144433Z'.PHP_EOL.
		'UID:85560E76-1B0D-47E1-A735-21625767FCA4'.PHP_EOL.
		'DTEND;VALUE=DATE:20161008'.PHP_EOL.
		'TRANSP:TRANSPARENT'.PHP_EOL.
		'DTSTART;VALUE=DATE:20161005'.PHP_EOL.
		'DTSTAMP:20161004T144437Z'.PHP_EOL.
		'SEQUENCE:0'.PHP_EOL.
		'END:VEVENT'.PHP_EOL.
		'END:VCALENDAR';

	// TIMED SAME DAY
	private $vEvent1 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'PRODID:-//Tests//'.PHP_EOL.
		'CALSCALE:GREGORIAN'.PHP_EOL.
		'BEGIN:VTIMEZONE'.PHP_EOL.
		'TZID:Europe/Berlin'.PHP_EOL.
		'BEGIN:DAYLIGHT'.PHP_EOL.
		'TZOFFSETFROM:+0100'.PHP_EOL.
		'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU'.PHP_EOL.
		'DTSTART:19810329T020000'.PHP_EOL.
		'TZNAME:GMT+2'.PHP_EOL.
		'TZOFFSETTO:+0200'.PHP_EOL.
		'END:DAYLIGHT'.PHP_EOL.
		'BEGIN:STANDARD'.PHP_EOL.
		'TZOFFSETFROM:+0200'.PHP_EOL.
		'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU'.PHP_EOL.
		'DTSTART:19961027T030000'.PHP_EOL.
		'TZNAME:GMT+1'.PHP_EOL.
		'TZOFFSETTO:+0100'.PHP_EOL.
		'END:STANDARD'.PHP_EOL.
		'END:VTIMEZONE'.PHP_EOL.
		'BEGIN:VEVENT'.PHP_EOL.
		'CREATED:20160809T163629Z'.PHP_EOL.
		'UID:0AD16F58-01B3-463B-A215-FD09FC729A02'.PHP_EOL.
		'DTEND;TZID=Europe/Berlin:20160816T100000'.PHP_EOL.
		'TRANSP:OPAQUE'.PHP_EOL.
		'SUMMARY:Test Europe Berlin'.PHP_EOL.
		'DTSTART;TZID=Europe/Berlin:20160816T090000'.PHP_EOL.
		'DTSTAMP:20160809T163632Z'.PHP_EOL.
		'SEQUENCE:0'.PHP_EOL.
		'END:VEVENT'.PHP_EOL.
		'END:VCALENDAR';

	// TIMED DIFFERENT DAY
	private $vEvent2 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'PRODID:-//Tests//'.PHP_EOL.
		'CALSCALE:GREGORIAN'.PHP_EOL.
		'BEGIN:VTIMEZONE'.PHP_EOL.
		'TZID:Europe/Berlin'.PHP_EOL.
		'BEGIN:DAYLIGHT'.PHP_EOL.
		'TZOFFSETFROM:+0100'.PHP_EOL.
		'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU'.PHP_EOL.
		'DTSTART:19810329T020000'.PHP_EOL.
		'TZNAME:GMT+2'.PHP_EOL.
		'TZOFFSETTO:+0200'.PHP_EOL.
		'END:DAYLIGHT'.PHP_EOL.
		'BEGIN:STANDARD'.PHP_EOL.
		'TZOFFSETFROM:+0200'.PHP_EOL.
		'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU'.PHP_EOL.
		'DTSTART:19961027T030000'.PHP_EOL.
		'TZNAME:GMT+1'.PHP_EOL.
		'TZOFFSETTO:+0100'.PHP_EOL.
		'END:STANDARD'.PHP_EOL.
		'END:VTIMEZONE'.PHP_EOL.
		'BEGIN:VEVENT'.PHP_EOL.
		'CREATED:20160809T163629Z'.PHP_EOL.
		'UID:0AD16F58-01B3-463B-A215-FD09FC729A02'.PHP_EOL.
		'DTEND;TZID=Europe/Berlin:20160817T100000'.PHP_EOL.
		'TRANSP:OPAQUE'.PHP_EOL.
		'SUMMARY:Test Europe Berlin'.PHP_EOL.
		'DTSTART;TZID=Europe/Berlin:20160816T090000'.PHP_EOL.
		'DTSTAMP:20160809T163632Z'.PHP_EOL.
		'SEQUENCE:0'.PHP_EOL.
		'END:VEVENT'.PHP_EOL.
		'END:VCALENDAR';

	// ALL-DAY ONE-DAY
	private $vEvent3 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'PRODID:-//Apple Inc.//Mac OS X 10.11.6//EN'.PHP_EOL.
		'CALSCALE:GREGORIAN'.PHP_EOL.
		'BEGIN:VEVENT'.PHP_EOL.
		'CREATED:20161004T144433Z'.PHP_EOL.
		'UID:85560E76-1B0D-47E1-A735-21625767FCA4'.PHP_EOL.
		'DTEND;VALUE=DATE:20161006'.PHP_EOL.
		'TRANSP:TRANSPARENT'.PHP_EOL.
		'DTSTART;VALUE=DATE:20161005'.PHP_EOL.
		'DTSTAMP:20161004T144437Z'.PHP_EOL.
		'SEQUENCE:0'.PHP_EOL.
		'END:VEVENT'.PHP_EOL.
		'END:VCALENDAR';

	// ALL-DAY MULTIPLE DAYS
	private $vEvent4 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'PRODID:-//Apple Inc.//Mac OS X 10.11.6//EN'.PHP_EOL.
		'CALSCALE:GREGORIAN'.PHP_EOL.
		'BEGIN:VEVENT'.PHP_EOL.
		'CREATED:20161004T144433Z'.PHP_EOL.
		'UID:85560E76-1B0D-47E1-A735-21625767FCA4'.PHP_EOL.
		'DTEND;VALUE=DATE:20161008'.PHP_EOL.
		'TRANSP:TRANSPARENT'.PHP_EOL.
		'DTSTART;VALUE=DATE:20161005'.PHP_EOL.
		'DTSTAMP:20161004T144437Z'.PHP_EOL.
		'SEQUENCE:0'.PHP_EOL.
		'END:VEVENT'.PHP_EOL.
		'END:VCALENDAR';

	// DURATION
	private $vEvent5 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'PRODID:-//Apple Inc.//Mac OS X 10.11.6//EN'.PHP_EOL.
		'CALSCALE:GREGORIAN'.PHP_EOL.
		'BEGIN:VEVENT'.PHP_EOL.
		'CREATED:20161004T144433Z'.PHP_EOL.
		'UID:85560E76-1B0D-47E1-A735-21625767FCA4'.PHP_EOL.
		'DURATION:P5D'.PHP_EOL.
		'TRANSP:TRANSPARENT'.PHP_EOL.
		'DTSTART;VALUE=DATE:20161005'.PHP_EOL.
		'DTSTAMP:20161004T144437Z'.PHP_EOL.
		'SEQUENCE:0'.PHP_EOL.
		'END:VEVENT'.PHP_EOL.
		'END:VCALENDAR';

	// NO DTEND - DATE
	private $vEvent6 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'PRODID:-//Apple Inc.//Mac OS X 10.11.6//EN'.PHP_EOL.
		'CALSCALE:GREGORIAN'.PHP_EOL.
		'BEGIN:VEVENT'.PHP_EOL.
		'CREATED:20161004T144433Z'.PHP_EOL.
		'UID:85560E76-1B0D-47E1-A735-21625767FCA4'.PHP_EOL.
		'TRANSP:TRANSPARENT'.PHP_EOL.
		'DTSTART;VALUE=DATE:20161005'.PHP_EOL.
		'DTSTAMP:20161004T144437Z'.PHP_EOL.
		'SEQUENCE:0'.PHP_EOL.
		'END:VEVENT'.PHP_EOL.
		'END:VCALENDAR';

	// NO DTEND - DATE-TIME
	private $vEvent7 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'PRODID:-//Tests//'.PHP_EOL.
		'CALSCALE:GREGORIAN'.PHP_EOL.
		'BEGIN:VTIMEZONE'.PHP_EOL.
		'TZID:Europe/Berlin'.PHP_EOL.
		'BEGIN:DAYLIGHT'.PHP_EOL.
		'TZOFFSETFROM:+0100'.PHP_EOL.
		'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU'.PHP_EOL.
		'DTSTART:19810329T020000'.PHP_EOL.
		'TZNAME:GMT+2'.PHP_EOL.
		'TZOFFSETTO:+0200'.PHP_EOL.
		'END:DAYLIGHT'.PHP_EOL.
		'BEGIN:STANDARD'.PHP_EOL.
		'TZOFFSETFROM:+0200'.PHP_EOL.
		'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU'.PHP_EOL.
		'DTSTART:19961027T030000'.PHP_EOL.
		'TZNAME:GMT+1'.PHP_EOL.
		'TZOFFSETTO:+0100'.PHP_EOL.
		'END:STANDARD'.PHP_EOL.
		'END:VTIMEZONE'.PHP_EOL.
		'BEGIN:VEVENT'.PHP_EOL.
		'CREATED:20160809T163629Z'.PHP_EOL.
		'UID:0AD16F58-01B3-463B-A215-FD09FC729A02'.PHP_EOL.
		'TRANSP:OPAQUE'.PHP_EOL.
		'SUMMARY:Test Europe Berlin'.PHP_EOL.
		'DTSTART;TZID=Europe/Berlin:20160816T090000'.PHP_EOL.
		'DTSTAMP:20160809T163632Z'.PHP_EOL.
		'SEQUENCE:0'.PHP_EOL.
		'END:VEVENT'.PHP_EOL.
		'END:VCALENDAR';

	protected function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(IAppManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->backend = $this->createMock(CalDavBackend::class);

		$this->provider = new EventsSearchProvider(
			$this->appManager,
			$this->l10n,
			$this->urlGenerator,
			$this->backend
		);
	}

	public function testGetId(): void {
		$this->assertEquals('calendar', $this->provider->getId());
	}

	public function testGetName(): void {
		$this->l10n->expects($this->exactly(1))
			->method('t')
			->with('Events')
			->willReturnArgument(0);

		$this->assertEquals('Events', $this->provider->getName());
	}

	public function testSearchAppDisabled(): void {
		$user = $this->createMock(IUser::class);
		$query = $this->createMock(ISearchQuery::class);
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('calendar', $user)
			->willReturn(false);
		$this->l10n->expects($this->exactly(1))
			->method('t')
			->willReturnArgument(0);
		$this->backend->expects($this->never())
			->method('getCalendarsForUser');
		$this->backend->expects($this->never())
			->method('getSubscriptionsForUser');
		$this->backend->expects($this->never())
			->method('searchPrincipalUri');

		$actual = $this->provider->search($user, $query);
		$data = $actual->jsonSerialize();
		$this->assertInstanceOf(SearchResult::class, $actual);
		$this->assertEquals('Events', $data['name']);
		$this->assertEmpty($data['entries']);
		$this->assertFalse($data['isPaginated']);
		$this->assertNull($data['cursor']);
	}

	public function testSearch(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john.doe');
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('search term');
		$query->method('getLimit')->willReturn(5);
		$query->method('getCursor')->willReturn(20);
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('calendar', $user)
			->willReturn(true);
		$this->l10n->method('t')->willReturnArgument(0);

		$this->backend->expects($this->once())
			->method('getCalendarsForUser')
			->with('principals/users/john.doe')
			->willReturn([
				[
					'id' => 99,
					'principaluri' => 'principals/users/john.doe',
					'uri' => 'calendar-uri-99',
				], [
					'id' => 123,
					'principaluri' => 'principals/users/john.doe',
					'uri' => 'calendar-uri-123',
				]
			]);
		$this->backend->expects($this->once())
			->method('getSubscriptionsForUser')
			->with('principals/users/john.doe')
			->willReturn([
				[
					'id' => 1337,
					'principaluri' => 'principals/users/john.doe',
					'uri' => 'subscription-uri-1337',
				]
			]);
		$this->backend->expects($this->once())
			->method('searchPrincipalUri')
			->with('principals/users/john.doe', 'search term', ['VEVENT'],
				['SUMMARY', 'LOCATION', 'DESCRIPTION', 'ATTENDEE', 'ORGANIZER', 'CATEGORIES'],
				['ATTENDEE' => ['CN'], 'ORGANIZER' => ['CN']],
				['limit' => 5, 'offset' => 20])
			->willReturn([
				[
					'calendarid' => 99,
					'calendartype' => CalDavBackend::CALENDAR_TYPE_CALENDAR,
					'uri' => 'event0.ics',
					'calendardata' => $this->vEvent0,
				],
				[
					'calendarid' => 123,
					'calendartype' => CalDavBackend::CALENDAR_TYPE_CALENDAR,
					'uri' => 'event1.ics',
					'calendardata' => $this->vEvent1,
				],
				[
					'calendarid' => 1337,
					'calendartype' => CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION,
					'uri' => 'event2.ics',
					'calendardata' => $this->vEvent2,
				]
			]);

		$provider = $this->getMockBuilder(EventsSearchProvider::class)
			->setConstructorArgs([
				$this->appManager,
				$this->l10n,
				$this->urlGenerator,
				$this->backend,
			])
			->setMethods([
				'getDeepLinkToCalendarApp',
				'generateSubline',
			])
			->getMock();

		$provider->expects($this->exactly(3))
			->method('generateSubline')
			->willReturn('subline');
		$provider->expects($this->exactly(3))
			->method('getDeepLinkToCalendarApp')
			->withConsecutive(
				['principals/users/john.doe', 'calendar-uri-99', 'event0.ics'],
				['principals/users/john.doe', 'calendar-uri-123', 'event1.ics'],
				['principals/users/john.doe', 'subscription-uri-1337', 'event2.ics']
			)
			->willReturn('deep-link-to-calendar');

		$actual = $provider->search($user, $query);
		$data = $actual->jsonSerialize();
		$this->assertInstanceOf(SearchResult::class, $actual);
		$this->assertEquals('Events', $data['name']);
		$this->assertCount(3, $data['entries']);
		$this->assertTrue($data['isPaginated']);
		$this->assertEquals(23, $data['cursor']);

		$result0 = $data['entries'][0];
		$result0Data = $result0->jsonSerialize();
		$result1 = $data['entries'][1];
		$result1Data = $result1->jsonSerialize();
		$result2 = $data['entries'][2];
		$result2Data = $result2->jsonSerialize();

		$this->assertInstanceOf(SearchResultEntry::class, $result0);
		$this->assertEmpty($result0Data['thumbnailUrl']);
		$this->assertEquals('Untitled event', $result0Data['title']);
		$this->assertEquals('subline', $result0Data['subline']);
		$this->assertEquals('deep-link-to-calendar', $result0Data['resourceUrl']);
		$this->assertEquals('icon-calendar-dark', $result0Data['icon']);
		$this->assertFalse($result0Data['rounded']);

		$this->assertInstanceOf(SearchResultEntry::class, $result1);
		$this->assertEmpty($result1Data['thumbnailUrl']);
		$this->assertEquals('Test Europe Berlin', $result1Data['title']);
		$this->assertEquals('subline', $result1Data['subline']);
		$this->assertEquals('deep-link-to-calendar', $result1Data['resourceUrl']);
		$this->assertEquals('icon-calendar-dark', $result1Data['icon']);
		$this->assertFalse($result1Data['rounded']);

		$this->assertInstanceOf(SearchResultEntry::class, $result2);
		$this->assertEmpty($result2Data['thumbnailUrl']);
		$this->assertEquals('Test Europe Berlin', $result2Data['title']);
		$this->assertEquals('subline', $result2Data['subline']);
		$this->assertEquals('deep-link-to-calendar', $result2Data['resourceUrl']);
		$this->assertEquals('icon-calendar-dark', $result2Data['icon']);
		$this->assertFalse($result2Data['rounded']);
	}

	public function testGetDeepLinkToCalendarApp(): void {
		$this->urlGenerator->expects($this->at(0))
			->method('linkTo')
			->with('', 'remote.php')
			->willReturn('link-to-remote.php');
		$this->urlGenerator->expects($this->at(1))
			->method('linkToRoute')
			->with('calendar.view.index')
			->willReturn('link-to-route-calendar/');
		$this->urlGenerator->expects($this->at(2))
			->method('getAbsoluteURL')
			->with('link-to-route-calendar/edit/bGluay10by1yZW1vdGUucGhwL2Rhdi9jYWxlbmRhcnMvam9obi5kb2UvZm9vL2Jhci5pY3M=')
			->willReturn('absolute-url-to-route');

		$actual = self::invokePrivate($this->provider, 'getDeepLinkToCalendarApp', ['principals/users/john.doe', 'foo', 'bar.ics']);

		$this->assertEquals('absolute-url-to-route', $actual);
	}

	/**
	 * @param string $ics
	 * @param string $expectedSubline
	 *
	 * @dataProvider generateSublineDataProvider
	 */
	public function testGenerateSubline(string $ics, string $expectedSubline): void {
		$vCalendar = Reader::read($ics, Reader::OPTION_FORGIVING);
		$eventComponent = $vCalendar->VEVENT;

		$this->l10n->method('l')
			->willReturnCallback(static function (string $type, \DateTime $date, $_):string {
				if ($type === 'time') {
					return $date->format('H:i');
				}

				return $date->format('m-d');
			});

		$actual = self::invokePrivate($this->provider, 'generateSubline', [$eventComponent]);
		$this->assertEquals($expectedSubline, $actual);
	}

	public function generateSublineDataProvider(): array {
		return [
			[$this->vEvent1, '08-16 09:00 - 10:00'],
			[$this->vEvent2, '08-16 09:00 - 08-17 10:00'],
			[$this->vEvent3, '10-05'],
			[$this->vEvent4, '10-05 - 10-07'],
			[$this->vEvent5, '10-05 - 10-09'],
			[$this->vEvent6, '10-05'],
			[$this->vEvent7, '08-16 09:00 - 09:00'],
		];
	}
}
