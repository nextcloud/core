<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Versions\Versions;

use OCP\Files\FileInfo;
use OCP\IUser;

class Version implements IVersion {
	/** @var int */
	private $timestamp;

	/** @var int|string */
	private $revisionId;

	/** @var string */
	private $name;

	/** @var int */
	private $size;

	/** @var string */
	private $mimetype;

	/** @var string */
	private $path;

	/** @var FileInfo */
	private $sourceFileInfo;

	/** @var IVersionBackend */
	private $backend;

	/** @var IUser */
	private $user;

	public function __construct(
		int $timestamp,
		$revisionId,
		string $name,
		int $size,
		string $mimetype,
		string $path,
		FileInfo $sourceFileInfo,
		IVersionBackend $backend,
		IUser $user
	) {
		$this->timestamp = $timestamp;
		$this->revisionId = $revisionId;
		$this->name = $name;
		$this->size = $size;
		$this->mimetype = $mimetype;
		$this->path = $path;
		$this->sourceFileInfo = $sourceFileInfo;
		$this->backend = $backend;
		$this->user = $user;
	}

	public function getBackend(): IVersionBackend {
		return $this->backend;
	}

	public function getSourceFile(): FileInfo {
		return $this->sourceFileInfo;
	}

	public function getRevisionId() {
		return $this->revisionId;
	}

	public function getTimestamp(): int {
		return $this->timestamp;
	}

	public function getSize(): int {
		return $this->size;
	}

	public function getSourceFileName(): string {
		return $this->name;
	}

	public function getMimeType(): string {
		return $this->mimetype;
	}

	public function getVersionPath(): string {
		return $this->path;
	}

	public function getUser(): IUser {
		return $this->user;
	}
}
