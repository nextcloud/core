<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Encryption\Keys;

use OC\Encryption\Util;
use OC\Files\Filesystem;
use OC\Files\View;
use OC\ServerNotAvailableException;
use OC\User\NoUserException;
use OCP\Encryption\Keys\IStorage;
use OCP\IConfig;
use OCP\Security\ICrypto;

class Storage implements IStorage {

	// hidden file which indicate that the folder is a valid key storage
	public const KEY_STORAGE_MARKER = '.oc_key_storage';

	/** @var View */
	private $view;

	/** @var Util */
	private $util;

	// base dir where all the file related keys are stored
	/** @var string */
	private $keys_base_dir;

	// root of the key storage default is empty which means that we use the data folder
	/** @var string */
	private $root_dir;

	/** @var string */
	private $encryption_base_dir;

	/** @var string */
	private $backup_base_dir;

	/** @var array */
	private $keyCache = [];

	/** @var ICrypto */
	private $crypto;

	/** @var IConfig */
	private $config;

	/**
	 * @param View $view
	 * @param Util $util
	 */
	public function __construct(View $view, Util $util, ICrypto $crypto, IConfig $config) {
		$this->view = $view;
		$this->util = $util;

		$this->encryption_base_dir = '/files_encryption';
		$this->keys_base_dir = $this->encryption_base_dir .'/keys';
		$this->backup_base_dir = $this->encryption_base_dir .'/backup';
		$this->root_dir = $this->util->getKeyStorageRoot();
		$this->crypto = $crypto;
		$this->config = $config;
	}

	/**
	 * @inheritdoc
	 */
	public function getUserKey($uid, $keyId, $encryptionModuleId) {
		$path = $this->constructUserKeyPath($encryptionModuleId, $keyId, $uid);
		return base64_decode($this->getKeyWithUid($path, $uid));
	}

	/**
	 * @inheritdoc
	 */
	public function getFileKey($path, $keyId, $encryptionModuleId) {
		$realFile = $this->util->stripPartialFileExtension($path);
		$keyDir = $this->getFileKeyDir($encryptionModuleId, $realFile);
		$key = $this->getKey($keyDir . $keyId)['key'];

		if ($key === '' && $realFile !== $path) {
			// Check if the part file has keys and use them, if no normal keys
			// exist. This is required to fix copyBetweenStorage() when we
			// rename a .part file over storage borders.
			$keyDir = $this->getFileKeyDir($encryptionModuleId, $path);
			$key = $this->getKey($keyDir . $keyId)['key'];
		}

		return base64_decode($key);
	}

	/**
	 * @inheritdoc
	 */
	public function getSystemUserKey($keyId, $encryptionModuleId) {
		$path = $this->constructUserKeyPath($encryptionModuleId, $keyId, null);
		return base64_decode($this->getKeyWithUid($path, null));
	}

