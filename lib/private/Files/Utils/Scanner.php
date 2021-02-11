<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Blaok <i@blaok.me>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\Utils;

use OC\Files\Cache\Cache;
use OC\Files\Filesystem;
use OC\Files\Storage\FailedStorage;
use OC\ForbiddenException;
use OC\Hooks\PublicEmitter;
use OC\Lock\DBLockingProvider;
use OCA\Files_Sharing\SharedStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\BeforeFileScannedEvent;
use OCP\Files\Events\BeforeFolderScannedEvent;
use OCP\Files\Events\NodeAddedToCache;
use OCP\Files\Events\FileCacheUpdated;
use OCP\Files\Events\NodeRemovedFromCache;
use OCP\Files\Events\FileScannedEvent;
use OCP\Files\Events\FolderScannedEvent;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\IDBConnection;
use OCP\ILogger;

/**
 * Class Scanner
 *
 * Hooks available in scope \OC\Utils\Scanner
 *  - scanFile(string $absolutePath)
 *  - scanFolder(string $absolutePath)
 *
 * @package OC\Files\Utils
 */
class Scanner extends PublicEmitter {
	public const MAX_ENTRIES_TO_COMMIT = 10000;

	/** @var string $user */
	private $user;

	/** @var IDBConnection */
	protected $db;

	/** @var IEventDispatcher */
	private $dispatcher;

	/** @var ILogger */
	protected $logger;

	/**
	 * Whether to use a DB transaction
	 *
	 * @var bool
	 */
	protected $useTransaction;

	/**
	 * Number of entries scanned to commit
	 *
	 * @var int
	 */
	protected $entriesToCommit;

	/**
	 * @param string $user
	 * @param IDBConnection|null $db
	 * @param IEventDispatcher $dispatcher
	 * @param ILogger $logger
	 */
	public function __construct($user, $db, IEventDispatcher $dispatcher, ILogger $logger) {
		$this->user = $user;
		$this->db = $db;
		$this->dispatcher = $dispatcher;
		$this->logger = $logger;
		// when DB locking is used, no DB transactions will be used
		$this->useTransaction = !(\OC::$server->getLockingProvider() instanceof DBLockingProvider);
	}

	/**
	 * get all storages for $dir
	 *
	 * @param string $dir
	 * @return \OC\Files\Mount\MountPoint[]
	 */
	protected function getMounts($dir) {
		//TODO: move to the node based fileapi once that's done
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($this->user);

		$mountManager = Filesystem::getMountManager();
		$mounts = $mountManager->findIn($dir);
		$mounts[] = $mountManager->find($dir);
		$mounts = array_reverse($mounts); //start with the mount of $dir

		return $mounts;
	}

	/**
	 * attach listeners to the scanner
	 *
	 * @param \OC\Files\Mount\MountPoint $mount
	 */
	protected function attachListener($mount) {
		$scanner = $mount->getStorage()->getScanner();
		$scanner->listen('\OC\Files\Cache\Scanner', 'scanFile', function ($path) use ($mount) {
			$this->emit('\OC\Files\Utils\Scanner', 'scanFile', [$mount->getMountPoint() . $path]);
			$this->dispatcher->dispatchTyped(new BeforeFileScannedEvent($mount->getMountPoint() . $path));
		});
		$scanner->listen('\OC\Files\Cache\Scanner', 'scanFolder', function ($path) use ($mount) {
			$this->emit('\OC\Files\Utils\Scanner', 'scanFolder', [$mount->getMountPoint() . $path]);
			$this->dispatcher->dispatchTyped(new BeforeFolderScannedEvent($mount->getMountPoint() . $path));
		});
		$scanner->listen('\OC\Files\Cache\Scanner', 'postScanFile', function ($path) use ($mount) {
			$this->emit('\OC\Files\Utils\Scanner', 'postScanFile', [$mount->getMountPoint() . $path]);
			$this->dispatcher->dispatchTyped(new FileScannedEvent($mount->getMountPoint() . $path));
		});
		$scanner->listen('\OC\Files\Cache\Scanner', 'postScanFolder', function ($path) use ($mount) {
			$this->emit('\OC\Files\Utils\Scanner', 'postScanFolder', [$mount->getMountPoint() . $path]);
			$this->dispatcher->dispatchTyped(new FolderScannedEvent($mount->getMountPoint() . $path));
		});
	}

