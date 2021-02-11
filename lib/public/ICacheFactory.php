<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCP;

/**
 * Interface ICacheFactory
 *
 * @since 7.0.0
 */
interface ICacheFactory {
	/**
	 * Get a distributed memory cache instance
	 *
	 * All entries added trough the cache instance will be namespaced by $prefix to prevent collisions between apps
	 *
	 * @param string $prefix
	 * @return ICache
	 * @since 7.0.0
	 * @deprecated 13.0.0 Use either createLocking, createDistributed or createLocal
	 */
	public function create(string $prefix = ''): ICache;

	/**
	 * Check if any memory cache backend is available
	 *
	 * @return bool
	 * @since 7.0.0
	 */
	public function isAvailable(): bool;

	/**
	 * Check if a local memory cache backend is available
	 *
	 * @return bool
	 * @since 14.0.0
	 */
	public function isLocalCacheAvailable(): bool;

	/**
	 * create a cache instance for storing locks
	 *
	 * @param string $prefix
	 * @return IMemcache
	 * @since 13.0.0
	 */
	public function createLocking(string $prefix = ''): IMemcache;

	/**
	 * create a distributed cache instance
	 *
	 * @param string $prefix
	 * @return ICache
	 * @since 13.0.0
	 */
	public function createDistributed(string $prefix = ''): ICache;

	/**
	 * create a local cache instance
	 *
	 * @param string $prefix
	 * @return ICache
	 * @since 13.0.0
	 */
	public function createLocal(string $prefix = ''): ICache;
}
