<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author J0WI <J0WI@users.noreply.github.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tigran Mkrtchyan <tigran.mkrtchyan@desy.de>
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

use OC\Files\Cache\Wrapper\CacheJail;
use OC\Files\Cache\Wrapper\JailPropagator;
use OC\Files\Filesystem;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\Lock\ILockingProvider;

/**
 * Jail to a subdirectory of the wrapped storage
 *
 * This restricts access to a subfolder of the wrapped storage with the subfolder becoming the root folder new storage
 */
class Jail extends Wrapper {
	/**
	 * @var string
	 */
	protected $rootPath;

	/**
	 * @param array $arguments ['storage' => $storage, 'mask' => $root]
	 *
	 * $storage: The storage that will be wrapper
	 * $root: The folder in the wrapped storage that will become the root folder of the wrapped storage
	 */
	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->rootPath = $arguments['root'];
	}

	public function getUnjailedPath($path) {
		return trim(Filesystem::normalizePath($this->rootPath . '/' . $path), '/');
	}

	/**
	 * This is separate from Wrapper::getWrapperStorage so we can get the jailed storage consistently even if the jail is inside another wrapper
	 */
	public function getUnjailedStorage() {
		return $this->storage;
	}


	public function getJailedPath($path) {
		$root = rtrim($this->rootPath, '/') . '/';

		if ($path !== $this->rootPath && strpos($path, $root) !== 0) {
			return null;
		} else {
			$path = substr($path, strlen($this->rootPath));
			return trim($path, '/');
		}
	}

	public function getId() {
		return parent::getId();
	}

	/**
	 * see https://www.php.net/manual/en/function.mkdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path) {
		return $this->getWrapperStorage()->mkdir($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path) {
		return $this->getWrapperStorage()->rmdir($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.opendir.php
	 *
	 * @param string $path
	 * @return resource|bool
	 */
	public function opendir($path) {
		return $this->getWrapperStorage()->opendir($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.is_dir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function is_dir($path) {
		return $this->getWrapperStorage()->is_dir($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.is_file.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function is_file($path) {
		return $this->getWrapperStorage()->is_file($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.stat.php
	 * only the following keys are required in the result: size and mtime
	 *
	 * @param string $path
	 * @return array|bool
	 */
	public function stat($path) {
		return $this->getWrapperStorage()->stat($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.filetype.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function filetype($path) {
		return $this->getWrapperStorage()->filetype($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.filesize.php
	 * The result for filesize when called on a folder is required to be 0
	 *
	 * @param string $path
	 * @return int|bool
	 */
	public function filesize($path) {
		return $this->getWrapperStorage()->filesize($this->getUnjailedPath($path));
	}

	/**
	 * check if a file can be created in $path
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isCreatable($path) {
		return $this->getWrapperStorage()->isCreatable($this->getUnjailedPath($path));
	}

	/**
	 * check if a file can be read
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isReadable($path) {
		return $this->getWrapperStorage()->isReadable($this->getUnjailedPath($path));
	}

	/**
	 * check if a file can be written to
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isUpdatable($path) {
		return $this->getWrapperStorage()->isUpdatable($this->getUnjailedPath($path));
	}

	/**
	 * check if a file can be deleted
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isDeletable($path) {
		return $this->getWrapperStorage()->isDeletable($this->getUnjailedPath($path));
	}

	/**
	 * check if a file can be shared
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isSharable($path) {
		return $this->getWrapperStorage()->isSharable($this->getUnjailedPath($path));
	}

	/**
	 * get the full permissions of a path.
	 * Should return a combination of the PERMISSION_ constants defined in lib/public/constants.php
	 *
	 * @param string $path
	 * @return int
	 */
	public function getPermissions($path) {
		return $this->getWrapperStorage()->getPermissions($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function file_exists($path) {
		return $this->getWrapperStorage()->file_exists($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.filemtime.php
	 *
	 * @param string $path
	 * @return int|bool
	 */
	public function filemtime($path) {
		return $this->getWrapperStorage()->filemtime($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.file_get_contents.php
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function file_get_contents($path) {
		return $this->getWrapperStorage()->file_get_contents($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param mixed $data
	 * @return int|false
	 */
	public function file_put_contents($path, $data) {
		return $this->getWrapperStorage()->file_put_contents($this->getUnjailedPath($path), $data);
	}

	/**
	 * see https://www.php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function unlink($path) {
		return $this->getWrapperStorage()->unlink($this->getUnjailedPath($path));
	}

	/**
	 * see https://www.php.net/manual/en/function.rename.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function rename($path1, $path2) {
		return $this->getWrapperStorage()->rename($this->getUnjailedPath($path1), $this->getUnjailedPath($path2));
	}

	/**
	 * see https://www.php.net/manual/en/function.copy.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function copy($path1, $path2) {
		return $this->getWrapperStorage()->copy($this->getUnjailedPath($path1), $this->getUnjailedPath($path2));
	}

	/**
	 * see https://www.php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|bool
	 */
	public function fopen($path, $mode) {
		return $this->getWrapperStorage()->fopen($this->getUnjailedPath($path), $mode);
	}

	/**
	 * get the mimetype for a file or folder
	 * The mimetype for a folder is required to be "httpd/unix-directory"
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function getMimeType($path) {
		return $this->getWrapperStorage()->getMimeType($this->getUnjailedPath($path));
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
		return $this->getWrapperStorage()->hash($type, $this->getUnjailedPath($path), $raw);
	}

	/**
	 * see https://www.php.net/manual/en/function.free_space.php
	 *
	 * @param string $path
	 * @return int|bool
	 */
	public function free_space($path) {
		return $this->getWrapperStorage()->free_space($this->getUnjailedPath($path));
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
		return $this->getWrapperStorage()->touch($this->getUnjailedPath($path), $mtime);
	}

	/**
	 * get the path to a local version of the file.
	 * The local version of the file can be temporary and doesn't have to be persistent across requests
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function getLocalFile($path) {
		return $this->getWrapperStorage()->getLocalFile($this->getUnjailedPath($path));
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
		return $this->getWrapperStorage()->hasUpdated($this->getUnjailedPath($path), $time);
	}

	/**
	 * get a cache instance for the storage
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the cache
	 * @return \OC\Files\Cache\Cache
	 */
	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this->getWrapperStorage();
		}
		$sourceCache = $this->getWrapperStorage()->getCache($this->getUnjailedPath($path), $storage);
		return new CacheJail($sourceCache, $this->rootPath);
	}

	/**
	 * get the user id of the owner of a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getOwner($path) {
		return $this->getWrapperStorage()->getOwner($this->getUnjailedPath($path));
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
		return $this->getWrapperStorage()->getWatcher($this->getUnjailedPath($path), $storage);
	}

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function getETag($path) {
		return $this->getWrapperStorage()->getETag($this->getUnjailedPath($path));
	}

	/**
	 * @param string $path
	 * @return array
	 */
	public function getMetaData($path) {
		return $this->getWrapperStorage()->getMetaData($this->getUnjailedPath($path));
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock($path, $type, ILockingProvider $provider) {
		$this->getWrapperStorage()->acquireLock($this->getUnjailedPath($path), $type, $provider);
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function releaseLock($path, $type, ILockingProvider $provider) {
		$this->getWrapperStorage()->releaseLock($this->getUnjailedPath($path), $type, $provider);
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function changeLock($path, $type, ILockingProvider $provider) {
		$this->getWrapperStorage()->changeLock($this->getUnjailedPath($path), $type, $provider);
	}

	/**
	 * Resolve the path for the source of the share
	 *
	 * @param string $path
	 * @return array
	 */
	public function resolvePath($path) {
		return [$this->getWrapperStorage(), $this->getUnjailedPath($path)];
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
		return $this->getWrapperStorage()->copyFromStorage($sourceStorage, $sourceInternalPath, $this->getUnjailedPath($targetInternalPath));
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
		return $this->getWrapperStorage()->moveFromStorage($sourceStorage, $sourceInternalPath, $this->getUnjailedPath($targetInternalPath));
	}

	public function getPropagator($storage = null) {
		if (isset($this->propagator)) {
			return $this->propagator;
		}

		if (!$storage) {
			$storage = $this;
		}
		$this->propagator = new JailPropagator($storage, \OC::$server->getDatabaseConnection());
		return $this->propagator;
	}

	public function writeStream(string $path, $stream, int $size = null): int {
		$storage = $this->getWrapperStorage();
		if ($storage->instanceOfStorage(IWriteStreamStorage::class)) {
			/** @var IWriteStreamStorage $storage */
			return $storage->writeStream($this->getUnjailedPath($path), $stream, $size);
		} else {
			$target = $this->fopen($path, 'w');
			list($count, $result) = \OC_Helper::streamCopy($stream, $target);
			fclose($stream);
			fclose($target);
			return $count;
		}
	}

	public function getDirectoryContent($directory): \Traversable {
		return $this->getWrapperStorage()->getDirectoryContent($this->getUnjailedPath($directory));
	}
}
