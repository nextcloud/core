<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

use bantu\IniGetWrapper\IniGetWrapper;
use OCP\IMemcache;

class APCu extends Cache implements IMemcache {
	use CASTrait {
		cas as casEmulated;
	}

	use CADTrait;

	public function get($key) {
		$result = apcu_fetch($this->getPrefix() . $key, $success);
		if (!$success) {
			return null;
		}
		return $result;
	}

	public function set($key, $value, $ttl = 0) {
		return apcu_store($this->getPrefix() . $key, $value, $ttl);
	}

	public function hasKey($key) {
		return apcu_exists($this->getPrefix() . $key);
	}

	public function remove($key) {
		return apcu_delete($this->getPrefix() . $key);
	}

	public function clear($prefix = '') {
		$ns = $this->getPrefix() . $prefix;
		$ns = preg_quote($ns, '/');
		if (class_exists('\APCIterator')) {
			$iter = new \APCIterator('user', '/^' . $ns . '/', APC_ITER_KEY);
		} else {
			$iter = new \APCUIterator('/^' . $ns . '/', APC_ITER_KEY);
		}
		return apcu_delete($iter);
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
		return apcu_add($this->getPrefix() . $key, $value, $ttl);
	}

	/**
	 * Increase a stored number
	 *
	 * @param string $key
	 * @param int $step
	 * @return int | bool
	 */
	public function inc($key, $step = 1) {
		$this->add($key, 0);
		/**
		 * TODO - hack around a PHP 7 specific issue in APCu
		 *
		 * on PHP 7 the apcu_inc method on a non-existing object will increment
		 * "0" and result in "1" as value - therefore we check for existence
		 * first
		 *
		 * on PHP 5.6 this is not the case
		 *
		 * see https://github.com/krakjoe/apcu/issues/183#issuecomment-244038221
		 * for details
		 */
		return apcu_exists($this->getPrefix() . $key)
			? apcu_inc($this->getPrefix() . $key, $step)
			: false;
	}

	/**
	 * Decrease a stored number
	 *
	 * @param string $key
	 * @param int $step
	 * @return int | bool
	 */
	public function dec($key, $step = 1) {
		/**
		 * TODO - hack around a PHP 7 specific issue in APCu
		 *
		 * on PHP 7 the apcu_dec method on a non-existing object will decrement
		 * "0" and result in "-1" as value - therefore we check for existence
		 * first
		 *
		 * on PHP 5.6 this is not the case
		 *
		 * see https://github.com/krakjoe/apcu/issues/183#issuecomment-244038221
		 * for details
		 */
		return apcu_exists($this->getPrefix() . $key)
			? apcu_dec($this->getPrefix() . $key, $step)
			: false;
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
		// apc only does cas for ints
		if (is_int($old) and is_int($new)) {
			return apcu_cas($this->getPrefix() . $key, $old, $new);
		} else {
			return $this->casEmulated($key, $old, $new);
		}
	}

	/**
	 * @return bool
	 */
	public static function isAvailable() {
		if (!extension_loaded('apcu')) {
			return false;
		} elseif (!\OC::$server->get(IniGetWrapper::class)->getBool('apc.enabled')) {
			return false;
		} elseif (!\OC::$server->get(IniGetWrapper::class)->getBool('apc.enable_cli') && \OC::$CLI) {
			return false;
		} elseif (
				version_compare(phpversion('apc') ?: '0.0.0', '4.0.6') === -1 &&
				version_compare(phpversion('apcu') ?: '0.0.0', '5.1.0') === -1
		) {
			return false;
		} else {
			return true;
		}
	}
}
