<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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

use OC\Files\Cache\Wrapper\CachePermissionsMask;
use OCP\Constants;

/**
 * Mask the permissions of a storage
 *
 * This can be used to restrict update, create, delete and/or share permissions of a storage
 *
 * Note that the read permissions can't be masked
 */
class PermissionsMask extends Wrapper {
	/**
	 * @var int the permissions bits we want to keep
	 */
	private $mask;

	/**
	 * @param array $arguments ['storage' => $storage, 'mask' => $mask]
	 *
	 * $storage: The storage the permissions mask should be applied on
	 * $mask: The permission bits that should be kept, a combination of the \OCP\Constant::PERMISSION_ constants
	 */
	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->mask = $arguments['mask'];
	}

	private function checkMask($permissions) {
		return ($this->mask & $permissions) === $permissions;
	}

	public function isUpdatable($path) {
		return $this->checkMask(Constants::PERMISSION_UPDATE) and parent::isUpdatable($path);
	}

	public function isCreatable($path) {
		return $this->checkMask(Constants::PERMISSION_CREATE) and parent::isCreatable($path);
	}

	public function isDeletable($path) {
		return $this->checkMask(Constants::PERMISSION_DELETE) and parent::isDeletable($path);
	}

	public function isSharable($path) {
		return $this->checkMask(Constants::PERMISSION_SHARE) and parent::isSharable($path);
	}

	public function getPermissions($path) {
		return $this->storage->getPermissions($path) & $this->mask;
	}

	public function rename($path1, $path2) {
		$p = strpos($path1, $path2);
		if ($p === 0) {
			$part = substr($path1, strlen($path2));
			//This is a rename of the transfer file to the original file
			if (strpos($part, '.ocTransferId') === 0) {
				return $this->checkMask(Constants::PERMISSION_CREATE) and parent::rename($path1, $path2);
			}
		}
		return $this->checkMask(Constants::PERMISSION_UPDATE) and parent::rename($path1, $path2);
	}

	public function copy($path1, $path2) {
		return $this->checkMask(Constants::PERMISSION_CREATE) and parent::copy($path1, $path2);
	}

	public function touch($path, $mtime = null) {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkMask($permissions) and parent::touch($path, $mtime);
	}

	public function mkdir($path) {
		return $this->checkMask(Constants::PERMISSION_CREATE) and parent::mkdir($path);
	}

	public function rmdir($path) {
		return $this->checkMask(Constants::PERMISSION_DELETE) and parent::rmdir($path);
	}

	public function unlink($path) {
		return $this->checkMask(Constants::PERMISSION_DELETE) and parent::unlink($path);
	}

	public function file_put_contents($path, $data) {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkMask($permissions) ? parent::file_put_contents($path, $data) : false;
	}

	public function fopen($path, $mode) {
		if ($mode === 'r' or $mode === 'rb') {
			return parent::fopen($path, $mode);
		} else {
			$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
			return $this->checkMask($permissions) ? parent::fopen($path, $mode) : false;
		}
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
			$storage = $this;
		}
		$sourceCache = parent::getCache($path, $storage);
		return new CachePermissionsMask($sourceCache, $this->mask);
	}

	public function getMetaData($path) {
		$data = parent::getMetaData($path);

		if ($data && isset($data['permissions'])) {
			$data['scan_permissions'] = isset($data['scan_permissions']) ? $data['scan_permissions'] : $data['permissions'];
			$data['permissions'] &= $this->mask;
		}
		return $data;
	}

	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this->storage;
		}
		return parent::getScanner($path, $storage);
	}
}
