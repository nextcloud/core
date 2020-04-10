<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author scambra <sergio@entrecables.com>
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

namespace OCA\Files_Sharing;

use OC\Files\Cache\FailedCache;
use OC\Files\Filesystem;
use OC\Files\Storage\FailedStorage;
use OC\Files\Storage\Wrapper\PermissionsMask;
use OC\User\NoUserException;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IDisableEncryptionStorage;
use OCP\Files\Storage\IStorage;
use OCP\Lock\ILockingProvider;

/**
 * Convert target path to source path and pass the function call to the correct storage provider
 */
class SharedStorage extends \OC\Files\Storage\Wrapper\Jail implements ISharedStorage, IDisableEncryptionStorage {

	/** @var \OCP\Share\IShare */
	private $superShare;

	/** @var \OCP\Share\IShare[] */
	private $groupedShares;

	/**
	 * @var \OC\Files\View
	 */
	private $ownerView;

	private $initialized = false;

	/**
	 * @var ICacheEntry
	 */
	private $sourceRootInfo;

	/** @var string */
	private $user;

	/**
	 * @var \OCP\ILogger
	 */
	private $logger;

	/** @var  IStorage */
	private $nonMaskedStorage;

	private $options;

	/** @var boolean */
	private $sharingDisabledForUser;

	public function __construct($arguments) {
		$this->ownerView = $arguments['ownerView'];
		$this->logger = \OC::$server->getLogger();

		$this->superShare = $arguments['superShare'];
		$this->groupedShares = $arguments['groupedShares'];

		$this->user = $arguments['user'];
		if (isset($arguments['sharingDisabledForUser'])) {
			$this->sharingDisabledForUser = $arguments['sharingDisabledForUser'];
		} else {
			$this->sharingDisabledForUser = false;
		}

		parent::__construct([
			'storage' => null,
			'root' => null,
		]);
	}

	/**
	 * @return ICacheEntry
	 */
	private function getSourceRootInfo() {
		if (is_null($this->sourceRootInfo)) {
			if (is_null($this->superShare->getNodeCacheEntry())) {
				$this->init();
				$this->sourceRootInfo = $this->nonMaskedStorage->getCache()->get($this->rootPath);
			} else {
				$this->sourceRootInfo = $this->superShare->getNodeCacheEntry();
			}
		}
		return $this->sourceRootInfo;
	}

