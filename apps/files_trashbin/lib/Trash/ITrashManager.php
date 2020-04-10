<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Trashbin\Trash;

use OCP\IUser;

interface ITrashManager extends ITrashBackend {
	/**
	 * Add a backend for the trashbin
	 *
	 * @param string $storageType
	 * @param ITrashBackend $backend
	 * @since 15.0.0
	 */
	public function registerBackend(string $storageType, ITrashBackend $backend);

	/**
	 * List all trash items in the root of the trashbin
	 *
	 * @param IUser $user
	 * @return ITrashItem[]
	 * @since 15.0.0
	 */
	public function listTrashRoot(IUser $user): array;

	/**
	 * Temporally prevent files from being moved to the trash
	 *
	 * @since 15.0.0
	 */
	public function pauseTrash();

	/**
	 * @since 15.0.0
	 */
	public function resumeTrash();
}
