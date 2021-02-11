<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Greta Doci <gretadoci@gmail.com>
 * @author hkjolhede <hkjolhede@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roland Tapken <roland@bitarbeiter.net>
 * @author Sam Tuke <mail@samtuke.com>
 * @author scambra <sergio@entrecables.com>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Files\Storage;

use OC\Files\Cache\Cache;
use OC\Files\Cache\Propagator;
use OC\Files\Cache\Scanner;
use OC\Files\Cache\Updater;
use OC\Files\Cache\Watcher;
use OC\Files\Filesystem;
use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Files\EmptyFileNameException;
use OCP\Files\FileNameTooLongException;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidCharacterInPathException;
use OCP\Files\InvalidDirectoryException;
use OCP\Files\InvalidPathException;
use OCP\Files\ReservedWordException;
use OCP\Files\Storage\ILockingStorage;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\ILogger;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * Storage backend class for providing common filesystem operation methods
 * which are not storage-backend specific.
 *
 * \OC\Files\Storage\Common is never used directly; it is extended by all other
 * storage backends, where its methods may be overridden, and additional
 * (backend-specific) methods are defined.
 *
 * Some \OC\Files\Storage\Common methods call functions which are first defined
 * in classes which extend it, e.g. $this->stat() .
 */
abstract class Common implements Storage, ILockingStorage, IWriteStreamStorage {
	use LocalTempFileTrait;

	protected $cache;
	protected $scanner;
	protected $watcher;
	protected $propagator;
	protected $storageCache;
	protected $updater;

	protected $mountOptions = [];
	protected $owner = null;

	private $shouldLogLocks = null;
	private $logger;

	public function __construct($parameters) {
	}

	/**
	 * Remove a file or folder
	 *
	 * @param string $path
	 * @return bool
	 */
	protected function remove($path) {
		if ($this->is_dir($path)) {
			return $this->rmdir($path);
		} elseif ($this->is_file($path)) {
			return $this->unlink($path);
		} else {
			return false;
		}
	}

	public function is_dir($path) {
		return $this->filetype($path) === 'dir';
	}

	public function is_file($path) {
		return $this->filetype($path) === 'file';
	}

	public function filesize($path) {
		if ($this->is_dir($path)) {
			return 0; //by definition
		} else {
			$stat = $this->stat($path);
			if (isset($stat['size'])) {
				return $stat['size'];
			} else {
				return 0;
			}
		}
	}

	public function isReadable($path) {
		// at least check whether it exists
		// subclasses might want to implement this more thoroughly
		return $this->file_exists($path);
	}

	public function isUpdatable($path) {
		// at least check whether it exists
		// subclasses might want to implement this more thoroughly
		// a non-existing file/folder isn't updatable
		return $this->file_exists($path);
	}

	public function isCreatable($path) {
		if ($this->is_dir($path) && $this->isUpdatable($path)) {
			return true;
		}
		return false;
	}

	public function isDeletable($path) {
		if ($path === '' || $path === '/') {
			return false;
		}
		$parent = dirname($path);
		return $this->isUpdatable($parent) && $this->isUpdatable($path);
	}

	public function isSharable($path) {
		return $this->isReadable($path);
	}

	public function getPermissions($path) {
		$permissions = 0;
		if ($this->isCreatable($path)) {
			$permissions |= \OCP\Constants::PERMISSION_CREATE;
		}
		if ($this->isReadable($path)) {
			$permissions |= \OCP\Constants::PERMISSION_READ;
		}
		if ($this->isUpdatable($path)) {
			$permissions |= \OCP\Constants::PERMISSION_UPDATE;
		}
		if ($this->isDeletable($path)) {
			$permissions |= \OCP\Constants::PERMISSION_DELETE;
		}
		if ($this->isSharable($path)) {
			$permissions |= \OCP\Constants::PERMISSION_SHARE;
		}
		return $permissions;
	}

	public function filemtime($path) {
		$stat = $this->stat($path);
		if (isset($stat['mtime']) && $stat['mtime'] > 0) {
			return $stat['mtime'];
		} else {
			return 0;
		}
	}

	public function file_get_contents($path) {
		$handle = $this->fopen($path, "r");
		if (!$handle) {
			return false;
		}
		$data = stream_get_contents($handle);
		fclose($handle);
		return $data;
	}

