<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Files_Versions\Sabre;

use OCA\Files_Versions\Versions\IVersionManager;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IUser;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class VersionRoot implements ICollection {

	/** @var IUser */
	private $user;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IVersionManager */
	private $versionManager;

	public function __construct(IUser $user, IRootFolder $rootFolder, IVersionManager $versionManager) {
		$this->user = $user;
		$this->rootFolder = $rootFolder;
		$this->versionManager = $versionManager;
	}

	public function delete() {
		throw new Forbidden();
	}

	public function getName(): string {
		return 'versions';
	}

	public function setName($name) {
		throw new Forbidden();
	}

	public function createFile($name, $data = null) {
		throw new Forbidden();
	}

	public function createDirectory($name) {
		throw new Forbidden();
	}

	public function getChild($name) {
		$userFolder = $this->rootFolder->getUserFolder($this->user->getUID());

		$fileId = (int)$name;
		$nodes = $userFolder->getById($fileId);

		if ($nodes === []) {
			throw new NotFound();
		}

		$node = array_pop($nodes);

		if (!$node instanceof File) {
			throw new NotFound();
		}

		return new VersionCollection($node, $this->user, $this->versionManager);
	}

	public function getChildren(): array {
		return [];
	}

	public function childExists($name): bool {
		try {
			$this->getChild($name);
			return true;
		} catch (NotFound $e) {
			return false;
		}
	}

	public function getLastModified(): int {
		return 0;
	}
}
