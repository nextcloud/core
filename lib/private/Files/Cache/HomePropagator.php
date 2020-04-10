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

namespace OC\Files\Cache;

use OCP\IDBConnection;

class HomePropagator extends Propagator {
	private $ignoredBaseFolders;

	/**
	 * @param \OC\Files\Storage\Storage $storage
	 */
	public function __construct(\OC\Files\Storage\Storage $storage, IDBConnection $connection) {
		parent::__construct($storage, $connection);
		$this->ignoredBaseFolders = ['files_encryption'];
	}


	/**
	 * @param string $internalPath
	 * @param int $time
	 * @param int $sizeDifference number of bytes the file has grown
	 */
	public function propagateChange($internalPath, $time, $sizeDifference = 0) {
		list($baseFolder) = explode('/', $internalPath, 2);
		if (in_array($baseFolder, $this->ignoredBaseFolders)) {
			return [];
		} else {
			parent::propagateChange($internalPath, $time, $sizeDifference);
		}
	}
}
