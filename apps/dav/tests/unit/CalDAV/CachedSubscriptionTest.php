<?php
/**
 * @copyright Copyright (c) 2018 Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OCA\DAV\CalDAV\CachedSubscription;
use OCA\DAV\CalDAV\CachedSubscriptionObject;
use OCA\DAV\CalDAV\CalDavBackend;
use Sabre\DAV\PropPatch;

class CachedSubscriptionTest extends \Test\TestCase {

	public function testGetACL() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$calendar = new CachedSubscription($backend, $calendarInfo);
		$this->assertEquals([
			[
				'privilege' => '{DAV:}read',
				'principal' => 'user1',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => 'user1/calendar-proxy-write',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => 'user1/calendar-proxy-read',
				'protected' => true,
			],
			[
				'privilege' => '{urn:ietf:params:xml:ns:caldav}read-free-busy',
				'principal' => '{DAV:}authenticated',
				'protected' => true,
			],
		], $calendar->getACL());
	}

	public function testGetChildACL() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$calendar = new CachedSubscription($backend, $calendarInfo);
		$this->assertEquals([
			[
				'privilege' => '{DAV:}read',
				'principal' => 'user1',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => 'user1/calendar-proxy-write',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => 'user1/calendar-proxy-read',
				'protected' => true,
			]
		], $calendar->getChildACL());
	}

	public function testGetOwner() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$calendar = new CachedSubscription($backend, $calendarInfo);
		$this->assertEquals('user1', $calendar->getOwner());
	}

	public function testDelete() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$backend->expects($this->once())
			->method('deleteSubscription')
			->with(666);

		$calendar = new CachedSubscription($backend, $calendarInfo);
		$calendar->delete();
	}

	public function testPropPatch() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];
		$propPatch = $this->createMock(PropPatch::class);

		$backend->expects($this->once())
			->method('updateSubscription')
			->with(666, $propPatch);

		$calendar = new CachedSubscription($backend, $calendarInfo);
		$calendar->propPatch($propPatch);
	}

	
	public function testGetChild() {
		$this->expectException(\Sabre\DAV\Exception\NotFound::class);
		$this->expectExceptionMessage('Calendar object not found');

		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$backend->expects($this->at(0))
			->method('getCalendarObject')
			->with(666, 'foo1', 1)
			->willReturn([
				'id' => 99,
				'uri' => 'foo1'
			]);
		$backend->expects($this->at(1))
			->method('getCalendarObject')
			->with(666, 'foo2', 1)
			->willReturn(null);

		$calendar = new CachedSubscription($backend, $calendarInfo);

		$first = $calendar->getChild('foo1');
		$this->assertInstanceOf(CachedSubscriptionObject::class, $first);

		$calendar->getChild('foo2');
	}

	public function testGetChildren() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$backend->expects($this->at(0))
			->method('getCalendarObjects')
			->with(666, 1)
			->willReturn([
				[
					'id' => 99,
					'uri' => 'foo1'
				],
				[
					'id' => 100,
					'uri' => 'foo2'
				],
			]);

		$calendar = new CachedSubscription($backend, $calendarInfo);

		$res = $calendar->getChildren();
		$this->assertCount(2, $res);
		$this->assertInstanceOf(CachedSubscriptionObject::class, $res[0]);
		$this->assertInstanceOf(CachedSubscriptionObject::class, $res[1]);
	}

	public function testGetMultipleChildren() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$backend->expects($this->at(0))
			->method('getMultipleCalendarObjects')
			->with(666, ['foo1', 'foo2'], 1)
			->willReturn([
				[
					'id' => 99,
					'uri' => 'foo1'
				],
				[
					'id' => 100,
					'uri' => 'foo2'
				],
			]);

		$calendar = new CachedSubscription($backend, $calendarInfo);

		$res = $calendar->getMultipleChildren(['foo1', 'foo2']);
		$this->assertCount(2, $res);
		$this->assertInstanceOf(CachedSubscriptionObject::class, $res[0]);
		$this->assertInstanceOf(CachedSubscriptionObject::class, $res[1]);
	}

	
	public function testCreateFile() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);
		$this->expectExceptionMessage('Creating objects in cached subscription is not allowed');

		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$calendar = new CachedSubscription($backend, $calendarInfo);
		$calendar->createFile('foo', []);
	}

	public function testChildExists() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$backend->expects($this->at(0))
			->method('getCalendarObject')
			->with(666, 'foo1', 1)
			->willReturn([
				'id' => 99,
				'uri' => 'foo1'
			]);
		$backend->expects($this->at(1))
			->method('getCalendarObject')
			->with(666, 'foo2', 1)
			->willReturn(null);

		$calendar = new CachedSubscription($backend, $calendarInfo);

		$this->assertEquals(true, $calendar->childExists('foo1'));
		$this->assertEquals(false, $calendar->childExists('foo2'));
	}

	public function testCalendarQuery() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];

		$backend->expects($this->once())
			->method('calendarQuery')
			->with(666, ['foo'], 1)
			->willReturn([99]);

		$calendar = new CachedSubscription($backend, $calendarInfo);

		$this->assertEquals([99], $calendar->calendarQuery(['foo']));
	}
}
