<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr M <mrow4a@yahoo.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author tbartenstein <tbartenstein@users.noreply.github.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Mount\IMountPoint;
use OCP\IUser;

class FileInfo implements \OCP\Files\FileInfo, \ArrayAccess {
	/**
	 * @var array $data
	 */
	private $data;

	/**
	 * @var string $path
	 */
	private $path;

	/**
	 * @var \OC\Files\Storage\Storage $storage
	 */
	private $storage;

	/**
	 * @var string $internalPath
	 */
	private $internalPath;

	/**
	 * @var \OCP\Files\Mount\IMountPoint
	 */
	private $mount;

	/**
	 * @var IUser
	 */
	private $owner;

	/**
	 * @var string[]
	 */
	private $childEtags = [];

	/**
	 * @var IMountPoint[]
	 */
	private $subMounts = [];

	private $subMountsUsed = false;

	/**
	 * The size of the file/folder without any sub mount
	 *
	 * @var int
	 */
	private $rawSize = 0;

	/**
	 * @param string|boolean $path
	 * @param Storage\Storage $storage
	 * @param string $internalPath
	 * @param array|ICacheEntry $data
	 * @param \OCP\Files\Mount\IMountPoint $mount
	 * @param \OCP\IUser|null $owner
	 */
	public function __construct($path, $storage, $internalPath, $data, $mount, $owner= null) {
		$this->path = $path;
		$this->storage = $storage;
		$this->internalPath = $internalPath;
		$this->data = $data;
		$this->mount = $mount;
		$this->owner = $owner;
		$this->rawSize = $this->data['size'] ?? 0;
	}

