<?php
/**
 * @author Robin McCorkell <rmccorkell@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Security;

/**
 * @group DB
 */
class CredentialsManagerTest extends \Test\TestCase {

	/**
	 * @dataProvider credentialsProvider
	 */
	public function testWithDB($userId, $identifier) {
		$credentialsManager = \OC::$server->getCredentialsManager();

		$secrets = 'Open Sesame';

		$credentialsManager->store($userId, $identifier, $secrets);
		$received = $credentialsManager->retrieve($userId, $identifier);

		$this->assertSame($secrets, $received);

		$removedRows = $credentialsManager->delete($userId, $identifier);
		$this->assertSame(1, $removedRows);
	}

	public function credentialsProvider() {
		return [
			[
				'alice',
				'privateCredentials'
			],
			[
				'',
				'systemCredentials',
			],
		];
	}
}
