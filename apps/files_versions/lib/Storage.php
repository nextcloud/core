<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Felix Nieuwenhuizen <felix@tdlrali.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Liam JACK <liamjack@users.noreply.github.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

/**
 * Versions
 *
 * A class to handle the versioning of files.
 */

namespace OCA\Files_Versions;

use OC\Files\Filesystem;
use OC\Files\View;
use OCA\Files_Versions\AppInfo\Application;
use OCA\Files_Versions\Command\Expire;
use OCA\Files_Versions\Events\CreateVersionEvent;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\User;

class Storage {
	public const DEFAULTENABLED = true;
	public const DEFAULTMAXSIZE = 50; // unit: percentage; 50% of available disk space/quota
	public const VERSIONS_ROOT = 'files_versions/';

	public const DELETE_TRIGGER_MASTER_REMOVED = 0;
	public const DELETE_TRIGGER_RETENTION_CONSTRAINT = 1;
	public const DELETE_TRIGGER_QUOTA_EXCEEDED = 2;

	// files for which we can remove the versions after the delete operation was successful
	private static $deletedFiles = [];

	private static $sourcePathAndUser = [];

	private static $max_versions_per_interval = [
		//first 10sec, one version every 2sec
		1 => ['intervalEndsAfter' => 10,      'step' => 2],
		//next minute, one version every 10sec
		2 => ['intervalEndsAfter' => 60,      'step' => 10],
		//next hour, one version every minute
		3 => ['intervalEndsAfter' => 3600,    'step' => 60],
		//next 24h, one version every hour
		4 => ['intervalEndsAfter' => 86400,   'step' => 3600],
		//next 30days, one version per day
		5 => ['intervalEndsAfter' => 2592000, 'step' => 86400],
		//until the end one version per week
		6 => ['intervalEndsAfter' => -1,      'step' => 604800],
	];

	/** @var \OCA\Files_Versions\AppInfo\Application */
	private static $application;

	/**
	 * get the UID of the owner of the file and the path to the file relative to
	 * owners files folder
	 *
	 * @param string $filename
	 * @return array
	 * @throws \OC\User\NoUserException
	 */
	public static function getUidAndFilename($filename) {
		$uid = Filesystem::getOwner($filename);
		$userManager = \OC::$server->getUserManager();
		// if the user with the UID doesn't exists, e.g. because the UID points
		// to a remote user with a federated cloud ID we use the current logged-in
		// user. We need a valid local user to create the versions
		if (!$userManager->userExists($uid)) {
			$uid = User::getUser();
		}
		Filesystem::initMountPoints($uid);
		if ($uid !== User::getUser()) {
			$info = Filesystem::getFileInfo($filename);
			$ownerView = new View('/'.$uid.'/files');
			try {
				$filename = $ownerView->getPath($info['fileid']);
				// make sure that the file name doesn't end with a trailing slash
				// can for example happen single files shared across servers
				$filename = rtrim($filename, '/');
			} catch (NotFoundException $e) {
				$filename = null;
			}
		}
		return [$uid, $filename];
	}

	/**
	 * Remember the owner and the owner path of the source file
	 *
	 * @param string $source source path
	 */
	public static function setSourcePathAndUser($source) {
		list($uid, $path) = self::getUidAndFilename($source);
		self::$sourcePathAndUser[$source] = ['uid' => $uid, 'path' => $path];
	}

	/**
	 * Gets the owner and the owner path from the source path
	 *
	 * @param string $source source path
	 * @return array with user id and path
	 */
	public static function getSourcePathAndUser($source) {
		if (isset(self::$sourcePathAndUser[$source])) {
			$uid = self::$sourcePathAndUser[$source]['uid'];
			$path = self::$sourcePathAndUser[$source]['path'];
			unset(self::$sourcePathAndUser[$source]);
		} else {
			$uid = $path = false;
		}
		return [$uid, $path];
	}

	/**
	 * get current size of all versions from a given user
	 *
	 * @param string $user user who owns the versions
	 * @return int versions size
	 */
	private static function getVersionsSize($user) {
		$view = new View('/' . $user);
		$fileInfo = $view->getFileInfo('/files_versions');
		return isset($fileInfo['size']) ? $fileInfo['size'] : 0;
	}

