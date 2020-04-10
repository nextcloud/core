<?php

declare(strict_types=1);

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

namespace OCA\Files_Versions\Versions;

use OCP\Files\FileInfo;
use OCP\IUser;

/**
 * @since 15.0.0
 */
interface IVersion {
	/**
	 * @return IVersionBackend
	 * @since 15.0.0
	 */
	public function getBackend(): IVersionBackend;

	/**
	 * Get the file info of the source file
	 *
	 * @return FileInfo
	 * @since 15.0.0
	 */
	public function getSourceFile(): FileInfo;

	/**
	 * Get the id of the revision for the file
	 *
	 * @return int|string
	 * @since 15.0.0
	 */
	public function getRevisionId();

	/**
	 * Get the timestamp this version was created
	 *
	 * @return int
	 * @since 15.0.0
	 */
	public function getTimestamp(): int;

	/**
	 * Get the size of this version
	 *
	 * @return int
	 * @since 15.0.0
	 */
	public function getSize(): int;

	/**
	 * Get the name of the source file at the time of making this version
	 *
	 * @return string
	 * @since 15.0.0
	 */
	public function getSourceFileName(): string;

	/**
	 * Get the mimetype of this version
	 *
	 * @return string
	 * @since 15.0.0
	 */
	public function getMimeType(): string;

	/**
	 * Get the path of this version
	 *
	 * @return string
	 * @since 15.0.0
	 */
	public function getVersionPath(): string;

	/**
	 * @return IUser
	 * @since 15.0.0
	 */
	public function getUser(): IUser;
}
