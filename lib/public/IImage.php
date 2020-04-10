<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP;

/**
 * Class for basic image manipulation
 * @since 8.1.0
 */
interface IImage {
	/**
	 * Determine whether the object contains an image resource.
	 *
	 * @return bool
	 * @since 8.1.0
	 */
	public function valid();

	/**
	 * Returns the MIME type of the image or an empty string if no image is loaded.
	 *
	 * @return string
	 * @since 8.1.0
	 */
	public function mimeType();

	/**
	 * Returns the width of the image or -1 if no image is loaded.
	 *
	 * @return int
	 * @since 8.1.0
	 */
	public function width();

	/**
	 * Returns the height of the image or -1 if no image is loaded.
	 *
	 * @return int
	 * @since 8.1.0
	 */
	public function height();

	/**
	 * Returns the width when the image orientation is top-left.
	 *
	 * @return int
	 * @since 8.1.0
	 */
	public function widthTopLeft();

	/**
	 * Returns the height when the image orientation is top-left.
	 *
	 * @return int
	 * @since 8.1.0
	 */
	public function heightTopLeft();

	/**
	 * Outputs the image.
	 *
	 * @param string $mimeType
	 * @return bool
	 * @since 8.1.0
	 */
	public function show($mimeType = null);

	/**
	 * Saves the image.
	 *
	 * @param string $filePath
	 * @param string $mimeType
	 * @return bool
	 * @since 8.1.0
	 */
	public function save($filePath = null, $mimeType = null);

	/**
	 * @return resource Returns the image resource in any.
	 * @since 8.1.0
	 */
	public function resource();

	/**
	 * @return string Returns the raw data mimetype
	 * @since 13.0.0
	 */
	public function dataMimeType();

	/**
	 * @return string Returns the raw image data.
	 * @since 8.1.0
	 */
	public function data();

	/**
	 * (I'm open for suggestions on better method name ;)
	 * Get the orientation based on EXIF data.
	 *
	 * @return int The orientation or -1 if no EXIF data is available.
	 * @since 8.1.0
	 */
	public function getOrientation();

	/**
	 * (I'm open for suggestions on better method name ;)
	 * Fixes orientation based on EXIF data.
	 *
	 * @return bool
	 * @since 8.1.0
	 */
	public function fixOrientation();

	/**
	 * Resizes the image preserving ratio.
	 *
	 * @param integer $maxSize The maximum size of either the width or height.
	 * @return bool
	 * @since 8.1.0
	 */
	public function resize($maxSize);

	/**
	 * @param int $width
	 * @param int $height
	 * @return bool
	 * @since 8.1.0
	 */
	public function preciseResize(int $width, int $height): bool;

	/**
	 * Crops the image to the middle square. If the image is already square it just returns.
	 *
	 * @param int $size maximum size for the result (optional)
	 * @return bool for success or failure
	 * @since 8.1.0
	 */
	public function centerCrop($size = 0);

	/**
	 * Crops the image from point $x$y with dimension $wx$h.
	 *
	 * @param int $x Horizontal position
	 * @param int $y Vertical position
	 * @param int $w Width
	 * @param int $h Height
	 * @return bool for success or failure
	 * @since 8.1.0
	 */
	public function crop(int $x, int $y, int $w, int $h): bool;

	/**
	 * Resizes the image to fit within a boundary while preserving ratio.
	 *
	 * @param integer $maxWidth
	 * @param integer $maxHeight
	 * @return bool
	 * @since 8.1.0
	 */
	public function fitIn($maxWidth, $maxHeight);

	/**
	 * Shrinks the image to fit within a boundary while preserving ratio.
	 *
	 * @param integer $maxWidth
	 * @param integer $maxHeight
	 * @return bool
	 * @since 8.1.0
	 */
	public function scaleDownToFit($maxWidth, $maxHeight);
}
