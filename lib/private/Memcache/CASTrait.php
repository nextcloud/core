<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
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

trait CASTrait {
	abstract public function get($key);

	abstract public function set($key, $value, $ttl = 0);

	abstract public function remove($key);

	abstract public function add($key, $value, $ttl = 0);

	/**
	 * Compare and set
	 *
	 * @param string $key
	 * @param mixed $old
	 * @param mixed $new
	 * @return bool
	 */
	public function cas($key, $old, $new) {
		//no native cas, emulate with locking
		if ($this->add($key . '_lock', true)) {
			if ($this->get($key) === $old) {
				$this->set($key, $new);
				$this->remove($key . '_lock');
				return true;
			} else {
				$this->remove($key . '_lock');
				return false;
			}
		} else {
			return false;
		}
	}
}
