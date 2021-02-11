<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Frédéric Fortier <frederic.fortier@oronospolytechnique.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Sharing;

use OC\Cache\CappedMemoryCache;
use OC\Files\Filesystem;
use OC\Files\Mount\MountPoint;
use OC\Files\Mount\MoveableMount;
use OC\Files\View;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Storage\IStorageFactory;
use OCP\Share\Events\VerifyMountPointEvent;

/**
 * Shared mount points can be moved by the user
 */
class SharedMount extends MountPoint implements MoveableMount {
	/**
	 * @var \OCA\Files_Sharing\SharedStorage $storage
	 */
	protected $storage = null;

	/**
	 * @var \OC\Files\View
	 */
	private $recipientView;

	/**
	 * @var string
	 */
	private $user;

	/** @var \OCP\Share\IShare */
	private $superShare;

	/** @var \OCP\Share\IShare[] */
	private $groupedShares;

	/**
	 * @param string $storage
	 * @param SharedMount[] $mountpoints
	 * @param array $arguments
	 * @param IStorageFactory $loader
	 * @param View $recipientView
	 */
	public function __construct($storage, array $mountpoints, $arguments, IStorageFactory $loader, View $recipientView, CappedMemoryCache $folderExistCache) {
		$this->user = $arguments['user'];
		$this->recipientView = $recipientView;

		$this->superShare = $arguments['superShare'];
		$this->groupedShares = $arguments['groupedShares'];

		$newMountPoint = $this->verifyMountPoint($this->superShare, $mountpoints, $folderExistCache);
		$absMountPoint = '/' . $this->user . '/files' . $newMountPoint;
		parent::__construct($storage, $absMountPoint, $arguments, $loader);
	}

	/**
	 * check if the parent folder exists otherwise move the mount point up
	 *
	 * @param \OCP\Share\IShare $share
	 * @param SharedMount[] $mountpoints
	 * @return string
	 */
	private function verifyMountPoint(\OCP\Share\IShare $share, array $mountpoints, CappedMemoryCache $folderExistCache) {
		$mountPoint = basename($share->getTarget());
		$parent = dirname($share->getTarget());

		$event = new VerifyMountPointEvent($share, $this->recipientView, $parent);
		/** @var IEventDispatcher $dispatcher */
		$dispatcher = \OC::$server->query(IEventDispatcher::class);
		$dispatcher->dispatchTyped($event);
		$parent = $event->getParent();

		if ($folderExistCache->hasKey($parent)) {
			$parentExists = $folderExistCache->get($parent);
		} else {
			$parentExists = $this->recipientView->is_dir($parent);
			$folderExistCache->set($parent, $parentExists);
		}
		if (!$parentExists) {
			$parent = Helper::getShareFolder($this->recipientView);
		}

		$newMountPoint = $this->generateUniqueTarget(
			\OC\Files\Filesystem::normalizePath($parent . '/' . $mountPoint),
			$this->recipientView,
			$mountpoints
		);

		if ($newMountPoint !== $share->getTarget()) {
			$this->updateFileTarget($newMountPoint, $share);
		}

		return $newMountPoint;
	}

	/**
	 * update fileTarget in the database if the mount point changed
	 *
	 * @param string $newPath
	 * @param \OCP\Share\IShare $share
	 * @return bool
	 */
	private function updateFileTarget($newPath, &$share) {
		$share->setTarget($newPath);

		foreach ($this->groupedShares as $tmpShare) {
			$tmpShare->setTarget($newPath);
			\OC::$server->getShareManager()->moveShare($tmpShare, $this->user);
		}
	}


	/**
	 * @param string $path
	 * @param View $view
	 * @param SharedMount[] $mountpoints
	 * @return mixed
	 */
	private function generateUniqueTarget($path, $view, array $mountpoints) {
		$pathinfo = pathinfo($path);
		$ext = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
		$name = $pathinfo['filename'];
		$dir = $pathinfo['dirname'];

		$i = 2;
		$absolutePath = $this->recipientView->getAbsolutePath($path) . '/';
		while ($view->file_exists($path) || isset($mountpoints[$absolutePath])) {
			$path = Filesystem::normalizePath($dir . '/' . $name . ' (' . $i . ')' . $ext);
			$absolutePath = $this->recipientView->getAbsolutePath($path) . '/';
			$i++;
		}

		return $path;
	}

	/**
	 * Format a path to be relative to the /user/files/ directory
	 *
	 * @param string $path the absolute path
	 * @return string e.g. turns '/admin/files/test.txt' into '/test.txt'
	 * @throws \OCA\Files_Sharing\Exceptions\BrokenPath
	 */
	protected function stripUserFilesPath($path) {
		$trimmed = ltrim($path, '/');
		$split = explode('/', $trimmed);

		// it is not a file relative to data/user/files
		if (count($split) < 3 || $split[1] !== 'files') {
			\OC::$server->getLogger()->error('Can not strip userid and "files/" from path: ' . $path, ['app' => 'files_sharing']);
			throw new \OCA\Files_Sharing\Exceptions\BrokenPath('Path does not start with /user/files', 10);
		}

		// skip 'user' and 'files'
		$sliced = array_slice($split, 2);
		$relPath = implode('/', $sliced);

		return '/' . $relPath;
	}

	/**
	 * Move the mount point to $target
	 *
	 * @param string $target the target mount point
	 * @return bool
	 */
	public function moveMount($target) {
		$relTargetPath = $this->stripUserFilesPath($target);
		$share = $this->storage->getShare();

		$result = true;

		try {
			$this->updateFileTarget($relTargetPath, $share);
			$this->setMountPoint($target);
			$this->storage->setMountPoint($relTargetPath);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_sharing', 'message' => 'Could not rename mount point for shared folder "' . $this->getMountPoint() . '" to "' . $target . '"']);
		}

		return $result;
	}

	/**
	 * Remove the mount points
	 *
	 * @return bool
	 */
	public function removeMount() {
		$mountManager = \OC\Files\Filesystem::getMountManager();
		/** @var \OCA\Files_Sharing\SharedStorage $storage */
		$storage = $this->getStorage();
		$result = $storage->unshareStorage();
		$mountManager->removeMount($this->mountPoint);

		return $result;
	}

	/**
	 * @return \OCP\Share\IShare
	 */
	public function getShare() {
		return $this->superShare;
	}

	/**
	 * Get the file id of the root of the storage
	 *
	 * @return int
	 */
	public function getStorageRootId() {
		return $this->getShare()->getNodeId();
	}

	/**
	 * @return int
	 */
	public function getNumericStorageId() {
		if (!is_null($this->getShare()->getNodeCacheEntry())) {
			return $this->getShare()->getNodeCacheEntry()->getStorageId();
		} else {
			$builder = \OC::$server->getDatabaseConnection()->getQueryBuilder();

			$query = $builder->select('storage')
				->from('filecache')
				->where($builder->expr()->eq('fileid', $builder->createNamedParameter($this->getStorageRootId())));

			$result = $query->execute();
			$row = $result->fetch();
			$result->closeCursor();
			if ($row) {
				return (int)$row['storage'];
			}
			return -1;
		}
	}

	public function getMountType() {
		return 'shared';
	}
}