	/**
	 * store a new version of a file.
	 */
	public static function store($filename) {

		// if the file gets streamed we need to remove the .part extension
		// to get the right target
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		if ($ext === 'part') {
			$filename = substr($filename, 0, -5);
		}

		// we only handle existing files
		if (! Filesystem::file_exists($filename) || Filesystem::is_dir($filename)) {
			return false;
		}

		list($uid, $filename) = self::getUidAndFilename($filename);

		$files_view = new View('/'.$uid .'/files');

		$eventDispatcher = \OC::$server->getEventDispatcher();
		$fileInfo = $files_view->getFileInfo($filename);
		$id = $fileInfo->getId();
		$nodes = \OC::$server->getRootFolder()->getUserFolder($uid)->getById($id);
		foreach ($nodes as $node) {
			$event = new CreateVersionEvent($node);
			$eventDispatcher->dispatch('OCA\Files_Versions::createVersion', $event);
			if ($event->shouldCreateVersion() === false) {
				return false;
			}
		}

		// no use making versions for empty files
		if ($fileInfo->getSize() === 0) {
			return false;
		}

		/** @var IVersionManager $versionManager */
		$versionManager = \OC::$server->query(IVersionManager::class);
		$userManager = \OC::$server->getUserManager();
		$user = $userManager->get($uid);

		$versionManager->createVersion($user, $fileInfo);
	}


	/**
	 * mark file as deleted so that we can remove the versions if the file is gone
	 * @param string $path
	 */
	public static function markDeletedFile($path) {
		list($uid, $filename) = self::getUidAndFilename($path);
		self::$deletedFiles[$path] = [
			'uid' => $uid,
			'filename' => $filename];
	}

	/**
	 * delete the version from the storage and cache
	 *
	 * @param View $view
	 * @param string $path
	 */
	protected static function deleteVersion($view, $path) {
		$view->unlink($path);
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 * @var string $internalPath
		 */
		list($storage, $internalPath) = $view->resolvePath($path);
		$cache = $storage->getCache($internalPath);
		$cache->remove($internalPath);
	}

	/**
	 * Delete versions of a file
	 */
	public static function delete($path) {
		$deletedFile = self::$deletedFiles[$path];
		$uid = $deletedFile['uid'];
		$filename = $deletedFile['filename'];

		if (!Filesystem::file_exists($path)) {
			$view = new View('/' . $uid . '/files_versions');

			$versions = self::getVersions($uid, $filename);
			if (!empty($versions)) {
				foreach ($versions as $v) {
					\OC_Hook::emit('\OCP\Versions', 'preDelete', ['path' => $path . $v['version'], 'trigger' => self::DELETE_TRIGGER_MASTER_REMOVED]);
					self::deleteVersion($view, $filename . '.v' . $v['version']);
					\OC_Hook::emit('\OCP\Versions', 'delete', ['path' => $path . $v['version'], 'trigger' => self::DELETE_TRIGGER_MASTER_REMOVED]);
				}
			}
		}
		unset(self::$deletedFiles[$path]);
	}

	/**
	 * Rename or copy versions of a file of the given paths
	 *
	 * @param string $sourcePath source path of the file to move, relative to
	 * the currently logged in user's "files" folder
	 * @param string $targetPath target path of the file to move, relative to
	 * the currently logged in user's "files" folder
	 * @param string $operation can be 'copy' or 'rename'
	 */
	public static function renameOrCopy($sourcePath, $targetPath, $operation) {
		list($sourceOwner, $sourcePath) = self::getSourcePathAndUser($sourcePath);

		// it was a upload of a existing file if no old path exists
		// in this case the pre-hook already called the store method and we can
		// stop here
		if ($sourcePath === false) {
			return true;
		}

		list($targetOwner, $targetPath) = self::getUidAndFilename($targetPath);

		$sourcePath = ltrim($sourcePath, '/');
		$targetPath = ltrim($targetPath, '/');

		$rootView = new View('');

		// did we move a directory ?
		if ($rootView->is_dir('/' . $targetOwner . '/files/' . $targetPath)) {
			// does the directory exists for versions too ?
			if ($rootView->is_dir('/' . $sourceOwner . '/files_versions/' . $sourcePath)) {
				// create missing dirs if necessary
				self::createMissingDirectories($targetPath, new View('/'. $targetOwner));

				// move the directory containing the versions
				$rootView->$operation(
					'/' . $sourceOwner . '/files_versions/' . $sourcePath,
					'/' . $targetOwner . '/files_versions/' . $targetPath
				);
			}
		} elseif ($versions = Storage::getVersions($sourceOwner, '/' . $sourcePath)) {
			// create missing dirs if necessary
			self::createMissingDirectories($targetPath, new View('/'. $targetOwner));

			foreach ($versions as $v) {
				// move each version one by one to the target directory
				$rootView->$operation(
					'/' . $sourceOwner . '/files_versions/' . $sourcePath.'.v' . $v['version'],
					'/' . $targetOwner . '/files_versions/' . $targetPath.'.v'.$v['version']
				);
			}
		}

		// if we moved versions directly for a file, schedule expiration check for that file
		if (!$rootView->is_dir('/' . $targetOwner . '/files/' . $targetPath)) {
			self::scheduleExpire($targetOwner, $targetPath);
		}
	}