	/**
	 * @param string $dir
	 */
	public function backgroundScan($dir) {
		$mounts = $this->getMounts($dir);
		foreach ($mounts as $mount) {
			$storage = $mount->getStorage();
			if (is_null($storage)) {
				continue;
			}

			// don't bother scanning failed storages (shortcut for same result)
			if ($storage->instanceOfStorage(FailedStorage::class)) {
				continue;
			}

			// don't scan received local shares, these can be scanned when scanning the owner's storage
			if ($storage->instanceOfStorage(SharedStorage::class)) {
				continue;
			}
			$scanner = $storage->getScanner();
			$this->attachListener($mount);

			$scanner->listen('\OC\Files\Cache\Scanner', 'removeFromCache', function ($path) use ($storage) {
				$this->triggerPropagator($storage, $path);
			});
			$scanner->listen('\OC\Files\Cache\Scanner', 'updateCache', function ($path) use ($storage) {
				$this->triggerPropagator($storage, $path);
			});
			$scanner->listen('\OC\Files\Cache\Scanner', 'addToCache', function ($path) use ($storage) {
				$this->triggerPropagator($storage, $path);
			});

			$propagator = $storage->getPropagator();
			$propagator->beginBatch();
			$scanner->backgroundScan();
			$propagator->commitBatch();
		}
	}

	/**
	 * @param string $dir
	 * @param $recursive
	 * @param callable|null $mountFilter
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 */
	public function scan($dir = '', $recursive = \OC\Files\Cache\Scanner::SCAN_RECURSIVE, callable $mountFilter = null) {
		if (!Filesystem::isValidPath($dir)) {
			throw new \InvalidArgumentException('Invalid path to scan');
		}
		$mounts = $this->getMounts($dir);
		foreach ($mounts as $mount) {
			if ($mountFilter && !$mountFilter($mount)) {
				continue;
			}
			$storage = $mount->getStorage();
			if (is_null($storage)) {
				continue;
			}

			// don't bother scanning failed storages (shortcut for same result)
			if ($storage->instanceOfStorage(FailedStorage::class)) {
				continue;
			}

			// if the home storage isn't writable then the scanner is run as the wrong user
			if ($storage->instanceOfStorage('\OC\Files\Storage\Home') and
				(!$storage->isCreatable('') or !$storage->isCreatable('files'))
			) {
				if ($storage->file_exists('') or $storage->getCache()->inCache('')) {
					throw new ForbiddenException();
				} else {// if the root exists in neither the cache nor the storage the user isn't setup yet
					break;
				}
			}

			// don't scan received local shares, these can be scanned when scanning the owner's storage
			if ($storage->instanceOfStorage(SharedStorage::class)) {
				continue;
			}
			$relativePath = $mount->getInternalPath($dir);
			$scanner = $storage->getScanner();
			$scanner->setUseTransactions(false);
			$this->attachListener($mount);

			$scanner->listen('\OC\Files\Cache\Scanner', 'removeFromCache', function ($path) use ($storage) {
				$this->postProcessEntry($storage, $path);
				$this->dispatcher->dispatchTyped(new NodeRemovedFromCache($storage, $path));
			});
			$scanner->listen('\OC\Files\Cache\Scanner', 'updateCache', function ($path) use ($storage) {
				$this->postProcessEntry($storage, $path);
				$this->dispatcher->dispatchTyped(new FileCacheUpdated($storage, $path));
			});
			$scanner->listen('\OC\Files\Cache\Scanner', 'addToCache', function ($path) use ($storage) {
				$this->postProcessEntry($storage, $path);
				$this->dispatcher->dispatchTyped(new NodeAddedToCache($storage, $path));
			});

			if (!$storage->file_exists($relativePath)) {
				throw new NotFoundException($dir);
			}

			if ($this->useTransaction) {
				$this->db->beginTransaction();
			}
			try {
				$propagator = $storage->getPropagator();
				$propagator->beginBatch();
				$scanner->scan($relativePath, $recursive, \OC\Files\Cache\Scanner::REUSE_ETAG | \OC\Files\Cache\Scanner::REUSE_SIZE);
				$cache = $storage->getCache();
				if ($cache instanceof Cache) {
					// only re-calculate for the root folder we scanned, anything below that is taken care of by the scanner
					$cache->correctFolderSize($relativePath);
				}
				$propagator->commitBatch();
			} catch (StorageNotAvailableException $e) {
				$this->logger->error('Storage ' . $storage->getId() . ' not available');
				$this->logger->logException($e);
				$this->emit('\OC\Files\Utils\Scanner', 'StorageNotAvailable', [$e]);
			}
			if ($this->useTransaction) {
				$this->db->commit();
			}
		}
	}

	private function triggerPropagator(IStorage $storage, $internalPath) {
		$storage->getPropagator()->propagateChange($internalPath, time());
	}

	private function postProcessEntry(IStorage $storage, $internalPath) {
		$this->triggerPropagator($storage, $internalPath);
		if ($this->useTransaction) {
			$this->entriesToCommit++;
			if ($this->entriesToCommit >= self::MAX_ENTRIES_TO_COMMIT) {
				$propagator = $storage->getPropagator();
				$this->entriesToCommit = 0;
				$this->db->commit();
				$propagator->commitBatch();
				$this->db->beginTransaction();
				$propagator->beginBatch();
			}
		}
	}
}
