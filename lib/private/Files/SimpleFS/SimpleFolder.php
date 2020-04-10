<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\SimpleFS;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

class SimpleFolder implements ISimpleFolder   {

	/** @var Folder */
	private $folder;

	/**
	 * Folder constructor.
	 *
	 * @param Folder $folder
	 */
	public function __construct(Folder $folder) {
		$this->folder = $folder;
	}

	public function getName() {
		return $this->folder->getName();
	}

	public function getDirectoryListing() {
		$listing = $this->folder->getDirectoryListing();

		$fileListing = array_map(function (Node $file) {
			if ($file instanceof File) {
				return new SimpleFile($file);
			}
			return null;
		}, $listing);

		$fileListing = array_filter($fileListing);

		return array_values($fileListing);
	}

	public function delete() {
		$this->folder->delete();
	}

	public function fileExists($name) {
		return $this->folder->nodeExists($name);
	}

	public function getFile($name) {
		$file = $this->folder->get($name);

		if (!($file instanceof File)) {
			throw new NotFoundException();
		}

		return new SimpleFile($file);
	}

	public function newFile($name, $content = null) {
		if ($content === null) {
			// delay creating the file until it's written to
			return new NewSimpleFile($this->folder, $name);
		} else {
			$file = $this->folder->newFile($name, $content);
			return new SimpleFile($file);
		}
	}
}
