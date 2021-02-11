<?php
/**
 * @copyright Copyright (c) 2020, Thomas Citharel <nextcloud@tcit.fr>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author eleith <online+github@eleith.com>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\Tests\unit\BackgroundJob;

use GuzzleHttp\HandlerStack;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\WebcalCaching\RefreshWebcalService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\LocalServerException;
use OCP\IConfig;
use OCP\ILogger;
use PHPUnit\Framework\MockObject\MockObject;
use Sabre\DAV\Exception\BadRequest;
use Sabre\VObject;
use Sabre\VObject\Recur\NoInstancesException;

use Test\TestCase;

class RefreshWebcalServiceTest extends TestCase {

	/** @var CalDavBackend | MockObject */
	private $caldavBackend;

	/** @var IClientService | MockObject */
	private $clientService;

	/** @var IConfig | MockObject */
	private $config;

	/** @var ILogger | MockObject */
	private $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->caldavBackend = $this->createMock(CalDavBackend::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(ILogger::class);
	}

	/**
	 * @param string $body
	 * @param string $contentType
	 * @param string $result
	 *
	 * @dataProvider runDataProvider
	 */
	public function testRun(string $body, string $contentType, string $result) {
		$refreshWebcalService = $this->getMockBuilder(RefreshWebcalService::class)
			->setMethods(['getRandomCalendarObjectUri'])
			->setConstructorArgs([$this->caldavBackend, $this->clientService, $this->config, $this->logger])
			->getMock();

		$refreshWebcalService
			->method('getRandomCalendarObjectUri')
			->willReturn('uri-1.ics');

		$this->caldavBackend->expects($this->once())
			->method('getSubscriptionsForUser')
			->with('principals/users/testuser')
			->willReturn([
				[
					'id' => '99',
					'uri' => 'sub456',
					'{http://apple.com/ns/ical/}refreshrate' => 'P1D',
					'{http://calendarserver.org/ns/}subscribed-strip-todos' => '1',
					'{http://calendarserver.org/ns/}subscribed-strip-alarms' => '1',
					'{http://calendarserver.org/ns/}subscribed-strip-attachments' => '1',
					'source' => 'webcal://foo.bar/bla'
				],
				[
					'id' => '42',
					'uri' => 'sub123',
					'{http://apple.com/ns/ical/}refreshrate' => 'PT1H',
					'{http://calendarserver.org/ns/}subscribed-strip-todos' => '1',
					'{http://calendarserver.org/ns/}subscribed-strip-alarms' => '1',
					'{http://calendarserver.org/ns/}subscribed-strip-attachments' => '1',
					'source' => 'webcal://foo.bar/bla2'
				],
			]);

		$client = $this->createMock(IClient::class);
		$response = $this->createMock(IResponse::class);
		$this->clientService->expects($this->once())
			->method('newClient')
			->with()
			->willReturn($client);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'webcalAllowLocalAccess', 'no')
			->willReturn('no');

		$client->expects($this->once())
			->method('get')
			->with('https://foo.bar/bla2', $this->callback(function ($obj) {
				return $obj['allow_redirects']['redirects'] === 10 && $obj['handler'] instanceof HandlerStack;
			}))
			->willReturn($response);

		$response->expects($this->once())
			->method('getBody')
			->with()
			->willReturn($body);
		$response->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn($contentType);

		$this->caldavBackend->expects($this->once())
			->method('purgeAllCachedEventsForSubscription')
			->with(42);

		$this->caldavBackend->expects($this->once())
			->method('createCalendarObject')
			->with(42, 'uri-1.ics', $result, 1);

		$refreshWebcalService->refreshSubscription('principals/users/testuser', 'sub123');
	}
  
	/**
	 * @param string $body
	 * @param string $contentType
	 * @param string $result
	 *
	 * @dataProvider runDataProvider
	 */
	public function testRunCreateCalendarNoException(string $body, string $contentType, string $result) {
		$client = $this->createMock(IClient::class);
		$response = $this->createMock(IResponse::class);
		$refreshWebcalService = $this->getMockBuilder(RefreshWebcalService::class)
			->setMethods(['getRandomCalendarObjectUri', 'getSubscription', 'queryWebcalFeed'])
			->setConstructorArgs([$this->caldavBackend, $this->clientService, $this->config, $this->logger])
			->getMock();

		$refreshWebcalService
			->method('getRandomCalendarObjectUri')
			->willReturn('uri-1.ics');

		$refreshWebcalService
			->method('getSubscription')
			->willReturn([
				'id' => '42',
				'uri' => 'sub123',
				'{http://apple.com/ns/ical/}refreshrate' => 'PT1H',
				'{http://calendarserver.org/ns/}subscribed-strip-todos' => '1',
				'{http://calendarserver.org/ns/}subscribed-strip-alarms' => '1',
				'{http://calendarserver.org/ns/}subscribed-strip-attachments' => '1',
				'source' => 'webcal://foo.bar/bla2'
			]);

		$this->clientService->expects($this->once())
			->method('newClient')
			->with()
			->willReturn($client);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'webcalAllowLocalAccess', 'no')
			->willReturn('no');

		$client->expects($this->once())
			->method('get')
			->with('https://foo.bar/bla2', $this->callback(function ($obj) {
				return $obj['allow_redirects']['redirects'] === 10 && $obj['handler'] instanceof HandlerStack;
			}))
			->willReturn($response);

		$response->expects($this->once())
			->method('getBody')
			->with()
			->willReturn($body);
		$response->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn($contentType);

		$this->caldavBackend->expects($this->once())
			->method('purgeAllCachedEventsForSubscription')
			->with(42);

		$this->caldavBackend->expects($this->once())
			->method('createCalendarObject')
			->with(42, 'uri-1.ics', $result, 1);
	
		$noInstanceException = new NoInstancesException("can't add calendar object");
		$this->caldavBackend->expects($this->once())
			->method("createCalendarObject")
			->willThrowException($noInstanceException);
	
		$this->logger->expects($this->once())
			->method('logException')
			->with($noInstanceException);

		$refreshWebcalService->refreshSubscription('principals/users/testuser', 'sub123');
	}

	/**
	 * @param string $body
	 * @param string $contentType
	 * @param string $result
	 *
	 * @dataProvider runDataProvider
	 */
	public function testRunCreateCalendarBadRequest(string $body, string $contentType, string $result) {
		$client = $this->createMock(IClient::class);
		$response = $this->createMock(IResponse::class);
		$refreshWebcalService = $this->getMockBuilder(RefreshWebcalService::class)
			->setMethods(['getRandomCalendarObjectUri', 'getSubscription', 'queryWebcalFeed'])
			->setConstructorArgs([$this->caldavBackend, $this->clientService, $this->config, $this->logger])
			->getMock();

		$refreshWebcalService
			->method('getRandomCalendarObjectUri')
			->willReturn('uri-1.ics');

		$refreshWebcalService
			->method('getSubscription')
			->willReturn([
				'id' => '42',
				'uri' => 'sub123',
				'{http://apple.com/ns/ical/}refreshrate' => 'PT1H',
				'{http://calendarserver.org/ns/}subscribed-strip-todos' => '1',
				'{http://calendarserver.org/ns/}subscribed-strip-alarms' => '1',
				'{http://calendarserver.org/ns/}subscribed-strip-attachments' => '1',
				'source' => 'webcal://foo.bar/bla2'
			]);

		$this->clientService->expects($this->once())
			->method('newClient')
			->with()
			->willReturn($client);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'webcalAllowLocalAccess', 'no')
			->willReturn('no');

		$client->expects($this->once())
			->method('get')
			->with('https://foo.bar/bla2', $this->callback(function ($obj) {
				return $obj['allow_redirects']['redirects'] === 10 && $obj['handler'] instanceof HandlerStack;
			}))
			->willReturn($response);

		$response->expects($this->once())
			->method('getBody')
			->with()
			->willReturn($body);
		$response->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn($contentType);

		$this->caldavBackend->expects($this->once())
			->method('purgeAllCachedEventsForSubscription')
			->with(42);

		$this->caldavBackend->expects($this->once())
			->method('createCalendarObject')
			->with(42, 'uri-1.ics', $result, 1);
	
		$badRequestException = new BadRequest("can't add reach calendar url");
		$this->caldavBackend->expects($this->once())
			->method("createCalendarObject")
			->willThrowException($badRequestException);
	
		$this->logger->expects($this->once())
			->method('logException')
			->with($badRequestException);

		$refreshWebcalService->refreshSubscription('principals/users/testuser', 'sub123');
	}

	/**
	 * @return array
	 */
	public function runDataProvider():array {
		return [
			[
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000101\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:12345's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
				'text/calendar;charset=utf8',
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject " . VObject\Version::VERSION . "//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000101\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:12345's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
			],
			[
				'["vcalendar",[["prodid",{},"text","-//Example Corp.//Example Client//EN"],["version",{},"text","2.0"]],[["vtimezone",[["last-modified",{},"date-time","2004-01-10T03:28:45Z"],["tzid",{},"text","US/Eastern"]],[["daylight",[["dtstart",{},"date-time","2000-04-04T02:00:00"],["rrule",{},"recur",{"freq":"YEARLY","byday":"1SU","bymonth":4}],["tzname",{},"text","EDT"],["tzoffsetfrom",{},"utc-offset","-05:00"],["tzoffsetto",{},"utc-offset","-04:00"]],[]],["standard",[["dtstart",{},"date-time","2000-10-26T02:00:00"],["rrule",{},"recur",{"freq":"YEARLY","byday":"1SU","bymonth":10}],["tzname",{},"text","EST"],["tzoffsetfrom",{},"utc-offset","-04:00"],["tzoffsetto",{},"utc-offset","-05:00"]],[]]]],["vevent",[["dtstamp",{},"date-time","2006-02-06T00:11:21Z"],["dtstart",{"tzid":"US/Eastern"},"date-time","2006-01-02T14:00:00"],["duration",{},"duration","PT1H"],["recurrence-id",{"tzid":"US/Eastern"},"date-time","2006-01-04T12:00:00"],["summary",{},"text","Event #2"],["uid",{},"text","12345"]],[]]]]',
				'application/calendar+json',
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject " . VObject\Version::VERSION . "//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VTIMEZONE\r\nLAST-MODIFIED:20040110T032845Z\r\nTZID:US/Eastern\r\nBEGIN:DAYLIGHT\r\nDTSTART:20000404T020000\r\nRRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=4\r\nTZNAME:EDT\r\nTZOFFSETFROM:-0500\r\nTZOFFSETTO:-0400\r\nEND:DAYLIGHT\r\nBEGIN:STANDARD\r\nDTSTART:20001026T020000\r\nRRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=10\r\nTZNAME:EST\r\nTZOFFSETFROM:-0400\r\nTZOFFSETTO:-0500\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT\r\nDTSTAMP:20060206T001121Z\r\nDTSTART;TZID=US/Eastern:20060102T140000\r\nDURATION:PT1H\r\nRECURRENCE-ID;TZID=US/Eastern:20060104T120000\r\nSUMMARY:Event #2\r\nUID:12345\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
			],
			[
				'<?xml version="1.0" encoding="utf-8" ?><icalendar xmlns="urn:ietf:params:xml:ns:icalendar-2.0"><vcalendar><properties><prodid><text>-//Example Inc.//Example Client//EN</text></prodid><version><text>2.0</text></version></properties><components><vevent><properties><dtstamp><date-time>2006-02-06T00:11:21Z</date-time></dtstamp><dtstart><parameters><tzid><text>US/Eastern</text></tzid></parameters><date-time>2006-01-04T14:00:00</date-time></dtstart><duration><duration>PT1H</duration></duration><recurrence-id><parameters><tzid><text>US/Eastern</text></tzid></parameters><date-time>2006-01-04T12:00:00</date-time></recurrence-id><summary><text>Event #2 bis</text></summary><uid><text>12345</text></uid></properties></vevent></components></vcalendar></icalendar>',
				'application/calendar+xml',
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject " . VObject\Version::VERSION . "//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nDTSTAMP:20060206T001121Z\r\nDTSTART;TZID=US/Eastern:20060104T140000\r\nDURATION:PT1H\r\nRECURRENCE-ID;TZID=US/Eastern:20060104T120000\r\nSUMMARY:Event #2 bis\r\nUID:12345\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
			]
		];
	}

	/**
	 * @dataProvider runLocalURLDataProvider
	 *
	 * @param string $source
	 */
	public function testRunLocalURL($source) {
		$refreshWebcalService = new RefreshWebcalService(
			$this->caldavBackend,
			$this->clientService,
			$this->config,
			$this->logger
		);

		$this->caldavBackend->expects($this->once())
			->method('getSubscriptionsForUser')
			->with('principals/users/testuser')
			->willReturn([
				[
					'id' => 42,
					'uri' => 'sub123',
					'refreshreate' => 'P1H',
					'striptodos' => 1,
					'stripalarms' => 1,
					'stripattachments' => 1,
					'source' => $source
				],
			]);

		$client = $this->createMock(IClient::class);
		$this->clientService->expects($this->once())
			->method('newClient')
			->with()
			->willReturn($client);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'webcalAllowLocalAccess', 'no')
			->willReturn('no');

		$client->expects($this->once())
			->method('get')
			->willThrowException(new LocalServerException());

		$this->logger->expects($this->once())
			->method('logException')
			->with($this->isInstanceOf(LocalServerException::class), $this->anything());

		$refreshWebcalService->refreshSubscription('principals/users/testuser', 'sub123');
	}

	public function runLocalURLDataProvider():array {
		return [
			['localhost/foo.bar'],
			['localHost/foo.bar'],
			['random-host/foo.bar'],
			['[::1]/bla.blub'],
			['[::]/bla.blub'],
			['192.168.0.1'],
			['172.16.42.1'],
			['[fdf8:f53b:82e4::53]/secret.ics'],
			['[fe80::200:5aee:feaa:20a2]/secret.ics'],
			['[0:0:0:0:0:0:10.0.0.1]/secret.ics'],
			['[0:0:0:0:0:ffff:127.0.0.0]/secret.ics'],
			['10.0.0.1'],
			['another-host.local'],
			['service.localhost'],
		];
	}

	public function testInvalidUrl() {
		$refreshWebcalService = new RefreshWebcalService($this->caldavBackend,
			$this->clientService, $this->config, $this->logger);

		$this->caldavBackend->expects($this->once())
			->method('getSubscriptionsForUser')
			->with('principals/users/testuser')
			->willReturn([
				[
					'id' => 42,
					'uri' => 'sub123',
					'refreshreate' => 'P1H',
					'striptodos' => 1,
					'stripalarms' => 1,
					'stripattachments' => 1,
					'source' => '!@#$'
				],
			]);

		$client = $this->createMock(IClient::class);
		$this->clientService->expects($this->once())
			->method('newClient')
			->with()
			->willReturn($client);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'webcalAllowLocalAccess', 'no')
			->willReturn('no');

		$client->expects($this->never())
			->method('get');

		$refreshWebcalService->refreshSubscription('principals/users/testuser', 'sub123');
	}
}
