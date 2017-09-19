<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Memcache;

use OC\Cache\CappedMemoryCache;
use OCP\IMemcacheTTL;

class Redis extends Cache implements IMemcacheTTL {
	/** @var \Redis $cache */
	private static $cache = null;

	/** @var CappedMemoryCache $localCache */
	private static $localCache = null;

	public function __construct($prefix = '') {
		parent::__construct($prefix);
		if (is_null(self::$cache)) {
			self::$cache = \OC::$server->getGetRedisFactory()->getInstance();
			self::$localCache = new CappedMemoryCache(1024);
		}
	}

	/**
	 * entries in redis get namespaced to prevent collisions between ownCloud instances and users
	 */
	protected function getNameSpace() {
		return $this->prefix;
	}

	public function get($key) {
		// First check local cache!
		if (isset(self::$localCache[$key])) {
			return self::$localCache[$key];
		}

		$result = self::$cache->get($this->getNameSpace() . $key);
		if ($result === false && !self::$cache->exists($this->getNameSpace() . $key)) {
			$ret = null;
		} else {
			$ret = json_decode($result, true);
		}

		self::$localCache[$key] = $ret;
		return $ret;
	}

	public function set($key, $value, $ttl = 0) {
		// Clear local cache
		unset(self::$localCache[$key]);

		if ($ttl > 0) {
			return self::$cache->setex($this->getNameSpace() . $key, $ttl, json_encode($value));
		} else {
			return self::$cache->set($this->getNameSpace() . $key, json_encode($value));
		}
	}

	public function hasKey($key) {
		// First check local cache!
		if (isset(self::$localCache[$key])) {
			return true;
		}

		return self::$cache->exists($this->getNameSpace() . $key);
	}

	public function remove($key) {
		// Clear local cache
		unset(self::$localCache[$key]);

		if (self::$cache->del($this->getNameSpace() . $key)) {
			return true;
		} else {
			return false;
		}
	}

	public function clear($prefix = '') {
		// Clear local cache
		self::$localCache->clear();

		$prefix = $this->getNameSpace() . $prefix . '*';
		$keys = self::$cache->keys($prefix);
		$deleted = self::$cache->del($keys);

		return count($keys) === $deleted;
	}

	/**
	 * Set a value in the cache if it's not already stored
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl Time To Live in seconds. Defaults to 60*60*24
	 * @return bool
	 */
	public function add($key, $value, $ttl = 0) {
		// Clear local cache
		unset(self::$localCache[$key]);

		// don't encode ints for inc/dec
		if (!is_int($value)) {
			$value = json_encode($value);
		}
		return self::$cache->setnx($this->getPrefix() . $key, $value);
	}

	/**
	 * Increase a stored number
	 *
	 * @param string $key
	 * @param int $step
	 * @return int | bool
	 */
	public function inc($key, $step = 1) {
		// Clear local cache
		unset(self::$localCache[$key]);

		return self::$cache->incrBy($this->getNameSpace() . $key, $step);
	}

	/**
	 * Decrease a stored number
	 *
	 * @param string $key
	 * @param int $step
	 * @return int | bool
	 */
	public function dec($key, $step = 1) {
		if (!$this->hasKey($key)) {
			return false;
		}

		// Clear local cache
		unset(self::$localCache[$key]);

		return self::$cache->decrBy($this->getNameSpace() . $key, $step);
	}

	/**
	 * Compare and set
	 *
	 * @param string $key
	 * @param mixed $old
	 * @param mixed $new
	 * @return bool
	 */
	public function cas($key, $old, $new) {
		if (!is_int($new)) {
			$new = json_encode($new);
		}
		self::$cache->watch($this->getNameSpace() . $key);
		if ($this->get($key) === $old) {
			// Clear local cache
			unset(self::$localCache[$key]);

			$result = self::$cache->multi()
				->set($this->getNameSpace() . $key, $new)
				->exec();
			return ($result === false) ? false : true;
		}

		self::$cache->unwatch();
		return false;
	}

	/**
	 * Compare and delete
	 *
	 * @param string $key
	 * @param mixed $old
	 * @return bool
	 */
	public function cad($key, $old) {
		self::$cache->watch($this->getNameSpace() . $key);
		if ($this->get($key) === $old) {
			// Clear local cache
			unset(self::$localCache[$key]);

			$result = self::$cache->multi()
				->del($this->getNameSpace() . $key)
				->exec();
			return ($result === false) ? false : true;
		}

		self::$cache->unwatch();
		return false;
	}

	public function setTTL($key, $ttl) {
		// Clear local cache
		unset(self::$localCache[$key]);

		self::$cache->expire($this->getNameSpace() . $key, $ttl);
	}

	static public function isAvailable() {
		return \OC::$server->getGetRedisFactory()->isAvailable();
	}
}

