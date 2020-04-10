<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace Test\Files\ObjectStore;

use OC\Files\ObjectStore\Mapper;
use OCP\IUser;

class MapperTest extends \Test\TestCase {

	public function dataGetBucket() {
		return [
			['user', 64, '17'],
			['USER', 64, '0'],
			['bc0e8b52-a66c-1035-90c6-d9663bda9e3f', 64, '56'],
			['user', 8, '1'],
			['user', 2, '1'],
			['USER', 2, '0'],
		];
	}

	/**
	 * @dataProvider dataGetBucket
	 * @param string $username
	 * @param int $numBuckets
	 * @param string $expectedBucket
	 */
	public function testGetBucket($username, $numBuckets, $expectedBucket) {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn($username);

		$mapper = new Mapper($user);

		$this->assertSame($expectedBucket, $mapper->getBucket($numBuckets));
	}
}
