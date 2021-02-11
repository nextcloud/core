<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

/**
 * test implementation for \OC\Files\Storage\Common with \OC\Files\Storage\Local
 */

namespace OC\Files\Storage;

class CommonTest extends \OC\Files\Storage\Common {
	/**
	 * underlying local storage used for missing functions
	 * @var \OC\Files\Storage\Local
	 */
	private $storage;

	public function __construct($params) {
		$this->storage = new \OC\Files\Storage\Local($params);
	}

	public function getId() {
		return 'test::'.$this->storage->getId();
	}
	public function mkdir($path) {
		return $this->storage->mkdir($path);
	}
	public function rmdir($path) {
		return $this->storage->rmdir($path);
	}
	public function opendir($path) {
		return $this->storage->opendir($path);
	}
	public function stat($path) {
		return $this->storage->stat($path);
	}
	public function filetype($path) {
		return @$this->storage->filetype($path);
	}
	public function isReadable($path) {
		return $this->storage->isReadable($path);
	}
	public function isUpdatable($path) {
		return $this->storage->isUpdatable($path);
	}
	public function file_exists($path) {
		return $this->storage->file_exists($path);
	}
	public function unlink($path) {
		return $this->storage->unlink($path);
	}
	public function fopen($path, $mode) {
		return $this->storage->fopen($path, $mode);
	}
	public function free_space($path) {
		return $this->storage->free_space($path);
	}
	public function touch($path, $mtime = null) {
		return $this->storage->touch($path, $mtime);
	}
}
