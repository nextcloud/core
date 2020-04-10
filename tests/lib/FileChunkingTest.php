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

namespace Test;

use OCP\ICache;

class FileChunkingTest extends \Test\TestCase {

	public function dataIsComplete() {
		return [
			[1, [], false],
			[1, [0], true],
			[2, [], false],
			[2, [0], false],
			[2, [1], false],
			[2, [0,1], true],
			[10, [], false],
			[10, [0,1,2,3,4,5,6,7,8], false],
			[10, [1,2,3,4,5,6,7,8,9], false],
			[10, [0,1,2,3,5,6,7,8,9], false],
			[10, [0,1,2,3,4,5,6,7,8,9], true],
		];
	}

	/**
	 * @dataProvider dataIsComplete
	 * @param $total
	 * @param array $present
	 * @param $expected
	 */
	public function testIsComplete($total, array $present, $expected) {
		$fileChunking = $this->getMockBuilder(\OC_FileChunking::class)
			->setMethods(['getCache'])
			->setConstructorArgs([[
				'name' => 'file',
				'transferid' => '42',
				'chunkcount' => $total,
			]])
			->getMock();

		$cache = $this->createMock(ICache::class);

		$cache->expects($this->atLeastOnce())
			->method('hasKey')
			->willReturnCallback(function ($key) use ($present) {
				$data = explode('-', $key);
				return in_array($data[3], $present);
			});

		$fileChunking->method('getCache')->willReturn($cache);

		$this->assertEquals($expected, $fileChunking->isComplete());
	}
}