	/**
	 * Rollback to an old version of a file.
	 *
	 * @param string $file file name
	 * @param int $revision revision timestamp
	 * @return bool
	 */
	public static function rollback(string $file, int $revision, IUser $user) {

		// add expected leading slash
		$filename = '/' . ltrim($file, '/');

		// Fetch the userfolder to trigger view hooks
		$userFolder = \OC::$server->getUserFolder($user->getUID());

		$users_view = new View('/'.$user->getUID());
		$files_view = new View('/'. $user->getUID().'/files');

		$versionCreated = false;

		$fileInfo = $files_view->getFileInfo($file);

		// check if user has the permissions to revert a version
		if (!$fileInfo->isUpdateable()) {
			return false;
		}

		//first create a new version
		$version = 'files_versions'.$filename.'.v'.$users_view->filemtime('files'.$filename);
		if (!$users_view->file_exists($version)) {
			$users_view->copy('files'.$filename, 'files_versions'.$filename.'.v'.$users_view->filemtime('files'.$filename));
			$versionCreated = true;
		}

		$fileToRestore = 'files_versions' . $filename . '.v' . $revision;

		// Restore encrypted version of the old file for the newly restored file
		// This has to happen manually here since the file is manually copied below
		$oldVersion = $users_view->getFileInfo($fileToRestore)->getEncryptedVersion();
		$oldFileInfo = $users_view->getFileInfo($fileToRestore);
		$cache = $fileInfo->getStorage()->getCache();
		$cache->update(
			$fileInfo->getId(), [
				'encrypted' => $oldVersion,
				'encryptedVersion' => $oldVersion,
				'size' => $oldFileInfo->getSize()
			]
		);

		// rollback
		if (self::copyFileContents($users_view, $fileToRestore, 'files' . $filename)) {
			$files_view->touch($file, $revision);
			Storage::scheduleExpire($user->getUID(), $file);

			$node = $userFolder->get($file);

			// TODO: move away from those legacy hooks!
			\OC_Hook::emit('\OCP\Versions', 'rollback', [
				'path' => $filename,
				'revision' => $revision,
				'node' => $node,
			]);
			return true;
		} elseif ($versionCreated) {
			self::deleteVersion($users_view, $version);
		}

		return false;
	}

	/**
	 * Stream copy file contents from $path1 to $path2
	 *
	 * @param View $view view to use for copying
	 * @param string $path1 source file to copy
	 * @param string $path2 target file
	 *
	 * @return bool true for success, false otherwise
	 */
	private static function copyFileContents($view, $path1, $path2) {
		/** @var \OC\Files\Storage\Storage $storage1 */
		list($storage1, $internalPath1) = $view->resolvePath($path1);
		/** @var \OC\Files\Storage\Storage $storage2 */
		list($storage2, $internalPath2) = $view->resolvePath($path2);

		$view->lockFile($path1, ILockingProvider::LOCK_EXCLUSIVE);
		$view->lockFile($path2, ILockingProvider::LOCK_EXCLUSIVE);

		// TODO add a proper way of overwriting a file while maintaining file ids
		if ($storage1->instanceOfStorage('\OC\Files\ObjectStore\ObjectStoreStorage') || $storage2->instanceOfStorage('\OC\Files\ObjectStore\ObjectStoreStorage')) {
			$source = $storage1->fopen($internalPath1, 'r');
			$target = $storage2->fopen($internalPath2, 'w');
			list(, $result) = \OC_Helper::streamCopy($source, $target);
			fclose($source);
			fclose($target);

			if ($result !== false) {
				$storage1->unlink($internalPath1);
			}
		} else {
			$result = $storage2->moveFromStorage($storage1, $internalPath1, $internalPath2);
		}

		$view->unlockFile($path1, ILockingProvider::LOCK_EXCLUSIVE);
		$view->unlockFile($path2, ILockingProvider::LOCK_EXCLUSIVE);

		return ($result !== false);
	}

