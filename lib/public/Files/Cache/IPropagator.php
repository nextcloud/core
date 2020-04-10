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
 * Propagate etags and mtimes within the storage
 *
 * @since 9.0.0
 */
interface IPropagator {
	/**
	 * Mark the beginning of a propagation batch
	 *
	 * Note that not all cache setups support propagation in which case this will be a noop
	 *
	 * Batching for cache setups that do support it has to be explicit since the cache state is not fully consistent
	 * before the batch is committed.
	 *
	 * @since 9.1.0
	 */
	public function beginBatch();

	/**
	 * Commit the active propagation batch
	 *
	 * @since 9.1.0
	 */
	public function commitBatch();

	/**
	 * @param string $internalPath
	 * @param int $time
	 * @since 9.0.0
	 */
	public function propagateChange($internalPath, $time);
}
