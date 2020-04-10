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

namespace OCP\Preview;

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IImage;

/**
 * @since 17.0.0
 */
interface IProviderV2 {
	/**
	 * @return string Regex with the mimetypes that are supported by this provider
	 * @since 17.0.0
	 */
	public function getMimeType(): string;

	/**
	 * Check if a preview can be generated for $path
	 *
	 * @param FileInfo $file
	 * @return bool
	 * @since 17.0.0
	 */
	public function isAvailable(FileInfo $file): bool;

	/**
	 * get thumbnail for file at path $path
	 *
	 * @param File $file
	 * @param int $maxX The maximum X size of the thumbnail. It can be smaller depending on the shape of the image
	 * @param int $maxY The maximum Y size of the thumbnail. It can be smaller depending on the shape of the image
	 * @return null|\OCP\IImage null if no preview was generated
	 * @since 17.0.0
	 */
	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage;
}