	/**
	 * get a list of all available versions of a file in descending chronological order
	 * @param string $uid user id from the owner of the file
	 * @param string $filename file to find versions of, relative to the user files dir
	 * @param string $userFullPath
	 * @return array versions newest version first
	 */
	public static function getVersions($uid, $filename, $userFullPath = '') {
		$versions = [];
		if (empty($filename)) {
			return $versions;
		}
		// fetch for old versions
		$view = new View('/' . $uid . '/');

		$pathinfo = pathinfo($filename);
		$versionedFile = $pathinfo['basename'];

		$dir = Filesystem::normalizePath(self::VERSIONS_ROOT . '/' . $pathinfo['dirname']);

		$dirContent = false;
		if ($view->is_dir($dir)) {
			$dirContent = $view->opendir($dir);
		}

		if ($dirContent === false) {
			return $versions;
		}

		if (is_resource($dirContent)) {
			while (($entryName = readdir($dirContent)) !== false) {
				if (!Filesystem::isIgnoredDir($entryName)) {
					$pathparts = pathinfo($entryName);
					$filename = $pathparts['filename'];
					if ($filename === $versionedFile) {
						$pathparts = pathinfo($entryName);
						$timestamp = substr($pathparts['extension'], 1);
						$filename = $pathparts['filename'];
						$key = $timestamp . '#' . $filename;
						$versions[$key]['version'] = $timestamp;
						$versions[$key]['humanReadableTimestamp'] = self::getHumanReadableTimestamp($timestamp);
						if (empty($userFullPath)) {
							$versions[$key]['preview'] = '';
						} else {
							$versions[$key]['preview'] = \OC::$server->getURLGenerator('files_version.Preview.getPreview', ['file' => $userFullPath, 'version' => $timestamp]);
						}
						$versions[$key]['path'] = Filesystem::normalizePath($pathinfo['dirname'] . '/' . $filename);
						$versions[$key]['name'] = $versionedFile;
						$versions[$key]['size'] = $view->filesize($dir . '/' . $entryName);
						$versions[$key]['mimetype'] = \OC::$server->getMimeTypeDetector()->detectPath($versionedFile);
					}
				}
			}
			closedir($dirContent);
		}

		// sort with newest version first
		krsort($versions);

		return $versions;
	}

	/**
	 * Expire versions that older than max version retention time
	 * @param string $uid
	 */
	public static function expireOlderThanMaxForUser($uid) {
		$expiration = self::getExpiration();
		$threshold = $expiration->getMaxAgeAsTimestamp();
		$versions = self::getAllVersions($uid);
		if (!$threshold || empty($versions['all'])) {
			return;
		}

		$toDelete = [];
		foreach (array_reverse($versions['all']) as $key => $version) {
			if ((int)$version['version'] < $threshold) {
				$toDelete[$key] = $version;
			} else {
				//Versions are sorted by time - nothing mo to iterate.
				break;
			}
		}

		$view = new View('/' . $uid . '/files_versions');
		if (!empty($toDelete)) {
			foreach ($toDelete as $version) {
				\OC_Hook::emit('\OCP\Versions', 'preDelete', ['path' => $version['path'].'.v'.$version['version'], 'trigger' => self::DELETE_TRIGGER_RETENTION_CONSTRAINT]);
				self::deleteVersion($view, $version['path'] . '.v' . $version['version']);
				\OC_Hook::emit('\OCP\Versions', 'delete', ['path' => $version['path'].'.v'.$version['version'], 'trigger' => self::DELETE_TRIGGER_RETENTION_CONSTRAINT]);
			}
		}
	}

