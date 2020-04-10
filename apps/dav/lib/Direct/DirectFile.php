<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\Direct;

use OCA\DAV\Db\Direct;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\IFile;

class DirectFile implements IFile {
	/** @var Direct */
	private $direct;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var File */
	private $file;

	public function __construct(Direct $direct, IRootFolder $rootFolder) {
		$this->direct = $direct;
		$this->rootFolder = $rootFolder;
	}

	public function put($data) {
		throw new Forbidden();
	}

	public function get() {
		$this->getFile();

		return $this->file->fopen('rb');
	}

	public function getContentType() {
		$this->getFile();

		return $this->file->getMimeType();
	}

	public function getETag() {
		$this->getFile();

		return $this->file->getEtag();
	}

	public function getSize() {
		$this->getFile();

		return $this->file->getSize();
	}

	public function delete() {
		throw new Forbidden();
	}

	public function getName() {
		return $this->direct->getToken();
	}

	public function setName($name) {
		throw new Forbidden();
	}

	public function getLastModified() {
		$this->getFile();

		return $this->file->getMTime();
	}

	private function getFile() {
		if ($this->file === null) {
			$userFolder = $this->rootFolder->getUserFolder($this->direct->getUserId());
			$files = $userFolder->getById($this->direct->getFileId());

			if ($files === []) {
				throw new NotFound();
			}

			$this->file = array_shift($files);
		}

		return $this->file;
	}

}
