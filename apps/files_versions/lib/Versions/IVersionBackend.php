<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Versions\Versions;

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\IUser;

/**
 * @since 15.0.0
 */
interface IVersionBackend {
	/**
	 * Whether or not this version backend should be used for a storage
	 *
	 * If false is returned then the next applicable backend will be used
	 *
	 * @param IStorage $storage
	 * @return bool
	 * @since 17.0.0
	 */
	public function useBackendForStorage(IStorage $storage): bool;

	/**
	 * Get all versions for a file
	 *
	 * @param IUser $user
	 * @param FileInfo $file
	 * @return IVersion[]
	 * @since 15.0.0
	 */
	public function getVersionsForFile(IUser $user, FileInfo $file): array;

	/**
	 * Create a new version for a file
	 *
	 * @param IUser $user
	 * @param FileInfo $file
	 * @since 15.0.0
	 */
	public function createVersion(IUser $user, FileInfo $file);

	/**
	 * Restore this version
	 *
	 * @param IVersion $version
	 * @since 15.0.0
	 */
	public function rollback(IVersion $version);

	/**
	 * Open the file for reading
	 *
	 * @param IVersion $version
	 * @return resource
	 * @throws NotFoundException
	 * @since 15.0.0
	 */
	public function read(IVersion $version);

	/**
	 * Get the preview for a specific version of a file
	 *
	 * @param IUser $user
	 * @param FileInfo $sourceFile
	 * @param int|string $revision
	 *
	 * @return File
	 *
	 * @since 15.0.0
	 */
	public function getVersionFile(IUser $user, FileInfo $sourceFile, $revision): File;
}
