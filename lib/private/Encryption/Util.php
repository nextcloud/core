<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Encryption;

use OC\Encryption\Exceptions\EncryptionHeaderKeyExistsException;
use OC\Encryption\Exceptions\EncryptionHeaderToLargeException;
use OC\Encryption\Exceptions\ModuleDoesNotExistsException;
use OC\Files\Filesystem;
use OC\Files\View;
use OCP\Encryption\IEncryptionModule;
use OCP\IConfig;
use OCP\IUser;

class Util {
	public const HEADER_START = 'HBEGIN';
	public const HEADER_END = 'HEND';
	public const HEADER_PADDING_CHAR = '-';

	public const HEADER_ENCRYPTION_MODULE_KEY = 'oc_encryption_module';

	/**
	 * block size will always be 8192 for a PHP stream
	 * @see https://bugs.php.net/bug.php?id=21641
	 * @var integer
	 */
	protected $headerSize = 8192;

	/**
	 * block size will always be 8192 for a PHP stream
	 * @see https://bugs.php.net/bug.php?id=21641
	 * @var integer
	 */
	protected $blockSize = 8192;

	/** @var View */
	protected $rootView;

	/** @var array */
	protected $ocHeaderKeys;

	/** @var \OC\User\Manager */
	protected $userManager;

	/** @var IConfig */
	protected $config;

	/** @var array paths excluded from encryption */
	protected $excludedPaths;

	/** @var \OC\Group\Manager $manager */
	protected $groupManager;

	/**
	 *
	 * @param View $rootView
	 * @param \OC\User\Manager $userManager
	 * @param \OC\Group\Manager $groupManager
	 * @param IConfig $config
	 */
	public function __construct(
		View $rootView,
		\OC\User\Manager $userManager,
		\OC\Group\Manager $groupManager,
		IConfig $config) {
		$this->ocHeaderKeys = [
			self::HEADER_ENCRYPTION_MODULE_KEY
		];

		$this->rootView = $rootView;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->config = $config;

		$this->excludedPaths[] = 'files_encryption';
		$this->excludedPaths[] = 'appdata_' . $config->getSystemValue('instanceid', null);
		$this->excludedPaths[] = 'files_external';
	}

	/**
	 * read encryption module ID from header
	 *
	 * @param array $header
	 * @return string
	 * @throws ModuleDoesNotExistsException
	 */
	public function getEncryptionModuleId(array $header = null) {
		$id = '';
		$encryptionModuleKey = self::HEADER_ENCRYPTION_MODULE_KEY;

		if (isset($header[$encryptionModuleKey])) {
			$id = $header[$encryptionModuleKey];
		} elseif (isset($header['cipher'])) {
			if (class_exists('\OCA\Encryption\Crypto\Encryption')) {
				// fall back to default encryption if the user migrated from
				// ownCloud <= 8.0 with the old encryption
				$id = \OCA\Encryption\Crypto\Encryption::ID;
			} else {
				throw new ModuleDoesNotExistsException('Default encryption module missing');
			}
		}

		return $id;
	}

	/**
	 * create header for encrypted file
	 *
	 * @param array $headerData
	 * @param IEncryptionModule $encryptionModule
	 * @return string
	 * @throws EncryptionHeaderToLargeException if header has to many arguments
	 * @throws EncryptionHeaderKeyExistsException if header key is already in use
	 */
	public function createHeader(array $headerData, IEncryptionModule $encryptionModule) {
		$header = self::HEADER_START . ':' . self::HEADER_ENCRYPTION_MODULE_KEY . ':' . $encryptionModule->getId() . ':';
		foreach ($headerData as $key => $value) {
			if (in_array($key, $this->ocHeaderKeys)) {
				throw new EncryptionHeaderKeyExistsException($key);
			}
			$header .= $key . ':' . $value . ':';
		}
		$header .= self::HEADER_END;

		if (strlen($header) > $this->getHeaderSize()) {
			throw new EncryptionHeaderToLargeException();
		}

		$paddedHeader = str_pad($header, $this->headerSize, self::HEADER_PADDING_CHAR, STR_PAD_RIGHT);

		return $paddedHeader;
	}

	/**
	 * go recursively through a dir and collect all files and sub files.
	 *
	 * @param string $dir relative to the users files folder
	 * @return array with list of files relative to the users files folder
	 */
	public function getAllFiles($dir) {
		$result = [];
		$dirList = [$dir];

		while ($dirList) {
			$dir = array_pop($dirList);
			$content = $this->rootView->getDirectoryContent($dir);

			foreach ($content as $c) {
				if ($c->getType() === 'dir') {
					$dirList[] = $c->getPath();
				} else {
					$result[] = $c->getPath();
				}
			}
		}

		return $result;
	}

	/**
	 * check if it is a file uploaded by the user stored in data/user/files
	 * or a metadata file
	 *
	 * @param string $path relative to the data/ folder
	 * @return boolean
	 */
	public function isFile($path) {
		$parts = explode('/', Filesystem::normalizePath($path), 4);
		if (isset($parts[2]) && $parts[2] === 'files') {
			return true;
		}
		return false;
	}

