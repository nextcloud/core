<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

/**
 * Public interface of ownCloud for apps to use.
 * Files/Storage interface
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP\Files\Storage;

use OCP\Files\Cache\ICache;
use OCP\Files\Cache\IPropagator;
use OCP\Files\Cache\IScanner;
use OCP\Files\Cache\IUpdater;
use OCP\Files\Cache\IWatcher;
use OCP\Files\InvalidPathException;

/**
 * Provide a common interface to all different storage options
 *
 * All paths passed to the storage are relative to the storage and should NOT have a leading slash.
 *
 * @since 9.0.0
 */
interface IStorage {
	/**
	 * $parameters is a free form array with the configuration options needed to construct the storage
	 *
	 * @param array $parameters
	 * @since 9.0.0
	 */
	public function __construct($parameters);

	/**
	 * Get the identifier for the storage,
	 * the returned id should be the same for every storage object that is created with the same parameters
	 * and two storage objects with the same id should refer to two storages that display the same files.
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getId();

	/**
	 * see http://php.net/manual/en/function.mkdir.php
	 * implementations need to implement a recursive mkdir
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function mkdir($path);

	/**
	 * see http://php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function rmdir($path);

	/**
	 * see http://php.net/manual/en/function.opendir.php
	 *
	 * @param string $path
	 * @return resource|false
	 * @since 9.0.0
	 */
	public function opendir($path);

	/**
	 * see http://php.net/manual/en/function.is-dir.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function is_dir($path);

	/**
	 * see http://php.net/manual/en/function.is-file.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function is_file($path);

	/**
	 * see http://php.net/manual/en/function.stat.php
	 * only the following keys are required in the result: size and mtime
	 *
	 * @param string $path
	 * @return array|false
	 * @since 9.0.0
	 */
	public function stat($path);

	/**
	 * see http://php.net/manual/en/function.filetype.php
	 *
	 * @param string $path
	 * @return string|false
	 * @since 9.0.0
	 */
	public function filetype($path);

	/**
	 * see http://php.net/manual/en/function.filesize.php
	 * The result for filesize when called on a folder is required to be 0
	 *
	 * @param string $path
	 * @return int|false
	 * @since 9.0.0
	 */
	public function filesize($path);

	/**
	 * check if a file can be created in $path
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function isCreatable($path);

	/**
	 * check if a file can be read
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function isReadable($path);

	/**
	 * check if a file can be written to
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function isUpdatable($path);

	/**
	 * check if a file can be deleted
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function isDeletable($path);

	/**
	 * check if a file can be shared
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function isSharable($path);

	/**
	 * get the full permissions of a path.
	 * Should return a combination of the PERMISSION_ constants defined in lib/public/constants.php
	 *
	 * @param string $path
	 * @return int
	 * @since 9.0.0
	 */
	public function getPermissions($path);

	/**
	 * see http://php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function file_exists($path);

	/**
	 * see http://php.net/manual/en/function.filemtime.php
	 *
	 * @param string $path
	 * @return int|false
	 * @since 9.0.0
	 */
	public function filemtime($path);

	/**
	 * see http://php.net/manual/en/function.file_get_contents.php
	 *
	 * @param string $path
	 * @return string|false
	 * @since 9.0.0
	 */
	public function file_get_contents($path);

	/**
	 * see http://php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param string $data
	 * @return bool
	 * @since 9.0.0
	 */
	public function file_put_contents($path, $data);

	/**
	 * see http://php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 * @since 9.0.0
	 */
	public function unlink($path);

	/**
	 * see http://php.net/manual/en/function.rename.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 * @since 9.0.0
	 */
	public function rename($path1, $path2);

	/**
	 * see http://php.net/manual/en/function.copy.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 * @since 9.0.0
	 */
	public function copy($path1, $path2);

	/**
	 * see http://php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|false
	 * @since 9.0.0
	 */
	public function fopen($path, $mode);

	/**
	 * get the mimetype for a file or folder
	 * The mimetype for a folder is required to be "httpd/unix-directory"
	 *
	 * @param string $path
	 * @return string|false
	 * @since 9.0.0
	 */
	public function getMimeType($path);

	/**
	 * see http://php.net/manual/en/function.hash-file.php
	 *
	 * @param string $type
	 * @param string $path
	 * @param bool $raw
	 * @return string|false
	 * @since 9.0.0
	 */
	public function hash($type, $path, $raw = false);

	/**
	 * see http://php.net/manual/en/function.free_space.php
	 *
	 * @param string $path
	 * @return int|false
	 * @since 9.0.0
	 */
	public function free_space($path);

	/**
	 * see http://php.net/manual/en/function.touch.php
	 * If the backend does not support the operation, false should be returned
	 *
	 * @param string $path
	 * @param int $mtime
	 * @return bool
	 * @since 9.0.0
	 */
	public function touch($path, $mtime = null);

	/**
	 * get the path to a local version of the file.
	 * The local version of the file can be temporary and doesn't have to be persistent across requests
	 *
	 * @param string $path
	 * @return string|false
	 * @since 9.0.0
	 */
	public function getLocalFile($path);

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 * @since 9.0.0
	 *
	 * hasUpdated for folders should return at least true if a file inside the folder is add, removed or renamed.
	 * returning true for other changes in the folder is optional
	 */
	public function hasUpdated($path, $time);

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string|false
	 * @since 9.0.0
	 */
	public function getETag($path);

	/**
	 * Returns whether the storage is local, which means that files
	 * are stored on the local filesystem instead of remotely.
	 * Calling getLocalFile() for local storages should always
	 * return the local files, whereas for non-local storages
	 * it might return a temporary file.
	 *
	 * @return bool true if the files are stored locally, false otherwise
	 * @since 9.0.0
	 */
	public function isLocal();

	/**
	 * Check if the storage is an instance of $class or is a wrapper for a storage that is an instance of $class
	 *
	 * @param string $class
	 * @return bool
	 * @since 9.0.0
	 */
	public function instanceOfStorage($class);

	/**
	 * A custom storage implementation can return an url for direct download of a give file.
	 *
	 * For now the returned array can hold the parameter url - in future more attributes might follow.
	 *
	 * @param string $path
	 * @return array|false
	 * @since 9.0.0
	 */
	public function getDirectDownload($path);

	/**
	 * @param string $path the path of the target folder
	 * @param string $fileName the name of the file itself
	 * @return void
	 * @throws InvalidPathException
	 * @since 9.0.0
	 */
	public function verifyPath($path, $fileName);

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 * @since 9.0.0
	 */
	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath);

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 * @since 9.0.0
	 */
	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath);

	/**
	 * Test a storage for availability
	 *
	 * @since 9.0.0
	 * @return bool
	 */
	public function test();

	/**
	 * @since 9.0.0
	 * @return array [ available, last_checked ]
	 */
	public function getAvailability();

	/**
	 * @since 9.0.0
	 * @param bool $isAvailable
	 */
	public function setAvailability($isAvailable);

	/**
	 * @param string $path path for which to retrieve the owner
	 * @since 9.0.0
	 */
	public function getOwner($path);

	/**
	 * @return ICache
	 * @since 9.0.0
	 */
	public function getCache();

	/**
	 * @return IPropagator
	 * @since 9.0.0
	 */
	public function getPropagator();

	/**
	 * @return IScanner
	 * @since 9.0.0
	 */
	public function getScanner();

	/**
	 * @return IUpdater
	 * @since 9.0.0
	 */
	public function getUpdater();

	/**
	 * @return IWatcher
	 * @since 9.0.0
	 */
	public function getWatcher();
}
