<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Testing\Controller;

use OC\Lock\DBLockingProvider;
use OC\User\NoUserException;
use OCA\Testing\Locking\FakeDBLockingProvider;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

class LockingController extends OCSController {

	/** @var ILockingProvider */
	protected $lockingProvider;

	/** @var FakeDBLockingProvider */
	protected $fakeDBLockingProvider;

	/** @var IDBConnection */
	protected $connection;

	/** @var IConfig */
	protected $config;

	/** @var IRootFolder */
	protected $rootFolder;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param ILockingProvider $lockingProvider
	 * @param FakeDBLockingProvider $fakeDBLockingProvider
	 * @param IDBConnection $connection
	 * @param IConfig $config
	 * @param IRootFolder $rootFolder
	 */
	public function __construct($appName,
								IRequest $request,
								ILockingProvider $lockingProvider,
								FakeDBLockingProvider $fakeDBLockingProvider,
								IDBConnection $connection,
								IConfig $config,
								IRootFolder $rootFolder) {
		parent::__construct($appName, $request);

		$this->lockingProvider = $lockingProvider;
		$this->fakeDBLockingProvider = $fakeDBLockingProvider;
		$this->connection = $connection;
		$this->config = $config;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @return ILockingProvider
	 * @throws \RuntimeException
	 */
	protected function getLockingProvider() {
		if ($this->lockingProvider instanceof DBLockingProvider) {
			return $this->fakeDBLockingProvider;
		}
		throw new \RuntimeException('Lock provisioning is only possible using the DBLockingProvider');
	}

	/**
	 * @param string $user
	 * @param string $path
	 * @return string
	 * @throws NotFoundException
	 */
	protected function getPath($user, $path) {
		$node = $this->rootFolder->getUserFolder($user)->get($path);
		return 'files/' . md5($node->getStorage()->getId() . '::' . trim($node->getInternalPath(), '/'));
	}

	/**
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function isLockingEnabled() {
		try {
			$this->getLockingProvider();
			return new DataResponse();
		} catch (\RuntimeException $e) {
			throw new OCSException($e->getMessage(), Http::STATUS_NOT_IMPLEMENTED, $e);
		}
	}

	/**
	 * @param int $type
	 * @param string $user
	 * @param string $path
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function acquireLock($type, $user, $path) {
		try {
			$path = $this->getPath($user, $path);
		} catch (NoUserException $e) {
			throw new OCSException('User not found', Http::STATUS_NOT_FOUND, $e);
		} catch (NotFoundException $e) {
			throw new OCSException('Path not found', Http::STATUS_NOT_FOUND, $e);
		}

		$lockingProvider = $this->getLockingProvider();

		try {
			$lockingProvider->acquireLock($path, $type);
			$this->config->setAppValue('testing', 'locking_' . $path, $type);
			return new DataResponse();
		} catch (LockedException $e) {
			throw new OCSException('', Http::STATUS_LOCKED, $e);
		}
	}

	/**
	 * @param int $type
	 * @param string $user
	 * @param string $path
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function changeLock($type, $user, $path) {
		try {
			$path = $this->getPath($user, $path);
		} catch (NoUserException $e) {
			throw new OCSException('User not found', Http::STATUS_NOT_FOUND, $e);
		} catch (NotFoundException $e) {
			throw new OCSException('Path not found', Http::STATUS_NOT_FOUND, $e);
		}

		$lockingProvider = $this->getLockingProvider();

		try {
			$lockingProvider->changeLock($path, $type);
			$this->config->setAppValue('testing', 'locking_' . $path, $type);
			return new DataResponse();
		} catch (LockedException $e) {
			throw new OCSException('', Http::STATUS_LOCKED, $e);
		}
	}

	/**
	 * @param int $type
	 * @param string $user
	 * @param string $path
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function releaseLock($type, $user, $path) {
		try {
			$path = $this->getPath($user, $path);
		} catch (NoUserException $e) {
			throw new OCSException('User not found', Http::STATUS_NOT_FOUND, $e);
		} catch (NotFoundException $e) {
			throw new OCSException('Path not found', Http::STATUS_NOT_FOUND, $e);
		}

		$lockingProvider = $this->getLockingProvider();

		try {
			$lockingProvider->releaseLock($path, $type);
			$this->config->deleteAppValue('testing', 'locking_' . $path);
			return new DataResponse();
		} catch (LockedException $e) {
			throw new OCSException('', Http::STATUS_LOCKED, $e);
		}
	}

	/**
	 * @param int $type
	 * @param string $user
	 * @param string $path
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function isLocked($type, $user, $path) {
		try {
			$path = $this->getPath($user, $path);
		} catch (NoUserException $e) {
			throw new OCSException('User not found', Http::STATUS_NOT_FOUND, $e);
		} catch (NotFoundException $e) {
			throw new OCSException('Path not found', Http::STATUS_NOT_FOUND, $e);
		}

		$lockingProvider = $this->getLockingProvider();

		if ($lockingProvider->isLocked($path, $type)) {
			return new DataResponse();
		}

		throw new OCSException('', Http::STATUS_LOCKED);
	}

	/**
	 * @param int $type
	 * @return DataResponse
	 */
	public function releaseAll($type = null) {
		$lockingProvider = $this->getLockingProvider();

		foreach ($this->config->getAppKeys('testing') as $lock) {
			if (strpos($lock, 'locking_') === 0) {
				$path = substr($lock, strlen('locking_'));

				if ($type === ILockingProvider::LOCK_EXCLUSIVE && (int)$this->config->getAppValue('testing', $lock) === ILockingProvider::LOCK_EXCLUSIVE) {
					$lockingProvider->releaseLock($path, $this->config->getAppValue('testing', $lock));
				} else if ($type === ILockingProvider::LOCK_SHARED && (int)$this->config->getAppValue('testing', $lock) === ILockingProvider::LOCK_SHARED) {
					$lockingProvider->releaseLock($path, $this->config->getAppValue('testing', $lock));
				} else {
					$lockingProvider->releaseLock($path, $this->config->getAppValue('testing', $lock));
				}
			}
		}

		return new DataResponse();
	}
}
