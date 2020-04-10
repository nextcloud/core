<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use OCA\DAV\CalDAV\Plugin;
use Test\TestCase;

class PluginTest extends TestCase  {
	/** @var Plugin */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();

		$this->plugin = new Plugin();
	}

	public function linkProvider() {
		return [
			[
				'principals/users/MyUserName',
				'calendars/MyUserName',
			],
			[
				'principals/calendar-resources/Resource-ABC',
				'system-calendars/calendar-resources/Resource-ABC',
			],
			[
				'principals/calendar-rooms/Room-ABC',
				'system-calendars/calendar-rooms/Room-ABC',
			],
		];
	}

	/**
	 * @dataProvider linkProvider
	 *
	 * @param $input
	 * @param $expected
	 */
	public function testGetCalendarHomeForPrincipal($input, $expected) {
		$this->assertSame($expected, $this->plugin->getCalendarHomeForPrincipal($input));
	}

	public function testGetCalendarHomeForUnknownPrincipal() {
		$this->assertNull($this->plugin->getCalendarHomeForPrincipal('FOO/BAR/BLUB'));
	}
}