	public function offsetSet($offset, $value) {
		$this->data[$offset] = $value;
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		if ($offset === 'type') {
			return $this->getType();
		} else if ($offset === 'etag') {
			return $this->getEtag();
		} else if ($offset === 'size') {
			return $this->getSize();
		} else if ($offset === 'mtime') {
			return $this->getMTime();
		} elseif ($offset === 'permissions') {
			return $this->getPermissions();
		} elseif (isset($this->data[$offset])) {
			return $this->data[$offset];
		} else {
			return null;
		}
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @return \OCP\Files\Storage
	 */
	public function getStorage() {
		return $this->storage;
	}

	/**
	 * @return string
	 */
	public function getInternalPath() {
		return $this->internalPath;
	}

	/**
	 * Get FileInfo ID or null in case of part file
	 *
	 * @return int|null
	 */
	public function getId() {
		return isset($this->data['fileid']) ? (int)  $this->data['fileid'] : null;
	}

	/**
	 * @return string
	 */
	public function getMimetype() {
		return $this->data['mimetype'];
	}

	/**
	 * @return string
	 */
	public function getMimePart() {
		return $this->data['mimepart'];
	}

	/**
	 * @return string
	 */
	public function getName() {
		return isset($this->data['name']) ? $this->data['name'] : basename($this->getPath());
	}

	/**
	 * @return string
	 */
	public function getEtag() {
		$this->updateEntryfromSubMounts();
		if (count($this->childEtags) > 0) {
			$combinedEtag = $this->data['etag'] . '::' . implode('::', $this->childEtags);
			return md5($combinedEtag);
		} else {
			return $this->data['etag'];
		}
	}

	/**
	 * @return int
	 */
	public function getSize($includeMounts = true) {
		if ($includeMounts) {
			$this->updateEntryfromSubMounts();
			return isset($this->data['size']) ? 0 + $this->data['size'] : 0;
		} else {
			return $this->rawSize;
		}
	}

	/**
	 * @return int
	 */
	public function getMTime() {
		$this->updateEntryfromSubMounts();
		return (int) $this->data['mtime'];
	}

	/**
	 * @return bool
	 */
	public function isEncrypted() {
		return $this->data['encrypted'];
	}

	/**
	 * Return the currently version used for the HMAC in the encryption app
	 *
	 * @return int
	 */
	public function getEncryptedVersion() {
		return isset($this->data['encryptedVersion']) ? (int) $this->data['encryptedVersion'] : 1;
	}

	/**
	 * @return int
	 */
	public function getPermissions() {
		$perms = (int) $this->data['permissions'];
		if (\OCP\Util::isSharingDisabledForUser() || ($this->isShared() && !\OC\Share\Share::isResharingAllowed())) {
			$perms = $perms & ~\OCP\Constants::PERMISSION_SHARE;
		}
		return (int) $perms;
	}

	/**
	 * @return string \OCP\Files\FileInfo::TYPE_FILE|\OCP\Files\FileInfo::TYPE_FOLDER
	 */
	public function getType() {
		if (!isset($this->data['type'])) {
			$this->data['type'] = ($this->getMimetype() === 'httpd/unix-directory') ? self::TYPE_FOLDER : self::TYPE_FILE;
		}
		return $this->data['type'];
	}

	public function getData() {
		return $this->data;
	}

	/**
	 * @param int $permissions
	 * @return bool
	 */
	protected function checkPermissions($permissions) {
		return ($this->getPermissions() & $permissions) === $permissions;
	}

	/**
	 * @return bool
	 */
	public function isReadable() {
		return $this->checkPermissions(\OCP\Constants::PERMISSION_READ);
	}

	/**
	 * @return bool
	 */
	public function isUpdateable() {
		return $this->checkPermissions(\OCP\Constants::PERMISSION_UPDATE);
	}

	/**
	 * Check whether new files or folders can be created inside this folder
	 *
	 * @return bool
	 */
	public function isCreatable() {
		return $this->checkPermissions(\OCP\Constants::PERMISSION_CREATE);
	}

	/**
	 * @return bool
	 */
	public function isDeletable() {
		return $this->checkPermissions(\OCP\Constants::PERMISSION_DELETE);
	}

	/**
	 * @return bool
	 */
	public function isShareable() {
		return $this->checkPermissions(\OCP\Constants::PERMISSION_SHARE);
	}

	/**
	 * Check if a file or folder is shared
	 *
	 * @return bool
	 */
	public function isShared() {
		$sid = $this->getStorage()->getId();
		if (!is_null($sid)) {
			$sid = explode(':', $sid);
			return ($sid[0] === 'shared');
		}

		return false;
	}

	public function isMounted() {
		$storage = $this->getStorage();
		if ($storage->instanceOfStorage('\OCP\Files\IHomeStorage')) {
			return false;
		}
		$sid = $storage->getId();
		if (!is_null($sid)) {
			$sid = explode(':', $sid);
			return ($sid[0] !== 'home' and $sid[0] !== 'shared');
		}

		return false;
	}

	/**
	 * Get the mountpoint the file belongs to
	 *
	 * @return \OCP\Files\Mount\IMountPoint
	 */
	public function getMountPoint() {
		return $this->mount;
	}

	/**
	 * Get the owner of the file
	 *
	 * @return \OCP\IUser
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * @param IMountPoint[] $mounts
	 */
	public function setSubMounts(array $mounts) {
		$this->subMounts = $mounts;
	}

	private function updateEntryfromSubMounts() {
		if ($this->subMountsUsed) {
			return;
		}
		$this->subMountsUsed = true;
		foreach ($this->subMounts as $mount) {
			$subStorage = $mount->getStorage();
			if ($subStorage) {
				$subCache = $subStorage->getCache('');
				$rootEntry = $subCache->get('');
				$this->addSubEntry($rootEntry, $mount->getMountPoint());
			}
		}
	}

	/**
	 * Add a cache entry which is the child of this folder
	 *
	 * Sets the size, etag and size to for cross-storage childs
	 *
	 * @param array|ICacheEntry $data cache entry for the child
	 * @param string $entryPath full path of the child entry
	 */
	public function addSubEntry($data, $entryPath) {
		$this->data['size'] += isset($data['size']) ? $data['size'] : 0;
		if (isset($data['mtime'])) {
			$this->data['mtime'] = max($this->data['mtime'], $data['mtime']);
		}
		if (isset($data['etag'])) {
			// prefix the etag with the relative path of the subentry to propagate etag on mount moves
			$relativeEntryPath = substr($entryPath, strlen($this->getPath()));
			// attach the permissions to propagate etag on permision changes of submounts
			$permissions = isset($data['permissions']) ? $data['permissions'] : 0;
			$this->childEtags[] = $relativeEntryPath . '/' . $data['etag'] . $permissions;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getChecksum() {
		return $this->data['checksum'];
	}

	public function getExtension(): string {
		return pathinfo($this->getName(), PATHINFO_EXTENSION);
	}

	public function getCreationTime(): int {
		return (int) $this->data['creation_time'];
	}

	public function getUploadTime(): int {
		return (int) $this->data['upload_time'];
	}
}
