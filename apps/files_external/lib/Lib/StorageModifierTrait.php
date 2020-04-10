<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Lib;

use OCP\Files\Storage;
use OCP\Files\StorageNotAvailableException;
use OCP\IUser;

/**
 * Trait for objects that can modify StorageConfigs and wrap Storages
 *
 * When a storage implementation is being prepared for use, the StorageConfig
 * is passed through manipulateStorageConfig() to update any parameters as
 * necessary. After the storage implementation has been constructed, it is
 * passed through wrapStorage(), potentially replacing the implementation with
 * a wrapped storage that changes its behaviour.
 *
 * Certain configuration options need to be set before the implementation is
 * constructed, while others are retrieved directly from the storage
 * implementation and so need a wrapper to be modified.
 */
trait StorageModifierTrait {

	/**
	 * Modify a StorageConfig parameters
	 *
	 * @param StorageConfig $storage
	 * @param IUser $user User the storage is being used as
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 */
	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
	}

	/**
	 * Wrap a Storage if necessary
	 *
	 * @param Storage $storage
	 * @return Storage
	 * @throws InsufficientDataForMeaningfulAnswerException
	 * @throws StorageNotAvailableException
	 */
	public function wrapStorage(Storage $storage) {
		return $storage;
	}

}
