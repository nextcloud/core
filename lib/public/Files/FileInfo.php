<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Felix Heidecke <felix@heidecke.me>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCP\Files;

/**
 * Interface FileInfo
 *
 * @since 7.0.0
 */
interface FileInfo {
	/**
	 * @since 7.0.0
	 */
	public const TYPE_FILE = 'file';
	/**
	 * @since 7.0.0
	 */
	public const TYPE_FOLDER = 'dir';

	/**
	 * @const \OCP\Files\FileInfo::SPACE_NOT_COMPUTED Return value for a not computed space value
	 * @since 8.0.0
	 */
	public const SPACE_NOT_COMPUTED = -1;
	/**
	 * @const \OCP\Files\FileInfo::SPACE_UNKNOWN Return value for unknown space value
	 * @since 8.0.0
	 */
	public const SPACE_UNKNOWN = -2;
	/**
	 * @const \OCP\Files\FileInfo::SPACE_UNLIMITED Return value for unlimited space
	 * @since 8.0.0
	 */
	public const SPACE_UNLIMITED = -3;

	/**
	 * @since 9.1.0
	 */
	public const MIMETYPE_FOLDER = 'httpd/unix-directory';

	/**
	 * @const \OCP\Files\FileInfo::BLACKLIST_FILES_REGEX Return regular expression to test filenames against (blacklisting)
	 * @since 12.0.0
	 */
	public const BLACKLIST_FILES_REGEX = '\.(part|filepart)$';

	/**
	 * Get the Etag of the file or folder
	 *
	 * @return string
	 * @since 7.0.0
	 */
	public function getEtag();

	/**
	 * Get the size in bytes for the file or folder
	 *
	 * @param bool $includeMounts whether or not to include the size of any sub mounts, since 16.0.0
	 * @return int
	 * @since 7.0.0
	 */
	public function getSize($includeMounts = true);

	/**
	 * Get the last modified date as timestamp for the file or folder
	 *
	 * @return int
	 * @since 7.0.0
	 */
	public function getMtime();

	/**
	 * Get the name of the file or folder
	 *
	 * @return string
	 * @since 7.0.0
	 */
	public function getName();

	/**
	 * Get the path relative to the storage
	 *
	 * @return string
	 * @since 7.0.0
	 */
	public function getInternalPath();

	/**
	 * Get the absolute path
	 *
	 * @return string
	 * @since 7.0.0
	 */
	public function getPath();

	/**
	 * Get the full mimetype of the file or folder i.e. 'image/png'
	 *
	 * @return string
	 * @since 7.0.0
	 */
	public function getMimetype();

	/**
	 * Get the first part of the mimetype of the file or folder i.e. 'image'
	 *
	 * @return string
	 * @since 7.0.0
	 */
	public function getMimePart();

	/**
	 * Get the storage the file or folder is storage on
	 *
	 * @return \OCP\Files\Storage
	 * @since 7.0.0
	 */
	public function getStorage();

	/**
	 * Get the file id of the file or folder
	 *
	 * @return int|null
	 * @since 7.0.0
	 */
	public function getId();

	/**
	 * Check whether the file is encrypted
	 *
	 * @return bool
	 * @since 7.0.0
	 */
	public function isEncrypted();

	/**
	 * Get the permissions of the file or folder as bitmasked combination of the following constants
	 * \OCP\Constants::PERMISSION_CREATE
	 * \OCP\Constants::PERMISSION_READ
	 * \OCP\Constants::PERMISSION_UPDATE
	 * \OCP\Constants::PERMISSION_DELETE
	 * \OCP\Constants::PERMISSION_SHARE
	 * \OCP\Constants::PERMISSION_ALL
	 *
	 * @return int
	 * @since 7.0.0 - namespace of constants has changed in 8.0.0
	 */
	public function getPermissions();

	/**
	 * Check whether this is a file or a folder
	 *
	 * @return string \OCP\Files\FileInfo::TYPE_FILE|\OCP\Files\FileInfo::TYPE_FOLDER
	 * @since 7.0.0
	 */
	public function getType();

	/**
	 * Check if the file or folder is readable
	 *
	 * @return bool
	 * @since 7.0.0
	 */
	public function isReadable();

	/**
	 * Check if a file is writable
	 *
	 * @return bool
	 * @since 7.0.0
	 */
	public function isUpdateable();

	/**
	 * Check whether new files or folders can be created inside this folder
	 *
	 * @return bool
	 * @since 8.0.0
	 */
	public function isCreatable();

	/**
	 * Check if a file or folder can be deleted
	 *
	 * @return bool
	 * @since 7.0.0
	 */
	public function isDeletable();

	/**
	 * Check if a file or folder can be shared
	 *
	 * @return bool
	 * @since 7.0.0
	 */
	public function isShareable();

	/**
	 * Check if a file or folder is shared
	 *
	 * @return bool
	 * @since 7.0.0
	 */
	public function isShared();

	/**
	 * Check if a file or folder is mounted
	 *
	 * @return bool
	 * @since 7.0.0
	 */
	public function isMounted();

	/**
	 * Get the mountpoint the file belongs to
	 *
	 * @return \OCP\Files\Mount\IMountPoint
	 * @since 8.0.0
	 */
	public function getMountPoint();

	/**
	 * Get the owner of the file
	 *
	 * @return \OCP\IUser
	 * @since 9.0.0
	 */
	public function getOwner();

	/**
	 * Get the stored checksum for this file
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getChecksum();

	/**
	 * Get the extension of the file
	 *
	 * @return string
	 * @since 15.0.0
	 */
	public function getExtension(): string;

	/**
	 * Get the creation date as unix timestamp
	 *
	 * If the creation time is not known, 0 will be returned
	 *
	 * creation time is not set automatically by the server and is generally only available
	 * for files uploaded by the sync clients
	 *
	 * @return int
	 * @since 18.0.0
	 */
	public function getCreationTime(): int;

	/**
	 * Get the upload date as unix timestamp
	 *
	 * If the upload time is not known, 0 will be returned
	 *
	 * Upload time will be set automatically by the server for files uploaded over DAV
	 * files created by Nextcloud apps generally do not have an the upload time set
	 *
	 * @return int
	 * @since 18.0.0
	 */
	public function getUploadTime(): int;
}
