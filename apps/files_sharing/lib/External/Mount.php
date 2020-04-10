<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files_Sharing\External;

use OC\Files\Mount\MountPoint;
use OC\Files\Mount\MoveableMount;

class Mount extends MountPoint implements MoveableMount {

	/**
	 * @var \OCA\Files_Sharing\External\Manager
	 */
	protected $manager;

	/**
	 * @param string|\OC\Files\Storage\Storage $storage
	 * @param string $mountpoint
	 * @param array $options
	 * @param \OCA\Files_Sharing\External\Manager $manager
	 * @param \OC\Files\Storage\StorageFactory $loader
	 */
	public function __construct($storage, $mountpoint, $options, $manager, $loader = null) {
		parent::__construct($storage, $mountpoint, $options, $loader);
		$this->manager = $manager;
	}

	/**
	 * Move the mount point to $target
	 *
	 * @param string $target the target mount point
	 * @return bool
	 */
	public function moveMount($target) {
		$result = $this->manager->setMountPoint($this->mountPoint, $target);
		$this->setMountPoint($target);

		return $result;
	}

	/**
	 * Remove the mount points
	 *
	 * @return mixed
	 * @return bool
	 */
	public function removeMount() {
		return $this->manager->removeShare($this->mountPoint);
	}

	/**
	 * Get the type of mount point, used to distinguish things like shares and external storages
	 * in the web interface
	 *
	 * @return string
	 */
	public function getMountType() {
		return 'shared';
	}
}