	/**
	 * @inheritdoc
	 */
	public function setUserKey($uid, $keyId, $key, $encryptionModuleId) {
		$path = $this->constructUserKeyPath($encryptionModuleId, $keyId, $uid);
		return $this->setKey($path, [
			'key' => base64_encode($key),
			'uid' => $uid,
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function setFileKey($path, $keyId, $key, $encryptionModuleId) {
		$keyDir = $this->getFileKeyDir($encryptionModuleId, $path);
		return $this->setKey($keyDir . $keyId, [
			'key' => base64_encode($key),
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function setSystemUserKey($keyId, $key, $encryptionModuleId) {
		$path = $this->constructUserKeyPath($encryptionModuleId, $keyId, null);
		return $this->setKey($path, [
			'key' => base64_encode($key),
			'uid' => null,
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function deleteUserKey($uid, $keyId, $encryptionModuleId) {
		try {
			$path = $this->constructUserKeyPath($encryptionModuleId, $keyId, $uid);
			return !$this->view->file_exists($path) || $this->view->unlink($path);
		} catch (NoUserException $e) {
			// this exception can come from initMountPoints() from setupUserMounts()
			// for a deleted user.
			//
			// It means, that:
			// - we are not running in alternative storage mode because we don't call
			// initMountPoints() in that mode
			// - the keys were in the user's home but since the user was deleted, the
			// user's home is gone and so are the keys
			//
			// So there is nothing to do, just ignore.
		}
	}

	/**
	 * @inheritdoc
	 */
	public function deleteFileKey($path, $keyId, $encryptionModuleId) {
		$keyDir = $this->getFileKeyDir($encryptionModuleId, $path);
		return !$this->view->file_exists($keyDir . $keyId) || $this->view->unlink($keyDir . $keyId);
	}

	/**
	 * @inheritdoc
	 */
	public function deleteAllFileKeys($path) {
		$keyDir = $this->getFileKeyDir('', $path);
		return !$this->view->file_exists($keyDir) || $this->view->deleteAll($keyDir);
	}

	/**
	 * @inheritdoc
	 */
	public function deleteSystemUserKey($keyId, $encryptionModuleId) {
		$path = $this->constructUserKeyPath($encryptionModuleId, $keyId, null);
		return !$this->view->file_exists($path) || $this->view->unlink($path);
	}

	/**
	 * construct path to users key
	 *
	 * @param string $encryptionModuleId
	 * @param string $keyId
	 * @param string $uid
	 * @return string
	 */
	protected function constructUserKeyPath($encryptionModuleId, $keyId, $uid) {
		if ($uid === null) {
			$path = $this->root_dir . '/' . $this->encryption_base_dir . '/' . $encryptionModuleId . '/' . $keyId;
		} else {
			$path = $this->root_dir . '/' . $uid . $this->encryption_base_dir . '/'
				. $encryptionModuleId . '/' . $uid . '.' . $keyId;
		}

		return \OC\Files\Filesystem::normalizePath($path);
	}

	/**
	 * @param string $path
	 * @param string|null $uid
	 * @return string
	 * @throws ServerNotAvailableException
	 *
	 * Small helper function to fetch the key and verify the value for user and system keys
	 */
	private function getKeyWithUid(string $path, ?string $uid): string {
		$data = $this->getKey($path);

		if (!isset($data['key'])) {
			throw new ServerNotAvailableException('Key is invalid');
		}

		if ($data['key'] === '') {
			return '';
		}

		if (!array_key_exists('uid', $data) || $data['uid'] !== $uid) {
			// If the migration is done we error out
			$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0.0');
			if (version_compare($versionFromBeforeUpdate, '20.0.0.1', '<=')) {
				return $data['key'];
			}

			if ($this->config->getSystemValueBool('encryption.key_storage_migrated', true)) {
				throw new ServerNotAvailableException('Key has been modified');
			} else {
				//Otherwise we migrate
				$data['uid'] = $uid;
				$this->setKey($path, $data);
			}
		}

		return $data['key'];
	}

	/**
	 * read key from hard disk
	 *
	 * @param string $path to key
	 * @return array containing key as base64encoded key, and possible the uid
	 */
	private function getKey($path): array {
		$key = [
			'key' => '',
		];

		if ($this->view->file_exists($path)) {
			if (isset($this->keyCache[$path])) {
				$key = $this->keyCache[$path];
			} else {
				$data = $this->view->file_get_contents($path);

				// Version <20.0.0.1 doesn't have this
				$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0.0');
				if (version_compare($versionFromBeforeUpdate, '20.0.0.1', '<=')) {
					$key = [
						'key' => base64_encode($data),
					];
				} else {
					if ($this->config->getSystemValueBool('encryption.key_storage_migrated', true)) {
						try {
							$clearData = $this->crypto->decrypt($data);
						} catch (\Exception $e) {
							throw new ServerNotAvailableException('Could not decrypt key', 0, $e);
						}

						$dataArray = json_decode($clearData, true);
						if ($dataArray === null) {
							throw new ServerNotAvailableException('Invalid encryption key');
						}

						$key = $dataArray;
					} else {
						/*
						 * Even if not all keys are migrated we should still try to decrypt it (in case some have moved).
						 * However it is only a failure now if it is an array and decryption fails
						 */
						$fallback = false;
						try {
							$clearData = $this->crypto->decrypt($data);
						} catch (\Throwable $e) {
							$fallback = true;
						}

						if (!$fallback) {
							$dataArray = json_decode($clearData, true);
							if ($dataArray === null) {
								throw new ServerNotAvailableException('Invalid encryption key');
							}
							$key = $dataArray;
						} else {
							$key = [
								'key' => base64_encode($data),
							];
						}
					}
				}

				$this->keyCache[$path] = $key;
			}
		}

		return $key;
	}

	/**
	 * write key to disk
	 *
	 *
	 * @param string $path path to key directory
	 * @param array $key key
	 * @return bool
	 */
	private function setKey($path, $key) {
		$this->keySetPreparation(dirname($path));

		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0.0');
		if (version_compare($versionFromBeforeUpdate, '20.0.0.1', '<=')) {
			// Only store old format if this happens during the migration.
			// TODO: Remove for 21
			$data = base64_decode($key['key']);
		} else {
			// Wrap the data
			$data = $this->crypto->encrypt(json_encode($key));
		}

		$result = $this->view->file_put_contents($path, $data);

		if (is_int($result) && $result > 0) {
			$this->keyCache[$path] = $key;
			return true;
		}

		return false;
	}

	/**
	 * get path to key folder for a given file
	 *
	 * @param string $encryptionModuleId
	 * @param string $path path to the file, relative to data/
	 * @return string
	 */
	private function getFileKeyDir($encryptionModuleId, $path) {
		list($owner, $filename) = $this->util->getUidAndFilename($path);

		// in case of system wide mount points the keys are stored directly in the data directory
		if ($this->util->isSystemWideMountPoint($filename, $owner)) {
			$keyPath = $this->root_dir . '/' . $this->keys_base_dir . $filename . '/';
		} else {
			$keyPath = $this->root_dir . '/' . $owner . $this->keys_base_dir . $filename . '/';
		}

		return Filesystem::normalizePath($keyPath . $encryptionModuleId . '/', false);
	}

	/**
	 * move keys if a file was renamed
	 *
	 * @param string $source
	 * @param string $target
	 * @return boolean
	 */
	public function renameKeys($source, $target) {
		$sourcePath = $this->getPathToKeys($source);
		$targetPath = $this->getPathToKeys($target);

		if ($this->view->file_exists($sourcePath)) {
			$this->keySetPreparation(dirname($targetPath));
			$this->view->rename($sourcePath, $targetPath);

			return true;
		}

		return false;
	}


	/**
	 * copy keys if a file was renamed
	 *
	 * @param string $source
	 * @param string $target
	 * @return boolean
	 */
	public function copyKeys($source, $target) {
		$sourcePath = $this->getPathToKeys($source);
		$targetPath = $this->getPathToKeys($target);

		if ($this->view->file_exists($sourcePath)) {
			$this->keySetPreparation(dirname($targetPath));
			$this->view->copy($sourcePath, $targetPath);
			return true;
		}

		return false;
	}

	/**
	 * backup keys of a given encryption module
	 *
	 * @param string $encryptionModuleId
	 * @param string $purpose
	 * @param string $uid
	 * @return bool
	 * @since 12.0.0
	 */
	public function backupUserKeys($encryptionModuleId, $purpose, $uid) {
		$source = $uid . $this->encryption_base_dir . '/' . $encryptionModuleId;
		$backupDir = $uid . $this->backup_base_dir;
		if (!$this->view->file_exists($backupDir)) {
			$this->view->mkdir($backupDir);
		}

		$backupDir = $backupDir . '/' . $purpose . '.' . $encryptionModuleId . '.' . $this->getTimestamp();
		$this->view->mkdir($backupDir);

		return $this->view->copy($source, $backupDir);
	}

	/**
	 * get the current timestamp
	 *
	 * @return int
	 */
	protected function getTimestamp() {
		return time();
	}

	/**
	 * get system wide path and detect mount points
	 *
	 * @param string $path
	 * @return string
	 */
	protected function getPathToKeys($path) {
		list($owner, $relativePath) = $this->util->getUidAndFilename($path);
		$systemWideMountPoint = $this->util->isSystemWideMountPoint($relativePath, $owner);

		if ($systemWideMountPoint) {
			$systemPath = $this->root_dir . '/' . $this->keys_base_dir . $relativePath . '/';
		} else {
			$systemPath = $this->root_dir . '/' . $owner . $this->keys_base_dir . $relativePath . '/';
		}

		return  Filesystem::normalizePath($systemPath, false);
	}

	/**
	 * Make preparations to filesystem for saving a key file
	 *
	 * @param string $path relative to the views root
	 */
	protected function keySetPreparation($path) {
		// If the file resides within a subdirectory, create it
		if (!$this->view->file_exists($path)) {
			$sub_dirs = explode('/', ltrim($path, '/'));
			$dir = '';
			foreach ($sub_dirs as $sub_dir) {
				$dir .= '/' . $sub_dir;
				if (!$this->view->is_dir($dir)) {
					$this->view->mkdir($dir);
				}
			}
		}
	}
}
