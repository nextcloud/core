<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OC\Memcache;

class NullCache extends Cache implements \OCP\IMemcache {
	public function get($key) {
		return null;
	}

	public function set($key, $value, $ttl = 0) {
		return true;
	}

	public function hasKey($key) {
		return false;
	}

	public function remove($key) {
		return true;
	}

	public function add($key, $value, $ttl = 0) {
		return true;
	}

	public function inc($key, $step = 1) {
		return true;
	}

	public function dec($key, $step = 1) {
		return true;
	}

	public function cas($key, $old, $new) {
		return true;
	}

	public function cad($key, $old) {
		return true;
	}

	public function clear($prefix = '') {
		return true;
	}

	static public function isAvailable() {
		return true;
	}
}
