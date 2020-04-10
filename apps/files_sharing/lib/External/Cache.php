<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace OCA\Files_Sharing\External;

use OCP\Federation\ICloudId;

class Cache extends \OC\Files\Cache\Cache {
	/** @var ICloudId */
	private $cloudId;
	private $remote;
	private $remoteUser;
	private $storage;

	/**
	 * @param \OCA\Files_Sharing\External\Storage $storage
	 * @param ICloudId $cloudId
	 */
	public function __construct($storage, ICloudId $cloudId) {
		$this->cloudId = $cloudId;
		$this->storage = $storage;
		list(, $remote) = explode('://', $cloudId->getRemote(), 2);
		$this->remote = $remote;
		$this->remoteUser = $cloudId->getUser();
		parent::__construct($storage);
	}

	public function get($file) {
		$result = parent::get($file);
		if (!$result) {
			return false;
		}
		$result['displayname_owner'] = $this->cloudId->getDisplayId();
		if (!$file || $file === '') {
			$result['is_share_mount_point'] = true;
			$mountPoint = rtrim($this->storage->getMountPoint());
			$result['name'] = basename($mountPoint);
		}
		return $result;
	}

	public function getFolderContentsById($id) {
		$results = parent::getFolderContentsById($id);
		foreach ($results as &$file) {
			$file['displayname_owner'] = $this->cloudId->getDisplayId();
		}
		return $results;
	}
}
