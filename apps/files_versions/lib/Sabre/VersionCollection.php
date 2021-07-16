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

use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\Files\File;
use OCP\IUser;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class VersionCollection implements ICollection {

	/** @var File */
	private $file;

	/** @var IUser */
	private $user;

	/** @var IVersionManager */
	private $versionManager;

	public function __construct(File $file, IUser $user, IVersionManager $versionManager) {
		$this->file = $file;
		$this->user = $user;
		$this->versionManager = $versionManager;
	}

	public function createFile($name, $data = null) {
		throw new Forbidden();
	}

	public function createDirectory($name) {
		throw new Forbidden();
	}

	public function getChild($name) {
		/** @var VersionFile[] $versions */
		$versions = $this->getChildren();

		foreach ($versions as $version) {
			if ($version->getName() === $name) {
				return $version;
			}
		}

		throw new NotFound();
	}

	public function getChildren(): array {
		$versions = $this->versionManager->getVersionsForFile($this->user, $this->file);

		return array_map(function (IVersion $version) {
			return new VersionFile($version, $this->versionManager);
		}, $versions);
	}

	public function childExists($name): bool {
		try {
			$this->getChild($name);
			return true;
		} catch (NotFound $e) {
			return false;
		}
	}

	public function delete() {
		throw new Forbidden();
	}

	public function getName(): string {
		return (string)$this->file->getId();
	}

	public function setName($name) {
		throw new Forbidden();
	}

	public function getLastModified(): int {
		return 0;
	}
}
