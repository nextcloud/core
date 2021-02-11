<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Ashod Nakashian <ashod.nakashian@collabora.co.uk>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Florin Peter <github@florin-peter.de>
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author karakayasemi <karakayasemi@itu.edu.tr>
 * @author Klaas Freitag <freitag@owncloud.com>
 * @author korelstar <korelstar@users.noreply.github.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Luke Policinski <lpolicinski@gmail.com>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Filiciak <piotr@filiciak.pl>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sam Tuke <mail@samtuke.com>
 * @author Scott Dutton <exussum12@users.noreply.github.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OC\Files;

use Icewind\Streams\CallbackWrapper;
use OC\Files\Mount\MoveableMount;
use OC\Files\Storage\Storage;
use OC\User\User;
use OCA\Files_Sharing\SharedMount;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\EmptyFileNameException;
use OCP\Files\FileNameTooLongException;
use OCP\Files\InvalidCharacterInPathException;
use OCP\Files\InvalidDirectoryException;
use OCP\Files\InvalidPathException;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\ReservedWordException;
use OCP\Files\Storage\IStorage;
use OCP\ILogger;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * Class to provide access to ownCloud filesystem via a "view", and methods for
 * working with files within that view (e.g. read, write, delete, etc.). Each
 * view is restricted to a set of directories via a virtual root. The default view
 * uses the currently logged in user's data directory as root (parts of
 * OC_Filesystem are merely a wrapper for OC\Files\View).
 *
 * Apps that need to access files outside of the user data folders (to modify files
 * belonging to a user other than the one currently logged in, for example) should
 * use this class directly rather than using OC_Filesystem, or making use of PHP's
 * built-in file manipulation functions. This will ensure all hooks and proxies
 * are triggered correctly.
 *
 * Filesystem functions are not called directly; they are passed to the correct
 * \OC\Files\Storage\Storage object
 */
class View {
	/** @var string */
	private $fakeRoot = '';

	/**
	 * @var \OCP\Lock\ILockingProvider
	 */
	protected $lockingProvider;

	private $lockingEnabled;

	private $updaterEnabled = true;

	/** @var \OC\User\Manager */
	private $userManager;

	/** @var \OCP\ILogger */
	private $logger;

	/**
	 * @param string $root
	 * @throws \Exception If $root contains an invalid path
	 */
	public function __construct($root = '') {
		if (is_null($root)) {
			throw new \InvalidArgumentException('Root can\'t be null');
		}
		if (!Filesystem::isValidPath($root)) {
			throw new \Exception();
		}

		$this->fakeRoot = $root;
		$this->lockingProvider = \OC::$server->getLockingProvider();
		$this->lockingEnabled = !($this->lockingProvider instanceof \OC\Lock\NoopLockingProvider);
		$this->userManager = \OC::$server->getUserManager();
		$this->logger = \OC::$server->getLogger();
	}

	public function getAbsolutePath($path = '/') {
		if ($path === null) {
			return null;
		}
		$this->assertPathLength($path);
		if ($path === '') {
			$path = '/';
		}
		if ($path[0] !== '/') {
			$path = '/' . $path;
		}
		return $this->fakeRoot . $path;
	}

	/**
	 * change the root to a fake root
	 *
	 * @param string $fakeRoot
	 * @return boolean|null
	 */
	public function chroot($fakeRoot) {
		if (!$fakeRoot == '') {
			if ($fakeRoot[0] !== '/') {
				$fakeRoot = '/' . $fakeRoot;
			}
		}
		$this->fakeRoot = $fakeRoot;
	}

	/**
	 * get the fake root
	 *
	 * @return string
	 */
	public function getRoot() {
		return $this->fakeRoot;
	}

	/**
	 * get path relative to the root of the view
	 *
	 * @param string $path
	 * @return string
	 */
	public function getRelativePath($path) {
		$this->assertPathLength($path);
		if ($this->fakeRoot == '') {
			return $path;
		}

		if (rtrim($path, '/') === rtrim($this->fakeRoot, '/')) {
			return '/';
		}

		// missing slashes can cause wrong matches!
		$root = rtrim($this->fakeRoot, '/') . '/';

		if (strpos($path, $root) !== 0) {
			return null;
		} else {
			$path = substr($path, strlen($this->fakeRoot));
			if (strlen($path) === 0) {
				return '/';
			} else {
				return $path;
			}
		}
	}

	/**
	 * get the mountpoint of the storage object for a path
	 * ( note: because a storage is not always mounted inside the fakeroot, the
	 * returned mountpoint is relative to the absolute root of the filesystem
	 * and does not take the chroot into account )
	 *
	 * @param string $path
	 * @return string
	 */
	public function getMountPoint($path) {
		return Filesystem::getMountPoint($this->getAbsolutePath($path));
	}

	/**
	 * get the mountpoint of the storage object for a path
	 * ( note: because a storage is not always mounted inside the fakeroot, the
	 * returned mountpoint is relative to the absolute root of the filesystem
	 * and does not take the chroot into account )
	 *
	 * @param string $path
	 * @return \OCP\Files\Mount\IMountPoint
	 */
	public function getMount($path) {
		return Filesystem::getMountManager()->find($this->getAbsolutePath($path));
	}

	/**
	 * resolve a path to a storage and internal path
	 *
	 * @param string $path
	 * @return array an array consisting of the storage and the internal path
	 */
	public function resolvePath($path) {
		$a = $this->getAbsolutePath($path);
		$p = Filesystem::normalizePath($a);
		return Filesystem::resolvePath($p);
	}

