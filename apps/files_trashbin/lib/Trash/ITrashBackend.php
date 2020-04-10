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

use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\IUser;

/**
 * @since 15.0.0
 */
interface ITrashBackend {
	/**
	 * List all trash items in the root of the trashbin
	 *
	 * @param IUser $user
	 * @return ITrashItem[]
	 * @since 15.0.0
	 */
	public function listTrashRoot(IUser $user): array;

	/**
	 * List all trash items in a subfolder in the trashbin
	 *
	 * @param ITrashItem $folder
	 * @return ITrashItem[]
	 * @since 15.0.0
	 */
	public function listTrashFolder(ITrashItem $folder): array;

	/**
	 * Restore a trashbin item
	 *
	 * @param ITrashItem $item
	 * @since 15.0.0
	 */
	public function restoreItem(ITrashItem $item);

	/**
	 * Permanently remove an item from trash
	 *
	 * @param ITrashItem $item
	 * @since 15.0.0
	 */
	public function removeItem(ITrashItem $item);

	/**
	 * Move a file or folder to trash
	 *
	 * @param IStorage $storage
	 * @param string $internalPath
	 * @return boolean whether or not the file was moved to trash, if false then the file should be deleted normally
	 * @since 15.0.0
	 */
	public function moveToTrash(IStorage $storage, string $internalPath): bool;

	/**
	 * @param IUser $user
	 * @param int $fileId
	 * @return Node|null
	 */
	public function getTrashNodeById(IUser $user, int $fileId);
}
