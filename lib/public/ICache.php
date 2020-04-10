<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
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

/**
 * Public interface of ownCloud for apps to use.
 * Cache interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * This interface defines method for accessing the file based user cache.
 * @since 6.0.0
 */
interface ICache {

	/**
	 * Get a value from the user cache
	 * @param string $key
	 * @return mixed
	 * @since 6.0.0
	 */
	public function get($key);

	/**
	 * Set a value in the user cache
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl Time To Live in seconds. Defaults to 60*60*24
	 * @return bool
	 * @since 6.0.0
	 */
	public function set($key, $value, $ttl = 0);

	/**
	 * Check if a value is set in the user cache
	 * @param string $key
	 * @return bool
	 * @since 6.0.0
	 * @deprecated 9.1.0 Directly read from GET to prevent race conditions
	 */
	public function hasKey($key);

	/**
	 * Remove an item from the user cache
	 * @param string $key
	 * @return bool
	 * @since 6.0.0
	 */
	public function remove($key);

	/**
	 * Clear the user cache of all entries starting with a prefix
	 * @param string $prefix (optional)
	 * @return bool
	 * @since 6.0.0
	 */
	public function clear($prefix = '');
}
