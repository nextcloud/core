<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace OCP\Files\Notify;

/**
 * Provides access to detected changes in the storage by either actively listening
 * or getting the list of changes that happened in the background
 *
 * @since 12.0.0
 */
interface INotifyHandler {
	/**
	 * Start listening for update notifications
	 *
	 * The provided callback will be called for every incoming notification with the following parameters
	 *  - IChange|IRenameChange $change
	 *
	 * Note that this call is blocking and will not exit on it's own, to stop listening for notifications return `false` from the callback
	 *
	 * @param callable $callback
	 *
	 * @since 12.0.0
	 */
	public function listen(callable $callback);

	/**
	 * Get all changes detected since the start of the notify process or the last call to getChanges
	 *
	 * @return IChange[]
	 *
	 * @since 12.0.0
	 */
	public function getChanges();

	/**
	 * Stop listening for changes
	 *
	 * Note that any pending changes will be discarded
	 *
	 * @since 12.0.0
	 */
	public function stop();
}
