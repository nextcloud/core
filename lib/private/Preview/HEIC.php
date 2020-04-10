<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @copyright Copyright (c) 2018, Sebastian Steinmetz (me@sebastiansteinmetz.ch)
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sebastian Steinmetz <462714+steiny2k@users.noreply.github.com>
 * @author Sebastian Steinmetz <me@sebastiansteinmetz.ch>
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

namespace OC\Preview;

use OCP\Files\File;
use OCP\IImage;
use OCP\ILogger;

/**
 * Creates a JPG preview using ImageMagick via the PECL extension
 *
 * @package OC\Preview
 */
class HEIC extends ProviderV2 {
	/**
	 * {@inheritDoc}
	 */
	public function getMimeType(): string {
		return '/image\/hei(f|c)/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAvailable(\OCP\Files\FileInfo $file): bool {
		return in_array('HEIC', \Imagick::queryFormats("HEI*"));
	}

	/**
	 * {@inheritDoc}
	 */
	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		$tmpPath = $this->getLocalFile($file);

		// Creates \Imagick object from the heic file
		try {
			$bp = $this->getResizedPreview($tmpPath, $maxX, $maxY);
			$bp->setFormat('jpg');
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => 'File: ' . $file->getPath() . ' Imagick says:',
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return null;
		}

		$this->cleanTmpFiles();

		//new bitmap image object
		$image = new \OC_Image();
		$image->loadFromData($bp);
		//check if image object is valid
		return $image->valid() ? $image : null;
	}

	/**
	 * Returns a preview of maxX times maxY dimensions in JPG format
	 *
	 *    * The default resolution is already 72dpi, no need to change it for a bitmap output
	 *    * It's possible to have proper colour conversion using profileimage().
	 *    ICC profiles are here: http://www.color.org/srgbprofiles.xalter
	 *    * It's possible to Gamma-correct an image via gammaImage()
	 *
	 * @param string $tmpPath the location of the file to convert
	 * @param int $maxX
	 * @param int $maxY
	 *
	 * @return \Imagick
	 */
	private function getResizedPreview($tmpPath, $maxX, $maxY) {
		$bp = new \Imagick();

		// Layer 0 contains either the bitmap or a flat representation of all vector layers
		$bp->readImage($tmpPath . '[0]');

		$bp->setImageFormat('jpg');

		$bp = $this->resize($bp, $maxX, $maxY);
		
		return $bp;
	}

	/**
	 * Returns a resized \Imagick object
	 *
	 * If you want to know more on the various methods available to resize an
	 * image, check out this link : @link https://stackoverflow.com/questions/8517304/what-the-difference-of-sample-resample-scale-resize-adaptive-resize-thumbnail-im
	 *
	 * @param \Imagick $bp
	 * @param int $maxX
	 * @param int $maxY
	 *
	 * @return \Imagick
	 */
	private function resize($bp, $maxX, $maxY) {
		list($previewWidth, $previewHeight) = array_values($bp->getImageGeometry());

		// We only need to resize a preview which doesn't fit in the maximum dimensions
		if ($previewWidth > $maxX || $previewHeight > $maxY) {
			// If we want a small image (thumbnail) let's be most space- and time-efficient
			if ($maxX <= 500 && $maxY <= 500) {
				$bp->thumbnailImage($maxY, $maxX, true);
				$bp->stripImage();
			} else {
				// A bigger image calls for some better resizing algorithm
				// According to http://www.imagemagick.org/Usage/filter/#lanczos
				// the catrom filter is almost identical to Lanczos2, but according
				// to http://php.net/manual/en/imagick.resizeimage.php it is
				// significantly faster
				$bp->resizeImage($maxX, $maxY, \Imagick::FILTER_CATROM, 1, true);
			}
		}

		return $bp;
	}

}
