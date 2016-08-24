<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OC\Files\SimpleFS;

use OCP\Files\Node;

class Folder implements \OCP\Files\SimpleFS\Folder  {

	/** @var \OCP\Files\Folder */
	private $folder;

	/**
	 * Folder constructor.
	 *
	 * @param \OCP\Files\Folder $folder
	 */
	public function __construct(\OCP\Files\Folder $folder) {
		$this->folder = $folder;
	}

	public function getName() {
		return $this->folder->getName();
	}

	public function getDirectoryListing() {
		$listing = $this->folder->getDirectoryListing();

		$fileListing = array_map(function(Node $file) {
			return new File($file);
		}, $listing);

		return $fileListing;
	}

	public function delete() {
		$this->folder->delete();
	}

	public function getFile($name) {
		$file = $this->folder->get($name);

		return new File($file);
	}

	public function newFile($name) {
		$file = $this->folder->newFile($name);

		return new File($file);
	}
}
