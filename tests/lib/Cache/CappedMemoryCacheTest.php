<?php
/**
 * ownCloud
 *
 * @author Robin Appelman
 * @copyright 2016 Robin Appelman icewind@owncloud.com
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

namespace Test\Cache;

/**
 * Class CappedMemoryCacheTest
 *
 * @package Test\Cache
 */
class CappedMemoryCacheTest extends TestCache {
	protected function setUp(): void {
		parent::setUp();
		$this->instance = new \OC\Cache\CappedMemoryCache();
	}

	public function testSetOverCap() {
		$instance = new \OC\Cache\CappedMemoryCache(3);

		$instance->set('1', 'a');
		$instance->set('2', 'b');
		$instance->set('3', 'c');
		$instance->set('4', 'd');
		$instance->set('5', 'e');

		$this->assertFalse($instance->hasKey('1'));
		$this->assertFalse($instance->hasKey('2'));
		$this->assertTrue($instance->hasKey('3'));
		$this->assertTrue($instance->hasKey('4'));
		$this->assertTrue($instance->hasKey('5'));
	}

	function testClear() {
		$value = 'ipsum lorum';
		$this->instance->set('1_value1', $value);
		$this->instance->set('1_value2', $value);
		$this->instance->set('2_value1', $value);
		$this->instance->set('3_value1', $value);

		$this->assertTrue($this->instance->clear());
		$this->assertFalse($this->instance->hasKey('1_value1'));
		$this->assertFalse($this->instance->hasKey('1_value2'));
		$this->assertFalse($this->instance->hasKey('2_value1'));
		$this->assertFalse($this->instance->hasKey('3_value1'));
	}

	function testIndirectSet() {
		$this->instance->set('array', []);

		$this->instance['array'][] = 'foo';

		$this->assertEquals(['foo'], $this->instance->get('array'));

		$this->instance['array']['bar'] = 'qwerty';

		$this->assertEquals(['foo', 'bar' => 'qwerty'], $this->instance->get('array'));
	}
}
