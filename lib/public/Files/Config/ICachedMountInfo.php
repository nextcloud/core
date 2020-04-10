<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OCP\Files\Config;

use OCP\Files\Node;
use OCP\IUser;

/**
 * Holds information about a mount for a user
 *
 * @since 9.0.0
 */
interface ICachedMountInfo {
	/**
	 * @return IUser
	 * @since 9.0.0
	 */
	public function getUser();

	/**
	 * @return int the numeric storage id of the mount
	 * @since 9.0.0
	 */
	public function getStorageId();

	/**
	 * @return int the fileid of the root of the mount
	 * @since 9.0.0
	 */
	public function getRootId();

	/**
	 * @return Node the root node of the mount
	 * @since 9.0.0
	 */
	public function getMountPointNode();

	/**
	 * @return string the mount point of the mount for the user
	 * @since 9.0.0
	 */
	public function getMountPoint();

	/**
	 * Get the id of the configured mount
	 *
	 * @return int|null mount id or null if not applicable
	 * @since 9.1.0
	 */
	public function getMountId();

	/**
	 * Get the internal path (within the storage) of the root of the mount
	 *
	 * @return string
	 * @since 11.0.0
	 */
	public function getRootInternalPath();
}
