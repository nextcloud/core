<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Tests\unit\CardDAV;

use OCA\DAV\CalDAV\BirthdayService;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\DAV\GroupPrincipalBackend;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Test\TestCase;

class BirthdayServiceTest extends TestCase {

	/** @var BirthdayService */
	private $service;
	/** @var CalDavBackend | \PHPUnit_Framework_MockObject_MockObject */
	private $calDav;
	/** @var CardDavBackend | \PHPUnit_Framework_MockObject_MockObject */
	private $cardDav;
	/** @var GroupPrincipalBackend | \PHPUnit_Framework_MockObject_MockObject */
	private $groupPrincipalBackend;
	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var IDBConnection | \PHPUnit_Framework_MockObject_MockObject */
	private $dbConnection;
	/** @var IL10N | \PHPUnit_Framework_MockObject_MockObject */
	private $l10n;

	protected function setUp(): void {
		parent::setUp();

		$this->calDav = $this->createMock(CalDavBackend::class);
		$this->cardDav = $this->createMock(CardDavBackend::class);
		$this->groupPrincipalBackend = $this->createMock(GroupPrincipalBackend::class);
		$this->config = $this->createMock(IConfig::class);
		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});

		$this->service = new BirthdayService($this->calDav,$this->cardDav,
			$this->groupPrincipalBackend, $this->config,
			$this->dbConnection, $this->l10n);
	}

	/**
	 * @dataProvider providesVCards
	 * @param string $expectedSummary
	 * @param string $expectedDTStart
	 * @param string $expectedFieldType
	 * @param string $expectedUnknownYear
	 * @param string $expectedOriginalYear
	 * @param string | null $data
	 */
	public function testBuildBirthdayFromContact($expectedSummary, $expectedDTStart, $expectedFieldType, $expectedUnknownYear, $expectedOriginalYear, $data, $fieldType, $prefix, $supports4Bytes) {
		$this->dbConnection->method('supports4ByteText')->willReturn($supports4Bytes);
		$cal = $this->service->buildDateFromContact($data, $fieldType, $prefix);

		if ($expectedSummary === null) {
			$this->assertNull($cal);
		} else {
			$this->assertInstanceOf('Sabre\VObject\Component\VCalendar', $cal);
			$this->assertEquals('-//IDN nextcloud.com//Birthday calendar//EN', $cal->PRODID->getValue());
			$this->assertTrue(isset($cal->VEVENT));
			$this->assertEquals('FREQ=YEARLY', $cal->VEVENT->RRULE->getValue());
			$this->assertEquals($expectedSummary, $cal->VEVENT->SUMMARY->getValue());
			$this->assertEquals($expectedDTStart, $cal->VEVENT->DTSTART->getValue());
			$this->assertEquals($expectedFieldType, $cal->VEVENT->{'X-NEXTCLOUD-BC-FIELD-TYPE'}->getValue());
			$this->assertEquals($expectedUnknownYear, $cal->VEVENT->{'X-NEXTCLOUD-BC-UNKNOWN-YEAR'}->getValue());

			if ($expectedOriginalYear) {
				$this->assertEquals($expectedOriginalYear, $cal->VEVENT->{'X-NEXTCLOUD-BC-YEAR'}->getValue());
			}

			$this->assertEquals('TRANSPARENT', $cal->VEVENT->TRANSP->getValue());
		}
	}

	public function testOnCardDeleteGloballyDisabled() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('no');

		$this->cardDav->expects($this->never())->method('getAddressBookById');

		$this->service->onCardDeleted(666, 'gump.vcf');
	}

	public function testOnCardDeleteUserDisabled() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user01', 'dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('no');

		$this->cardDav->expects($this->once())->method('getAddressBookById')
			->with(666)
			->willReturn([
				'principaluri' => 'principals/users/user01',
				'uri' => 'default'
			]);
		$this->cardDav->expects($this->once())->method('getShares')->willReturn([]);
		$this->calDav->expects($this->never())->method('getCalendarByUri');
		$this->calDav->expects($this->never())->method('deleteCalendarObject');

		$this->service->onCardDeleted(666, 'gump.vcf');
	}

	public function testOnCardDeleted() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user01', 'dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->cardDav->expects($this->once())->method('getAddressBookById')
			->with(666)
			->willReturn([
				'principaluri' => 'principals/users/user01',
				'uri' => 'default'
			]);
		$this->calDav->expects($this->once())->method('getCalendarByUri')
			->with('principals/users/user01', 'contact_birthdays')
			->willReturn([
				'id' => 1234
			]);
		$this->calDav->expects($this->at(1))->method('deleteCalendarObject')->with(1234, 'default-gump.vcf.ics');
		$this->calDav->expects($this->at(2))->method('deleteCalendarObject')->with(1234, 'default-gump.vcf-death.ics');
		$this->calDav->expects($this->at(3))->method('deleteCalendarObject')->with(1234, 'default-gump.vcf-anniversary.ics');
		$this->cardDav->expects($this->once())->method('getShares')->willReturn([]);

		$this->service->onCardDeleted(666, 'gump.vcf');
	}

	public function testOnCardChangedGloballyDisabled() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('no');

		$this->cardDav->expects($this->never())->method('getAddressBookById');

		$service = $this->getMockBuilder(BirthdayService::class)
			->setMethods(['buildDateFromContact', 'birthdayEvenChanged'])
			->setConstructorArgs([$this->calDav, $this->cardDav, $this->groupPrincipalBackend, $this->config, $this->dbConnection, $this->l10n])
			->getMock();

		$service->onCardChanged(666, 'gump.vcf', '');
	}

	public function testOnCardChangedUserDisabled() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user01', 'dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('no');

		$this->cardDav->expects($this->once())->method('getAddressBookById')
			->with(666)
			->willReturn([
				'principaluri' => 'principals/users/user01',
				'uri' => 'default'
			]);
		$this->cardDav->expects($this->once())->method('getShares')->willReturn([]);
		$this->calDav->expects($this->never())->method('getCalendarByUri');

		/** @var BirthdayService | \PHPUnit_Framework_MockObject_MockObject $service */
		$service = $this->getMockBuilder(BirthdayService::class)
			->setMethods(['buildDateFromContact', 'birthdayEvenChanged'])
			->setConstructorArgs([$this->calDav, $this->cardDav, $this->groupPrincipalBackend, $this->config, $this->dbConnection, $this->l10n])
			->getMock();

		$service->onCardChanged(666, 'gump.vcf', '');
	}

	/**
	 * @dataProvider providesCardChanges
	 */
	public function testOnCardChanged($expectedOp) {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user01', 'dav', 'generateBirthdayCalendar', 'yes')
			->willReturn('yes');

		$this->cardDav->expects($this->once())->method('getAddressBookById')
			->with(666)
			->willReturn([
				'principaluri' => 'principals/users/user01',
				'uri' => 'default'
			]);
		$this->calDav->expects($this->once())->method('getCalendarByUri')
			->with('principals/users/user01', 'contact_birthdays')
			->willReturn([
				'id' => 1234
			]);
		$this->cardDav->expects($this->once())->method('getShares')->willReturn([]);

		/** @var BirthdayService | \PHPUnit_Framework_MockObject_MockObject $service */
		$service = $this->getMockBuilder(BirthdayService::class)
			->setMethods(['buildDateFromContact', 'birthdayEvenChanged'])
			->setConstructorArgs([$this->calDav, $this->cardDav, $this->groupPrincipalBackend, $this->config, $this->dbConnection, $this->l10n])
			->getMock();

		if ($expectedOp === 'delete') {
			$this->calDav->expects($this->exactly(3))->method('getCalendarObject')->willReturn('');
			$service->expects($this->exactly(3))->method('buildDateFromContact')->willReturn(null);
			$this->calDav->expects($this->exactly(3))->method('deleteCalendarObject')->withConsecutive(
				[1234, 'default-gump.vcf.ics'],
				[1234, 'default-gump.vcf-death.ics'],
				[1234, 'default-gump.vcf-anniversary.ics']
			);
		}
		if ($expectedOp === 'create') {
			$vCal = new VCalendar();
			$vCal->PRODID = '-//Nextcloud testing//mocked object//';

			$service->expects($this->exactly(3))->method('buildDateFromContact')->willReturn($vCal);
			$this->calDav->expects($this->exactly(3))->method('createCalendarObject')->withConsecutive(
				[1234, 'default-gump.vcf.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nCALSCALE:GREGORIAN\r\nPRODID:-//Nextcloud testing//mocked object//\r\nEND:VCALENDAR\r\n"],
				[1234, 'default-gump.vcf-death.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nCALSCALE:GREGORIAN\r\nPRODID:-//Nextcloud testing//mocked object//\r\nEND:VCALENDAR\r\n"],
				[1234, 'default-gump.vcf-anniversary.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nCALSCALE:GREGORIAN\r\nPRODID:-//Nextcloud testing//mocked object//\r\nEND:VCALENDAR\r\n"]
				);
		}
		if ($expectedOp === 'update') {
			$vCal = new VCalendar();
			$vCal->PRODID = '-//Nextcloud testing//mocked object//';

			$service->expects($this->exactly(3))->method('buildDateFromContact')->willReturn($vCal);
			$service->expects($this->exactly(3))->method('birthdayEvenChanged')->willReturn(true);
			$this->calDav->expects($this->exactly(3))->method('getCalendarObject')->willReturn(['calendardata' => '']);
			$this->calDav->expects($this->exactly(3))->method('updateCalendarObject')->withConsecutive(
				[1234, 'default-gump.vcf.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nCALSCALE:GREGORIAN\r\nPRODID:-//Nextcloud testing//mocked object//\r\nEND:VCALENDAR\r\n"],
				[1234, 'default-gump.vcf-death.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nCALSCALE:GREGORIAN\r\nPRODID:-//Nextcloud testing//mocked object//\r\nEND:VCALENDAR\r\n"],
				[1234, 'default-gump.vcf-anniversary.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nCALSCALE:GREGORIAN\r\nPRODID:-//Nextcloud testing//mocked object//\r\nEND:VCALENDAR\r\n"]
				);
		}

		$service->onCardChanged(666, 'gump.vcf', '');
	}

	/**
	 * @dataProvider providesBirthday
	 * @param $expected
	 * @param $old
	 * @param $new
	 */
	public function testBirthdayEvenChanged($expected, $old, $new) {
		$new = Reader::read($new);
		$this->assertEquals($expected, $this->service->birthdayEvenChanged($old, $new));
	}

	public function testGetAllAffectedPrincipals() {
		$this->cardDav->expects($this->once())->method('getShares')->willReturn([
			[
				'{http://owncloud.org/ns}group-share' => false,
				'{http://owncloud.org/ns}principal' => 'principals/users/user01'
			],
			[
				'{http://owncloud.org/ns}group-share' => false,
				'{http://owncloud.org/ns}principal' => 'principals/users/user01'
			],
			[
				'{http://owncloud.org/ns}group-share' => false,
				'{http://owncloud.org/ns}principal' => 'principals/users/user02'
			],
			[
				'{http://owncloud.org/ns}group-share' => true,
				'{http://owncloud.org/ns}principal' => 'principals/groups/users'
			],
		]);
		$this->groupPrincipalBackend->expects($this->once())->method('getGroupMemberSet')
			->willReturn([
				[
					'uri' => 'principals/users/user01',
				],
				[
					'uri' => 'principals/users/user02',
				],
				[
					'uri' => 'principals/users/user03',
				],
			]);
		$users = $this->invokePrivate($this->service, 'getAllAffectedPrincipals', [6666]);
		$this->assertEquals([
			'principals/users/user01',
			'principals/users/user02',
			'principals/users/user03'
		], $users);
	}

	public function testBirthdayCalendarHasComponentEvent() {
		$this->calDav->expects($this->once())
			->method('createCalendar')
			->with('principal001', 'contact_birthdays', [
				'{DAV:}displayname' => 'Contact birthdays',
				'{http://apple.com/ns/ical/}calendar-color' => '#FFFFCA',
				'components'   => 'VEVENT',
			]);
		$this->service->ensureCalendarExists('principal001');
	}

	public function testResetForUser() {
		$this->calDav->expects($this->at(0))
			->method('getCalendarByUri')
			->with('principals/users/user123', 'contact_birthdays')
			->willReturn(['id' => 42]);

		$this->calDav->expects($this->at(1))
			->method('getCalendarObjects')
			->with(42, 0)
			->willReturn([['uri' => '1.ics'], ['uri' => '2.ics'], ['uri' => '3.ics']]);

		$this->calDav->expects($this->at(2))
			->method('deleteCalendarObject')
			->with(42, '1.ics', 0);

		$this->calDav->expects($this->at(3))
			->method('deleteCalendarObject')
			->with(42, '2.ics', 0);

		$this->calDav->expects($this->at(4))
			->method('deleteCalendarObject')
			->with(42, '3.ics', 0);

		$this->service->resetForUser('user123');
	}

	public function providesBirthday() {
		return [
			[true,
				'',
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000101\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:12345's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"],
			[false,
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000101\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:12345's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000101\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:12345's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"],
			[true,
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000101\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:4567's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000101\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:12345's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"],
			[true,
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000101\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:12345's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
				"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\nUID:12345\r\nDTSTAMP:20160218T133704Z\r\nDTSTART;VALUE=DATE:19000102\r\nDTEND;VALUE=DATE:19000102\r\nRRULE:FREQ=YEARLY\r\nSUMMARY:12345's Birthday (1900)\r\nTRANSP:TRANSPARENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"]
		];
	}

	public function providesCardChanges() {
		return[
			['delete'],
			['create'],
			['update']
		];
	}

	public function providesVCards() {
		return [
			// $expectedSummary, $expectedDTStart, $expectedFieldType, $expectedUnknownYear, $expectedOriginalYear, $data, $fieldType, $prefix, $supports4Byte
			[null, null, null, null, null, 'yasfewf', '', '', true],
			[null, null, null, null, null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nEND:VCARD\r\n", 'BDAY', '', true],
			[null, null, null, null, null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:\r\nEND:VCARD\r\n", 'BDAY', '', true],
			[null, null, null, null, null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:someday\r\nEND:VCARD\r\n", 'BDAY', '', true],
			['🎂 12345 (1900)', '19700101', 'BDAY', '0', '1900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:19000101\r\nEND:VCARD\r\n", 'BDAY', '', true],
			['🎂 12345 (1900)', '19701231', 'BDAY', '0', '1900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:19001231\r\nEND:VCARD\r\n", 'BDAY', '', true],
			['Death of 12345 (1900)', '19701231', 'DEATHDATE', '0', '1900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nDEATHDATE:19001231\r\nEND:VCARD\r\n", 'DEATHDATE', '-death', true],
			['Death of 12345 (1900)', '19701231', 'DEATHDATE', '0', '1900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nDEATHDATE:19001231\r\nEND:VCARD\r\n", 'DEATHDATE', '-death', false],
			['💍 12345 (1900)', '19701231', 'ANNIVERSARY', '0', '1900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nANNIVERSARY:19001231\r\nEND:VCARD\r\n", 'ANNIVERSARY', '-anniversary', true],
			['12345 (⚭1900)', '19701231', 'ANNIVERSARY', '0', '1900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nANNIVERSARY:19001231\r\nEND:VCARD\r\n", 'ANNIVERSARY', '-anniversary', false],
			['🎂 12345', '19701231', 'BDAY', '1', null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:--1231\r\nEND:VCARD\r\n", 'BDAY', '', true],
			['🎂 12345', '19701231', 'BDAY', '1', null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY;X-APPLE-OMIT-YEAR=1604:16041231\r\nEND:VCARD\r\n", 'BDAY', '', true],
			[null, null, null, null, null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:;VALUE=text:circa 1800\r\nEND:VCARD\r\n", 'BDAY', '', true],
			[null, null, null, null, null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nN:12345;;;;\r\nBDAY:20031231\r\nEND:VCARD\r\n", 'BDAY', '', true],
			['🎂 12345 (900)', '19701231', 'BDAY', '0', '900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:09001231\r\nEND:VCARD\r\n", 'BDAY', '', true],
			['12345 (*1900)', '19700101', 'BDAY', '0', '1900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:19000101\r\nEND:VCARD\r\n", 'BDAY', '', false],
			['12345 (*1900)', '19701231', 'BDAY', '0', '1900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:19001231\r\nEND:VCARD\r\n", 'BDAY', '', false],
			['12345 *', '19701231', 'BDAY', '1', null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:--1231\r\nEND:VCARD\r\n", 'BDAY', '', false],
			['12345 *', '19701231', 'BDAY', '1', null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY;X-APPLE-OMIT-YEAR=1604:16041231\r\nEND:VCARD\r\n", 'BDAY', '', false],
			[null, null, null, null, null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:;VALUE=text:circa 1800\r\nEND:VCARD\r\n", 'BDAY', '', false],
			[null, null, null, null, null, "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nN:12345;;;;\r\nBDAY:20031231\r\nEND:VCARD\r\n", 'BDAY', '', false],
			['12345 (*900)', '19701231', 'BDAY', '0', '900', "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Sabre//Sabre VObject 4.1.1//EN\r\nUID:12345\r\nFN:12345\r\nN:12345;;;;\r\nBDAY:09001231\r\nEND:VCARD\r\n", 'BDAY', '', false],
		];
	}
}