	public function file_put_contents($path, $data) {
		$handle = $this->fopen($path, "w");
		$this->removeCachedFile($path);
		$count = fwrite($handle, $data);
		fclose($handle);
		return $count;
	}

	public function rename($path1, $path2) {
		$this->remove($path2);

		$this->removeCachedFile($path1);
		return $this->copy($path1, $path2) and $this->remove($path1);
	}

	public function copy($path1, $path2) {
		if ($this->is_dir($path1)) {
			$this->remove($path2);
			$dir = $this->opendir($path1);
			$this->mkdir($path2);
			while ($file = readdir($dir)) {
				if (!Filesystem::isIgnoredDir($file)) {
					if (!$this->copy($path1 . '/' . $file, $path2 . '/' . $file)) {
						return false;
					}
				}
			}
			closedir($dir);
			return true;
		} else {
			$source = $this->fopen($path1, 'r');
			$target = $this->fopen($path2, 'w');
			[, $result] = \OC_Helper::streamCopy($source, $target);
			if (!$result) {
				\OC::$server->getLogger()->warning("Failed to write data while copying $path1 to $path2");
			}
			$this->removeCachedFile($path2);
			return $result;
		}
	}

	public function getMimeType($path) {
		if ($this->is_dir($path)) {
			return 'httpd/unix-directory';
		} elseif ($this->file_exists($path)) {
			return \OC::$server->getMimeTypeDetector()->detectPath($path);
		} else {
			return false;
		}
	}

	public function hash($type, $path, $raw = false) {
		$fh = $this->fopen($path, 'rb');
		$ctx = hash_init($type);
		hash_update_stream($ctx, $fh);
		fclose($fh);
		return hash_final($ctx, $raw);
	}

	public function search($query) {
		return $this->searchInDir($query);
	}

	public function getLocalFile($path) {
		return $this->getCachedFile($path);
	}

	/**
	 * @param string $path
	 * @param string $target
	 */
	private function addLocalFolder($path, $target) {
		$dh = $this->opendir($path);
		if (is_resource($dh)) {
			while (($file = readdir($dh)) !== false) {
				if (!\OC\Files\Filesystem::isIgnoredDir($file)) {
					if ($this->is_dir($path . '/' . $file)) {
						mkdir($target . '/' . $file);
						$this->addLocalFolder($path . '/' . $file, $target . '/' . $file);
					} else {
						$tmp = $this->toTmpFile($path . '/' . $file);
						rename($tmp, $target . '/' . $file);
					}
				}
			}
		}
	}

