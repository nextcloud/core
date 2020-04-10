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

namespace OC\Files\Node;

use OCP\Files\NotFoundException;

class NonExistingFolder extends Folder {
	/**
	 * @param string $newPath
	 * @throws \OCP\Files\NotFoundException
	 */
	public function rename($newPath) {
		throw new NotFoundException();
	}

	public function delete() {
		throw new NotFoundException();
	}

	public function copy($newPath) {
		throw new NotFoundException();
	}

	public function touch($mtime = null) {
		throw new NotFoundException();
	}

	public function getId() {
		if ($this->fileInfo) {
			return parent::getId();
		} else {
			throw new NotFoundException();
		}
	}

	public function stat() {
		throw new NotFoundException();
	}

	public function getMTime() {
		if ($this->fileInfo) {
			return parent::getMTime();
		} else {
			throw new NotFoundException();
		}
	}

	public function getSize($includeMounts = true) {
		if ($this->fileInfo) {
			return parent::getSize($includeMounts);
		} else {
			throw new NotFoundException();
		}
	}

	public function getEtag() {
		if ($this->fileInfo) {
			return parent::getEtag();
		} else {
			throw new NotFoundException();
		}
	}

	public function getPermissions() {
		if ($this->fileInfo) {
			return parent::getPermissions();
		} else {
			throw new NotFoundException();
		}
	}

	public function isReadable() {
		if ($this->fileInfo) {
			return parent::isReadable();
		} else {
			throw new NotFoundException();
		}
	}

	public function isUpdateable() {
		if ($this->fileInfo) {
			return parent::isUpdateable();
		} else {
			throw new NotFoundException();
		}
	}

	public function isDeletable() {
		if ($this->fileInfo) {
			return parent::isDeletable();
		} else {
			throw new NotFoundException();
		}
	}

	public function isShareable() {
		if ($this->fileInfo) {
			return parent::isShareable();
		} else {
			throw new NotFoundException();
		}
	}

	public function get($path) {
		throw new NotFoundException();
	}

	public function getDirectoryListing() {
		throw new NotFoundException();
	}

	public function nodeExists($path) {
		return false;
	}

	public function newFolder($path) {
		throw new NotFoundException();
	}

	public function newFile($path, $content = null) {
		throw new NotFoundException();
	}

	public function search($pattern) {
		throw new NotFoundException();
	}

	public function searchByMime($mime) {
		throw new NotFoundException();
	}

	public function searchByTag($tag, $userId) {
		throw new NotFoundException();
	}

	public function getById($id) {
		throw new NotFoundException();
	}

	public function getFreeSpace() {
		throw new NotFoundException();
	}

	public function isCreatable() {
		if ($this->fileInfo) {
			return parent::isCreatable();
		} else {
			throw new NotFoundException();
		}
	}
}
