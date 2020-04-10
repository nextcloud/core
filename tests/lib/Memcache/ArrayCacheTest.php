<?php

/**
 * Copyright (c) 2015 Joas Schilling <nickvergessen@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Memcache;

class ArrayCacheTest extends Cache {
	protected function setUp(): void {
		parent::setUp();
		$this->instance = new \OC\Memcache\ArrayCache('');
	}
}
