<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OCA\Federation\Tests\DAV;

use OCA\Federation\DAV\FedAuth;
use OCA\Federation\DbHandler;
use Test\TestCase;

class FedAuthTest extends TestCase {

	/**
	 * @dataProvider providesUser
	 *
	 * @param array $expected
	 * @param string $user
	 * @param string $password
	 */
	public function testFedAuth($expected, $user, $password) {
		/** @var DbHandler | \PHPUnit_Framework_MockObject_MockObject $db */
		$db = $this->getMockBuilder('OCA\Federation\DbHandler')->disableOriginalConstructor()->getMock();
		$db->method('auth')->willReturn(true);
		$auth = new FedAuth($db);
		$result = $this->invokePrivate($auth, 'validateUserPass', [$user, $password]);
		$this->assertEquals($expected, $result);
	}

	public function providesUser() {
		return [
			[true, 'system', '123456']
		];
	}
}
