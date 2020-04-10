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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_Versions\Sabre;

use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\Files\NotFoundException;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\IFile;

class VersionFile implements IFile {
	/** @var IVersion */
	private $version;

	/** @var IVersionManager */
	private $versionManager;

	public function __construct(IVersion $version, IVersionManager $versionManager) {
		$this->version = $version;
		$this->versionManager = $versionManager;
	}

	public function put($data) {
		throw new Forbidden();
	}

	public function get() {
		try {
			return $this->versionManager->read($this->version);
		} catch (NotFoundException $e) {
			throw new NotFound();
		}
	}

	public function getContentType(): string {
		return $this->version->getMimeType();
	}

	public function getETag(): string {
		return (string)$this->version->getRevisionId();
	}

	public function getSize(): int {
		return $this->version->getSize();
	}

	public function delete() {
		throw new Forbidden();
	}

	public function getName(): string {
		return (string)$this->version->getRevisionId();
	}

	public function setName($name) {
		throw new Forbidden();
	}

	public function getLastModified(): int {
		return $this->version->getTimestamp();
	}

	public function rollBack() {
		$this->versionManager->rollback($this->version);
	}

	public function getVersion(): IVersion {
		return $this->version;
	}
}