	private function init() {
		if ($this->initialized) {
			return;
		}
		$this->initialized = true;
		try {
			Filesystem::initMountPoints($this->superShare->getShareOwner());
			$sourcePath = $this->ownerView->getPath($this->superShare->getNodeId());
			list($this->nonMaskedStorage, $this->rootPath) = $this->ownerView->resolvePath($sourcePath);
			$this->storage = new PermissionsMask([
				'storage' => $this->nonMaskedStorage,
				'mask' => $this->superShare->getPermissions()
			]);
		} catch (NotFoundException $e) {
			// original file not accessible or deleted, set FailedStorage
			$this->storage = new FailedStorage(['exception' => $e]);
			$this->cache = new FailedCache();
			$this->rootPath = '';
		} catch (NoUserException $e) {
			// sharer user deleted, set FailedStorage
			$this->storage = new FailedStorage(['exception' => $e]);
			$this->cache = new FailedCache();
			$this->rootPath = '';
		} catch (\Exception $e) {
			$this->storage = new FailedStorage(['exception' => $e]);
			$this->cache = new FailedCache();
			$this->rootPath = '';
			$this->logger->logException($e);
		}

		if (!$this->nonMaskedStorage) {
			$this->nonMaskedStorage = $this->storage;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function instanceOfStorage($class) {
		if ($class === '\OC\Files\Storage\Common') {
			return true;
		}
		if (in_array($class, ['\OC\Files\Storage\Home', '\OC\Files\ObjectStore\HomeObjectStoreStorage'])) {
			return false;
		}
		return parent::instanceOfStorage($class);
	}

	/**
	 * @return string
	 */
	public function getShareId() {
		return $this->superShare->getId();
	}

	private function isValid() {
		return $this->getSourceRootInfo() && ($this->getSourceRootInfo()->getPermissions() & Constants::PERMISSION_SHARE) === Constants::PERMISSION_SHARE;
	}

	/**
	 * get id of the mount point
	 *
	 * @return string
	 */
	public function getId() {
		return 'shared::' . $this->getMountPoint();
	}

	/**
	 * Get the permissions granted for a shared file
	 *
	 * @param string $target Shared target file path
	 * @return int CRUDS permissions granted
	 */
	public function getPermissions($target = '') {
		if (!$this->isValid()) {
			return 0;
		}
		$permissions = parent::getPermissions($target) & $this->superShare->getPermissions();

		// part files and the mount point always have delete permissions
		if ($target === '' || pathinfo($target, PATHINFO_EXTENSION) === 'part') {
			$permissions |= \OCP\Constants::PERMISSION_DELETE;
		}

		if ($this->sharingDisabledForUser) {
			$permissions &= ~\OCP\Constants::PERMISSION_SHARE;
		}

		return $permissions;
	}

	public function isCreatable($path) {
		return ($this->getPermissions($path) & \OCP\Constants::PERMISSION_CREATE);
	}

	public function isReadable($path) {
		if (!$this->isValid()) {
			return false;
		}
		if (!$this->file_exists($path)) {
			return false;
		}
		/** @var IStorage $storage */
		/** @var string $internalPath */
		list($storage, $internalPath) = $this->resolvePath($path);
		return $storage->isReadable($internalPath);
	}

	public function isUpdatable($path) {
		return ($this->getPermissions($path) & \OCP\Constants::PERMISSION_UPDATE);
	}

	public function isDeletable($path) {
		return ($this->getPermissions($path) & \OCP\Constants::PERMISSION_DELETE);
	}

	public function isSharable($path) {
		if (\OCP\Util::isSharingDisabledForUser() || !\OC\Share\Share::isResharingAllowed()) {
			return false;
		}
		return ($this->getPermissions($path) & \OCP\Constants::PERMISSION_SHARE);
	}

	public function fopen($path, $mode) {
		if ($source = $this->getUnjailedPath($path)) {
			switch ($mode) {
				case 'r+':
				case 'rb+':
				case 'w+':
				case 'wb+':
				case 'x+':
				case 'xb+':
				case 'a+':
				case 'ab+':
				case 'w':
				case 'wb':
				case 'x':
				case 'xb':
				case 'a':
				case 'ab':
					$creatable = $this->isCreatable(dirname($path));
					$updatable = $this->isUpdatable($path);
					// if neither permissions given, no need to continue
					if (!$creatable && !$updatable) {
						if (pathinfo($path, PATHINFO_EXTENSION) === 'part') {
							$updatable = $this->isUpdatable(dirname($path));
						}

						if (!$updatable) {
							return false;
						}
					}

					$exists = $this->file_exists($path);
					// if a file exists, updatable permissions are required
					if ($exists && !$updatable) {
						return false;
					}

					// part file is allowed if !$creatable but the final file is $updatable
					if (pathinfo($path, PATHINFO_EXTENSION) !== 'part') {
						if (!$exists && !$creatable) {
							return false;
						}
					}
			}
			$info = [
				'target' => $this->getMountPoint() . $path,
				'source' => $source,
				'mode' => $mode,
			];
			\OCP\Util::emitHook('\OC\Files\Storage\Shared', 'fopen', $info);
			return $this->nonMaskedStorage->fopen($this->getUnjailedPath($path), $mode);
		}
		return false;
	}

	/**
	 * see http://php.net/manual/en/function.rename.php
	 *
	 * @param string $path1
	 * @param string $path2
	 * @return bool
	 */
	public function rename($path1, $path2) {
		$this->init();
		$isPartFile = pathinfo($path1, PATHINFO_EXTENSION) === 'part';
		$targetExists = $this->file_exists($path2);
		$sameFodler = dirname($path1) === dirname($path2);

		if ($targetExists || ($sameFodler && !$isPartFile)) {
			if (!$this->isUpdatable('')) {
				return false;
			}
		} else {
			if (!$this->isCreatable('')) {
				return false;
			}
		}

		return $this->nonMaskedStorage->rename($this->getUnjailedPath($path1), $this->getUnjailedPath($path2));
	}

	/**
	 * return mount point of share, relative to data/user/files
	 *
	 * @return string
	 */
	public function getMountPoint() {
		return $this->superShare->getTarget();
	}

	/**
	 * @param string $path
	 */
	public function setMountPoint($path) {
		$this->superShare->setTarget($path);

		foreach ($this->groupedShares as $share) {
			$share->setTarget($path);
		}
	}

	/**
	 * get the user who shared the file
	 *
	 * @return string
	 */
	public function getSharedFrom() {
		return $this->superShare->getShareOwner();
	}

	/**
	 * @return \OCP\Share\IShare
	 */
	public function getShare() {
		return $this->superShare;
	}

	/**
	 * return share type, can be "file" or "folder"
	 *
	 * @return string
	 */
	public function getItemType() {
		return $this->superShare->getNodeType();
	}

	/**
	 * @param string $path
	 * @param null $storage
	 * @return Cache
	 */
	public function getCache($path = '', $storage = null) {
		if ($this->cache) {
			return $this->cache;
		}
		if (!$storage) {
			$storage = $this;
		}
		$sourceRoot  = $this->getSourceRootInfo();
		if ($this->storage instanceof FailedStorage) {
			return new FailedCache();
		}

		$this->cache = new \OCA\Files_Sharing\Cache($storage, $sourceRoot, $this->superShare);
		return $this->cache;
	}

	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		return new \OCA\Files_Sharing\Scanner($storage);
	}

	public function getOwner($path) {
		return $this->superShare->getShareOwner();
	}

	/**
	 * unshare complete storage, also the grouped shares
	 *
	 * @return bool
	 */
	public function unshareStorage() {
		foreach ($this->groupedShares as $share) {
			\OC::$server->getShareManager()->deleteFromSelf($share, $this->user);
		}
		return true;
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock($path, $type, ILockingProvider $provider) {
		/** @var \OCP\Files\Storage $targetStorage */
		list($targetStorage, $targetInternalPath) = $this->resolvePath($path);
		$targetStorage->acquireLock($targetInternalPath, $type, $provider);
		// lock the parent folders of the owner when locking the share as recipient
		if ($path === '') {
			$sourcePath = $this->ownerView->getPath($this->superShare->getNodeId());
			$this->ownerView->lockFile(dirname($sourcePath), ILockingProvider::LOCK_SHARED, true);
		}
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function releaseLock($path, $type, ILockingProvider $provider) {
		/** @var \OCP\Files\Storage $targetStorage */
		list($targetStorage, $targetInternalPath) = $this->resolvePath($path);
		$targetStorage->releaseLock($targetInternalPath, $type, $provider);
		// unlock the parent folders of the owner when unlocking the share as recipient
		if ($path === '') {
			$sourcePath = $this->ownerView->getPath($this->superShare->getNodeId());
			$this->ownerView->unlockFile(dirname($sourcePath), ILockingProvider::LOCK_SHARED, true);
		}
	}

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function changeLock($path, $type, ILockingProvider $provider) {
		/** @var \OCP\Files\Storage $targetStorage */
		list($targetStorage, $targetInternalPath) = $this->resolvePath($path);
		$targetStorage->changeLock($targetInternalPath, $type, $provider);
	}

	/**
	 * @return array [ available, last_checked ]
	 */
	public function getAvailability() {
		// shares do not participate in availability logic
		return [
			'available' => true,
			'last_checked' => 0
		];
	}

	/**
	 * @param bool $available
	 */
	public function setAvailability($available) {
		// shares do not participate in availability logic
	}

	public function getSourceStorage() {
		$this->init();
		return $this->nonMaskedStorage;
	}

	public function getWrapperStorage() {
		$this->init();
		return $this->storage;
	}

	public function file_get_contents($path) {
		$info = [
			'target' => $this->getMountPoint() . '/' . $path,
			'source' => $this->getUnjailedPath($path),
		];
		\OCP\Util::emitHook('\OC\Files\Storage\Shared', 'file_get_contents', $info);
		return parent::file_get_contents($path);
	}

	public function file_put_contents($path, $data) {
		$info = [
			'target' => $this->getMountPoint() . '/' . $path,
			'source' => $this->getUnjailedPath($path),
		];
		\OCP\Util::emitHook('\OC\Files\Storage\Shared', 'file_put_contents', $info);
		return parent::file_put_contents($path, $data);
	}

	public function setMountOptions(array $options) {
		$this->mountOptions = $options;
	}
}
