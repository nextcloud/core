<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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
	public const NOTIFY_ADDED = 1;
	public const NOTIFY_REMOVED = 2;
	public const NOTIFY_MODIFIED = 3;
	public const NOTIFY_RENAMED = 4;

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
