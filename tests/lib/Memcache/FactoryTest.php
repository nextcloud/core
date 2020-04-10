<?php
/**
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
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

namespace Test\Memcache;

use OC\Memcache\NullCache;
use OCP\ILogger;

class Test_Factory_Available_Cache1 extends NullCache {
	public function __construct($prefix = '') {
	}

	public static function isAvailable() {
		return true;
	}
}

class Test_Factory_Available_Cache2 extends NullCache {
	public function __construct($prefix = '') {
	}

	public static function isAvailable() {
		return true;
	}
}

class Test_Factory_Unavailable_Cache1 extends NullCache {
	public function __construct($prefix = '') {
	}

	public static function isAvailable() {
		return false;
	}
}

class Test_Factory_Unavailable_Cache2 extends NullCache {
	public function __construct($prefix = '') {
	}

	public static function isAvailable() {
		return false;
	}
}

class FactoryTest extends \Test\TestCase {
	const AVAILABLE1 = '\\Test\\Memcache\\Test_Factory_Available_Cache1';
	const AVAILABLE2 = '\\Test\\Memcache\\Test_Factory_Available_Cache2';
	const UNAVAILABLE1 = '\\Test\\Memcache\\Test_Factory_Unavailable_Cache1';
	const UNAVAILABLE2 = '\\Test\\Memcache\\Test_Factory_Unavailable_Cache2';

	public function cacheAvailabilityProvider() {
		return [
			[
				// local and distributed available
				self::AVAILABLE1, self::AVAILABLE2, null,
				self::AVAILABLE1, self::AVAILABLE2, \OC\Memcache\Factory::NULL_CACHE
			],
			[
				// local and distributed null
				null, null, null,
				\OC\Memcache\Factory::NULL_CACHE, \OC\Memcache\Factory::NULL_CACHE, \OC\Memcache\Factory::NULL_CACHE
			],
			[
				// local available, distributed null (most common scenario)
				self::AVAILABLE1, null, null,
				self::AVAILABLE1, self::AVAILABLE1, \OC\Memcache\Factory::NULL_CACHE
			],
			[
				// locking cache available
				null, null, self::AVAILABLE1,
				\OC\Memcache\Factory::NULL_CACHE, \OC\Memcache\Factory::NULL_CACHE, self::AVAILABLE1
			],
			[
				// locking cache unavailable: no exception here in the factory
				null, null, self::UNAVAILABLE1,
				\OC\Memcache\Factory::NULL_CACHE, \OC\Memcache\Factory::NULL_CACHE, \OC\Memcache\Factory::NULL_CACHE
			]
		];
	}

	public function cacheUnavailableProvider() {
		return [
			[
				// local available, distributed unavailable
				self::AVAILABLE1, self::UNAVAILABLE1
			],
			[
				// local unavailable, distributed available
				self::UNAVAILABLE1, self::AVAILABLE1
			],
			[
				// local and distributed unavailable
				self::UNAVAILABLE1, self::UNAVAILABLE2
			],
		];
	}

	/**
	 * @dataProvider cacheAvailabilityProvider
	 */
	public function testCacheAvailability($localCache, $distributedCache, $lockingCache,
		$expectedLocalCache, $expectedDistributedCache, $expectedLockingCache) {
		$logger = $this->getMockBuilder(ILogger::class)->getMock();
		$factory = new \OC\Memcache\Factory('abc', $logger, $localCache, $distributedCache, $lockingCache);
		$this->assertTrue(is_a($factory->createLocal(), $expectedLocalCache));
		$this->assertTrue(is_a($factory->createDistributed(), $expectedDistributedCache));
		$this->assertTrue(is_a($factory->createLocking(), $expectedLockingCache));
	}

	/**
	 * @dataProvider cacheUnavailableProvider
	 */
	public function testCacheNotAvailableException($localCache, $distributedCache) {
		$this->expectException(\OC\HintException::class);

		$logger = $this->getMockBuilder(ILogger::class)->getMock();
		new \OC\Memcache\Factory('abc', $logger, $localCache, $distributedCache);
	}
}
