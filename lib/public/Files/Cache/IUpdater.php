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

use OCP\Files\Storage\IStorage;

/**
 * Update the cache and propagate changes
 *
 * @since 9.0.0
 */
interface IUpdater {
	/**
	 * Get the propagator for etags and mtime for the view the updater works on
	 *
	 * @return IPropagator
	 * @since 9.0.0
	 */
	public function getPropagator();

	/**
	 * Propagate etag and mtime changes for the parent folders of $path up to the root of the filesystem
	 *
	 * @param string $path the path of the file to propagate the changes for
	 * @param int|null $time the timestamp to set as mtime for the parent folders, if left out the current time is used
	 * @since 9.0.0
	 */
	public function propagate($path, $time = null);

	/**
	 * Update the cache for $path and update the size, etag and mtime of the parent folders
	 *
	 * @param string $path
	 * @param int $time
	 * @since 9.0.0
	 */
	public function update($path, $time = null);

	/**
	 * Remove $path from the cache and update the size, etag and mtime of the parent folders
	 *
	 * @param string $path
	 * @since 9.0.0
	 */
	public function remove($path);

	/**
	 * Rename a file or folder in the cache and update the size, etag and mtime of the parent folders
	 *
	 * @param IStorage $sourceStorage
	 * @param string $source
	 * @param string $target
	 * @since 9.0.0
	 */
	public function renameFromStorage(IStorage $sourceStorage, $source, $target);
}
