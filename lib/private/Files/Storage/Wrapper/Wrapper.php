<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author J0WI <J0WI@users.noreply.github.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tigran Mkrtchyan <tigran.mkrtchyan@desy.de>
 * @author Vincent Petry <vincent@nextcloud.com>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

namespace OC\Files\Storage\Wrapper;

use OCP\Files\InvalidPathException;
use OCP\Files\Storage\ILockingStorage;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\Lock\ILockingProvider;

class Wrapper implements \OC\Files\Storage\Storage, ILockingStorage, IWriteStreamStorage {
	/**
	 * @var \OC\Files\Storage\Storage $storage
	 */
	protected $storage;

	public $cache;
	public $scanner;
	public $watcher;
	public $propagator;
	public $updater;

	/**
	 * @param array $parameters
	 */
	public function __construct($parameters) {
		$this->storage = $parameters['storage'];
	}

	/**
	 * @return \OC\Files\Storage\Storage
	 */
	public function getWrapperStorage() {
		return $this->storage;
	}

	/**
	 * Get the identifier for the storage,
	 * the returned id should be the same for every storage object that is created with the same parameters
	 * and two storage objects with the same id should refer to two storages that display the same files.
	 *
	 * @return string
	 */
	public function getId() {
		return $this->getWrapperStorage()->getId();
	}

