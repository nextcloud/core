<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Encryption;

use OC\Files\Filesystem;
use OC\Files\Storage\Wrapper\Encryption;
use OC\Files\View;
use OC\Memcache\ArrayCache;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage;
use OCP\ILogger;

/**
 * Class EncryptionWrapper
 *
 * applies the encryption storage wrapper
 *
 * @package OC\Encryption
 */
class EncryptionWrapper {

	/** @var ArrayCache  */
	private $arrayCache;

	/** @var  Manager */
	private $manager;

	/** @var  ILogger */
	private $logger;

	/**
	 * EncryptionWrapper constructor.
	 *
	 * @param ArrayCache $arrayCache
	 * @param Manager $manager
	 * @param ILogger $logger
	 */
	public function __construct(ArrayCache $arrayCache,
								Manager $manager,
								ILogger $logger
	) {
		$this->arrayCache = $arrayCache;
		$this->manager = $manager;
		$this->logger = $logger;
	}

	/**
	 * Wraps the given storage when it is not a shared storage
	 *
	 * @param string $mountPoint
	 * @param Storage $storage
	 * @param IMountPoint $mount
	 * @return Encryption|Storage
	 */
	public function wrapStorage($mountPoint, Storage $storage, IMountPoint $mount) {
		$parameters = [
			'storage' => $storage,
			'mountPoint' => $mountPoint,
			'mount' => $mount
		];

		if (!$storage->instanceOfStorage(Storage\IDisableEncryptionStorage::class) && $mountPoint !== '/') {
			$user = \OC::$server->getUserSession()->getUser();
			$mountManager = Filesystem::getMountManager();
			$uid = $user ? $user->getUID() : null;
			$fileHelper = \OC::$server->getEncryptionFilesHelper();
			$keyStorage = \OC::$server->getEncryptionKeyStorage();

			$util = new Util(
				new View(),
				\OC::$server->getUserManager(),
				\OC::$server->getGroupManager(),
				\OC::$server->getConfig()
			);
			$update = new Update(
				new View(),
				$util,
				Filesystem::getMountManager(),
				$this->manager,
				$fileHelper,
				$uid
			);
			return new Encryption(
				$parameters,
				$this->manager,
				$util,
				$this->logger,
				$fileHelper,
				$uid,
				$keyStorage,
				$update,
				$mountManager,
				$this->arrayCache
			);
		} else {
			return $storage;
		}
	}
}
