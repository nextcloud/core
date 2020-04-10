<?php
/**
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Lib\Backend;

use OCA\Files_External\Lib\StorageConfig;
use OCP\Files\StorageNotAvailableException;
use OCP\IUser;

/**
 * Invalid storage backend representing a backend
 * that could not be resolved
 */
class InvalidBackend extends Backend {

	/** @var string Invalid backend id */
	private $invalidId;

	/**
	 * Constructs a new InvalidBackend with the id of the invalid backend
	 * for display purposes
	 *
	 * @param string $invalidId id of the backend that did not exist
	 */
	function __construct($invalidId) {
		$this->invalidId = $invalidId;
		$this
			->setIdentifier($invalidId)
			->setStorageClass('\OC\Files\Storage\FailedStorage')
			->setText('Unknown storage backend ' . $invalidId);
	}

	/**
	 * Returns the invalid backend id
	 *
	 * @return string invalid backend id
	 */
	public function getInvalidId() {
		return $this->invalidId;
	}

	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
		$storage->setBackendOption('exception', new \Exception('Unknown storage backend "' . $this->invalidId . '"', StorageNotAvailableException::STATUS_ERROR));
	}
}