	/**
	 * return the path to a local version of the file
	 * we need this because we can't know if a file is stored local or not from
	 * outside the filestorage and for some purposes a local file is needed
	 *
	 * @param string $path
	 * @return string
	 */
	public function getLocalFile($path) {
		$parent = substr($path, 0, strrpos($path, '/'));
		$path = $this->getAbsolutePath($path);
		[$storage, $internalPath] = Filesystem::resolvePath($path);
		if (Filesystem::isValidPath($parent) and $storage) {
			return $storage->getLocalFile($internalPath);
		} else {
			return null;
		}
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public function getLocalFolder($path) {
		$parent = substr($path, 0, strrpos($path, '/'));
		$path = $this->getAbsolutePath($path);
		[$storage, $internalPath] = Filesystem::resolvePath($path);
		if (Filesystem::isValidPath($parent) and $storage) {
			return $storage->getLocalFolder($internalPath);
		} else {
			return null;
		}
	}

	/**
	 * the following functions operate with arguments and return values identical
	 * to those of their PHP built-in equivalents. Mostly they are merely wrappers
	 * for \OC\Files\Storage\Storage via basicOperation().
	 */
	public function mkdir($path) {
		return $this->basicOperation('mkdir', $path, ['create', 'write']);
	}

	/**
	 * remove mount point
	 *
	 * @param \OC\Files\Mount\MoveableMount $mount
	 * @param string $path relative to data/
	 * @return boolean
	 */
	protected function removeMount($mount, $path) {
		if ($mount instanceof MoveableMount) {
			// cut of /user/files to get the relative path to data/user/files
			$pathParts = explode('/', $path, 4);
			$relPath = '/' . $pathParts[3];
			$this->lockFile($relPath, ILockingProvider::LOCK_SHARED, true);
			\OC_Hook::emit(
				Filesystem::CLASSNAME, "umount",
				[Filesystem::signal_param_path => $relPath]
			);
			$this->changeLock($relPath, ILockingProvider::LOCK_EXCLUSIVE, true);
			$result = $mount->removeMount();
			$this->changeLock($relPath, ILockingProvider::LOCK_SHARED, true);
			if ($result) {
				\OC_Hook::emit(
					Filesystem::CLASSNAME, "post_umount",
					[Filesystem::signal_param_path => $relPath]
				);
			}
			$this->unlockFile($relPath, ILockingProvider::LOCK_SHARED, true);
			return $result;
		} else {
			// do not allow deleting the storage's root / the mount point
			// because for some storages it might delete the whole contents
			// but isn't supposed to work that way
			return false;
		}
	}

	public function disableCacheUpdate() {
		$this->updaterEnabled = false;
	}

	public function enableCacheUpdate() {
		$this->updaterEnabled = true;
	}

	protected function writeUpdate(Storage $storage, $internalPath, $time = null) {
		if ($this->updaterEnabled) {
			if (is_null($time)) {
				$time = time();
			}
			$storage->getUpdater()->update($internalPath, $time);
		}
	}

	protected function removeUpdate(Storage $storage, $internalPath) {
		if ($this->updaterEnabled) {
			$storage->getUpdater()->remove($internalPath);
		}
	}

	protected function renameUpdate(Storage $sourceStorage, Storage $targetStorage, $sourceInternalPath, $targetInternalPath) {
		if ($this->updaterEnabled) {
			$targetStorage->getUpdater()->renameFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		}
	}

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function rmdir($path) {
		$absolutePath = $this->getAbsolutePath($path);
		$mount = Filesystem::getMountManager()->find($absolutePath);
		if ($mount->getInternalPath($absolutePath) === '') {
			return $this->removeMount($mount, $absolutePath);
		}
		if ($this->is_dir($path)) {
			$result = $this->basicOperation('rmdir', $path, ['delete']);
		} else {
			$result = false;
		}

		if (!$result && !$this->file_exists($path)) { //clear ghost files from the cache on delete
			$storage = $mount->getStorage();
			$internalPath = $mount->getInternalPath($absolutePath);
			$storage->getUpdater()->remove($internalPath);
		}
		return $result;
	}

	/**
	 * @param string $path
	 * @return resource
	 */
	public function opendir($path) {
		return $this->basicOperation('opendir', $path, ['read']);
	}

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function is_dir($path) {
		if ($path == '/') {
			return true;
		}
		return $this->basicOperation('is_dir', $path);
	}

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function is_file($path) {
		if ($path == '/') {
			return false;
		}
		return $this->basicOperation('is_file', $path);
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function stat($path) {
		return $this->basicOperation('stat', $path);
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function filetype($path) {
		return $this->basicOperation('filetype', $path);
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function filesize($path) {
		return $this->basicOperation('filesize', $path);
	}

	/**
	 * @param string $path
	 * @return bool|mixed
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function readfile($path) {
		$this->assertPathLength($path);
		@ob_end_clean();
		$handle = $this->fopen($path, 'rb');
		if ($handle) {
			$chunkSize = 524288; // 512 kB chunks
			while (!feof($handle)) {
				echo fread($handle, $chunkSize);
				flush();
			}
			fclose($handle);
			return $this->filesize($path);
		}
		return false;
	}

	/**
	 * @param string $path
	 * @param int $from
	 * @param int $to
	 * @return bool|mixed
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\UnseekableException
	 */
	public function readfilePart($path, $from, $to) {
		$this->assertPathLength($path);
		@ob_end_clean();
		$handle = $this->fopen($path, 'rb');
		if ($handle) {
			$chunkSize = 524288; // 512 kB chunks
			$startReading = true;

			if ($from !== 0 && $from !== '0' && fseek($handle, $from) !== 0) {
				// forward file handle via chunked fread because fseek seem to have failed

				$end = $from + 1;
				while (!feof($handle) && ftell($handle) < $end) {
					$len = $from - ftell($handle);
					if ($len > $chunkSize) {
						$len = $chunkSize;
					}
					$result = fread($handle, $len);

					if ($result === false) {
						$startReading = false;
						break;
					}
				}
			}

			if ($startReading) {
				$end = $to + 1;
				while (!feof($handle) && ftell($handle) < $end) {
					$len = $end - ftell($handle);
					if ($len > $chunkSize) {
						$len = $chunkSize;
					}
					echo fread($handle, $len);
					flush();
				}
				return ftell($handle) - $from;
			}

			throw new \OCP\Files\UnseekableException('fseek error');
		}
		return false;
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function isCreatable($path) {
		return $this->basicOperation('isCreatable', $path);
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function isReadable($path) {
		return $this->basicOperation('isReadable', $path);
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function isUpdatable($path) {
		return $this->basicOperation('isUpdatable', $path);
	}

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function isDeletable($path) {
		$absolutePath = $this->getAbsolutePath($path);
		$mount = Filesystem::getMountManager()->find($absolutePath);
		if ($mount->getInternalPath($absolutePath) === '') {
			return $mount instanceof MoveableMount;
		}
		return $this->basicOperation('isDeletable', $path);
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function isSharable($path) {
		return $this->basicOperation('isSharable', $path);
	}

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function file_exists($path) {
		if ($path == '/') {
			return true;
		}
		return $this->basicOperation('file_exists', $path);
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function filemtime($path) {
		return $this->basicOperation('filemtime', $path);
	}

	/**
	 * @param string $path
	 * @param int|string $mtime
	 * @return bool
	 */
	public function touch($path, $mtime = null) {
		if (!is_null($mtime) and !is_numeric($mtime)) {
			$mtime = strtotime($mtime);
		}

		$hooks = ['touch'];

		if (!$this->file_exists($path)) {
			$hooks[] = 'create';
			$hooks[] = 'write';
		}
		try {
			$result = $this->basicOperation('touch', $path, $hooks, $mtime);
		} catch (\Exception $e) {
			$this->logger->logException($e, ['level' => ILogger::INFO, 'message' => 'Error while setting modified time']);
			$result = false;
		}
		if (!$result) {
			// If create file fails because of permissions on external storage like SMB folders,
			// check file exists and return false if not.
			if (!$this->file_exists($path)) {
				return false;
			}
			if (is_null($mtime)) {
				$mtime = time();
			}
			//if native touch fails, we emulate it by changing the mtime in the cache
			$this->putFileInfo($path, ['mtime' => floor($mtime)]);
		}
		return true;
	}

	/**
	 * @param string $path
	 * @return mixed
	 * @throws LockedException
	 */
	public function file_get_contents($path) {
		return $this->basicOperation('file_get_contents', $path, ['read']);
	}

	/**
	 * @param bool $exists
	 * @param string $path
	 * @param bool $run
	 */
	protected function emit_file_hooks_pre($exists, $path, &$run) {
		if (!$exists) {
			\OC_Hook::emit(Filesystem::CLASSNAME, Filesystem::signal_create, [
				Filesystem::signal_param_path => $this->getHookPath($path),
				Filesystem::signal_param_run => &$run,
			]);
		} else {
			\OC_Hook::emit(Filesystem::CLASSNAME, Filesystem::signal_update, [
				Filesystem::signal_param_path => $this->getHookPath($path),
				Filesystem::signal_param_run => &$run,
			]);
		}
		\OC_Hook::emit(Filesystem::CLASSNAME, Filesystem::signal_write, [
			Filesystem::signal_param_path => $this->getHookPath($path),
			Filesystem::signal_param_run => &$run,
		]);
	}

	/**
	 * @param bool $exists
	 * @param string $path
	 */
	protected function emit_file_hooks_post($exists, $path) {
		if (!$exists) {
			\OC_Hook::emit(Filesystem::CLASSNAME, Filesystem::signal_post_create, [
				Filesystem::signal_param_path => $this->getHookPath($path),
			]);
		} else {
			\OC_Hook::emit(Filesystem::CLASSNAME, Filesystem::signal_post_update, [
				Filesystem::signal_param_path => $this->getHookPath($path),
			]);
		}
		\OC_Hook::emit(Filesystem::CLASSNAME, Filesystem::signal_post_write, [
			Filesystem::signal_param_path => $this->getHookPath($path),
		]);
	}

	/**
	 * @param string $path
	 * @param string|resource $data
	 * @return bool|mixed
	 * @throws LockedException
	 */
	public function file_put_contents($path, $data) {
		if (is_resource($data)) { //not having to deal with streams in file_put_contents makes life easier
			$absolutePath = Filesystem::normalizePath($this->getAbsolutePath($path));
			if (Filesystem::isValidPath($path)
				and !Filesystem::isFileBlacklisted($path)
			) {
				$path = $this->getRelativePath($absolutePath);

				$this->lockFile($path, ILockingProvider::LOCK_SHARED);

				$exists = $this->file_exists($path);
				$run = true;
				if ($this->shouldEmitHooks($path)) {
					$this->emit_file_hooks_pre($exists, $path, $run);
				}
				if (!$run) {
					$this->unlockFile($path, ILockingProvider::LOCK_SHARED);
					return false;
				}

				try {
					$this->changeLock($path, ILockingProvider::LOCK_EXCLUSIVE);
				} catch (\Exception $e) {
					// Release the shared lock before throwing.
					$this->unlockFile($path, ILockingProvider::LOCK_SHARED);
					throw $e;
				}

				/** @var \OC\Files\Storage\Storage $storage */
				[$storage, $internalPath] = $this->resolvePath($path);
				$target = $storage->fopen($internalPath, 'w');
				if ($target) {
					[, $result] = \OC_Helper::streamCopy($data, $target);
					fclose($target);
					fclose($data);

					$this->writeUpdate($storage, $internalPath);

					$this->changeLock($path, ILockingProvider::LOCK_SHARED);

					if ($this->shouldEmitHooks($path) && $result !== false) {
						$this->emit_file_hooks_post($exists, $path);
					}
					$this->unlockFile($path, ILockingProvider::LOCK_SHARED);
					return $result;
				} else {
					$this->unlockFile($path, ILockingProvider::LOCK_EXCLUSIVE);
					return false;
				}
			} else {
				return false;
			}
		} else {
			$hooks = $this->file_exists($path) ? ['update', 'write'] : ['create', 'write'];
			return $this->basicOperation('file_put_contents', $path, $hooks, $data);
		}
	}

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function unlink($path) {
		if ($path === '' || $path === '/') {
			// do not allow deleting the root
			return false;
		}
		$postFix = (substr($path, -1) === '/') ? '/' : '';
		$absolutePath = Filesystem::normalizePath($this->getAbsolutePath($path));
		$mount = Filesystem::getMountManager()->find($absolutePath . $postFix);
		if ($mount and $mount->getInternalPath($absolutePath) === '') {
			return $this->removeMount($mount, $absolutePath);
		}
		if ($this->is_dir($path)) {
			$result = $this->basicOperation('rmdir', $path, ['delete']);
		} else {
			$result = $this->basicOperation('unlink', $path, ['delete']);
		}
		if (!$result && !$this->file_exists($path)) { //clear ghost files from the cache on delete
			$storage = $mount->getStorage();
			$internalPath = $mount->getInternalPath($absolutePath);
			$storage->getUpdater()->remove($internalPath);
			return true;
		} else {
			return $result;
		}
	}

	/**
	 * @param string $directory
	 * @return bool|mixed
	 */
	public function deleteAll($directory) {
		return $this->rmdir($directory);
	}

	/**
	 * Rename/move a file or folder from the source path to target path.
	 *
	 * @param string $path1 source path
	 * @param string $path2 target path
	 *
	 * @return bool|mixed
	 * @throws LockedException
	 */
	public function rename($path1, $path2) {
		$absolutePath1 = Filesystem::normalizePath($this->getAbsolutePath($path1));
		$absolutePath2 = Filesystem::normalizePath($this->getAbsolutePath($path2));
		$result = false;
		if (
			Filesystem::isValidPath($path2)
			and Filesystem::isValidPath($path1)
			and !Filesystem::isFileBlacklisted($path2)
		) {
			$path1 = $this->getRelativePath($absolutePath1);
			$path2 = $this->getRelativePath($absolutePath2);
			$exists = $this->file_exists($path2);

			if ($path1 == null or $path2 == null) {
				return false;
			}

			$this->lockFile($path1, ILockingProvider::LOCK_SHARED, true);
			try {
				$this->lockFile($path2, ILockingProvider::LOCK_SHARED, true);

				$run = true;
				if ($this->shouldEmitHooks($path1) && (Cache\Scanner::isPartialFile($path1) && !Cache\Scanner::isPartialFile($path2))) {
					// if it was a rename from a part file to a regular file it was a write and not a rename operation
					$this->emit_file_hooks_pre($exists, $path2, $run);
				} elseif ($this->shouldEmitHooks($path1)) {
					\OC_Hook::emit(
						Filesystem::CLASSNAME, Filesystem::signal_rename,
						[
							Filesystem::signal_param_oldpath => $this->getHookPath($path1),
							Filesystem::signal_param_newpath => $this->getHookPath($path2),
							Filesystem::signal_param_run => &$run
						]
					);
				}
				if ($run) {
					$this->verifyPath(dirname($path2), basename($path2));

					$manager = Filesystem::getMountManager();
					$mount1 = $this->getMount($path1);
					$mount2 = $this->getMount($path2);
					$storage1 = $mount1->getStorage();
					$storage2 = $mount2->getStorage();
					$internalPath1 = $mount1->getInternalPath($absolutePath1);
					$internalPath2 = $mount2->getInternalPath($absolutePath2);

					$this->changeLock($path1, ILockingProvider::LOCK_EXCLUSIVE, true);
					try {
						$this->changeLock($path2, ILockingProvider::LOCK_EXCLUSIVE, true);

						if ($internalPath1 === '') {
							if ($mount1 instanceof MoveableMount) {
								$sourceParentMount = $this->getMount(dirname($path1));
								if ($sourceParentMount === $mount2 && $this->targetIsNotShared($storage2, $internalPath2)) {
									/**
									 * @var \OC\Files\Mount\MountPoint | \OC\Files\Mount\MoveableMount $mount1
									 */
									$sourceMountPoint = $mount1->getMountPoint();
									$result = $mount1->moveMount($absolutePath2);
									$manager->moveMount($sourceMountPoint, $mount1->getMountPoint());
								} else {
									$result = false;
								}
							} else {
								$result = false;
							}
							// moving a file/folder within the same mount point
						} elseif ($storage1 === $storage2) {
							if ($storage1) {
								$result = $storage1->rename($internalPath1, $internalPath2);
							} else {
								$result = false;
							}
							// moving a file/folder between storages (from $storage1 to $storage2)
						} else {
							$result = $storage2->moveFromStorage($storage1, $internalPath1, $internalPath2);
						}

						if ((Cache\Scanner::isPartialFile($path1) && !Cache\Scanner::isPartialFile($path2)) && $result !== false) {
							// if it was a rename from a part file to a regular file it was a write and not a rename operation
							$this->writeUpdate($storage2, $internalPath2);
						} elseif ($result) {
							if ($internalPath1 !== '') { // don't do a cache update for moved mounts
								$this->renameUpdate($storage1, $storage2, $internalPath1, $internalPath2);
							}
						}
					} catch (\Exception $e) {
						throw $e;
					} finally {
						$this->changeLock($path1, ILockingProvider::LOCK_SHARED, true);
						$this->changeLock($path2, ILockingProvider::LOCK_SHARED, true);
					}

					if ((Cache\Scanner::isPartialFile($path1) && !Cache\Scanner::isPartialFile($path2)) && $result !== false) {
						if ($this->shouldEmitHooks()) {
							$this->emit_file_hooks_post($exists, $path2);
						}
					} elseif ($result) {
						if ($this->shouldEmitHooks($path1) and $this->shouldEmitHooks($path2)) {
							\OC_Hook::emit(
								Filesystem::CLASSNAME,
								Filesystem::signal_post_rename,
								[
									Filesystem::signal_param_oldpath => $this->getHookPath($path1),
									Filesystem::signal_param_newpath => $this->getHookPath($path2)
								]
							);
						}
					}
				}
			} catch (\Exception $e) {
				throw $e;
			} finally {
				$this->unlockFile($path1, ILockingProvider::LOCK_SHARED, true);
				$this->unlockFile($path2, ILockingProvider::LOCK_SHARED, true);
			}
		}
		return $result;
	}

	/**
	 * Copy a file/folder from the source path to target path
	 *
	 * @param string $path1 source path
	 * @param string $path2 target path
	 * @param bool $preserveMtime whether to preserve mtime on the copy
	 *
	 * @return bool|mixed
	 */
	public function copy($path1, $path2, $preserveMtime = false) {
		$absolutePath1 = Filesystem::normalizePath($this->getAbsolutePath($path1));
		$absolutePath2 = Filesystem::normalizePath($this->getAbsolutePath($path2));
		$result = false;
		if (
			Filesystem::isValidPath($path2)
			and Filesystem::isValidPath($path1)
			and !Filesystem::isFileBlacklisted($path2)
		) {
			$path1 = $this->getRelativePath($absolutePath1);
			$path2 = $this->getRelativePath($absolutePath2);

			if ($path1 == null or $path2 == null) {
				return false;
			}
			$run = true;

			$this->lockFile($path2, ILockingProvider::LOCK_SHARED);
			$this->lockFile($path1, ILockingProvider::LOCK_SHARED);
			$lockTypePath1 = ILockingProvider::LOCK_SHARED;
			$lockTypePath2 = ILockingProvider::LOCK_SHARED;

			try {
				$exists = $this->file_exists($path2);
				if ($this->shouldEmitHooks()) {
					\OC_Hook::emit(
						Filesystem::CLASSNAME,
						Filesystem::signal_copy,
						[
							Filesystem::signal_param_oldpath => $this->getHookPath($path1),
							Filesystem::signal_param_newpath => $this->getHookPath($path2),
							Filesystem::signal_param_run => &$run
						]
					);
					$this->emit_file_hooks_pre($exists, $path2, $run);
				}
				if ($run) {
					$mount1 = $this->getMount($path1);
					$mount2 = $this->getMount($path2);
					$storage1 = $mount1->getStorage();
					$internalPath1 = $mount1->getInternalPath($absolutePath1);
					$storage2 = $mount2->getStorage();
					$internalPath2 = $mount2->getInternalPath($absolutePath2);

					$this->changeLock($path2, ILockingProvider::LOCK_EXCLUSIVE);
					$lockTypePath2 = ILockingProvider::LOCK_EXCLUSIVE;

					if ($mount1->getMountPoint() == $mount2->getMountPoint()) {
						if ($storage1) {
							$result = $storage1->copy($internalPath1, $internalPath2);
						} else {
							$result = false;
						}
					} else {
						$result = $storage2->copyFromStorage($storage1, $internalPath1, $internalPath2);
					}

					$this->writeUpdate($storage2, $internalPath2);

					$this->changeLock($path2, ILockingProvider::LOCK_SHARED);
					$lockTypePath2 = ILockingProvider::LOCK_SHARED;

					if ($this->shouldEmitHooks() && $result !== false) {
						\OC_Hook::emit(
							Filesystem::CLASSNAME,
							Filesystem::signal_post_copy,
							[
								Filesystem::signal_param_oldpath => $this->getHookPath($path1),
								Filesystem::signal_param_newpath => $this->getHookPath($path2)
							]
						);
						$this->emit_file_hooks_post($exists, $path2);
					}
				}
			} catch (\Exception $e) {
				$this->unlockFile($path2, $lockTypePath2);
				$this->unlockFile($path1, $lockTypePath1);
				throw $e;
			}

			$this->unlockFile($path2, $lockTypePath2);
			$this->unlockFile($path1, $lockTypePath1);
		}
		return $result;
	}

	/**
	 * @param string $path
	 * @param string $mode 'r' or 'w'
	 * @return resource
	 * @throws LockedException
	 */
	public function fopen($path, $mode) {
		$mode = str_replace('b', '', $mode); // the binary flag is a windows only feature which we do not support
		$hooks = [];
		switch ($mode) {
			case 'r':
				$hooks[] = 'read';
				break;
			case 'r+':
			case 'w+':
			case 'x+':
			case 'a+':
				$hooks[] = 'read';
				$hooks[] = 'write';
				break;
			case 'w':
			case 'x':
			case 'a':
				$hooks[] = 'write';
				break;
			default:
				\OCP\Util::writeLog('core', 'invalid mode (' . $mode . ') for ' . $path, ILogger::ERROR);
		}

		if ($mode !== 'r' && $mode !== 'w') {
			\OC::$server->getLogger()->info('Trying to open a file with a mode other than "r" or "w" can cause severe performance issues with some backends');
		}

		return $this->basicOperation('fopen', $path, $hooks, $mode);
	}

	/**
	 * @param string $path
	 * @return bool|string
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function toTmpFile($path) {
		$this->assertPathLength($path);
		if (Filesystem::isValidPath($path)) {
			$source = $this->fopen($path, 'r');
			if ($source) {
				$extension = pathinfo($path, PATHINFO_EXTENSION);
				$tmpFile = \OC::$server->getTempManager()->getTemporaryFile($extension);
				file_put_contents($tmpFile, $source);
				return $tmpFile;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param string $tmpFile
	 * @param string $path
	 * @return bool|mixed
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function fromTmpFile($tmpFile, $path) {
		$this->assertPathLength($path);
		if (Filesystem::isValidPath($path)) {

			// Get directory that the file is going into
			$filePath = dirname($path);

			// Create the directories if any
			if (!$this->file_exists($filePath)) {
				$result = $this->createParentDirectories($filePath);
				if ($result === false) {
					return false;
				}
			}

			$source = fopen($tmpFile, 'r');
			if ($source) {
				$result = $this->file_put_contents($path, $source);
				// $this->file_put_contents() might have already closed
				// the resource, so we check it, before trying to close it
				// to avoid messages in the error log.
				if (is_resource($source)) {
					fclose($source);
				}
				unlink($tmpFile);
				return $result;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}


	/**
	 * @param string $path
	 * @return mixed
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function getMimeType($path) {
		$this->assertPathLength($path);
		return $this->basicOperation('getMimeType', $path);
	}

	/**
	 * @param string $type
	 * @param string $path
	 * @param bool $raw
	 * @return bool|null|string
	 */
	public function hash($type, $path, $raw = false) {
		$postFix = (substr($path, -1) === '/') ? '/' : '';
		$absolutePath = Filesystem::normalizePath($this->getAbsolutePath($path));
		if (Filesystem::isValidPath($path)) {
			$path = $this->getRelativePath($absolutePath);
			if ($path == null) {
				return false;
			}
			if ($this->shouldEmitHooks($path)) {
				\OC_Hook::emit(
					Filesystem::CLASSNAME,
					Filesystem::signal_read,
					[Filesystem::signal_param_path => $this->getHookPath($path)]
				);
			}
			[$storage, $internalPath] = Filesystem::resolvePath($absolutePath . $postFix);
			if ($storage) {
				return $storage->hash($type, $internalPath, $raw);
			}
		}
		return null;
	}

	/**
	 * @param string $path
	 * @return mixed
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function free_space($path = '/') {
		$this->assertPathLength($path);
		$result = $this->basicOperation('free_space', $path);
		if ($result === null) {
			throw new InvalidPathException();
		}
		return $result;
	}

	/**
	 * abstraction layer for basic filesystem functions: wrapper for \OC\Files\Storage\Storage
	 *
	 * @param string $operation
	 * @param string $path
	 * @param array $hooks (optional)
	 * @param mixed $extraParam (optional)
	 * @return mixed
	 * @throws LockedException
	 *
	 * This method takes requests for basic filesystem functions (e.g. reading & writing
	 * files), processes hooks and proxies, sanitises paths, and finally passes them on to
	 * \OC\Files\Storage\Storage for delegation to a storage backend for execution
	 */
	private function basicOperation($operation, $path, $hooks = [], $extraParam = null) {
		$postFix = (substr($path, -1) === '/') ? '/' : '';
		$absolutePath = Filesystem::normalizePath($this->getAbsolutePath($path));
		if (Filesystem::isValidPath($path)
			and !Filesystem::isFileBlacklisted($path)
		) {
			$path = $this->getRelativePath($absolutePath);
			if ($path == null) {
				return false;
			}

			if (in_array('write', $hooks) || in_array('delete', $hooks) || in_array('read', $hooks)) {
				// always a shared lock during pre-hooks so the hook can read the file
				$this->lockFile($path, ILockingProvider::LOCK_SHARED);
			}

			$run = $this->runHooks($hooks, $path);
			/** @var \OC\Files\Storage\Storage $storage */
			[$storage, $internalPath] = Filesystem::resolvePath($absolutePath . $postFix);
			if ($run and $storage) {
				if (in_array('write', $hooks) || in_array('delete', $hooks)) {
					try {
						$this->changeLock($path, ILockingProvider::LOCK_EXCLUSIVE);
					} catch (LockedException $e) {
						// release the shared lock we acquired before quiting
						$this->unlockFile($path, ILockingProvider::LOCK_SHARED);
						throw $e;
					}
				}
				try {
					if (!is_null($extraParam)) {
						$result = $storage->$operation($internalPath, $extraParam);
					} else {
						$result = $storage->$operation($internalPath);
					}
				} catch (\Exception $e) {
					if (in_array('write', $hooks) || in_array('delete', $hooks)) {
						$this->unlockFile($path, ILockingProvider::LOCK_EXCLUSIVE);
					} elseif (in_array('read', $hooks)) {
						$this->unlockFile($path, ILockingProvider::LOCK_SHARED);
					}
					throw $e;
				}

				if ($result && in_array('delete', $hooks) and $result) {
					$this->removeUpdate($storage, $internalPath);
				}
				if ($result && in_array('write', $hooks,  true) && $operation !== 'fopen' && $operation !== 'touch') {
					$this->writeUpdate($storage, $internalPath);
				}
				if ($result && in_array('touch', $hooks)) {
					$this->writeUpdate($storage, $internalPath, $extraParam);
				}

				if ((in_array('write', $hooks) || in_array('delete', $hooks)) && ($operation !== 'fopen' || $result === false)) {
					$this->changeLock($path, ILockingProvider::LOCK_SHARED);
				}

				$unlockLater = false;
				if ($this->lockingEnabled && $operation === 'fopen' && is_resource($result)) {
					$unlockLater = true;
					// make sure our unlocking callback will still be called if connection is aborted
					ignore_user_abort(true);
					$result = CallbackWrapper::wrap($result, null, null, function () use ($hooks, $path) {
						if (in_array('write', $hooks)) {
							$this->unlockFile($path, ILockingProvider::LOCK_EXCLUSIVE);
						} elseif (in_array('read', $hooks)) {
							$this->unlockFile($path, ILockingProvider::LOCK_SHARED);
						}
					});
				}

				if ($this->shouldEmitHooks($path) && $result !== false) {
					if ($operation != 'fopen') { //no post hooks for fopen, the file stream is still open
						$this->runHooks($hooks, $path, true);
					}
				}

				if (!$unlockLater
					&& (in_array('write', $hooks) || in_array('delete', $hooks) || in_array('read', $hooks))
				) {
					$this->unlockFile($path, ILockingProvider::LOCK_SHARED);
				}
				return $result;
			} else {
				$this->unlockFile($path, ILockingProvider::LOCK_SHARED);
			}
		}
		return null;
	}

	/**
	 * get the path relative to the default root for hook usage
	 *
	 * @param string $path
	 * @return string
	 */
	private function getHookPath($path) {
		if (!Filesystem::getView()) {
			return $path;
		}
		return Filesystem::getView()->getRelativePath($this->getAbsolutePath($path));
	}

	private function shouldEmitHooks($path = '') {
		if ($path && Cache\Scanner::isPartialFile($path)) {
			return false;
		}
		if (!Filesystem::$loaded) {
			return false;
		}
		$defaultRoot = Filesystem::getRoot();
		if ($defaultRoot === null) {
			return false;
		}
		if ($this->fakeRoot === $defaultRoot) {
			return true;
		}
		$fullPath = $this->getAbsolutePath($path);

		if ($fullPath === $defaultRoot) {
			return true;
		}

		return (strlen($fullPath) > strlen($defaultRoot)) && (substr($fullPath, 0, strlen($defaultRoot) + 1) === $defaultRoot . '/');
	}

	/**
	 * @param string[] $hooks
	 * @param string $path
	 * @param bool $post
	 * @return bool
	 */
	private function runHooks($hooks, $path, $post = false) {
		$relativePath = $path;
		$path = $this->getHookPath($path);
		$prefix = $post ? 'post_' : '';
		$run = true;
		if ($this->shouldEmitHooks($relativePath)) {
			foreach ($hooks as $hook) {
				if ($hook != 'read') {
					\OC_Hook::emit(
						Filesystem::CLASSNAME,
						$prefix . $hook,
						[
							Filesystem::signal_param_run => &$run,
							Filesystem::signal_param_path => $path
						]
					);
				} elseif (!$post) {
					\OC_Hook::emit(
						Filesystem::CLASSNAME,
						$prefix . $hook,
						[
							Filesystem::signal_param_path => $path
						]
					);
				}
			}
		}
		return $run;
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time) {
		return $this->basicOperation('hasUpdated', $path, [], $time);
	}

	/**
	 * @param string $ownerId
	 * @return \OC\User\User
	 */
	private function getUserObjectForOwner($ownerId) {
		$owner = $this->userManager->get($ownerId);
		if ($owner instanceof IUser) {
			return $owner;
		} else {
			return new User($ownerId, null, \OC::$server->getEventDispatcher());
		}
	}

	/**
	 * Get file info from cache
	 *
	 * If the file is not in cached it will be scanned
	 * If the file has changed on storage the cache will be updated
	 *
	 * @param \OC\Files\Storage\Storage $storage
	 * @param string $internalPath
	 * @param string $relativePath
	 * @return ICacheEntry|bool
	 */
	private function getCacheEntry($storage, $internalPath, $relativePath) {
		$cache = $storage->getCache($internalPath);
		$data = $cache->get($internalPath);
		$watcher = $storage->getWatcher($internalPath);

		try {
			// if the file is not in the cache or needs to be updated, trigger the scanner and reload the data
			if (!$data || $data['size'] === -1) {
				if (!$storage->file_exists($internalPath)) {
					return false;
				}
				// don't need to get a lock here since the scanner does it's own locking
				$scanner = $storage->getScanner($internalPath);
				$scanner->scan($internalPath, Cache\Scanner::SCAN_SHALLOW);
				$data = $cache->get($internalPath);
			} elseif (!Cache\Scanner::isPartialFile($internalPath) && $watcher->needsUpdate($internalPath, $data)) {
				$this->lockFile($relativePath, ILockingProvider::LOCK_SHARED);
				$watcher->update($internalPath, $data);
				$storage->getPropagator()->propagateChange($internalPath, time());
				$data = $cache->get($internalPath);
				$this->unlockFile($relativePath, ILockingProvider::LOCK_SHARED);
			}
		} catch (LockedException $e) {
			// if the file is locked we just use the old cache info
		}

		return $data;
	}

	/**
	 * get the filesystem info
	 *
	 * @param string $path
	 * @param boolean|string $includeMountPoints true to add mountpoint sizes,
	 * 'ext' to add only ext storage mount point sizes. Defaults to true.
	 * defaults to true
	 * @return \OC\Files\FileInfo|false False if file does not exist
	 */
	public function getFileInfo($path, $includeMountPoints = true) {
		$this->assertPathLength($path);
		if (!Filesystem::isValidPath($path)) {
			return false;
		}
		if (Cache\Scanner::isPartialFile($path)) {
			return $this->getPartFileInfo($path);
		}
		$relativePath = $path;
		$path = Filesystem::normalizePath($this->fakeRoot . '/' . $path);

		$mount = Filesystem::getMountManager()->find($path);
		if (!$mount) {
			\OC::$server->getLogger()->warning('Mountpoint not found for path: ' . $path);
			return false;
		}
		$storage = $mount->getStorage();
		$internalPath = $mount->getInternalPath($path);
		if ($storage) {
			$data = $this->getCacheEntry($storage, $internalPath, $relativePath);

			if (!$data instanceof ICacheEntry) {
				return false;
			}

			if ($mount instanceof MoveableMount && $internalPath === '') {
				$data['permissions'] |= \OCP\Constants::PERMISSION_DELETE;
			}
			$ownerId = $storage->getOwner($internalPath);
			$owner = null;
			if ($ownerId !== null && $ownerId !== false) {
				// ownerId might be null if files are accessed with an access token without file system access
				$owner = $this->getUserObjectForOwner($ownerId);
			}
			$info = new FileInfo($path, $storage, $internalPath, $data, $mount, $owner);

			if ($data and isset($data['fileid'])) {
				if ($includeMountPoints and $data['mimetype'] === 'httpd/unix-directory') {
					//add the sizes of other mount points to the folder
					$extOnly = ($includeMountPoints === 'ext');
					$mounts = Filesystem::getMountManager()->findIn($path);
					$info->setSubMounts(array_filter($mounts, function (IMountPoint $mount) use ($extOnly) {
						$subStorage = $mount->getStorage();
						return !($extOnly && $subStorage instanceof \OCA\Files_Sharing\SharedStorage);
					}));
				}
			}

			return $info;
		} else {
			\OC::$server->getLogger()->warning('Storage not valid for mountpoint: ' . $mount->getMountPoint());
		}

		return false;
	}

	/**
	 * get the content of a directory
	 *
	 * @param string $directory path under datadirectory
	 * @param string $mimetype_filter limit returned content to this mimetype or mimepart
	 * @return FileInfo[]
	 */
	public function getDirectoryContent($directory, $mimetype_filter = '') {
		$this->assertPathLength($directory);
		if (!Filesystem::isValidPath($directory)) {
			return [];
		}
		$path = $this->getAbsolutePath($directory);
		$path = Filesystem::normalizePath($path);
		$mount = $this->getMount($directory);
		if (!$mount) {
			return [];
		}
		$storage = $mount->getStorage();
		$internalPath = $mount->getInternalPath($path);
		if ($storage) {
			$cache = $storage->getCache($internalPath);
			$user = \OC_User::getUser();

			$data = $this->getCacheEntry($storage, $internalPath, $directory);

			if (!$data instanceof ICacheEntry || !isset($data['fileid']) || !($data->getPermissions() && Constants::PERMISSION_READ)) {
				return [];
			}

			$folderId = $data['fileid'];
			$contents = $cache->getFolderContentsById($folderId); //TODO: mimetype_filter

			$sharingDisabled = \OCP\Util::isSharingDisabledForUser();

			$fileNames = array_map(function (ICacheEntry $content) {
				return $content->getName();
			}, $contents);
			/**
			 * @var \OC\Files\FileInfo[] $fileInfos
			 */
			$fileInfos = array_map(function (ICacheEntry $content) use ($path, $storage, $mount, $sharingDisabled) {
				if ($sharingDisabled) {
					$content['permissions'] = $content['permissions'] & ~\OCP\Constants::PERMISSION_SHARE;
				}
				$owner = $this->getUserObjectForOwner($storage->getOwner($content['path']));
				return new FileInfo($path . '/' . $content['name'], $storage, $content['path'], $content, $mount, $owner);
			}, $contents);
			$files = array_combine($fileNames, $fileInfos);

			//add a folder for any mountpoint in this directory and add the sizes of other mountpoints to the folders
			$mounts = Filesystem::getMountManager()->findIn($path);
			$dirLength = strlen($path);
			foreach ($mounts as $mount) {
				$mountPoint = $mount->getMountPoint();
				$subStorage = $mount->getStorage();
				if ($subStorage) {
					$subCache = $subStorage->getCache('');

					$rootEntry = $subCache->get('');
					if (!$rootEntry) {
						$subScanner = $subStorage->getScanner('');
						try {
							$subScanner->scanFile('');
						} catch (\OCP\Files\StorageNotAvailableException $e) {
							continue;
						} catch (\OCP\Files\StorageInvalidException $e) {
							continue;
						} catch (\Exception $e) {
							// sometimes when the storage is not available it can be any exception
							\OC::$server->getLogger()->logException($e, [
								'message' => 'Exception while scanning storage "' . $subStorage->getId() . '"',
								'level' => ILogger::ERROR,
								'app' => 'lib',
							]);
							continue;
						}
						$rootEntry = $subCache->get('');
					}

					if ($rootEntry && ($rootEntry->getPermissions() && Constants::PERMISSION_READ)) {
						$relativePath = trim(substr($mountPoint, $dirLength), '/');
						if ($pos = strpos($relativePath, '/')) {
							//mountpoint inside subfolder add size to the correct folder
							$entryName = substr($relativePath, 0, $pos);
							foreach ($files as &$entry) {
								if ($entry->getName() === $entryName) {
									$entry->addSubEntry($rootEntry, $mountPoint);
								}
							}
						} else { //mountpoint in this folder, add an entry for it
							$rootEntry['name'] = $relativePath;
							$rootEntry['type'] = $rootEntry['mimetype'] === 'httpd/unix-directory' ? 'dir' : 'file';
							$permissions = $rootEntry['permissions'];
							// do not allow renaming/deleting the mount point if they are not shared files/folders
							// for shared files/folders we use the permissions given by the owner
							if ($mount instanceof MoveableMount) {
								$rootEntry['permissions'] = $permissions | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE;
							} else {
								$rootEntry['permissions'] = $permissions & (\OCP\Constants::PERMISSION_ALL - (\OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE));
							}

							$rootEntry['path'] = substr(Filesystem::normalizePath($path . '/' . $rootEntry['name']), strlen($user) + 2); // full path without /$user/

							// if sharing was disabled for the user we remove the share permissions
							if (\OCP\Util::isSharingDisabledForUser()) {
								$rootEntry['permissions'] = $rootEntry['permissions'] & ~\OCP\Constants::PERMISSION_SHARE;
							}

							$owner = $this->getUserObjectForOwner($subStorage->getOwner(''));
							$files[$rootEntry->getName()] = new FileInfo($path . '/' . $rootEntry['name'], $subStorage, '', $rootEntry, $mount, $owner);
						}
					}
				}
			}

			if ($mimetype_filter) {
				$files = array_filter($files, function (FileInfo $file) use ($mimetype_filter) {
					if (strpos($mimetype_filter, '/')) {
						return $file->getMimetype() === $mimetype_filter;
					} else {
						return $file->getMimePart() === $mimetype_filter;
					}
				});
			}

			return array_values($files);
		} else {
			return [];
		}
	}

	/**
	 * change file metadata
	 *
	 * @param string $path
	 * @param array|\OCP\Files\FileInfo $data
	 * @return int
	 *
	 * returns the fileid of the updated file
	 */
	public function putFileInfo($path, $data) {
		$this->assertPathLength($path);
		if ($data instanceof FileInfo) {
			$data = $data->getData();
		}
		$path = Filesystem::normalizePath($this->fakeRoot . '/' . $path);
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 * @var string $internalPath
		 */
		[$storage, $internalPath] = Filesystem::resolvePath($path);
		if ($storage) {
			$cache = $storage->getCache($path);

			if (!$cache->inCache($internalPath)) {
				$scanner = $storage->getScanner($internalPath);
				$scanner->scan($internalPath, Cache\Scanner::SCAN_SHALLOW);
			}

			return $cache->put($internalPath, $data);
		} else {
			return -1;
		}
	}

	/**
	 * search for files with the name matching $query
	 *
	 * @param string $query
	 * @return FileInfo[]
	 */
	public function search($query) {
		return $this->searchCommon('search', ['%' . $query . '%']);
	}

	/**
	 * search for files with the name matching $query
	 *
	 * @param string $query
	 * @return FileInfo[]
	 */
	public function searchRaw($query) {
		return $this->searchCommon('search', [$query]);
	}

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype
	 * @return FileInfo[]
	 */
	public function searchByMime($mimetype) {
		return $this->searchCommon('searchByMime', [$mimetype]);
	}

	/**
	 * search for files by tag
	 *
	 * @param string|int $tag name or tag id
	 * @param string $userId owner of the tags
	 * @return FileInfo[]
	 */
	public function searchByTag($tag, $userId) {
		return $this->searchCommon('searchByTag', [$tag, $userId]);
	}

	/**
	 * @param string $method cache method
	 * @param array $args
	 * @return FileInfo[]
	 */
	private function searchCommon($method, $args) {
		$files = [];
		$rootLength = strlen($this->fakeRoot);

		$mount = $this->getMount('');
		$mountPoint = $mount->getMountPoint();
		$storage = $mount->getStorage();
		if ($storage) {
			$cache = $storage->getCache('');

			$results = call_user_func_array([$cache, $method], $args);
			foreach ($results as $result) {
				if (substr($mountPoint . $result['path'], 0, $rootLength + 1) === $this->fakeRoot . '/') {
					$internalPath = $result['path'];
					$path = $mountPoint . $result['path'];
					$result['path'] = substr($mountPoint . $result['path'], $rootLength);
					$owner = \OC::$server->getUserManager()->get($storage->getOwner($internalPath));
					$files[] = new FileInfo($path, $storage, $internalPath, $result, $mount, $owner);
				}
			}

			$mounts = Filesystem::getMountManager()->findIn($this->fakeRoot);
			foreach ($mounts as $mount) {
				$mountPoint = $mount->getMountPoint();
				$storage = $mount->getStorage();
				if ($storage) {
					$cache = $storage->getCache('');

					$relativeMountPoint = substr($mountPoint, $rootLength);
					$results = call_user_func_array([$cache, $method], $args);
					if ($results) {
						foreach ($results as $result) {
							$internalPath = $result['path'];
							$result['path'] = rtrim($relativeMountPoint . $result['path'], '/');
							$path = rtrim($mountPoint . $internalPath, '/');
							$owner = \OC::$server->getUserManager()->get($storage->getOwner($internalPath));
							$files[] = new FileInfo($path, $storage, $internalPath, $result, $mount, $owner);
						}
					}
				}
			}
		}
		return $files;
	}

	/**
	 * Get the owner for a file or folder
	 *
	 * @param string $path
	 * @return string the user id of the owner
	 * @throws NotFoundException
	 */
	public function getOwner($path) {
		$info = $this->getFileInfo($path);
		if (!$info) {
			throw new NotFoundException($path . ' not found while trying to get owner');
		}

		if ($info->getOwner() === null) {
			throw new NotFoundException($path . ' has no owner');
		}

		return $info->getOwner()->getUID();
	}

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getETag($path) {
		/**
		 * @var Storage\Storage $storage
		 * @var string $internalPath
		 */
		[$storage, $internalPath] = $this->resolvePath($path);
		if ($storage) {
			return $storage->getETag($internalPath);
		} else {
			return null;
		}
	}

	/**
	 * Get the path of a file by id, relative to the view
	 *
	 * Note that the resulting path is not guarantied to be unique for the id, multiple paths can point to the same file
	 *
	 * @param int $id
	 * @param int|null $storageId
	 * @return string
	 * @throws NotFoundException
	 */
	public function getPath($id, int $storageId = null) {
		$id = (int)$id;
		$manager = Filesystem::getMountManager();
		$mounts = $manager->findIn($this->fakeRoot);
		$mounts[] = $manager->find($this->fakeRoot);
		// reverse the array so we start with the storage this view is in
		// which is the most likely to contain the file we're looking for
		$mounts = array_reverse($mounts);

		// put non shared mounts in front of the shared mount
		// this prevent unneeded recursion into shares
		usort($mounts, function (IMountPoint $a, IMountPoint $b) {
			return $a instanceof SharedMount && (!$b instanceof SharedMount) ? 1 : -1;
		});

		if (!is_null($storageId)) {
			$mounts = array_filter($mounts, function (IMountPoint $mount) use ($storageId) {
				return $mount->getNumericStorageId() === $storageId;
			});
		}

		foreach ($mounts as $mount) {
			/**
			 * @var \OC\Files\Mount\MountPoint $mount
			 */
			if ($mount->getStorage()) {
				$cache = $mount->getStorage()->getCache();
				$internalPath = $cache->getPathById($id);
				if (is_string($internalPath)) {
					$fullPath = $mount->getMountPoint() . $internalPath;
					if (!is_null($path = $this->getRelativePath($fullPath))) {
						return $path;
					}
				}
			}
		}
		throw new NotFoundException(sprintf('File with id "%s" has not been found.', $id));
	}

	/**
	 * @param string $path
	 * @throws InvalidPathException
	 */
	private function assertPathLength($path) {
		$maxLen = min(PHP_MAXPATHLEN, 4000);
		// Check for the string length - performed using isset() instead of strlen()
		// because isset() is about 5x-40x faster.
		if (isset($path[$maxLen])) {
			$pathLen = strlen($path);
			throw new \OCP\Files\InvalidPathException("Path length($pathLen) exceeds max path length($maxLen): $path");
		}
	}

	/**
	 * check if it is allowed to move a mount point to a given target.
	 * It is not allowed to move a mount point into a different mount point or
	 * into an already shared folder
	 *
	 * @param IStorage $targetStorage
	 * @param string $targetInternalPath
	 * @return boolean
	 */
	private function targetIsNotShared(IStorage $targetStorage, string $targetInternalPath) {

		// note: cannot use the view because the target is already locked
		$fileId = (int)$targetStorage->getCache()->getId($targetInternalPath);
		if ($fileId === -1) {
			// target might not exist, need to check parent instead
			$fileId = (int)$targetStorage->getCache()->getId(dirname($targetInternalPath));
		}

		// check if any of the parents were shared by the current owner (include collections)
		$shares = \OCP\Share::getItemShared(
			'folder',
			$fileId,
			\OCP\Share::FORMAT_NONE,
			null,
			true
		);

		if (count($shares) > 0) {
			\OCP\Util::writeLog('files',
				'It is not allowed to move one mount point into a shared folder',
				ILogger::DEBUG);
			return false;
		}

		return true;
	}

	/**
	 * Get a fileinfo object for files that are ignored in the cache (part files)
	 *
	 * @param string $path
	 * @return \OCP\Files\FileInfo
	 */
	private function getPartFileInfo($path) {
		$mount = $this->getMount($path);
		$storage = $mount->getStorage();
		$internalPath = $mount->getInternalPath($this->getAbsolutePath($path));
		$owner = \OC::$server->getUserManager()->get($storage->getOwner($internalPath));
		return new FileInfo(
			$this->getAbsolutePath($path),
			$storage,
			$internalPath,
			[
				'fileid' => null,
				'mimetype' => $storage->getMimeType($internalPath),
				'name' => basename($path),
				'etag' => null,
				'size' => $storage->filesize($internalPath),
				'mtime' => $storage->filemtime($internalPath),
				'encrypted' => false,
				'permissions' => \OCP\Constants::PERMISSION_ALL
			],
			$mount,
			$owner
		);
	}

	/**
	 * @param string $path
	 * @param string $fileName
	 * @throws InvalidPathException
	 */
	public function verifyPath($path, $fileName) {
		try {
			/** @type \OCP\Files\Storage $storage */
			[$storage, $internalPath] = $this->resolvePath($path);
			$storage->verifyPath($internalPath, $fileName);
		} catch (ReservedWordException $ex) {
			$l = \OC::$server->getL10N('lib');
			throw new InvalidPathException($l->t('File name is a reserved word'));
		} catch (InvalidCharacterInPathException $ex) {
			$l = \OC::$server->getL10N('lib');
			throw new InvalidPathException($l->t('File name contains at least one invalid character'));
		} catch (FileNameTooLongException $ex) {
			$l = \OC::$server->getL10N('lib');
			throw new InvalidPathException($l->t('File name is too long'));
		} catch (InvalidDirectoryException $ex) {
			$l = \OC::$server->getL10N('lib');
			throw new InvalidPathException($l->t('Dot files are not allowed'));
		} catch (EmptyFileNameException $ex) {
			$l = \OC::$server->getL10N('lib');
			throw new InvalidPathException($l->t('Empty filename is not allowed'));
		}
	}

	/**
	 * get all parent folders of $path
	 *
	 * @param string $path
	 * @return string[]
	 */
	private function getParents($path) {
		$path = trim($path, '/');
		if (!$path) {
			return [];
		}

		$parts = explode('/', $path);

		// remove the single file
		array_pop($parts);
		$result = ['/'];
		$resultPath = '';
		foreach ($parts as $part) {
			if ($part) {
				$resultPath .= '/' . $part;
				$result[] = $resultPath;
			}
		}
		return $result;
	}

	/**
	 * Returns the mount point for which to lock
	 *
	 * @param string $absolutePath absolute path
	 * @param bool $useParentMount true to return parent mount instead of whatever
	 * is mounted directly on the given path, false otherwise
	 * @return \OC\Files\Mount\MountPoint mount point for which to apply locks
	 */
	private function getMountForLock($absolutePath, $useParentMount = false) {
		$results = [];
		$mount = Filesystem::getMountManager()->find($absolutePath);
		if (!$mount) {
			return $results;
		}

		if ($useParentMount) {
			// find out if something is mounted directly on the path
			$internalPath = $mount->getInternalPath($absolutePath);
			if ($internalPath === '') {
				// resolve the parent mount instead
				$mount = Filesystem::getMountManager()->find(dirname($absolutePath));
			}
		}

		return $mount;
	}

	/**
	 * Lock the given path
	 *
	 * @param string $path the path of the file to lock, relative to the view
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param bool $lockMountPoint true to lock the mount point, false to lock the attached mount/storage
	 *
	 * @return bool False if the path is excluded from locking, true otherwise
	 * @throws LockedException if the path is already locked
	 */
	private function lockPath($path, $type, $lockMountPoint = false) {
		$absolutePath = $this->getAbsolutePath($path);
		$absolutePath = Filesystem::normalizePath($absolutePath);
		if (!$this->shouldLockFile($absolutePath)) {
			return false;
		}

		$mount = $this->getMountForLock($absolutePath, $lockMountPoint);
		if ($mount) {
			try {
				$storage = $mount->getStorage();
				if ($storage && $storage->instanceOfStorage('\OCP\Files\Storage\ILockingStorage')) {
					$storage->acquireLock(
						$mount->getInternalPath($absolutePath),
						$type,
						$this->lockingProvider
					);
				}
			} catch (LockedException $e) {
				// rethrow with the a human-readable path
				throw new LockedException(
					$this->getPathRelativeToFiles($absolutePath),
					$e,
					$e->getExistingLock()
				);
			}
		}

		return true;
	}

	/**
	 * Change the lock type
	 *
	 * @param string $path the path of the file to lock, relative to the view
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param bool $lockMountPoint true to lock the mount point, false to lock the attached mount/storage
	 *
	 * @return bool False if the path is excluded from locking, true otherwise
	 * @throws LockedException if the path is already locked
	 */
	public function changeLock($path, $type, $lockMountPoint = false) {
		$path = Filesystem::normalizePath($path);
		$absolutePath = $this->getAbsolutePath($path);
		$absolutePath = Filesystem::normalizePath($absolutePath);
		if (!$this->shouldLockFile($absolutePath)) {
			return false;
		}

		$mount = $this->getMountForLock($absolutePath, $lockMountPoint);
		if ($mount) {
			try {
				$storage = $mount->getStorage();
				if ($storage && $storage->instanceOfStorage('\OCP\Files\Storage\ILockingStorage')) {
					$storage->changeLock(
						$mount->getInternalPath($absolutePath),
						$type,
						$this->lockingProvider
					);
				}
			} catch (LockedException $e) {
				try {
					// rethrow with the a human-readable path
					throw new LockedException(
						$this->getPathRelativeToFiles($absolutePath),
						$e,
						$e->getExistingLock()
					);
				} catch (\InvalidArgumentException $ex) {
					throw new LockedException(
						$absolutePath,
						$ex,
						$e->getExistingLock()
					);
				}
			}
		}

		return true;
	}

	/**
	 * Unlock the given path
	 *
	 * @param string $path the path of the file to unlock, relative to the view
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param bool $lockMountPoint true to lock the mount point, false to lock the attached mount/storage
	 *
	 * @return bool False if the path is excluded from locking, true otherwise
	 * @throws LockedException
	 */
	private function unlockPath($path, $type, $lockMountPoint = false) {
		$absolutePath = $this->getAbsolutePath($path);
		$absolutePath = Filesystem::normalizePath($absolutePath);
		if (!$this->shouldLockFile($absolutePath)) {
			return false;
		}

		$mount = $this->getMountForLock($absolutePath, $lockMountPoint);
		if ($mount) {
			$storage = $mount->getStorage();
			if ($storage && $storage->instanceOfStorage('\OCP\Files\Storage\ILockingStorage')) {
				$storage->releaseLock(
					$mount->getInternalPath($absolutePath),
					$type,
					$this->lockingProvider
				);
			}
		}

		return true;
	}

	/**
	 * Lock a path and all its parents up to the root of the view
	 *
	 * @param string $path the path of the file to lock relative to the view
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param bool $lockMountPoint true to lock the mount point, false to lock the attached mount/storage
	 *
	 * @return bool False if the path is excluded from locking, true otherwise
	 * @throws LockedException
	 */
	public function lockFile($path, $type, $lockMountPoint = false) {
		$absolutePath = $this->getAbsolutePath($path);
		$absolutePath = Filesystem::normalizePath($absolutePath);
		if (!$this->shouldLockFile($absolutePath)) {
			return false;
		}

		$this->lockPath($path, $type, $lockMountPoint);

		$parents = $this->getParents($path);
		foreach ($parents as $parent) {
			$this->lockPath($parent, ILockingProvider::LOCK_SHARED);
		}

		return true;
	}

	/**
	 * Unlock a path and all its parents up to the root of the view
	 *
	 * @param string $path the path of the file to lock relative to the view
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param bool $lockMountPoint true to lock the mount point, false to lock the attached mount/storage
	 *
	 * @return bool False if the path is excluded from locking, true otherwise
	 * @throws LockedException
	 */
	public function unlockFile($path, $type, $lockMountPoint = false) {
		$absolutePath = $this->getAbsolutePath($path);
		$absolutePath = Filesystem::normalizePath($absolutePath);
		if (!$this->shouldLockFile($absolutePath)) {
			return false;
		}

		$this->unlockPath($path, $type, $lockMountPoint);

		$parents = $this->getParents($path);
		foreach ($parents as $parent) {
			$this->unlockPath($parent, ILockingProvider::LOCK_SHARED);
		}

		return true;
	}

	/**
	 * Only lock files in data/user/files/
	 *
	 * @param string $path Absolute path to the file/folder we try to (un)lock
	 * @return bool
	 */
	protected function shouldLockFile($path) {
		$path = Filesystem::normalizePath($path);

		$pathSegments = explode('/', $path);
		if (isset($pathSegments[2])) {
			// E.g.: /username/files/path-to-file
			return ($pathSegments[2] === 'files') && (count($pathSegments) > 3);
		}

		return strpos($path, '/appdata_') !== 0;
	}

	/**
	 * Shortens the given absolute path to be relative to
	 * "$user/files".
	 *
	 * @param string $absolutePath absolute path which is under "files"
	 *
	 * @return string path relative to "files" with trimmed slashes or null
	 * if the path was NOT relative to files
	 *
	 * @throws \InvalidArgumentException if the given path was not under "files"
	 * @since 8.1.0
	 */
	public function getPathRelativeToFiles($absolutePath) {
		$path = Filesystem::normalizePath($absolutePath);
		$parts = explode('/', trim($path, '/'), 3);
		// "$user", "files", "path/to/dir"
		if (!isset($parts[1]) || $parts[1] !== 'files') {
			$this->logger->error(
				'$absolutePath must be relative to "files", value is "%s"',
				[
					$absolutePath
				]
			);
			throw new \InvalidArgumentException('$absolutePath must be relative to "files"');
		}
		if (isset($parts[2])) {
			return $parts[2];
		}
		return '';
	}

	/**
	 * @param string $filename
	 * @return array
	 * @throws \OC\User\NoUserException
	 * @throws NotFoundException
	 */
	public function getUidAndFilename($filename) {
		$info = $this->getFileInfo($filename);
		if (!$info instanceof \OCP\Files\FileInfo) {
			throw new NotFoundException($this->getAbsolutePath($filename) . ' not found');
		}
		$uid = $info->getOwner()->getUID();
		if ($uid != \OCP\User::getUser()) {
			Filesystem::initMountPoints($uid);
			$ownerView = new View('/' . $uid . '/files');
			try {
				$filename = $ownerView->getPath($info['fileid']);
			} catch (NotFoundException $e) {
				throw new NotFoundException('File with id ' . $info['fileid'] . ' not found for user ' . $uid);
			}
		}
		return [$uid, $filename];
	}

	/**
	 * Creates parent non-existing folders
	 *
	 * @param string $filePath
	 * @return bool
	 */
	private function createParentDirectories($filePath) {
		$directoryParts = explode('/', $filePath);
		$directoryParts = array_filter($directoryParts);
		foreach ($directoryParts as $key => $part) {
			$currentPathElements = array_slice($directoryParts, 0, $key);
			$currentPath = '/' . implode('/', $currentPathElements);
			if ($this->is_file($currentPath)) {
				return false;
			}
			if (!$this->file_exists($currentPath)) {
				$this->mkdir($currentPath);
			}
		}

		return true;
	}
}
