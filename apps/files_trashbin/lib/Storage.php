<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Trashbin;

use OC\Files\Filesystem;
use OC\Files\Storage\Wrapper\Wrapper;
use OCA\Files_Trashbin\Events\MoveToTrashEvent;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Encryption\Exceptions\GenericEncryptionException;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Node;
use OCP\ILogger;
use OCP\IUserManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Storage extends Wrapper {
	/** @var IMountPoint */
	private $mountPoint;

	/** @var  IUserManager */
	private $userManager;

	/** @var ILogger */
	private $logger;

	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var ITrashManager */
	private $trashManager;

	/**
	 * Storage constructor.
	 *
	 * @param array $parameters
	 * @param ITrashManager $trashManager
	 * @param IUserManager|null $userManager
	 * @param ILogger|null $logger
	 * @param EventDispatcherInterface|null $eventDispatcher
	 * @param IRootFolder|null $rootFolder
	 */
	public function __construct(
		$parameters,
		ITrashManager $trashManager = null,
		IUserManager $userManager = null,
		ILogger $logger = null,
		EventDispatcherInterface $eventDispatcher = null,
		IRootFolder $rootFolder = null
	) {
		$this->mountPoint = $parameters['mountPoint'];
		$this->trashManager = $trashManager;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->eventDispatcher = $eventDispatcher;
		$this->rootFolder = $rootFolder;
		parent::__construct($parameters);
	}

	/**
	 * Deletes the given file by moving it into the trashbin.
	 *
	 * @param string $path path of file or folder to delete
	 *
	 * @return bool true if the operation succeeded, false otherwise
	 */
	public function unlink($path) {
		try {
			return $this->doDelete($path, 'unlink');
		} catch (GenericEncryptionException $e) {
			// in case of a encryption exception we delete the file right away
			$this->logger->info(
				"Can't move file" . $path .
				"to the trash bin, therefore it was deleted right away");

			return $this->storage->unlink($path);
		}
	}

	/**
	 * Deletes the given folder by moving it into the trashbin.
	 *
	 * @param string $path path of folder to delete
	 *
	 * @return bool true if the operation succeeded, false otherwise
	 */
	public function rmdir($path) {
		return $this->doDelete($path, 'rmdir');
	}

	/**
	 * check if it is a file located in data/user/files only files in the
	 * 'files' directory should be moved to the trash
	 *
	 * @param $path
	 * @return bool
	 */
	protected function shouldMoveToTrash($path) {
		$normalized = Filesystem::normalizePath($this->mountPoint . '/' . $path);
		$parts = explode('/', $normalized);
		if (count($parts) < 4) {
			return false;
		}

		// check if there is a app which want to disable the trash bin for this file
		$fileId = $this->storage->getCache()->getId($path);
		$owner = $this->storage->getOwner($path);
		if ($owner === false) {
			$nodes = $this->rootFolder->getById($fileId);
		} else {
			$nodes = $this->rootFolder->getUserFolder($owner)->getById($fileId);
		}

		foreach ($nodes as $node) {
			$event = $this->createMoveToTrashEvent($node);
			$this->eventDispatcher->dispatch('OCA\Files_Trashbin::moveToTrash', $event);
			if ($event->shouldMoveToTrashBin() === false) {
				return false;
			}
		}

		if ($parts[2] === 'files' && $this->userManager->userExists($parts[1])) {
			return true;
		}

		return false;
	}

	/**
	 * get move to trash event
	 *
	 * @param Node $node
	 * @return MoveToTrashEvent
	 */
	protected function createMoveToTrashEvent(Node $node) {
		return new MoveToTrashEvent($node);
	}

	/**
	 * Run the delete operation with the given method
	 *
	 * @param string $path path of file or folder to delete
	 * @param string $method either "unlink" or "rmdir"
	 *
	 * @return bool true if the operation succeeded, false otherwise
	 */
	private function doDelete($path, $method) {
		if (
			!\OC::$server->getAppManager()->isEnabledForUser('files_trashbin')
			|| (pathinfo($path, PATHINFO_EXTENSION) === 'part')
			|| $this->shouldMoveToTrash($path) === false
		) {
			return call_user_func([$this->storage, $method], $path);
		}

		// check permissions before we continue, this is especially important for
		// shared files
		if (!$this->isDeletable($path)) {
			return false;
		}

		$isMovedToTrash = $this->trashManager->moveToTrash($this, $path);
		if (!$isMovedToTrash) {
			return call_user_func([$this->storage, $method], $path);
		} else {
			return true;
		}
	}

	/**
	 * Setup the storate wrapper callback
	 */
	public static function setupStorage() {
		\OC\Files\Filesystem::addStorageWrapper('oc_trashbin', function ($mountPoint, $storage) {
			return new \OCA\Files_Trashbin\Storage(
				['storage' => $storage, 'mountPoint' => $mountPoint],
				\OC::$server->query(ITrashManager::class),
				\OC::$server->getUserManager(),
				\OC::$server->getLogger(),
				\OC::$server->getEventDispatcher(),
				\OC::$server->getLazyRootFolder()
			);
		}, 1);
	}

	public function getMountPoint() {
		return $this->mountPoint;
	}
}