	/**
	 * @param string $query
	 * @param string $dir
	 * @return array
	 */
	protected function searchInDir($query, $dir = '') {
		$files = [];
		$dh = $this->opendir($dir);
		if (is_resource($dh)) {
			while (($item = readdir($dh)) !== false) {
				if (\OC\Files\Filesystem::isIgnoredDir($item)) {
					continue;
				}
				if (strstr(strtolower($item), strtolower($query)) !== false) {
					$files[] = $dir . '/' . $item;
				}
				if ($this->is_dir($dir . '/' . $item)) {
					$files = array_merge($files, $this->searchInDir($query, $dir . '/' . $item));
				}
			}
		}
		closedir($dh);
		return $files;
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * The method is only used to check if the cache needs to be updated. Storage backends that don't support checking
	 * the mtime should always return false here. As a result storage implementations that always return false expect
	 * exclusive access to the backend and will not pick up files that have been added in a way that circumvents
	 * ownClouds filesystem.
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time) {
		return $this->filemtime($path) > $time;
	}

	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($storage->cache)) {
			$storage->cache = new Cache($storage);
		}
		return $storage->cache;
	}

	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($storage->scanner)) {
			$storage->scanner = new Scanner($storage);
		}
		return $storage->scanner;
	}

	public function getWatcher($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->watcher)) {
			$this->watcher = new Watcher($storage);
			$globalPolicy = \OC::$server->getConfig()->getSystemValue('filesystem_check_changes', Watcher::CHECK_NEVER);
			$this->watcher->setPolicy((int)$this->getMountOption('filesystem_check_changes', $globalPolicy));
		}
		return $this->watcher;
	}

	/**
	 * get a propagator instance for the cache
	 *
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the watcher
	 * @return \OC\Files\Cache\Propagator
	 */
	public function getPropagator($storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($storage->propagator)) {
			$config = \OC::$server->getSystemConfig();
			$storage->propagator = new Propagator($storage, \OC::$server->getDatabaseConnection(), ['appdata_' . $config->getValue('instanceid')]);
		}
		return $storage->propagator;
	}

	public function getUpdater($storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($storage->updater)) {
			$storage->updater = new Updater($storage);
		}
		return $storage->updater;
	}

	public function getStorageCache($storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->storageCache)) {
			$this->storageCache = new \OC\Files\Cache\Storage($storage);
		}
		return $this->storageCache;
	}

	/**
	 * get the owner of a path
	 *
	 * @param string $path The path to get the owner
	 * @return string|false uid or false
	 */
	public function getOwner($path) {
		if ($this->owner === null) {
			$this->owner = \OC_User::getUser();
		}

		return $this->owner;
	}

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getETag($path) {
		return uniqid();
	}

	/**
	 * clean a path, i.e. remove all redundant '.' and '..'
	 * making sure that it can't point to higher than '/'
	 *
	 * @param string $path The path to clean
	 * @return string cleaned path
	 */
	public function cleanPath($path) {
		if (strlen($path) == 0 or $path[0] != '/') {
			$path = '/' . $path;
		}

		$output = [];
		foreach (explode('/', $path) as $chunk) {
			if ($chunk == '..') {
				array_pop($output);
			} elseif ($chunk == '.') {
			} else {
				$output[] = $chunk;
			}
		}
		return implode('/', $output);
	}

	/**
	 * Test a storage for availability
	 *
	 * @return bool
	 */
	public function test() {
		try {
			if ($this->stat('')) {
				return true;
			}
			\OC::$server->getLogger()->info("External storage not available: stat() failed");
			return false;
		} catch (\Exception $e) {
			\OC::$server->getLogger()->warning("External storage not available: " . $e->getMessage());
			\OC::$server->getLogger()->logException($e, ['level' => ILogger::WARN]);
			return false;
		}
	}

	/**
	 * get the free space in the storage
	 *
	 * @param string $path
	 * @return int|false
	 */
	public function free_space($path) {
		return \OCP\Files\FileInfo::SPACE_UNKNOWN;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isLocal() {
		// the common implementation returns a temporary file by
		// default, which is not local
		return false;
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
		return is_a($this, $class);
	}

	/**
	 * A custom storage implementation can return an url for direct download of a give file.
	 *
	 * For now the returned array can hold the parameter url - in future more attributes might follow.
	 *
	 * @param string $path
	 * @return array|false
	 */
	public function getDirectDownload($path) {
		return [];
	}

	/**
	 * @inheritdoc
	 * @throws InvalidPathException
	 */
	public function verifyPath($path, $fileName) {

		// verify empty and dot files
		$trimmed = trim($fileName);
		if ($trimmed === '') {
			throw new EmptyFileNameException();
		}

		if (\OC\Files\Filesystem::isIgnoredDir($trimmed)) {
			throw new InvalidDirectoryException();
		}

		if (!\OC::$server->getDatabaseConnection()->supports4ByteText()) {
			// verify database - e.g. mysql only 3-byte chars
			if (preg_match('%(?:
      \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
    | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
    | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
)%xs', $fileName)) {
				throw new InvalidCharacterInPathException();
			}
		}

		// 255 characters is the limit on common file systems (ext/xfs)
		// oc_filecache has a 250 char length limit for the filename
		if (isset($fileName[250])) {
			throw new FileNameTooLongException();
		}

		// NOTE: $path will remain unverified for now
		$this->verifyPosixPath($fileName);
	}

	/**
	 * @param string $fileName
	 * @throws InvalidPathException
	 */
	protected function verifyPosixPath($fileName) {
		$fileName = trim($fileName);
		$this->scanForInvalidCharacters($fileName, "\\/");
		$reservedNames = ['*'];
		if (in_array($fileName, $reservedNames)) {
			throw new ReservedWordException();
		}
	}

	/**
	 * @param string $fileName
	 * @param string $invalidChars
	 * @throws InvalidPathException
	 */
	private function scanForInvalidCharacters($fileName, $invalidChars) {
		foreach (str_split($invalidChars) as $char) {
			if (strpos($fileName, $char) !== false) {
				throw new InvalidCharacterInPathException();
			}
		}

		$sanitizedFileName = filter_var($fileName, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
		if ($sanitizedFileName !== $fileName) {
			throw new InvalidCharacterInPathException();
		}
	}

	/**
	 * @param array $options
	 */
	public function setMountOptions(array $options) {
		$this->mountOptions = $options;
	}

	/**
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getMountOption($name, $default = null) {
		return isset($this->mountOptions[$name]) ? $this->mountOptions[$name] : $default;
	}

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $preserveMtime
	 * @return bool
	 */
	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false) {
		if ($sourceStorage === $this) {
			return $this->copy($sourceInternalPath, $targetInternalPath);
		}

		if ($sourceStorage->is_dir($sourceInternalPath)) {
			$dh = $sourceStorage->opendir($sourceInternalPath);
			$result = $this->mkdir($targetInternalPath);
			if (is_resource($dh)) {
				while ($result and ($file = readdir($dh)) !== false) {
					if (!Filesystem::isIgnoredDir($file)) {
						$result &= $this->copyFromStorage($sourceStorage, $sourceInternalPath . '/' . $file, $targetInternalPath . '/' . $file);
					}
				}
			}
		} else {
			$source = $sourceStorage->fopen($sourceInternalPath, 'r');
			$result = false;
			if ($source) {
				try {
					$this->writeStream($targetInternalPath, $source);
					$result = true;
				} catch (\Exception $e) {
					\OC::$server->getLogger()->logException($e, ['level' => ILogger::WARN, 'message' => 'Failed to copy stream to storage']);
				}
			}

			if ($result && $preserveMtime) {
				$mtime = $sourceStorage->filemtime($sourceInternalPath);
				$this->touch($targetInternalPath, is_int($mtime) ? $mtime : null);
			}

			if (!$result) {
				// delete partially written target file
				$this->unlink($targetInternalPath);
				// delete cache entry that was created by fopen
				$this->getCache()->remove($targetInternalPath);
			}
		}
		return (bool)$result;
	}

	/**
	 * Check if a storage is the same as the current one, including wrapped storages
	 *
	 * @param IStorage $storage
	 * @return bool
	 */
	private function isSameStorage(IStorage $storage): bool {
		while ($storage->instanceOfStorage(Wrapper::class)) {
			/**
			 * @var Wrapper $sourceStorage
			 */
			$storage = $storage->getWrapperStorage();
		}

		return $storage === $this;
	}

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		if ($this->isSameStorage($sourceStorage)) {
			// resolve any jailed paths
			while ($sourceStorage->instanceOfStorage(Jail::class)) {
				/**
				 * @var Jail $sourceStorage
				 */
				$sourceInternalPath = $sourceStorage->getUnjailedPath($sourceInternalPath);
				$sourceStorage = $sourceStorage->getUnjailedStorage();
			}

			return $this->rename($sourceInternalPath, $targetInternalPath);
		}

		if (!$sourceStorage->isDeletable($sourceInternalPath)) {
			return false;
		}

		$result = $this->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath, true);
		if ($result) {
			if ($sourceStorage->is_dir($sourceInternalPath)) {
				$result = $result && $sourceStorage->rmdir($sourceInternalPath);
			} else {
				$result = $result && $sourceStorage->unlink($sourceInternalPath);
			}
		}
		return $result;
	}

	/**
	 * @inheritdoc
	 */
	public function getMetaData($path) {
		$permissions = $this->getPermissions($path);
		if (!$permissions & \OCP\Constants::PERMISSION_READ) {
			//can't read, nothing we can do
			return null;
		}

		$data = [];
		$data['mimetype'] = $this->getMimeType($path);
		$data['mtime'] = $this->filemtime($path);
		if ($data['mtime'] === false) {
			$data['mtime'] = time();
		}
		if ($data['mimetype'] == 'httpd/unix-directory') {
			$data['size'] = -1; //unknown
		} else {
			$data['size'] = $this->filesize($path);
		}
		$data['etag'] = $this->getETag($path);
		$data['storage_mtime'] = $data['mtime'];
		$data['permissions'] = $permissions;
		$data['name'] = basename($path);

		return $data;
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock($path, $type, ILockingProvider $provider) {
		$logger = $this->getLockLogger();
		if ($logger) {
			$typeString = ($type === ILockingProvider::LOCK_SHARED) ? 'shared' : 'exclusive';
			$logger->info(
				sprintf(
					'acquire %s lock on "%s" on storage "%s"',
					$typeString,
					$path,
					$this->getId()
				),
				[
					'app' => 'locking',
				]
			);
		}
		try {
			$provider->acquireLock('files/' . md5($this->getId() . '::' . trim($path, '/')), $type, $this->getId() . '::' . $path);
		} catch (LockedException $e) {
			if ($logger) {
				$logger->logException($e, ['level' => ILogger::INFO]);
			}
			throw $e;
		}
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function releaseLock($path, $type, ILockingProvider $provider) {
		$logger = $this->getLockLogger();
		if ($logger) {
			$typeString = ($type === ILockingProvider::LOCK_SHARED) ? 'shared' : 'exclusive';
			$logger->info(
				sprintf(
					'release %s lock on "%s" on storage "%s"',
					$typeString,
					$path,
					$this->getId()
				),
				[
					'app' => 'locking',
				]
			);
		}
		try {
			$provider->releaseLock('files/' . md5($this->getId() . '::' . trim($path, '/')), $type);
		} catch (LockedException $e) {
			if ($logger) {
				$logger->logException($e, ['level' => ILogger::INFO]);
			}
			throw $e;
		}
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function changeLock($path, $type, ILockingProvider $provider) {
		$logger = $this->getLockLogger();
		if ($logger) {
			$typeString = ($type === ILockingProvider::LOCK_SHARED) ? 'shared' : 'exclusive';
			$logger->info(
				sprintf(
					'change lock on "%s" to %s on storage "%s"',
					$path,
					$typeString,
					$this->getId()
				),
				[
					'app' => 'locking',
				]
			);
		}
		try {
			$provider->changeLock('files/' . md5($this->getId() . '::' . trim($path, '/')), $type);
		} catch (LockedException $e) {
			\OC::$server->getLogger()->logException($e, ['level' => ILogger::INFO]);
			throw $e;
		}
	}

	private function getLockLogger() {
		if (is_null($this->shouldLogLocks)) {
			$this->shouldLogLocks = \OC::$server->getConfig()->getSystemValue('filelocking.debug', false);
			$this->logger = $this->shouldLogLocks ? \OC::$server->getLogger() : null;
		}
		return $this->logger;
	}

	/**
	 * @return array [ available, last_checked ]
	 */
	public function getAvailability() {
		return $this->getStorageCache()->getAvailability();
	}

	/**
	 * @param bool $isAvailable
	 */
	public function setAvailability($isAvailable) {
		$this->getStorageCache()->setAvailability($isAvailable);
	}

	/**
	 * @return bool
	 */
	public function needsPartFile() {
		return true;
	}

	/**
	 * fallback implementation
	 *
	 * @param string $path
	 * @param resource $stream
	 * @param int $size
	 * @return int
	 */
	public function writeStream(string $path, $stream, int $size = null): int {
		$target = $this->fopen($path, 'w');
		if (!$target) {
			throw new GenericFileException("Failed to open $path for writing");
		}
		try {
			[$count, $result] = \OC_Helper::streamCopy($stream, $target);
			if (!$result) {
				throw new GenericFileException("Failed to copy stream");
			}
		} finally {
			fclose($target);
			fclose($stream);
		}
		return $count;
	}

	public function getDirectoryContent($directory): \Traversable {
		$dh = $this->opendir($directory);
		if (is_resource($dh)) {
			$basePath = rtrim($directory, '/');
			while (($file = readdir($dh)) !== false) {
				if (!Filesystem::isIgnoredDir($file) && !Filesystem::isFileBlacklisted($file)) {
					$childPath = $basePath . '/' . trim($file, '/');
					$metadata = $this->getMetaData($childPath);
					if ($metadata !== null) {
						yield $metadata;
					}
				}
			}
		}
	}
}
