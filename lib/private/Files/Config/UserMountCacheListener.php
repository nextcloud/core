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

namespace OC\Files\Config;

use OC\User\Manager;
use OCP\Files\Config\IUserMountCache;

/**
 * Listen to hooks and update the mount cache as needed
 */
class UserMountCacheListener {
	/**
	 * @var IUserMountCache
	 */
	private $userMountCache;

	/**
	 * UserMountCacheListener constructor.
	 *
	 * @param IUserMountCache $userMountCache
	 */
	public function __construct(IUserMountCache $userMountCache) {
		$this->userMountCache = $userMountCache;
	}

	public function listen(Manager $manager) {
		$manager->listen('\OC\User', 'postDelete', [$this->userMountCache, 'removeUserMounts']);
	}
}