	/**
	 * see https://www.php.net/manual/en/function.mkdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path) {
		return $this->getWrapperStorage()->mkdir($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path) {
		return $this->getWrapperStorage()->rmdir($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.opendir.php
	 *
	 * @param string $path
	 * @return resource|bool
	 */
	public function opendir($path) {
		return $this->getWrapperStorage()->opendir($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.is_dir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function is_dir($path) {
		return $this->getWrapperStorage()->is_dir($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.is_file.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function is_file($path) {
		return $this->getWrapperStorage()->is_file($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.stat.php
	 * only the following keys are required in the result: size and mtime
	 *
	 * @param string $path
	 * @return array|bool
	 */
	public function stat($path) {
		return $this->getWrapperStorage()->stat($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.filetype.php
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function filetype($path) {
		return $this->getWrapperStorage()->filetype($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.filesize.php
	 * The result for filesize when called on a folder is required to be 0
	 *
	 * @param string $path
	 * @return int|bool
	 */
	public function filesize($path) {
		return $this->getWrapperStorage()->filesize($path);
	}

	/**
	 * check if a file can be created in $path
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isCreatable($path) {
		return $this->getWrapperStorage()->isCreatable($path);
	}

	/**
	 * check if a file can be read
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isReadable($path) {
		return $this->getWrapperStorage()->isReadable($path);
	}

	/**
	 * check if a file can be written to
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isUpdatable($path) {
		return $this->getWrapperStorage()->isUpdatable($path);
	}

	/**
	 * check if a file can be deleted
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isDeletable($path) {
		return $this->getWrapperStorage()->isDeletable($path);
	}

	/**
	 * check if a file can be shared
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isSharable($path) {
		return $this->getWrapperStorage()->isSharable($path);
	}

	/**
	 * get the full permissions of a path.
	 * Should return a combination of the PERMISSION_ constants defined in lib/public/constants.php
	 *
	 * @param string $path
	 * @return int
	 */
	public function getPermissions($path) {
		return $this->getWrapperStorage()->getPermissions($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function file_exists($path) {
		return $this->getWrapperStorage()->file_exists($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.filemtime.php
	 *
	 * @param string $path
	 * @return int|bool
	 */
	public function filemtime($path) {
		return $this->getWrapperStorage()->filemtime($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.file_get_contents.php
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function file_get_contents($path) {
		return $this->getWrapperStorage()->file_get_contents($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param mixed $data
	 * @return int|false
	 */
	public function file_put_contents($path, $data) {
		return $this->getWrapperStorage()->file_put_contents($path, $data);
	}

	/**
	 * see https://www.php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function unlink($path) {
		return $this->getWrapperStorage()->unlink($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.rename.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function rename($path1, $path2) {
		return $this->getWrapperStorage()->rename($path1, $path2);
	}

	/**
	 * see https://www.php.net/manual/en/function.copy.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function copy($path1, $path2) {
		return $this->getWrapperStorage()->copy($path1, $path2);
	}

	/**
	 * see https://www.php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|bool
	 */
	public function fopen($path, $mode) {
		return $this->getWrapperStorage()->fopen($path, $mode);
	}

	/**
	 * get the mimetype for a file or folder
	 * The mimetype for a folder is required to be "httpd/unix-directory"
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function getMimeType($path) {
		return $this->getWrapperStorage()->getMimeType($path);
	}

	/**
	 * see https://www.php.net/manual/en/function.hash.php
	 *
	 * @param string $type
	 * @param string $path
	 * @param bool $raw
	 * @return string|bool
	 */
	public function hash($type, $path, $raw = false) {
		return $this->getWrapperStorage()->hash($type, $path, $raw);
	}

	/**
	 * see https://www.php.net/manual/en/function.free_space.php
	 *
	 * @param string $path
	 * @return int|bool
	 */
	public function free_space($path) {
		return $this->getWrapperStorage()->free_space($path);
	}

	/**
	 * search for occurrences of $query in file names
	 *
	 * @param string $query
	 * @return array|bool
	 */
	public function search($query) {
		return $this->getWrapperStorage()->search($query);
	}

	/**
	 * see https://www.php.net/manual/en/function.touch.php
	 * If the backend does not support the operation, false should be returned
	 *
	 * @param string $path
	 * @param int $mtime
	 * @return bool
	 */
	public function touch($path, $mtime = null) {
		return $this->getWrapperStorage()->touch($path, $mtime);
	}

	/**
	 * get the path to a local version of the file.
	 * The local version of the file can be temporary and doesn't have to be persistent across requests
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function getLocalFile($path) {
		return $this->getWrapperStorage()->getLocalFile($path);
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 *
	 * hasUpdated for folders should return at least true if a file inside the folder is add, removed or renamed.
	 * returning true for other changes in the folder is optional
	 */
	public function hasUpdated($path, $time) {
		return $this->getWrapperStorage()->hasUpdated($path, $time);
	}

	/**
	 * get a cache instance for the storage
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage|null (optional) the storage to pass to the cache
	 * @return \OC\Files\Cache\Cache
	 */
	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		return $this->getWrapperStorage()->getCache($path, $storage);
	}

	/**
	 * get a scanner instance for the storage
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the scanner
	 * @return \OC\Files\Cache\Scanner
	 */
	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		return $this->getWrapperStorage()->getScanner($path, $storage);
	}


	/**
	 * get the user id of the owner of a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getOwner($path) {
		return $this->getWrapperStorage()->getOwner($path);
	}

	/**
	 * get a watcher instance for the cache
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the watcher
	 * @return \OC\Files\Cache\Watcher
	 */
	public function getWatcher($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		return $this->getWrapperStorage()->getWatcher($path, $storage);
	}

	public function getPropagator($storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		return $this->getWrapperStorage()->getPropagator($storage);
	}

	public function getUpdater($storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		return $this->getWrapperStorage()->getUpdater($storage);
	}

	/**
	 * @return \OC\Files\Cache\Storage
	 */
	public function getStorageCache() {
		return $this->getWrapperStorage()->getStorageCache();
	}

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function getETag($path) {
		return $this->getWrapperStorage()->getETag($path);
	}

	/**
	 * Returns true
	 *
	 * @return true
	 */
	public function test() {
		return $this->getWrapperStorage()->test();
	}

	/**
	 * Returns the wrapped storage's value for isLocal()
	 *
	 * @return bool wrapped storage's isLocal() value
	 */
	public function isLocal() {
		return $this->getWrapperStorage()->isLocal();
	}

	/**
	 * Check if the storage is an instance of $class or is a wrapper for a storage that is an instance of $class
	 *
	 * @param string $class
	 * @return bool
	 */
	public function instanceOfStorage($class) {
		if (ltrim($class, '\\') === 'OC\Files\Storage\Shared') {
			// FIXME Temporary fix to keep existing checks working
			$class = '\OCA\Files_Sharing\SharedStorage';
		}
		return is_a($this, $class) or $this->getWrapperStorage()->instanceOfStorage($class);
	}

	/**
	 * Pass any methods custom to specific storage implementations to the wrapped storage
	 *
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function __call($method, $args) {
		return call_user_func_array([$this->getWrapperStorage(), $method], $args);
	}

	/**
	 * A custom storage implementation can return an url for direct download of a give file.
	 *
	 * For now the returned array can hold the parameter url - in future more attributes might follow.
	 *
	 * @param string $path
	 * @return array|bool
	 */
	public function getDirectDownload($path) {
		return $this->getWrapperStorage()->getDirectDownload($path);
	}

	/**
	 * Get availability of the storage
	 *
	 * @return array [ available, last_checked ]
	 */
	public function getAvailability() {
		return $this->getWrapperStorage()->getAvailability();
	}

	/**
	 * Set availability of the storage
	 *
	 * @param bool $isAvailable
	 */
	public function setAvailability($isAvailable) {
		$this->getWrapperStorage()->setAvailability($isAvailable);
	}

	/**
	 * @param string $path the path of the target folder
	 * @param string $fileName the name of the file itself
	 * @return void
	 * @throws InvalidPathException
	 */
	public function verifyPath($path, $fileName) {
		$this->getWrapperStorage()->verifyPath($path, $fileName);
	}

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		if ($sourceStorage === $this) {
			return $this->copy($sourceInternalPath, $targetInternalPath);
		}

		return $this->getWrapperStorage()->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		if ($sourceStorage === $this) {
			return $this->rename($sourceInternalPath, $targetInternalPath);
		}

		return $this->getWrapperStorage()->moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}

	public function getMetaData($path) {
		return $this->getWrapperStorage()->getMetaData($path);
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock($path, $type, ILockingProvider $provider) {
		if ($this->getWrapperStorage()->instanceOfStorage('\OCP\Files\Storage\ILockingStorage')) {
			$this->getWrapperStorage()->acquireLock($path, $type, $provider);
		}
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function releaseLock($path, $type, ILockingProvider $provider) {
		if ($this->getWrapperStorage()->instanceOfStorage('\OCP\Files\Storage\ILockingStorage')) {
			$this->getWrapperStorage()->releaseLock($path, $type, $provider);
		}
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function changeLock($path, $type, ILockingProvider $provider) {
		if ($this->getWrapperStorage()->instanceOfStorage('\OCP\Files\Storage\ILockingStorage')) {
			$this->getWrapperStorage()->changeLock($path, $type, $provider);
		}
	}

	/**
	 * @return bool
	 */
	public function needsPartFile() {
		return $this->getWrapperStorage()->needsPartFile();
	}

	public function writeStream(string $path, $stream, int $size = null): int {
		$storage = $this->getWrapperStorage();
		if ($storage->instanceOfStorage(IWriteStreamStorage::class)) {
			/** @var IWriteStreamStorage $storage */
			return $storage->writeStream($path, $stream, $size);
		} else {
			$target = $this->fopen($path, 'w');
			[$count, $result] = \OC_Helper::streamCopy($stream, $target);
			fclose($stream);
			fclose($target);
			return $count;
		}
	}

	public function getDirectoryContent($directory): \Traversable {
		return $this->getWrapperStorage()->getDirectoryContent($directory);
	}
}