	/**
	 * translate a timestamp into a string like "5 days ago"
	 * @param int $timestamp
	 * @return string for example "5 days ago"
	 */
	private static function getHumanReadableTimestamp($timestamp) {
		$diff = time() - $timestamp;

		if ($diff < 60) { // first minute
			return  $diff . " seconds ago";
		} elseif ($diff < 3600) { //first hour
			return round($diff / 60) . " minutes ago";
		} elseif ($diff < 86400) { // first day
			return round($diff / 3600) . " hours ago";
		} elseif ($diff < 604800) { //first week
			return round($diff / 86400) . " days ago";
		} elseif ($diff < 2419200) { //first month
			return round($diff / 604800) . " weeks ago";
		} elseif ($diff < 29030400) { // first year
			return round($diff / 2419200) . " months ago";
		} else {
			return round($diff / 29030400) . " years ago";
		}
	}

	/**
	 * returns all stored file versions from a given user
	 * @param string $uid id of the user
	 * @return array with contains two arrays 'all' which contains all versions sorted by age and 'by_file' which contains all versions sorted by filename
	 */
	private static function getAllVersions($uid) {
		$view = new View('/' . $uid . '/');
		$dirs = [self::VERSIONS_ROOT];
		$versions = [];

		while (!empty($dirs)) {
			$dir = array_pop($dirs);
			$files = $view->getDirectoryContent($dir);

			foreach ($files as $file) {
				$fileData = $file->getData();
				$filePath = $dir . '/' . $fileData['name'];
				if ($file['type'] === 'dir') {
					$dirs[] = $filePath;
				} else {
					$versionsBegin = strrpos($filePath, '.v');
					$relPathStart = strlen(self::VERSIONS_ROOT);
					$version = substr($filePath, $versionsBegin + 2);
					$relpath = substr($filePath, $relPathStart, $versionsBegin - $relPathStart);
					$key = $version . '#' . $relpath;
					$versions[$key] = ['path' => $relpath, 'timestamp' => $version];
				}
			}
		}

		// newest version first
		krsort($versions);

		$result = [
			'all' => [],
			'by_file' => [],
		];

		foreach ($versions as $key => $value) {
			$size = $view->filesize(self::VERSIONS_ROOT.'/'.$value['path'].'.v'.$value['timestamp']);
			$filename = $value['path'];

			$result['all'][$key]['version'] = $value['timestamp'];
			$result['all'][$key]['path'] = $filename;
			$result['all'][$key]['size'] = $size;

			$result['by_file'][$filename][$key]['version'] = $value['timestamp'];
			$result['by_file'][$filename][$key]['path'] = $filename;
			$result['by_file'][$filename][$key]['size'] = $size;
		}

		return $result;
	}

	/**
	 * get list of files we want to expire
	 * @param array $versions list of versions
	 * @param integer $time
	 * @param bool $quotaExceeded is versions storage limit reached
	 * @return array containing the list of to deleted versions and the size of them
	 */
	protected static function getExpireList($time, $versions, $quotaExceeded = false) {
		$expiration = self::getExpiration();

		if ($expiration->shouldAutoExpire()) {
			list($toDelete, $size) = self::getAutoExpireList($time, $versions);
		} else {
			$size = 0;
			$toDelete = [];  // versions we want to delete
		}

		foreach ($versions as $key => $version) {
			if ($expiration->isExpired($version['version'], $quotaExceeded) && !isset($toDelete[$key])) {
				$size += $version['size'];
				$toDelete[$key] = $version['path'] . '.v' . $version['version'];
			}
		}

		return [$toDelete, $size];
	}

