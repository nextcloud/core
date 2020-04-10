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

namespace OCP\Files\Cache;

/**
 * check the storage backends for updates and change the cache accordingly
 *
 * @since 9.0.0
 */
interface IWatcher {
	const CHECK_NEVER = 0; // never check the underlying filesystem for updates
	const CHECK_ONCE = 1; // check the underlying filesystem for updates once every request for each file
	const CHECK_ALWAYS = 2; // always check the underlying filesystem for updates

	/**
	 * @param int $policy either IWatcher::CHECK_NEVER, IWatcher::CHECK_ONCE, IWatcher::CHECK_ALWAYS
	 * @since 9.0.0
	 */
	public function setPolicy($policy);

	/**
	 * @return int either IWatcher::CHECK_NEVER, IWatcher::CHECK_ONCE, IWatcher::CHECK_ALWAYS
	 * @since 9.0.0
	 */
	public function getPolicy();

	/**
	 * check $path for updates and update if needed
	 *
	 * @param string $path
	 * @param ICacheEntry|null $cachedEntry
	 * @return boolean true if path was updated
	 * @since 9.0.0
	 */
	public function checkUpdate($path, $cachedEntry = null);

	/**
	 * Update the cache for changes to $path
	 *
	 * @param string $path
	 * @param ICacheEntry $cachedData
	 * @since 9.0.0
	 */
	public function update($path, $cachedData);

	/**
	 * Check if the cache for $path needs to be updated
	 *
	 * @param string $path
	 * @param ICacheEntry $cachedData
	 * @return bool
	 * @since 9.0.0
	 */
	public function needsUpdate($path, $cachedData);

	/**
	 * remove deleted files in $path from the cache
	 *
	 * @param string $path
	 * @since 9.0.0
	 */
	public function cleanFolder($path);
}
