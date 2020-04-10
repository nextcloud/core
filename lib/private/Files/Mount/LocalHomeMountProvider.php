<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\Mount;

use OCP\Files\Config\IHomeMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;

/**
 * Mount provider for regular posix home folders
 */
class LocalHomeMountProvider implements IHomeMountProvider {
	/**
	 * Get the cache mount for a user
	 *
	 * @param IUser $user
	 * @param IStorageFactory $loader
	 * @return \OCP\Files\Mount\IMountPoint[]
	 */
	public function getHomeMountForUser(IUser $user, IStorageFactory $loader) {
		$arguments = ['user' => $user];
		return new MountPoint('\OC\Files\Storage\Home', '/' . $user->getUID(), $arguments, $loader);
	}
}
