<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\DAV\Upload;

use OCA\DAV\Connector\Sabre\File;
use Sabre\DAV\IFile;

class UploadFile implements IFile {

	/**  @var File */
	private $file;

	public function __construct(File $file) {
		$this->file = $file;
	}

	public function put($data) {
		return $this->file->put($data);
	}

	public function get() {
		return $this->file->get();
	}

	public function getContentType() {
		return $this->file->getContentType();
	}

	public function getETag() {
		return $this->file->getETag();
	}

	public function getSize() {
		return $this->file->getSize();
	}

	public function delete() {
		$this->file->delete();
	}

	public function getName() {
		return $this->file->getName();
	}

	public function setName($name) {
		$this->file->setName($name);
	}

	public function getLastModified() {
		return $this->file->getLastModified();
	}

	public function getInternalPath(): string {
		return $this->file->getInternalPath();
	}

	public function getAbsoluteInternalPath(): string {
		return $this->file->getFileInfo()->getPath();
	}
}
