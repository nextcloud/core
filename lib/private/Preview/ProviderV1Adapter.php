<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

use OC\Files\View;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IImage;
use OCP\Preview\IProvider;
use OCP\Preview\IProviderV2;

class ProviderV1Adapter implements IProviderV2 {
	private $providerV1;

	public function __construct(IProvider $providerV1) {
		$this->providerV1 = $providerV1;
	}

	public function getMimeType(): string {
		return (string)$this->providerV1->getMimeType();
	}

	public function isAvailable(FileInfo $file): bool {
		return (bool)$this->providerV1->isAvailable($file);
	}

	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		list($view, $path) = $this->getViewAndPath($file);
		$thumbnail = $this->providerV1->getThumbnail($path, $maxX, $maxY, false, $view);
		return $thumbnail === false ? null: $thumbnail;
	}

	private function getViewAndPath(File $file) {
		$view = new View($file->getParent()->getPath());
		$path = $file->getName();

		return [$view, $path];
	}

}
