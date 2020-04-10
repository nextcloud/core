<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Michael Weimann (<mail@michael-weimann.eu>)
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test;

use OC\MemoryInfo;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * This class provides tests for the MemoryInfo class.
 */
class MemoryInfoTest extends TestCase {
	/**
	 * The "memory_limit" value before tests.
	 *
	 * @var string
	 */
	private $iniSettingBeforeTest;

	/**
	 * @beforeClass
	 */
	public function backupMemoryInfoIniSetting() {
		$this->iniSettingBeforeTest = ini_get('memory_limit');
	}

	/**
	 * @afterClass
	 */
	public function restoreMemoryInfoIniSetting() {
		ini_set('memory_limit', $this->iniSettingBeforeTest);
	}

	/**
	 * Provides test data.
	 *
	 * @return array
	 */
	public function getMemoryLimitTestData(): array {
		return [
			'unlimited' => ['-1', -1,],
			'0' => ['0', 0,],
			'134217728 bytes' => ['134217728', 134217728,],
			'128M' => ['128M', 134217728,],
			'131072K' => ['131072K', 134217728,],
			'2G' => ['2G', 2147483648,],
		];
	}

	/**
	 * Tests that getMemoryLimit works as expected.
	 *
	 * @param string $iniValue The "memory_limit" ini data.
	 * @param int $expected The expected detected memory limit.
	 * @dataProvider getMemoryLimitTestData
	 */
	public function testMemoryLimit($iniValue, int $expected) {
		ini_set('memory_limit', $iniValue);
		$memoryInfo = new MemoryInfo();
		self::assertEquals($expected, $memoryInfo->getMemoryLimit());
	}

	/**
	 * Provides sufficient memory limit test data.
	 *
	 * @return array
	 */
	public function getSufficientMemoryTestData(): array {
		return [
			'unlimited' => [-1, true,],
			'512M' => [512 * 1024 * 1024, true,],
			'1G' => [1024 * 1024 * 1024, true,],
			'256M' => [256 * 1024 * 1024, false,],
		];
	}

	/**
	 * Tests that isMemoryLimitSufficient returns the correct values.
	 *
	 * @param int $memoryLimit The memory limit
	 * @param bool $expected If the memory limit is sufficient.
	 * @dataProvider getSufficientMemoryTestData
	 * @return void
	 */
	public function testIsMemoryLimitSufficient(int $memoryLimit, bool $expected) {
		/* @var MemoryInfo|MockObject $memoryInfo */
		$memoryInfo = $this->getMockBuilder(MemoryInfo::class)
			->setMethods(['getMemoryLimit',])
			->getMock();

		$memoryInfo
			->method('getMemoryLimit')
			->willReturn($memoryLimit);

		$isMemoryLimitSufficient = $memoryInfo->isMemoryLimitSufficient();
		self::assertEquals($expected, $isMemoryLimitSufficient);
	}
}
