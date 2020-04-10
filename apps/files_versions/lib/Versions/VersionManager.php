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

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Storage\IStorage;
use OCP\IUser;

class VersionManager implements IVersionManager {
	/** @var (IVersionBackend[])[] */
	private $backends = [];

	public function registerBackend(string $storageType, IVersionBackend $backend) {
		if (!isset($this->backends[$storageType])) {
			$this->backends[$storageType] = [];
		}
		$this->backends[$storageType][] = $backend;
	}

	/**
	 * @return (IVersionBackend[])[]
	 */
	private function getBackends(): array {
		return $this->backends;
	}

	/**
	 * @param IStorage $storage
	 * @return IVersionBackend
	 * @throws BackendNotFoundException
	 */
	public function getBackendForStorage(IStorage $storage): IVersionBackend {
		$fullType = get_class($storage);
		$backends = $this->getBackends();

		$foundType = '';
		$foundBackend = null;

		foreach ($backends as $type => $backendsForType) {
			if (
				$storage->instanceOfStorage($type) &&
				($foundType === '' || is_subclass_of($type, $foundType))
			) {
				foreach ($backendsForType as $backend) {
					/** @var IVersionBackend $backend */
					if ($backend->useBackendForStorage($storage)) {
						$foundBackend = $backend;
						$foundType = $type;
					}
				}
			}
		}

		if ($foundType === '' || $foundBackend === null) {
			throw new BackendNotFoundException("Version backend for $fullType not found");
		} else {
			return $foundBackend;
		}
	}

	public function getVersionsForFile(IUser $user, FileInfo $file): array {
		$backend = $this->getBackendForStorage($file->getStorage());
		return $backend->getVersionsForFile($user, $file);
	}

	public function createVersion(IUser $user, FileInfo $file) {
		$backend = $this->getBackendForStorage($file->getStorage());
		$backend->createVersion($user, $file);
	}

	public function rollback(IVersion $version) {
		$backend = $version->getBackend();
		return $backend->rollback($version);
	}

	public function read(IVersion $version) {
		$backend = $version->getBackend();
		return $backend->read($version);
	}

	public function getVersionFile(IUser $user, FileInfo $sourceFile, $revision): File {
		$backend = $this->getBackendForStorage($sourceFile->getStorage());
		return $backend->getVersionFile($user, $sourceFile, $revision);
	}

	public function useBackendForStorage(IStorage $storage): bool {
		return false;
	}
}
