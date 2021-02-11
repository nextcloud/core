<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Klaas Freitag <freitag@owncloud.com>
 * @author Markus Goetz <markus@woboq.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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

namespace OCA\DAV\Connector\Sabre;

use OC\Files\Mount\MoveableMount;
use OC\Files\View;
use OCA\DAV\Connector\Sabre\Exception\InvalidPath;
use OCP\Files\FileInfo;
use OCP\Files\StorageNotAvailableException;
use OCP\Share\IShare;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

abstract class Node implements \Sabre\DAV\INode {

	/**
	 * @var \OC\Files\View
	 */
	protected $fileView;

	/**
	 * The path to the current node
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * node properties cache
	 *
	 * @var array
	 */
	protected $property_cache = null;

	/**
	 * @var \OCP\Files\FileInfo
	 */
	protected $info;

	/**
	 * @var IManager
	 */
	protected $shareManager;

	/**
	 * Sets up the node, expects a full path name
	 *
	 * @param \OC\Files\View $view
	 * @param \OCP\Files\FileInfo $info
	 * @param IManager $shareManager
	 */
	public function __construct(View $view, FileInfo $info, IManager $shareManager = null) {
		$this->fileView = $view;
		$this->path = $this->fileView->getRelativePath($info->getPath());
		$this->info = $info;
		if ($shareManager) {
			$this->shareManager = $shareManager;
		} else {
			$this->shareManager = \OC::$server->getShareManager();
		}
	}

	protected function refreshInfo() {
		$this->info = $this->fileView->getFileInfo($this->path);
	}

	/**
	 *  Returns the name of the node
	 *
	 * @return string
	 */
	public function getName() {
		return $this->info->getName();
	}

	/**
	 * Returns the full path
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Renames the node
	 *
	 * @param string $name The new name
	 * @throws \Sabre\DAV\Exception\BadRequest
	 * @throws \Sabre\DAV\Exception\Forbidden
	 */
	public function setName($name) {

		// rename is only allowed if the update privilege is granted
		if (!$this->info->isUpdateable()) {
			throw new \Sabre\DAV\Exception\Forbidden();
		}

		list($parentPath,) = \Sabre\Uri\split($this->path);
		list(, $newName) = \Sabre\Uri\split($name);

		// verify path of the target
		$this->verifyPath();

		$newPath = $parentPath . '/' . $newName;

		if (!$this->fileView->rename($this->path, $newPath)) {
			throw new \Sabre\DAV\Exception('Failed to rename '. $this->path . ' to ' . $newPath);
		}

		$this->path = $newPath;

		$this->refreshInfo();
	}

	public function setPropertyCache($property_cache) {
		$this->property_cache = $property_cache;
	}

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int timestamp as integer
	 */
	public function getLastModified() {
		$timestamp = $this->info->getMtime();
		if (!empty($timestamp)) {
			return (int)$timestamp;
		}
		return $timestamp;
	}

	/**
	 *  sets the last modification time of the file (mtime) to the value given
	 *  in the second parameter or to now if the second param is empty.
	 *  Even if the modification time is set to a custom value the access time is set to now.
	 */
	public function touch($mtime) {
		$mtime = $this->sanitizeMtime($mtime);
		$this->fileView->touch($this->path, $mtime);
		$this->refreshInfo();
	}

	/**
	 * Returns the ETag for a file
	 *
	 * An ETag is a unique identifier representing the current version of the
	 * file. If the file changes, the ETag MUST change.  The ETag is an
	 * arbitrary string, but MUST be surrounded by double-quotes.
	 *
	 * Return null if the ETag can not effectively be determined
	 *
	 * @return string
	 */
	public function getETag() {
		return '"' . $this->info->getEtag() . '"';
	}

	/**
	 * Sets the ETag
	 *
	 * @param string $etag
	 *
	 * @return int file id of updated file or -1 on failure
	 */
	public function setETag($etag) {
		return $this->fileView->putFileInfo($this->path, ['etag' => $etag]);
	}

	public function setCreationTime(int $time) {
		return $this->fileView->putFileInfo($this->path, ['creation_time' => $time]);
	}

	public function setUploadTime(int $time) {
		return $this->fileView->putFileInfo($this->path, ['upload_time' => $time]);
	}

	/**
	 * Returns the size of the node, in bytes
	 *
	 * @return integer
	 */
	public function getSize() {
		return $this->info->getSize();
	}

	/**
	 * Returns the cache's file id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->info->getId();
	}

	/**
	 * @return string|null
	 */
	public function getFileId() {
		if ($this->info->getId()) {
			$instanceId = \OC_Util::getInstanceId();
			$id = sprintf('%08d', $this->info->getId());
			return $id . $instanceId;
		}

		return null;
	}

