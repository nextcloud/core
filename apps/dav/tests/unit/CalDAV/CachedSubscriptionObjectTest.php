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

use OCA\DAV\CalDAV\CachedSubscriptionObject;
use OCA\DAV\CalDAV\CalDavBackend;

class CachedSubscriptionObjectTest extends \Test\TestCase {

	public function testGet() {
		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];
		$objectData = [
			'uri' => 'foo123'
		];

		$backend->expects($this->once())
			->method('getCalendarObject')
			->with(666, 'foo123', 1)
			->willReturn([
				'calendardata' => 'BEGIN...',
			]);

		$calendarObject = new CachedSubscriptionObject($backend, $calendarInfo, $objectData);
		$this->assertEquals('BEGIN...', $calendarObject->get());
	}

	
	public function testPut() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);
		$this->expectExceptionMessage('Creating objects in a cached subscription is not allowed');

		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];
		$objectData = [
			'uri' => 'foo123'
		];

		$calendarObject = new CachedSubscriptionObject($backend, $calendarInfo, $objectData);
		$calendarObject->put('');
	}

	
	public function testDelete() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);
		$this->expectExceptionMessage('Deleting objects in a cached subscription is not allowed');

		$backend = $this->createMock(CalDavBackend::class);
		$calendarInfo = [
			'{http://owncloud.org/ns}owner-principal' => 'user1',
			'principaluri' => 'user2',
			'id' => 666,
			'uri' => 'cal',
		];
		$objectData = [
			'uri' => 'foo123'
		];

		$calendarObject = new CachedSubscriptionObject($backend, $calendarInfo, $objectData);
		$calendarObject->delete();
	}

}
