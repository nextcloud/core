<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\Files\Storage;

use OCP\Files\Notify\INotifyHandler;

/**
 * Storage backend that support active notifications
 *
 * @since 9.1.0
 */
interface INotifyStorage {
	const NOTIFY_ADDED = 1;
	const NOTIFY_REMOVED = 2;
	const NOTIFY_MODIFIED = 3;
	const NOTIFY_RENAMED = 4;

	/**
	 * Start listening for update notifications
	 *
	 * The provided callback will be called for every incoming notification with the following parameters
	 *  - int $type the type of update, one of the INotifyStorage::NOTIFY_* constants
	 *  - string $path the path of the update
	 *  - string $renameTarget the target of the rename operation, only provided for rename updates
	 *
	 * Note that this call is blocking and will not exit on it's own, to stop listening for notifications return `false` from the callback
	 *
	 * @param string $path
	 * @param callable $callback
	 *
	 * @since 9.1.0
	 * @deprecated 12.0.0 use INotifyStorage::notify()->listen() instead
	 */
	public function listen($path, callable $callback);

	/**
	 * Start the notification handler for this storage
	 *
	 * @param $path
	 * @return INotifyHandler
	 *
	 * @since 12.0.0
	 */
	public function notify($path);
}