	/**
	 * @return integer
	 */
	public function getInternalFileId() {
		return $this->info->getId();
	}

	/**
	 * @param string $user
	 * @return int
	 */
	public function getSharePermissions($user) {

		// check of we access a federated share
		if ($user !== null) {
			try {
				$share = $this->shareManager->getShareByToken($user);
				return $share->getPermissions();
			} catch (ShareNotFound $e) {
				// ignore
			}
		}

		try {
			$storage = $this->info->getStorage();
		} catch (StorageNotAvailableException $e) {
			$storage = null;
		}

		if ($storage && $storage->instanceOfStorage('\OCA\Files_Sharing\SharedStorage')) {
			/** @var \OCA\Files_Sharing\SharedStorage $storage */
			$permissions = (int)$storage->getShare()->getPermissions();
		} else {
			$permissions = $this->info->getPermissions();
		}

		/*
		 * We can always share non moveable mount points with DELETE and UPDATE
		 * Eventually we need to do this properly
		 */
		$mountpoint = $this->info->getMountPoint();
		if (!($mountpoint instanceof MoveableMount)) {
			$mountpointpath = $mountpoint->getMountPoint();
			if (substr($mountpointpath, -1) === '/') {
				$mountpointpath = substr($mountpointpath, 0, -1);
			}

			if (!$mountpoint->getOption('readonly', false) && $mountpointpath === $this->info->getPath()) {
				$permissions |= \OCP\Constants::PERMISSION_DELETE | \OCP\Constants::PERMISSION_UPDATE;
			}
		}

		/*
		 * Files can't have create or delete permissions
		 */
		if ($this->info->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
			$permissions &= ~(\OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_DELETE);
		}

		return $permissions;
	}

	/**
	 * @param string $user
	 * @return string
	 */
	public function getNoteFromShare($user) {
		if ($user === null) {
			return '';
		}

		$types = [
			IShare::TYPE_USER,
			IShare::TYPE_GROUP,
			IShare::TYPE_CIRCLE,
			IShare::TYPE_ROOM
		];

		foreach ($types as $shareType) {
			$shares = $this->shareManager->getSharedWith($user, $shareType, $this, -1);
			foreach ($shares as $share) {
				$note = $share->getNote();
				if ($share->getShareOwner() !== $user && !empty($note)) {
					return $note;
				}
			}
		}

		return '';
	}

	/**
	 * @return string
	 */
	public function getDavPermissions() {
		$p = '';
		if ($this->info->isShared()) {
			$p .= 'S';
		}
		if ($this->info->isShareable()) {
			$p .= 'R';
		}
		if ($this->info->isMounted()) {
			$p .= 'M';
		}
		if ($this->info->isReadable()) {
			$p .= 'G';
		}
		if ($this->info->isDeletable()) {
			$p .= 'D';
		}
		if ($this->info->isUpdateable()) {
			$p .= 'NV'; // Renameable, Moveable
		}
		if ($this->info->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
			if ($this->info->isUpdateable()) {
				$p .= 'W';
			}
		} else {
			if ($this->info->isCreatable()) {
				$p .= 'CK';
			}
		}
		return $p;
	}

	public function getOwner() {
		return $this->info->getOwner();
	}

	protected function verifyPath() {
		try {
			$fileName = basename($this->info->getPath());
			$this->fileView->verifyPath($this->path, $fileName);
		} catch (\OCP\Files\InvalidPathException $ex) {
			throw new InvalidPath($ex->getMessage());
		}
	}

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 */
	public function acquireLock($type) {
		$this->fileView->lockFile($this->path, $type);
	}

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 */
	public function releaseLock($type) {
		$this->fileView->unlockFile($this->path, $type);
	}

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 */
	public function changeLock($type) {
		$this->fileView->changeLock($this->path, $type);
	}

	public function getFileInfo() {
		return $this->info;
	}

	protected function sanitizeMtime($mtimeFromRequest) {
		// In PHP 5.X "is_numeric" returns true for strings in hexadecimal
		// notation. This is no longer the case in PHP 7.X, so this check
		// ensures that strings with hexadecimal notations fail too in PHP 5.X.
		$isHexadecimal = is_string($mtimeFromRequest) && preg_match('/^\s*0[xX]/', $mtimeFromRequest);
		if ($isHexadecimal || !is_numeric($mtimeFromRequest)) {
			throw new \InvalidArgumentException('X-OC-MTime header must be an integer (unix timestamp).');
		}

		return (int)$mtimeFromRequest;
	}
}
