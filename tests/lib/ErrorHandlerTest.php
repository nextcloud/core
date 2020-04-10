<?php
/**
 * ownCloud
 *
 * @author Bjoern Schiessle
 * @copyright 2014 Bjoern Schiessle <schiessle@owncloud.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test;

class ErrorHandlerTest extends \Test\TestCase {

	/**
	 * provide username, password combinations for testRemovePassword
	 * @return array
	 */
	function passwordProvider() {
		return [
			['user', 'password'],
			['user@owncloud.org', 'password'],
			['user', 'pass@word'],
			['us:er', 'password'],
			['user', 'pass:word'],
		];

	}

	/**
	 * @dataProvider passwordProvider
	 * @param string $username
	 * @param string $password
	 */
	function testRemovePassword($username, $password) {
		$url = 'http://'.$username.':'.$password.'@owncloud.org';
		$expectedResult = 'http://xxx:xxx@owncloud.org';
		$result = TestableErrorHandler::testRemovePassword($url);

		$this->assertEquals($expectedResult, $result);
	}

}

/**
 * dummy class to access protected methods of \OC\Log\ErrorHandler
 */
class TestableErrorHandler extends \OC\Log\ErrorHandler {

	/**
	 * @param string $msg
	 */
	public static function testRemovePassword($msg) {
		return self::removePassword($msg);
	}
}
