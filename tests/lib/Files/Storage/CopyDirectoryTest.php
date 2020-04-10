<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Files\Storage;

use OC\Files\Storage\Temporary;

class StorageNoRecursiveCopy extends Temporary {
	public function copy($path1, $path2) {
		if ($this->is_dir($path1)) {
			return false;
		}
		return copy($this->getSourcePath($path1), $this->getSourcePath($path2));
	}
}

class CopyDirectoryStorage extends StorageNoRecursiveCopy {
	use \OC\Files\Storage\PolyFill\CopyDirectory;
}

/**
 * Class CopyDirectoryTest
 *
 * @group DB
 *
 * @package Test\Files\Storage
 */
class CopyDirectoryTest extends Storage {

	protected function setUp(): void {
		parent::setUp();
		$this->instance = new CopyDirectoryStorage([]);
	}
}
