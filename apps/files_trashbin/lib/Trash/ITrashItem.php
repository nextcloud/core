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

use OCP\Files\FileInfo;
use OCP\IUser;

/**
 * @since 15.0.0
 */
interface ITrashItem extends FileInfo {
	/**
	 * Get the trash backend for this item
	 *
	 * @return ITrashBackend
	 * @since 15.0.0
	 */
	public function getTrashBackend(): ITrashBackend;

	/**
	 * Get the original location for the trash item
	 *
	 * @return string
	 * @since 15.0.0
	 */
	public function getOriginalLocation(): string;

	/**
	 * Get the timestamp that the file was moved to trash
	 *
	 * @return int
	 * @since 15.0.0
	 */
	public function getDeletedTime(): int;

	/**
	 * Get the path of the item relative to the users trashbin
	 *
	 * @return string
	 * @since 15.0.0
	 */
	public function getTrashPath(): string;

	/**
	 * Whether the item is a deleted item in the root of the trash, or a file in a subfolder
	 *
	 * @return bool
	 * @since 15.0.0
	 */
	public function isRootItem(): bool;

	/**
	 * Get the user for which this trash item applies
	 *
	 * @return IUser
	 * @since 15.0.0
	 */
	public function getUser(): IUser;

	public function getTitle(): string;
}