	/**
	 * return size of encryption header
	 *
	 * @return integer
	 */
	public function getHeaderSize() {
		return $this->headerSize;
	}

	/**
	 * return size of block read by a PHP stream
	 *
	 * @return integer
	 */
	public function getBlockSize() {
		return $this->blockSize;
	}

	/**
	 * get the owner and the path for the file relative to the owners files folder
	 *
	 * @param string $path
	 * @return array
	 * @throws \BadMethodCallException
	 */
	public function getUidAndFilename($path) {
		$parts = explode('/', $path);
		$uid = '';
		if (count($parts) > 2) {
			$uid = $parts[1];
		}
		if (!$this->userManager->userExists($uid)) {
			throw new \BadMethodCallException(
				'path needs to be relative to the system wide data folder and point to a user specific file'
			);
		}

		$ownerPath = implode('/', array_slice($parts, 2));

		return [$uid, Filesystem::normalizePath($ownerPath)];
	}

	/**
	 * Remove .path extension from a file path
	 * @param string $path Path that may identify a .part file
	 * @return string File path without .part extension
	 * @note this is needed for reusing keys
	 */
	public function stripPartialFileExtension($path) {
		$extension = pathinfo($path, PATHINFO_EXTENSION);

		if ($extension === 'part') {
			$newLength = strlen($path) - 5; // 5 = strlen(".part")
			$fPath = substr($path, 0, $newLength);

			// if path also contains a transaction id, we remove it too
			$extension = pathinfo($fPath, PATHINFO_EXTENSION);
			if (substr($extension, 0, 12) === 'ocTransferId') { // 12 = strlen("ocTransferId")
				$newLength = strlen($fPath) - strlen($extension) - 1;
				$fPath = substr($fPath, 0, $newLength);
			}
			return $fPath;
		} else {
			return $path;
		}
	}

	public function getUserWithAccessToMountPoint($users, $groups) {
		$result = [];
		if (in_array('all', $users)) {
			$users = $this->userManager->search('', null, null);
			$result = array_map(function (IUser $user) {
				return $user->getUID();
			}, $users);
		} else {
			$result = array_merge($result, $users);

			$groupManager = \OC::$server->getGroupManager();
			foreach ($groups as $group) {
				$groupObject = $groupManager->get($group);
				if ($groupObject) {
					$foundUsers = $groupObject->searchUsers('', -1, 0);
					$userIds = [];
					foreach ($foundUsers as $user) {
						$userIds[] = $user->getUID();
					}
					$result = array_merge($result, $userIds);
				}
			}
		}

		return $result;
	}

	/**
	 * check if the file is stored on a system wide mount point
	 * @param string $path relative to /data/user with leading '/'
	 * @param string $uid
	 * @return boolean
	 */
	public function isSystemWideMountPoint($path, $uid) {
		if (\OCP\App::isEnabled("files_external")) {
			$mounts = \OCA\Files_External\MountConfig::getSystemMountPoints();
			foreach ($mounts as $mount) {
				if (strpos($path, '/files/' . $mount['mountpoint']) === 0) {
					if ($this->isMountPointApplicableToUser($mount, $uid)) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * check if mount point is applicable to user
	 *
	 * @param array $mount contains $mount['applicable']['users'], $mount['applicable']['groups']
	 * @param string $uid
	 * @return boolean
	 */
	private function isMountPointApplicableToUser($mount, $uid) {
		$acceptedUids = ['all', $uid];
		// check if mount point is applicable for the user
		$intersection = array_intersect($acceptedUids, $mount['applicable']['users']);
		if (!empty($intersection)) {
			return true;
		}
		// check if mount point is applicable for group where the user is a member
		foreach ($mount['applicable']['groups'] as $gid) {
			if ($this->groupManager->isInGroup($uid, $gid)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * check if it is a path which is excluded by ownCloud from encryption
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function isExcluded($path) {
		$normalizedPath = Filesystem::normalizePath($path);
		$root = explode('/', $normalizedPath, 4);
		if (count($root) > 1) {

			// detect alternative key storage root
			$rootDir = $this->getKeyStorageRoot();
			if ($rootDir !== '' &&
				0 === strpos(
					Filesystem::normalizePath($path),
					Filesystem::normalizePath($rootDir)
				)
			) {
				return true;
			}


			//detect system wide folders
			if (in_array($root[1], $this->excludedPaths)) {
				return true;
			}

			// detect user specific folders
			if ($this->userManager->userExists($root[1])
				&& in_array($root[2], $this->excludedPaths)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * check if recovery key is enabled for user
	 *
	 * @param string $uid
	 * @return boolean
	 */
	public function recoveryEnabled($uid) {
		$enabled = $this->config->getUserValue($uid, 'encryption', 'recovery_enabled', '0');

		return $enabled === '1';
	}

	/**
	 * set new key storage root
	 *
	 * @param string $root new key store root relative to the data folder
	 */
	public function setKeyStorageRoot($root) {
		$this->config->setAppValue('core', 'encryption_key_storage_root', $root);
	}

	/**
	 * get key storage root
	 *
	 * @return string key storage root
	 */
	public function getKeyStorageRoot() {
		return $this->config->getAppValue('core', 'encryption_key_storage_root', '');
	}
}
