<?php

/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Memcache;

class MemcachedTest extends Cache {
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if (!\OC\Memcache\Memcached::isAvailable()) {
			self::markTestSkipped('The memcached extension is not available.');
		}
		$instance = new \OC\Memcache\Memcached(self::getUniqueID());
		if ($instance->set(self::getUniqueID(), self::getUniqueID()) === false) {
			self::markTestSkipped('memcached server seems to be down.');
		}
	}

	protected function setUp(): void {
		parent::setUp();
		$this->instance = new \OC\Memcache\Memcached($this->getUniqueID());
	}

	public function testClear() {
		// Memcached is sometimes broken with clear(), so we don't test it thoroughly
		$value = 'ipsum lorum';
		$this->instance->set('1_value1', $value);
		$this->instance->set('1_value2', $value);
		$this->instance->set('2_value1', $value);
		$this->instance->set('3_value1', $value);

		$this->assertTrue($this->instance->clear('1_'));

		$this->assertFalse($this->instance->hasKey('1_value1'));
		$this->assertFalse($this->instance->hasKey('1_value2'));
		//$this->assertTrue($this->instance->hasKey('2_value1'));
		//$this->assertTrue($this->instance->hasKey('3_value1'));

		$this->assertTrue($this->instance->clear());

		$this->assertFalse($this->instance->hasKey('1_value1'));
		$this->assertFalse($this->instance->hasKey('1_value2'));
		$this->assertFalse($this->instance->hasKey('2_value1'));
		$this->assertFalse($this->instance->hasKey('3_value1'));
	}
}
