<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

abstract class Cache implements \ArrayAccess, \OCP\ICache {
	/**
	 * @var string $prefix
	 */
	protected $prefix;

	/**
	 * @param string $prefix
	 */
	public function __construct($prefix = '') {
		$this->prefix = $prefix;
	}

	/**
	 * @return string Prefix used for caching purposes
	 */
	public function getPrefix() {
		return $this->prefix;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	abstract public function get($key);

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @return mixed
	 */
	abstract public function set($key, $value, $ttl = 0);

	/**
	 * @param string $key
	 * @return mixed
	 */
	abstract public function hasKey($key);

	/**
	 * @param string $key
	 * @return mixed
	 */
	abstract public function remove($key);

	/**
	 * @param string $prefix
	 * @return mixed
	 */
	abstract public function clear($prefix = '');

	//implement the ArrayAccess interface

	public function offsetExists($offset) {
		return $this->hasKey($offset);
	}

	public function offsetSet($offset, $value) {
		$this->set($offset, $value);
	}

	public function offsetGet($offset) {
		return $this->get($offset);
	}

	public function offsetUnset($offset) {
		$this->remove($offset);
	}
}
