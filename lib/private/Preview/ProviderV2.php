<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
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

namespace OC\Preview;

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IImage;
use OCP\Preview\IProviderV2;

abstract class ProviderV2 implements IProviderV2 {
	private $options;

	private $tmpFiles = [];

	/**
	 * Constructor
	 *
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options;
	}

	/**
	 * @return string Regex with the mimetypes that are supported by this provider
	 */
	abstract public function getMimeType(): string ;

	/**
	 * Check if a preview can be generated for $path
	 *
	 * @param FileInfo $file
	 * @return bool
	 */
	public function isAvailable(FileInfo $file): bool {
		return true;
	}

	/**
	 * get thumbnail for file at path $path
	 *
	 * @param File $file
	 * @param int $maxX The maximum X size of the thumbnail. It can be smaller depending on the shape of the image
	 * @param int $maxY The maximum Y size of the thumbnail. It can be smaller depending on the shape of the image
	 * @return null|\OCP\IImage false if no preview was generated
	 * @since 17.0.0
	 */
	abstract public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage;

	/**
	 * Get a path to either the local file or temporary file
	 *
	 * @param File $file
	 * @param int $maxSize maximum size for temporary files
	 * @return string
	 */
	protected function getLocalFile(File $file, int $maxSize = null): string {
		$useTempFile = $file->isEncrypted() || !$file->getStorage()->isLocal();
		if ($useTempFile) {
			$absPath = \OC::$server->getTempManager()->getTemporaryFile();

			$content = $file->fopen('r');

			if ($maxSize) {
				$content = stream_get_contents($content, $maxSize);
			}

			file_put_contents($absPath, $content);
			$this->tmpFiles[] = $absPath;
			return $absPath;
		} else {
			return $file->getStorage()->getLocalFile($file->getInternalPath());
		}
	}

	/**
	 * Clean any generated temporary files
	 */
	protected function cleanTmpFiles() {
		foreach ($this->tmpFiles as $tmpFile) {
			unlink($tmpFile);
		}

		$this->tmpFiles = [];
	}
}
