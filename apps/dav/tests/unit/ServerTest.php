<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\DAV\Tests\unit;

use OCA\DAV\Server;
use OCP\IRequest;

/**
 * Class ServerTest
 *
 * @group DB
 *
 * @package OCA\DAV\Tests\Unit
 */
class ServerTest extends \Test\TestCase {

	/**
	 * @dataProvider providesUris
	 */
	public function test($uri, array $plugins) {
		/** @var IRequest | \PHPUnit_Framework_MockObject_MockObject $r */
		$r = $this->createMock(IRequest::class);
		$r->expects($this->any())->method('getRequestUri')->willReturn($uri);
		$s = new Server($r, '/');
		$this->assertNotNull($s->server);
		foreach ($plugins as $plugin) {
			$this->assertNotNull($s->server->getPlugin($plugin));
		}
	}
	public function providesUris() {
		return [
			'principals' => ['principals/users/admin', ['caldav', 'oc-resource-sharing', 'carddav']],
			'calendars' => ['calendars/admin', ['caldav', 'oc-resource-sharing']],
			'addressbooks' => ['addressbooks/admin', ['carddav', 'oc-resource-sharing']],
		];
	}
}
