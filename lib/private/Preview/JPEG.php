<?php
/**
 * @copyright Copyright (c) 2019, ownCloud, Inc.
 *
 * @author Olivier Paroz <github@oparoz.com>
 * @author Ignacio Nunez <nachoparker@ownyourbits.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Preview;

use Imagick;
use OCP\ILogger;

class JPEG extends Image {
	/**
	 * {@inheritDoc}
	 */
	public function getMimeType(): string {
		return '/image\/jpeg/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getThumbnail($path, $maxX, $maxY, $scalingup, $fileview) {
		$imagick_mode = (bool)\OC::$server->getConfig()->getSystemValue('preview_use_imagick', false);
		if (!$imagick_mode || !extension_loaded('imagick')) {
			return parent::getThumbnail($path, $maxX, $maxY, $scalingup, $fileview);
		}

		$tmpPath = $fileview->toTmpFile($path);
		if (!$tmpPath) {
			return false;
		}

		// Creates \Imagick object from the JPG file
		try {
			$bp = $this->getResizedPreview($tmpPath, $maxX, $maxY);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => 'File: ' . $fileview->getAbsolutePath($path) . ' Imagick says:',
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return false;
		}

		unlink($tmpPath);

		//new bitmap image object
		$image = new OC_Image_JPEG();
		$image->loadFromData($bp->getImageBlob());
		//check if image object is valid
		return $image->valid() ? $image : false;
	}

	/**
	 * Returns a preview of maxX times maxY dimensions in JPG format
	 *
	 * @param string $tmpPath the location of the file to convert
	 * @param int $maxX
	 * @param int $maxY
	 *
	 * @return \Imagick
	 */
	private function getResizedPreview($tmpPath, $maxX, $maxY) {
		$config = \OC::$server->getConfig();
		$bp = new Imagick();

		$bp->readImage($tmpPath);

		$threads = (int)$config->getSystemValue('preview_thread_limit', 1);
		if ($threads != -1) {
			$bp->setResourceLimit(imagick::RESOURCETYPE_THREAD, $threads);
		}
		$quality = (int)$config->getSystemValue('jpeg_quality', 90);
		if ($quality !== null) {
			$quality = min(100, max(10, (int) $quality));
		}
		$bp->setImageCompressionQuality($quality);
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
			$interpolate = (bool)\OC::$server->getConfig()->getSystemValue('preview_interpolate', false);
			if ($interpolate) {
				$bp->resizeImage($maxX, $maxY, imagick::FILTER_CATROM, 1, true);
			} else {
				$bp->scaleImage($maxX, $maxY, true);
			}
		}

		return $bp;
	}
}

// TODO move to a new file
class OC_Image_JPEG extends \OC_Image {

	/** @var string */
	protected $mimeType = 'image/jpeg';

	/**
	 * Loads an image from a string of data.
	 *
	 * @param string $str A string of image data as read from a file.
	 * @return bool|resource An image resource or false on error
	 */
	public function loadFromData($str) {
		$bp = new Imagick();
		try {
			$bp->readImageBlob($str);
		} catch (\Exception $e) {
			$this->logger->error('OC_Image_JPEG->loadFromData. Error loading image.', array('app' => 'core'));
			return false;
		}
		$threads = (int)$this->config->getSystemValue('preview_thread_limit', 1);
		if ($threads != 0) {
			$bp->setResourceLimit(imagick::RESOURCETYPE_THREAD, $threads);
		}
		$bp->setImageFormat('jpg');
		$this->resource = $bp;
		return $this->resource;
	}

	/**
	 * @return null|string Returns the raw image data.
	 */
	public function data() {
		if (!$this->valid()) {
			return null;
		}
		try {
			$quality = $this->getJpegQuality();
			$this->resource->setImageCompressionQuality($quality);
			$data = $this->resource->getImageBlob();
		} catch (\Exception $e) {
			$this->logger->error('OC_Image_JPEG->data. Error getting image data.', array('app' => 'core'));
		}
		return $data;
	}
	/**
	 * Determine whether the object contains an image resource.
	 *
	 * @return bool
	 */
	public function valid() { // apparently you can't name a method 'empty'...
		return $this->resource->valid();
	}

	/**
	 * Destroys the current image and resets the object
	 */
	public function destroy() {
		if ($this->valid()) {
			$this->resource->clear();
		}
		$this->resource = null;
	}

	public function __destruct() {
		$this->destroy();
	}

	/**
	 * Returns the width of the image or -1 if no image is loaded.
	 *
	 * @return int
	 */
	public function width() {
		return $this->valid() ? $this->resource->getImageWidth() : -1;
	}

	/**
	 * Returns the height of the image or -1 if no image is loaded.
	 *
	 * @return int
	 */
	public function height() {
		return $this->valid() ? $this->resource->getImageHeight() : -1;
	}

	/**
	 * Resizes the image preserving ratio.
	 *
	 * @param integer $maxSize The maximum size of either the width or height.
	 * @return bool
	 */
	public function resize($maxSize) {
		if (!$this->valid()) {
			$this->logger->error(__METHOD__ . '(): No image loaded', array('app' => 'core'));
			return false;
		}
		$widthOrig = $this->resource->getImageWidth();
		$heightOrig = $this->resource->getImageHeight();
		$ratioOrig = $widthOrig / $heightOrig;

		if ($ratioOrig > 1) {
			$newHeight = round($maxSize / $ratioOrig);
			$newWidth = $maxSize;
		} else {
			$newWidth = round($maxSize * $ratioOrig);
			$newHeight = $maxSize;
		}

		$this->preciseResize((int)round($newWidth), (int)round($newHeight));
		return true;
	}

	/**
	 * @param int $width
	 * @param int $height
	 * @return bool
	 */
	public function preciseResize(int $width, int $height): bool {
		if (!$this->valid()) {
			$this->logger->error(__METHOD__ . '(): No image loaded', array('app' => 'core'));
			return false;
		}
		$interpolate = (bool)$this->config->getSystemValue('preview_interpolate', false);
		if ($interpolate) {
			$this->resource->resizeImage($width, $height, imagick::FILTER_CATROM, 1, true);
		} else {
			$this->resource->scaleImage($width, $height, true);
		}
		return true;
	}

	/**
	 * Crops the image from point $x$y with dimension $wx$h.
	 *
	 * @param int $x Horizontal position
	 * @param int $y Vertical position
	 * @param int $w Width
	 * @param int $h Height
	 * @return bool for success or failure
	 */
	public function crop(int $x, int $y, int $w, int $h): bool {
		if (!$this->valid()) {
			$this->logger->error(__METHOD__ . '(): No image loaded', array('app' => 'core'));
			return false;
		}
		$this->resource->cropImage($w, $h, $x, $y);
		return true;
	}
}