	/**
	 * get list of files we want to expire
	 * @param array $versions list of versions
	 * @param integer $time
	 * @return array containing the list of to deleted versions and the size of them
	 */
	protected static function getAutoExpireList($time, $versions) {
		$size = 0;
		$toDelete = [];  // versions we want to delete

		$interval = 1;
		$step = Storage::$max_versions_per_interval[$interval]['step'];
		if (Storage::$max_versions_per_interval[$interval]['intervalEndsAfter'] === -1) {
			$nextInterval = -1;
		} else {
			$nextInterval = $time - Storage::$max_versions_per_interval[$interval]['intervalEndsAfter'];
		}

		$firstVersion = reset($versions);

		if ($firstVersion === false) {
			return [$toDelete, $size];
		}

		$firstKey = key($versions);
		$prevTimestamp = $firstVersion['version'];
		$nextVersion = $firstVersion['version'] - $step;
		unset($versions[$firstKey]);

		foreach ($versions as $key => $version) {
			$newInterval = true;
			while ($newInterval) {
				if ($nextInterval === -1 || $prevTimestamp > $nextInterval) {
					if ($version['version'] > $nextVersion) {
						//distance between two version too small, mark to delete
						$toDelete[$key] = $version['path'] . '.v' . $version['version'];
						$size += $version['size'];
						\OC::$server->getLogger()->info('Mark to expire '. $version['path'] .' next version should be ' . $nextVersion . " or smaller. (prevTimestamp: " . $prevTimestamp . "; step: " . $step, ['app' => 'files_versions']);
					} else {
						$nextVersion = $version['version'] - $step;
						$prevTimestamp = $version['version'];
					}
					$newInterval = false; // version checked so we can move to the next one
				} else { // time to move on to the next interval
					$interval++;
					$step = Storage::$max_versions_per_interval[$interval]['step'];
					$nextVersion = $prevTimestamp - $step;
					if (Storage::$max_versions_per_interval[$interval]['intervalEndsAfter'] === -1) {
						$nextInterval = -1;
					} else {
						$nextInterval = $time - Storage::$max_versions_per_interval[$interval]['intervalEndsAfter'];
					}
					$newInterval = true; // we changed the interval -> check same version with new interval
				}
			}
		}

		return [$toDelete, $size];
	}

	/**
	 * Schedule versions expiration for the given file
	 *
	 * @param string $uid owner of the file
	 * @param string $fileName file/folder for which to schedule expiration
	 */
	public static function scheduleExpire($uid, $fileName) {
		// let the admin disable auto expire
		$expiration = self::getExpiration();
		if ($expiration->isEnabled()) {
			$command = new Expire($uid, $fileName);
			\OC::$server->getCommandBus()->push($command);
		}
	}

	/**
	 * Expire versions which exceed the quota.
	 *
	 * This will setup the filesystem for the given user but will not
	 * tear it down afterwards.
	 *
	 * @param string $filename path to file to expire
	 * @param string $uid user for which to expire the version
	 * @return bool|int|null
	 */
	public static function expire($filename, $uid) {
		$expiration = self::getExpiration();

		if ($expiration->isEnabled()) {
			// get available disk space for user
			$user = \OC::$server->getUserManager()->get($uid);
			if (is_null($user)) {
				\OC::$server->getLogger()->error('Backends provided no user object for ' . $uid, ['app' => 'files_versions']);
				throw new \OC\User\NoUserException('Backends provided no user object for ' . $uid);
			}

			\OC_Util::setupFS($uid);

			try {
				if (!Filesystem::file_exists($filename)) {
					return false;
				}
			} catch (StorageNotAvailableException $e) {
				// if we can't check that the file hasn't been deleted we can only assume that it hasn't
				// note that this `StorageNotAvailableException` is about the file the versions originate from,
				// not the storage that the versions are stored on
			}

			if (empty($filename)) {
				// file maybe renamed or deleted
				return false;
			}
			$versionsFileview = new View('/'.$uid.'/files_versions');

			$softQuota = true;
			$quota = $user->getQuota();
			if ($quota === null || $quota === 'none') {
				$quota = Filesystem::free_space('/');
				$softQuota = false;
			} else {
				$quota = \OCP\Util::computerFileSize($quota);
			}

			// make sure that we have the current size of the version history
			$versionsSize = self::getVersionsSize($uid);

			// calculate available space for version history
			// subtract size of files and current versions size from quota
			if ($quota >= 0) {
				if ($softQuota) {
					$userFolder = \OC::$server->getUserFolder($uid);
					if (is_null($userFolder)) {
						$availableSpace = 0;
					} else {
						$free = $quota - $userFolder->getSize(false); // remaining free space for user
						if ($free > 0) {
							$availableSpace = ($free * self::DEFAULTMAXSIZE / 100) - $versionsSize; // how much space can be used for versions
						} else {
							$availableSpace = $free - $versionsSize;
						}
					}
				} else {
					$availableSpace = $quota;
				}
			} else {
				$availableSpace = PHP_INT_MAX;
			}

			$allVersions = Storage::getVersions($uid, $filename);

			$time = time();
			list($toDelete, $sizeOfDeletedVersions) = self::getExpireList($time, $allVersions, $availableSpace <= 0);

			$availableSpace = $availableSpace + $sizeOfDeletedVersions;
			$versionsSize = $versionsSize - $sizeOfDeletedVersions;

			// if still not enough free space we rearrange the versions from all files
			if ($availableSpace <= 0) {
				$result = self::getAllVersions($uid);
				$allVersions = $result['all'];

				foreach ($result['by_file'] as $versions) {
					list($toDeleteNew, $size) = self::getExpireList($time, $versions, $availableSpace <= 0);
					$toDelete = array_merge($toDelete, $toDeleteNew);
					$sizeOfDeletedVersions += $size;
				}
				$availableSpace = $availableSpace + $sizeOfDeletedVersions;
				$versionsSize = $versionsSize - $sizeOfDeletedVersions;
			}

			$logger = \OC::$server->getLogger();
			foreach ($toDelete as $key => $path) {
				\OC_Hook::emit('\OCP\Versions', 'preDelete', ['path' => $path, 'trigger' => self::DELETE_TRIGGER_QUOTA_EXCEEDED]);
				self::deleteVersion($versionsFileview, $path);
				\OC_Hook::emit('\OCP\Versions', 'delete', ['path' => $path, 'trigger' => self::DELETE_TRIGGER_QUOTA_EXCEEDED]);
				unset($allVersions[$key]); // update array with the versions we keep
				$logger->info('Expire: ' . $path, ['app' => 'files_versions']);
			}

			// Check if enough space is available after versions are rearranged.
			// If not we delete the oldest versions until we meet the size limit for versions,
			// but always keep the two latest versions
			$numOfVersions = count($allVersions) - 2 ;
			$i = 0;
			// sort oldest first and make sure that we start at the first element
			ksort($allVersions);
			reset($allVersions);
			while ($availableSpace < 0 && $i < $numOfVersions) {
				$version = current($allVersions);
				\OC_Hook::emit('\OCP\Versions', 'preDelete', ['path' => $version['path'].'.v'.$version['version'], 'trigger' => self::DELETE_TRIGGER_QUOTA_EXCEEDED]);
				self::deleteVersion($versionsFileview, $version['path'] . '.v' . $version['version']);
				\OC_Hook::emit('\OCP\Versions', 'delete', ['path' => $version['path'].'.v'.$version['version'], 'trigger' => self::DELETE_TRIGGER_QUOTA_EXCEEDED]);
				\OC::$server->getLogger()->info('running out of space! Delete oldest version: ' . $version['path'].'.v'.$version['version'], ['app' => 'files_versions']);
				$versionsSize -= $version['size'];
				$availableSpace += $version['size'];
				next($allVersions);
				$i++;
			}

			return $versionsSize; // finally return the new size of the version history
		}

		return false;
	}

	/**
	 * Create recursively missing directories inside of files_versions
	 * that match the given path to a file.
	 *
	 * @param string $filename $path to a file, relative to the user's
	 * "files" folder
	 * @param View $view view on data/user/
	 */
	public static function createMissingDirectories($filename, $view) {
		$dirname = Filesystem::normalizePath(dirname($filename));
		$dirParts = explode('/', $dirname);
		$dir = "/files_versions";
		foreach ($dirParts as $part) {
			$dir = $dir . '/' . $part;
			if (!$view->file_exists($dir)) {
				$view->mkdir($dir);
			}
		}
	}

	/**
	 * Static workaround
	 * @return Expiration
	 */
	protected static function getExpiration() {
		if (self::$application === null) {
			self::$application = \OC::$server->query(Application::class);
		}
		return self::$application->getContainer()->query(Expiration::class);
	}
}
