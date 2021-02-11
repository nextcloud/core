<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bartek Przybylski <bart.p.pl@gmail.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Byron Marohn <combustible@live.com>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author J0WI <J0WI@users.noreply.github.com>
 * @author j-ed <juergen@eisfair.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Johannes Willnecker <johannes@willnecker.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Samuel CHEMLA <chemla.samuel@gmail.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
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

use OCP\IImage;

/**
 * Class for basic image manipulation
 */
class OC_Image implements \OCP\IImage {
	/** @var false|resource */
	protected $resource = false; // tmp resource.
	/** @var int */
	protected $imageType = IMAGETYPE_PNG; // Default to png if file type isn't evident.
	/** @var string */
	protected $mimeType = 'image/png'; // Default to png
	/** @var int */
	protected $bitDepth = 24;
	/** @var null|string */
	protected $filePath = null;
	/** @var finfo */
	private $fileInfo;
	/** @var \OCP\ILogger */
	private $logger;
	/** @var \OCP\IConfig */
	private $config;
	/** @var array */
	private $exif;

	/**
	 * Constructor.
	 *
	 * @param resource|string $imageRef The path to a local file, a base64 encoded string or a resource created by
	 * an imagecreate* function.
	 * @param \OCP\ILogger $logger
	 * @param \OCP\IConfig $config
	 * @throws \InvalidArgumentException in case the $imageRef parameter is not null
	 */
	public function __construct($imageRef = null, \OCP\ILogger $logger = null, \OCP\IConfig $config = null) {
		$this->logger = $logger;
		if ($logger === null) {
			$this->logger = \OC::$server->getLogger();
		}
		$this->config = $config;
		if ($config === null) {
			$this->config = \OC::$server->getConfig();
		}

		if (\OC_Util::fileInfoLoaded()) {
			$this->fileInfo = new finfo(FILEINFO_MIME_TYPE);
		}

		if ($imageRef !== null) {
			throw new \InvalidArgumentException('The first parameter in the constructor is not supported anymore. Please use any of the load* methods of the image object to load an image.');
		}
	}

	/**
	 * Determine whether the object contains an image resource.
	 *
	 * @return bool
	 */
	public function valid() { // apparently you can't name a method 'empty'...
		if (is_resource($this->resource)) {
			return true;
		}
		if (is_object($this->resource) && get_class($this->resource) === 'GdImage') {
			return true;
		}

		return false;
	}

	/**
	 * Returns the MIME type of the image or an empty string if no image is loaded.
	 *
	 * @return string
	 */
	public function mimeType() {
		return $this->valid() ? $this->mimeType : '';
	}

	/**
	 * Returns the width of the image or -1 if no image is loaded.
	 *
	 * @return int
	 */
	public function width() {
		return $this->valid() ? imagesx($this->resource) : -1;
	}

	/**
	 * Returns the height of the image or -1 if no image is loaded.
	 *
	 * @return int
	 */
	public function height() {
		return $this->valid() ? imagesy($this->resource) : -1;
	}

	/**
	 * Returns the width when the image orientation is top-left.
	 *
	 * @return int
	 */
	public function widthTopLeft() {
		$o = $this->getOrientation();
		$this->logger->debug('OC_Image->widthTopLeft() Orientation: ' . $o, ['app' => 'core']);
		switch ($o) {
			case -1:
			case 1:
			case 2: // Not tested
			case 3:
			case 4: // Not tested
				return $this->width();
			case 5: // Not tested
			case 6:
			case 7: // Not tested
			case 8:
				return $this->height();
		}
		return $this->width();
	}

	/**
	 * Returns the height when the image orientation is top-left.
	 *
	 * @return int
	 */
	public function heightTopLeft() {
		$o = $this->getOrientation();
		$this->logger->debug('OC_Image->heightTopLeft() Orientation: ' . $o, ['app' => 'core']);
		switch ($o) {
			case -1:
			case 1:
			case 2: // Not tested
			case 3:
			case 4: // Not tested
				return $this->height();
			case 5: // Not tested
			case 6:
			case 7: // Not tested
			case 8:
				return $this->width();
		}
		return $this->height();
	}

	/**
	 * Outputs the image.
	 *
	 * @param string $mimeType
	 * @return bool
	 */
	public function show($mimeType = null) {
		if ($mimeType === null) {
			$mimeType = $this->mimeType();
		}
		header('Content-Type: ' . $mimeType);
		return $this->_output(null, $mimeType);
	}

	/**
	 * Saves the image.
	 *
	 * @param string $filePath
	 * @param string $mimeType
	 * @return bool
	 */

	public function save($filePath = null, $mimeType = null) {
		if ($mimeType === null) {
			$mimeType = $this->mimeType();
		}
		if ($filePath === null) {
			if ($this->filePath === null) {
				$this->logger->error(__METHOD__ . '(): called with no path.', ['app' => 'core']);
				return false;
			} else {
				$filePath = $this->filePath;
			}
		}
		return $this->_output($filePath, $mimeType);
	}

	/**
	 * Outputs/saves the image.
	 *
	 * @param string $filePath
	 * @param string $mimeType
	 * @return bool
	 * @throws Exception
	 */
	private function _output($filePath = null, $mimeType = null) {
		if ($filePath) {
			if (!file_exists(dirname($filePath))) {
				mkdir(dirname($filePath), 0777, true);
			}
			$isWritable = is_writable(dirname($filePath));
			if (!$isWritable) {
				$this->logger->error(__METHOD__ . '(): Directory \'' . dirname($filePath) . '\' is not writable.', ['app' => 'core']);
				return false;
			} elseif ($isWritable && file_exists($filePath) && !is_writable($filePath)) {
				$this->logger->error(__METHOD__ . '(): File \'' . $filePath . '\' is not writable.', ['app' => 'core']);
				return false;
			}
		}
		if (!$this->valid()) {
			return false;
		}

		$imageType = $this->imageType;
		if ($mimeType !== null) {
			switch ($mimeType) {
				case 'image/gif':
					$imageType = IMAGETYPE_GIF;
					break;
				case 'image/jpeg':
					$imageType = IMAGETYPE_JPEG;
					break;
				case 'image/png':
					$imageType = IMAGETYPE_PNG;
					break;
				case 'image/x-xbitmap':
					$imageType = IMAGETYPE_XBM;
					break;
				case 'image/bmp':
				case 'image/x-ms-bmp':
					$imageType = IMAGETYPE_BMP;
					break;
				default:
					throw new Exception('\OC_Image::_output(): "' . $mimeType . '" is not supported when forcing a specific output format');
			}
		}

		switch ($imageType) {
			case IMAGETYPE_GIF:
				$retVal = imagegif($this->resource, $filePath);
				break;
			case IMAGETYPE_JPEG:
				$retVal = imagejpeg($this->resource, $filePath, $this->getJpegQuality());
				break;
			case IMAGETYPE_PNG:
				$retVal = imagepng($this->resource, $filePath);
				break;
			case IMAGETYPE_XBM:
				if (function_exists('imagexbm')) {
					$retVal = imagexbm($this->resource, $filePath);
				} else {
					throw new Exception('\OC_Image::_output(): imagexbm() is not supported.');
				}

				break;
			case IMAGETYPE_WBMP:
				$retVal = imagewbmp($this->resource, $filePath);
				break;
			case IMAGETYPE_BMP:
				$retVal = imagebmp($this->resource, $filePath, $this->bitDepth);
				break;
			default:
				$retVal = imagepng($this->resource, $filePath);
		}
		return $retVal;
	}

	/**
	 * Prints the image when called as $image().
	 */
	public function __invoke() {
		return $this->show();
	}

	/**
	 * @param resource Returns the image resource in any.
	 * @throws \InvalidArgumentException in case the supplied resource does not have the type "gd"
	 */
	public function setResource($resource) {
		// For PHP<8
		if (is_resource($resource) && get_resource_type($resource) === 'gd') {
			$this->resource = $resource;
			return;
		}
		// PHP 8 has real objects for GD stuff
		if (is_object($resource) && get_class($resource) === 'GdImage') {
			$this->resource = $resource;
			return;
		}
		throw new \InvalidArgumentException('Supplied resource is not of type "gd".');
	}

	/**
	 * @return resource Returns the image resource in any.
	 */
	public function resource() {
		return $this->resource;
	}

	/**
	 * @return string Returns the mimetype of the data. Returns the empty string
	 * if the data is not valid.
	 */
	public function dataMimeType() {
		if (!$this->valid()) {
			return '';
		}

		switch ($this->mimeType) {
			case 'image/png':
			case 'image/jpeg':
			case 'image/gif':
				return $this->mimeType;
			default:
				return 'image/png';
		}
	}

	/**
	 * @return null|string Returns the raw image data.
	 */
	public function data() {
		if (!$this->valid()) {
			return null;
		}
		ob_start();
		switch ($this->mimeType) {
			case "image/png":
				$res = imagepng($this->resource);
				break;
			case "image/jpeg":
				$quality = $this->getJpegQuality();
				if ($quality !== null) {
					$res = imagejpeg($this->resource, null, $quality);
				} else {
					$res = imagejpeg($this->resource);
				}
				break;
			case "image/gif":
				$res = imagegif($this->resource);
				break;
			default:
				$res = imagepng($this->resource);
				$this->logger->info('OC_Image->data. Could not guess mime-type, defaulting to png', ['app' => 'core']);
				break;
		}
		if (!$res) {
			$this->logger->error('OC_Image->data. Error getting image data.', ['app' => 'core']);
		}
		return ob_get_clean();
	}

	/**
	 * @return string - base64 encoded, which is suitable for embedding in a VCard.
	 */
	public function __toString() {
		return base64_encode($this->data());
	}

	/**
	 * @return int|null
	 */
	protected function getJpegQuality() {
		$quality = $this->config->getAppValue('preview', 'jpeg_quality', 90);
		if ($quality !== null) {
			$quality = min(100, max(10, (int) $quality));
		}
		return $quality;
	}

	/**
	 * (I'm open for suggestions on better method name ;)
	 * Get the orientation based on EXIF data.
	 *
	 * @return int The orientation or -1 if no EXIF data is available.
	 */
	public function getOrientation() {
		if ($this->exif !== null) {
			return $this->exif['Orientation'];
		}

		if ($this->imageType !== IMAGETYPE_JPEG) {
			$this->logger->debug('OC_Image->fixOrientation() Image is not a JPEG.', ['app' => 'core']);
			return -1;
		}
		if (!is_callable('exif_read_data')) {
			$this->logger->debug('OC_Image->fixOrientation() Exif module not enabled.', ['app' => 'core']);
			return -1;
		}
		if (!$this->valid()) {
			$this->logger->debug('OC_Image->fixOrientation() No image loaded.', ['app' => 'core']);
			return -1;
		}
		if (is_null($this->filePath) || !is_readable($this->filePath)) {
			$this->logger->debug('OC_Image->fixOrientation() No readable file path set.', ['app' => 'core']);
			return -1;
		}
		$exif = @exif_read_data($this->filePath, 'IFD0');
		if (!$exif) {
			return -1;
		}
		if (!isset($exif['Orientation'])) {
			return -1;
		}
		$this->exif = $exif;
		return $exif['Orientation'];
	}

	public function readExif($data) {
		if (!is_callable('exif_read_data')) {
			$this->logger->debug('OC_Image->fixOrientation() Exif module not enabled.', ['app' => 'core']);
			return;
		}
		if (!$this->valid()) {
			$this->logger->debug('OC_Image->fixOrientation() No image loaded.', ['app' => 'core']);
			return;
		}

		$exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($data));
		if (!$exif) {
			return;
		}
		if (!isset($exif['Orientation'])) {
			return;
		}
		$this->exif = $exif;
	}

	/**
	 * (I'm open for suggestions on better method name ;)
	 * Fixes orientation based on EXIF data.
	 *
	 * @return bool
	 */
	public function fixOrientation() {
		$o = $this->getOrientation();
		$this->logger->debug('OC_Image->fixOrientation() Orientation: ' . $o, ['app' => 'core']);
		$rotate = 0;
		$flip = false;
		switch ($o) {
			case -1:
				return false; //Nothing to fix
			case 1:
				$rotate = 0;
				break;
			case 2:
				$rotate = 0;
				$flip = true;
				break;
			case 3:
				$rotate = 180;
				break;
			case 4:
				$rotate = 180;
				$flip = true;
				break;
			case 5:
				$rotate = 90;
				$flip = true;
				break;
			case 6:
				$rotate = 270;
				break;
			case 7:
				$rotate = 270;
				$flip = true;
				break;
			case 8:
				$rotate = 90;
				break;
		}
		if ($flip && function_exists('imageflip')) {
			imageflip($this->resource, IMG_FLIP_HORIZONTAL);
		}
		if ($rotate) {
			$res = imagerotate($this->resource, $rotate, 0);
			if ($res) {
				if (imagealphablending($res, true)) {
					if (imagesavealpha($res, true)) {
						imagedestroy($this->resource);
						$this->resource = $res;
						return true;
					} else {
						$this->logger->debug('OC_Image->fixOrientation() Error during alpha-saving', ['app' => 'core']);
						return false;
					}
				} else {
					$this->logger->debug('OC_Image->fixOrientation() Error during alpha-blending', ['app' => 'core']);
					return false;
				}
			} else {
				$this->logger->debug('OC_Image->fixOrientation() Error during orientation fixing', ['app' => 'core']);
				return false;
			}
		}
		return false;
	}

	/**
	 * Loads an image from an open file handle.
	 * It is the responsibility of the caller to position the pointer at the correct place and to close the handle again.
	 *
	 * @param resource $handle
	 * @return resource|false An image resource or false on error
	 */
	public function loadFromFileHandle($handle) {
		$contents = stream_get_contents($handle);
		if ($this->loadFromData($contents)) {
			return $this->resource;
		}
		return false;
	}

	/**
	 * Loads an image from a local file.
	 *
	 * @param bool|string $imagePath The path to a local file.
	 * @return bool|resource An image resource or false on error
	 */
	public function loadFromFile($imagePath = false) {
		// exif_imagetype throws "read error!" if file is less than 12 byte
		if (is_bool($imagePath) || !@is_file($imagePath) || !file_exists($imagePath) || filesize($imagePath) < 12 || !is_readable($imagePath)) {
			return false;
		}
		$iType = exif_imagetype($imagePath);
		switch ($iType) {
			case IMAGETYPE_GIF:
				if (imagetypes() & IMG_GIF) {
					$this->resource = imagecreatefromgif($imagePath);
					// Preserve transparency
					imagealphablending($this->resource, true);
					imagesavealpha($this->resource, true);
				} else {
					$this->logger->debug('OC_Image->loadFromFile, GIF images not supported: ' . $imagePath, ['app' => 'core']);
				}
				break;
			case IMAGETYPE_JPEG:
				if (imagetypes() & IMG_JPG) {
					if (getimagesize($imagePath) !== false) {
						$this->resource = @imagecreatefromjpeg($imagePath);
					} else {
						$this->logger->debug('OC_Image->loadFromFile, JPG image not valid: ' . $imagePath, ['app' => 'core']);
					}
				} else {
					$this->logger->debug('OC_Image->loadFromFile, JPG images not supported: ' . $imagePath, ['app' => 'core']);
				}
				break;
			case IMAGETYPE_PNG:
				if (imagetypes() & IMG_PNG) {
					$this->resource = @imagecreatefrompng($imagePath);
					// Preserve transparency
					imagealphablending($this->resource, true);
					imagesavealpha($this->resource, true);
				} else {
					$this->logger->debug('OC_Image->loadFromFile, PNG images not supported: ' . $imagePath, ['app' => 'core']);
				}
				break;
			case IMAGETYPE_XBM:
				if (imagetypes() & IMG_XPM) {
					$this->resource = @imagecreatefromxbm($imagePath);
				} else {
					$this->logger->debug('OC_Image->loadFromFile, XBM/XPM images not supported: ' . $imagePath, ['app' => 'core']);
				}
				break;
			case IMAGETYPE_WBMP:
				if (imagetypes() & IMG_WBMP) {
					$this->resource = @imagecreatefromwbmp($imagePath);
				} else {
					$this->logger->debug('OC_Image->loadFromFile, WBMP images not supported: ' . $imagePath, ['app' => 'core']);
				}
				break;
			case IMAGETYPE_BMP:
				$this->resource = $this->imagecreatefrombmp($imagePath);
				break;
			case IMAGETYPE_WEBP:
				if (imagetypes() & IMG_WEBP) {
					$this->resource = @imagecreatefromwebp($imagePath);
				} else {
					$this->logger->debug('OC_Image->loadFromFile, webp images not supported: ' . $imagePath, ['app' => 'core']);
				}
				break;
			/*
			case IMAGETYPE_TIFF_II: // (intel byte order)
				break;
			case IMAGETYPE_TIFF_MM: // (motorola byte order)
				break;
			case IMAGETYPE_JPC:
				break;
			case IMAGETYPE_JP2:
				break;
			case IMAGETYPE_JPX:
				break;
			case IMAGETYPE_JB2:
				break;
			case IMAGETYPE_SWC:
				break;
			case IMAGETYPE_IFF:
				break;
			case IMAGETYPE_ICO:
				break;
			case IMAGETYPE_SWF:
				break;
			case IMAGETYPE_PSD:
				break;
			*/
			default:

				// this is mostly file created from encrypted file
				$this->resource = imagecreatefromstring(\OC\Files\Filesystem::file_get_contents(\OC\Files\Filesystem::getLocalPath($imagePath)));
				$iType = IMAGETYPE_PNG;
				$this->logger->debug('OC_Image->loadFromFile, Default', ['app' => 'core']);
				break;
		}
		if ($this->valid()) {
			$this->imageType = $iType;
			$this->mimeType = image_type_to_mime_type($iType);
			$this->filePath = $imagePath;
		}
		return $this->resource;
	}

	/**
	 * Loads an image from a string of data.
	 *
	 * @param string $str A string of image data as read from a file.
	 * @return bool|resource An image resource or false on error
	 */
	public function loadFromData($str) {
		if (is_resource($str)) {
			return false;
		}
		$this->resource = @imagecreatefromstring($str);
		if ($this->fileInfo) {
			$this->mimeType = $this->fileInfo->buffer($str);
		}
		if (is_resource($this->resource)) {
			imagealphablending($this->resource, false);
			imagesavealpha($this->resource, true);
		}

		if (!$this->resource) {
			$this->logger->debug('OC_Image->loadFromFile, could not load', ['app' => 'core']);
			return false;
		}
		return $this->resource;
	}

	/**
	 * Loads an image from a base64 encoded string.
	 *
	 * @param string $str A string base64 encoded string of image data.
	 * @return bool|resource An image resource or false on error
	 */
	public function loadFromBase64($str) {
		if (!is_string($str)) {
			return false;
		}
		$data = base64_decode($str);
		if ($data) { // try to load from string data
			$this->resource = @imagecreatefromstring($data);
			if ($this->fileInfo) {
				$this->mimeType = $this->fileInfo->buffer($data);
			}
			if (!$this->resource) {
				$this->logger->debug('OC_Image->loadFromBase64, could not load', ['app' => 'core']);
				return false;
			}
			return $this->resource;
		} else {
			return false;
		}
	}

	/**
	 * Create a new image from file or URL
	 *
	 * @link http://www.programmierer-forum.de/function-imagecreatefrombmp-laeuft-mit-allen-bitraten-t143137.htm
	 * @version 1.00
	 * @param string $fileName <p>
	 * Path to the BMP image.
	 * </p>
	 * @return bool|resource an image resource identifier on success, <b>FALSE</b> on errors.
	 */
	private function imagecreatefrombmp($fileName) {
		if (!($fh = fopen($fileName, 'rb'))) {
			$this->logger->warning('imagecreatefrombmp: Can not open ' . $fileName, ['app' => 'core']);
			return false;
		}
		// read file header
		$meta = unpack('vtype/Vfilesize/Vreserved/Voffset', fread($fh, 14));
		// check for bitmap
		if ($meta['type'] != 19778) {
			fclose($fh);
			$this->logger->warning('imagecreatefrombmp: Can not open ' . $fileName . ' is not a bitmap!', ['app' => 'core']);
			return false;
		}
		// read image header
		$meta += unpack('Vheadersize/Vwidth/Vheight/vplanes/vbits/Vcompression/Vimagesize/Vxres/Vyres/Vcolors/Vimportant', fread($fh, 40));
		// read additional 16bit header
		if ($meta['bits'] == 16) {
			$meta += unpack('VrMask/VgMask/VbMask', fread($fh, 12));
		}
		// set bytes and padding
		$meta['bytes'] = $meta['bits'] / 8;
		$this->bitDepth = $meta['bits']; //remember the bit depth for the imagebmp call
		$meta['decal'] = 4 - (4 * (($meta['width'] * $meta['bytes'] / 4) - floor($meta['width'] * $meta['bytes'] / 4)));
		if ($meta['decal'] == 4) {
			$meta['decal'] = 0;
		}
		// obtain imagesize
		if ($meta['imagesize'] < 1) {
			$meta['imagesize'] = $meta['filesize'] - $meta['offset'];
			// in rare cases filesize is equal to offset so we need to read physical size
			if ($meta['imagesize'] < 1) {
				$meta['imagesize'] = @filesize($fileName) - $meta['offset'];
				if ($meta['imagesize'] < 1) {
					fclose($fh);
					$this->logger->warning('imagecreatefrombmp: Can not obtain file size of ' . $fileName . ' is not a bitmap!', ['app' => 'core']);
					return false;
				}
			}
		}
		// calculate colors
		$meta['colors'] = !$meta['colors'] ? pow(2, $meta['bits']) : $meta['colors'];
		// read color palette
		$palette = [];
		if ($meta['bits'] < 16) {
			$palette = unpack('l' . $meta['colors'], fread($fh, $meta['colors'] * 4));
			// in rare cases the color value is signed
			if ($palette[1] < 0) {
				foreach ($palette as $i => $color) {
					$palette[$i] = $color + 16777216;
				}
			}
		}
		// create gd image
		$im = imagecreatetruecolor($meta['width'], $meta['height']);
		if ($im == false) {
			fclose($fh);
			$this->logger->warning(
				'imagecreatefrombmp: imagecreatetruecolor failed for file "' . $fileName . '" with dimensions ' . $meta['width'] . 'x' . $meta['height'],
				['app' => 'core']);
			return false;
		}

		$data = fread($fh, $meta['imagesize']);
		$p = 0;
		$vide = chr(0);
		$y = $meta['height'] - 1;
		$error = 'imagecreatefrombmp: ' . $fileName . ' has not enough data!';
		// loop through the image data beginning with the lower left corner
		while ($y >= 0) {
			$x = 0;
			while ($x < $meta['width']) {
				switch ($meta['bits']) {
					case 32:
					case 24:
						if (!($part = substr($data, $p, 3))) {
							$this->logger->warning($error, ['app' => 'core']);
							return $im;
						}
						$color = @unpack('V', $part . $vide);
						break;
					case 16:
						if (!($part = substr($data, $p, 2))) {
							fclose($fh);
							$this->logger->warning($error, ['app' => 'core']);
							return $im;
						}
						$color = @unpack('v', $part);
						$color[1] = (($color[1] & 0xf800) >> 8) * 65536 + (($color[1] & 0x07e0) >> 3) * 256 + (($color[1] & 0x001f) << 3);
						break;
					case 8:
						$color = @unpack('n', $vide . ($data[$p] ?? ''));
						$color[1] = isset($palette[$color[1] + 1]) ? $palette[$color[1] + 1] : $palette[1];
						break;
					case 4:
						$color = @unpack('n', $vide . ($data[floor($p)] ?? ''));
						$color[1] = ($p * 2) % 2 == 0 ? $color[1] >> 4 : $color[1] & 0x0F;
						$color[1] = isset($palette[$color[1] + 1]) ? $palette[$color[1] + 1] : $palette[1];
						break;
					case 1:
						$color = @unpack('n', $vide . ($data[floor($p)] ?? ''));
						switch (($p * 8) % 8) {
							case 0:
								$color[1] = $color[1] >> 7;
								break;
							case 1:
								$color[1] = ($color[1] & 0x40) >> 6;
								break;
							case 2:
								$color[1] = ($color[1] & 0x20) >> 5;
								break;
							case 3:
								$color[1] = ($color[1] & 0x10) >> 4;
								break;
							case 4:
								$color[1] = ($color[1] & 0x8) >> 3;
								break;
							case 5:
								$color[1] = ($color[1] & 0x4) >> 2;
								break;
							case 6:
								$color[1] = ($color[1] & 0x2) >> 1;
								break;
							case 7:
								$color[1] = ($color[1] & 0x1);
								break;
						}
						$color[1] = isset($palette[$color[1] + 1]) ? $palette[$color[1] + 1] : $palette[1];
						break;
					default:
						fclose($fh);
						$this->logger->warning('imagecreatefrombmp: ' . $fileName . ' has ' . $meta['bits'] . ' bits and this is not supported!', ['app' => 'core']);
						return false;
				}
				imagesetpixel($im, $x, $y, $color[1]);
				$x++;
				$p += $meta['bytes'];
			}
			$y--;
			$p += $meta['decal'];
		}
		fclose($fh);
		return $im;
	}

	/**
	 * Resizes the image preserving ratio.
	 *
	 * @param integer $maxSize The maximum size of either the width or height.
	 * @return bool
	 */
	public function resize($maxSize) {
		$result = $this->resizeNew($maxSize);
		imagedestroy($this->resource);
		$this->resource = $result;
		return is_resource($result);
	}

	/**
	 * @param $maxSize
	 * @return resource | bool
	 */
	private function resizeNew($maxSize) {
		if (!$this->valid()) {
			$this->logger->error(__METHOD__ . '(): No image loaded', ['app' => 'core']);
			return false;
		}
		$widthOrig = imagesx($this->resource);
		$heightOrig = imagesy($this->resource);
		$ratioOrig = $widthOrig / $heightOrig;

		if ($ratioOrig > 1) {
			$newHeight = round($maxSize / $ratioOrig);
			$newWidth = $maxSize;
		} else {
			$newWidth = round($maxSize * $ratioOrig);
			$newHeight = $maxSize;
		}

		return $this->preciseResizeNew((int)round($newWidth), (int)round($newHeight));
	}

	/**
	 * @param int $width
	 * @param int $height
	 * @return bool
	 */
	public function preciseResize(int $width, int $height): bool {
		$result = $this->preciseResizeNew($width, $height);
		imagedestroy($this->resource);
		$this->resource = $result;
		return is_resource($result);
	}


	/**
	 * @param int $width
	 * @param int $height
	 * @return resource | bool
	 */
	public function preciseResizeNew(int $width, int $height) {
		if (!$this->valid()) {
			$this->logger->error(__METHOD__ . '(): No image loaded', ['app' => 'core']);
			return false;
		}
		$widthOrig = imagesx($this->resource);
		$heightOrig = imagesy($this->resource);
		$process = imagecreatetruecolor($width, $height);
		if ($process === false) {
			$this->logger->error(__METHOD__ . '(): Error creating true color image', ['app' => 'core']);
			return false;
		}

		// preserve transparency
		if ($this->imageType == IMAGETYPE_GIF or $this->imageType == IMAGETYPE_PNG) {
			imagecolortransparent($process, imagecolorallocatealpha($process, 0, 0, 0, 127));
			imagealphablending($process, false);
			imagesavealpha($process, true);
		}

		$res = imagecopyresampled($process, $this->resource, 0, 0, 0, 0, $width, $height, $widthOrig, $heightOrig);
		if ($res === false) {
			$this->logger->error(__METHOD__ . '(): Error re-sampling process image', ['app' => 'core']);
			imagedestroy($process);
			return false;
		}
		return $process;
	}

	/**
	 * Crops the image to the middle square. If the image is already square it just returns.
	 *
	 * @param int $size maximum size for the result (optional)
	 * @return bool for success or failure
	 */
	public function centerCrop($size = 0) {
		if (!$this->valid()) {
			$this->logger->error('OC_Image->centerCrop, No image loaded', ['app' => 'core']);
			return false;
		}
		$widthOrig = imagesx($this->resource);
		$heightOrig = imagesy($this->resource);
		if ($widthOrig === $heightOrig and $size == 0) {
			return true;
		}
		$ratioOrig = $widthOrig / $heightOrig;
		$width = $height = min($widthOrig, $heightOrig);

		if ($ratioOrig > 1) {
			$x = ($widthOrig / 2) - ($width / 2);
			$y = 0;
		} else {
			$y = ($heightOrig / 2) - ($height / 2);
			$x = 0;
		}
		if ($size > 0) {
			$targetWidth = $size;
			$targetHeight = $size;
		} else {
			$targetWidth = $width;
			$targetHeight = $height;
		}
		$process = imagecreatetruecolor($targetWidth, $targetHeight);
		if ($process == false) {
			$this->logger->error('OC_Image->centerCrop, Error creating true color image', ['app' => 'core']);
			imagedestroy($process);
			return false;
		}

		// preserve transparency
		if ($this->imageType == IMAGETYPE_GIF or $this->imageType == IMAGETYPE_PNG) {
			imagecolortransparent($process, imagecolorallocatealpha($process, 0, 0, 0, 127));
			imagealphablending($process, false);
			imagesavealpha($process, true);
		}

		imagecopyresampled($process, $this->resource, 0, 0, $x, $y, $targetWidth, $targetHeight, $width, $height);
		if ($process == false) {
			$this->logger->error('OC_Image->centerCrop, Error re-sampling process image ' . $width . 'x' . $height, ['app' => 'core']);
			imagedestroy($process);
			return false;
		}
		imagedestroy($this->resource);
		$this->resource = $process;
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
		$result = $this->cropNew($x, $y, $w, $h);
		imagedestroy($this->resource);
		$this->resource = $result;
		return is_resource($result);
	}

	/**
	 * Crops the image from point $x$y with dimension $wx$h.
	 *
	 * @param int $x Horizontal position
	 * @param int $y Vertical position
	 * @param int $w Width
	 * @param int $h Height
	 * @return resource | bool
	 */
	public function cropNew(int $x, int $y, int $w, int $h) {
		if (!$this->valid()) {
			$this->logger->error(__METHOD__ . '(): No image loaded', ['app' => 'core']);
			return false;
		}
		$process = imagecreatetruecolor($w, $h);
		if ($process == false) {
			$this->logger->error(__METHOD__ . '(): Error creating true color image', ['app' => 'core']);
			imagedestroy($process);
			return false;
		}

		// preserve transparency
		if ($this->imageType == IMAGETYPE_GIF or $this->imageType == IMAGETYPE_PNG) {
			imagecolortransparent($process, imagecolorallocatealpha($process, 0, 0, 0, 127));
			imagealphablending($process, false);
			imagesavealpha($process, true);
		}

		imagecopyresampled($process, $this->resource, 0, 0, $x, $y, $w, $h, $w, $h);
		if ($process == false) {
			$this->logger->error(__METHOD__ . '(): Error re-sampling process image ' . $w . 'x' . $h, ['app' => 'core']);
			imagedestroy($process);
			return false;
		}
		return $process;
	}

	/**
	 * Resizes the image to fit within a boundary while preserving ratio.
	 *
	 * Warning: Images smaller than $maxWidth x $maxHeight will end up being scaled up
	 *
	 * @param integer $maxWidth
	 * @param integer $maxHeight
	 * @return bool
	 */
	public function fitIn($maxWidth, $maxHeight) {
		if (!$this->valid()) {
			$this->logger->error(__METHOD__ . '(): No image loaded', ['app' => 'core']);
			return false;
		}
		$widthOrig = imagesx($this->resource);
		$heightOrig = imagesy($this->resource);
		$ratio = $widthOrig / $heightOrig;

		$newWidth = min($maxWidth, $ratio * $maxHeight);
		$newHeight = min($maxHeight, $maxWidth / $ratio);

		$this->preciseResize((int)round($newWidth), (int)round($newHeight));
		return true;
	}

	/**
	 * Shrinks larger images to fit within specified boundaries while preserving ratio.
	 *
	 * @param integer $maxWidth
	 * @param integer $maxHeight
	 * @return bool
	 */
	public function scaleDownToFit($maxWidth, $maxHeight) {
		if (!$this->valid()) {
			$this->logger->error(__METHOD__ . '(): No image loaded', ['app' => 'core']);
			return false;
		}
		$widthOrig = imagesx($this->resource);
		$heightOrig = imagesy($this->resource);

		if ($widthOrig > $maxWidth || $heightOrig > $maxHeight) {
			return $this->fitIn($maxWidth, $maxHeight);
		}

		return false;
	}

	public function copy(): IImage {
		$image = new OC_Image(null, $this->logger, $this->config);
		$image->resource = imagecreatetruecolor($this->width(), $this->height());
		imagecopy(
			$image->resource(),
			$this->resource(),
			0,
			0,
			0,
			0,
			$this->width(),
			$this->height()
		);

		return $image;
	}

	public function cropCopy(int $x, int $y, int $w, int $h): IImage {
		$image = new OC_Image(null, $this->logger, $this->config);
		$image->imageType = $this->imageType;
		$image->mimeType = $this->mimeType;
		$image->bitDepth = $this->bitDepth;
		$image->resource = $this->cropNew($x, $y, $w, $h);

		return $image;
	}

	public function preciseResizeCopy(int $width, int $height): IImage {
		$image = new OC_Image(null, $this->logger, $this->config);
		$image->imageType = $this->imageType;
		$image->mimeType = $this->mimeType;
		$image->bitDepth = $this->bitDepth;
		$image->resource = $this->preciseResizeNew($width, $height);

		return $image;
	}

	public function resizeCopy(int $maxSize): IImage {
		$image = new OC_Image(null, $this->logger, $this->config);
		$image->imageType = $this->imageType;
		$image->mimeType = $this->mimeType;
		$image->bitDepth = $this->bitDepth;
		$image->resource = $this->resizeNew($maxSize);

		return $image;
	}


	/**
	 * Resizes the image preserving ratio, returning a new copy
	 *
	 * @param integer $maxSize The maximum size of either the width or height.
	 * @return bool
	 */
	public function copyResize($maxSize): IImage {
	}

	/**
	 * Destroys the current image and resets the object
	 */
	public function destroy() {
		if ($this->valid()) {
			imagedestroy($this->resource);
		}
		$this->resource = null;
	}

	public function __destruct() {
		$this->destroy();
	}
}

if (!function_exists('imagebmp')) {
	/**
	 * Output a BMP image to either the browser or a file
	 *
	 * @link http://www.ugia.cn/wp-data/imagebmp.php
	 * @author legend <legendsky@hotmail.com>
	 * @link http://www.programmierer-forum.de/imagebmp-gute-funktion-gefunden-t143716.htm
	 * @author mgutt <marc@gutt.it>
	 * @version 1.00
	 * @param resource $im
	 * @param string $fileName [optional] <p>The path to save the file to.</p>
	 * @param int $bit [optional] <p>Bit depth, (default is 24).</p>
	 * @param int $compression [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 */
	function imagebmp($im, $fileName = '', $bit = 24, $compression = 0) {
		if (!in_array($bit, [1, 4, 8, 16, 24, 32])) {
			$bit = 24;
		} elseif ($bit == 32) {
			$bit = 24;
		}
		$bits = (int)pow(2, $bit);
		imagetruecolortopalette($im, true, $bits);
		$width = imagesx($im);
		$height = imagesy($im);
		$colorsNum = imagecolorstotal($im);
		$rgbQuad = '';
		if ($bit <= 8) {
			for ($i = 0; $i < $colorsNum; $i++) {
				$colors = imagecolorsforindex($im, $i);
				$rgbQuad .= chr($colors['blue']) . chr($colors['green']) . chr($colors['red']) . "\0";
			}
			$bmpData = '';
			if ($compression == 0 || $bit < 8) {
				$compression = 0;
				$extra = '';
				$padding = 4 - ceil($width / (8 / $bit)) % 4;
				if ($padding % 4 != 0) {
					$extra = str_repeat("\0", $padding);
				}
				for ($j = $height - 1; $j >= 0; $j--) {
					$i = 0;
					while ($i < $width) {
						$bin = 0;
						$limit = $width - $i < 8 / $bit ? (8 / $bit - $width + $i) * $bit : 0;
						for ($k = 8 - $bit; $k >= $limit; $k -= $bit) {
							$index = imagecolorat($im, $i, $j);
							$bin |= $index << $k;
							$i++;
						}
						$bmpData .= chr($bin);
					}
					$bmpData .= $extra;
				}
			} // RLE8
			elseif ($compression == 1 && $bit == 8) {
				for ($j = $height - 1; $j >= 0; $j--) {
					$lastIndex = null;
					$sameNum = 0;
					for ($i = 0; $i <= $width; $i++) {
						$index = imagecolorat($im, $i, $j);
						if ($index !== $lastIndex || $sameNum > 255) {
							if ($sameNum != 0) {
								$bmpData .= chr($sameNum) . chr($lastIndex);
							}
							$lastIndex = $index;
							$sameNum = 1;
						} else {
							$sameNum++;
						}
					}
					$bmpData .= "\0\0";
				}
				$bmpData .= "\0\1";
			}
			$sizeQuad = strlen($rgbQuad);
			$sizeData = strlen($bmpData);
		} else {
			$extra = '';
			$padding = 4 - ($width * ($bit / 8)) % 4;
			if ($padding % 4 != 0) {
				$extra = str_repeat("\0", $padding);
			}
			$bmpData = '';
			for ($j = $height - 1; $j >= 0; $j--) {
				for ($i = 0; $i < $width; $i++) {
					$index = imagecolorat($im, $i, $j);
					$colors = imagecolorsforindex($im, $index);
					if ($bit == 16) {
						$bin = 0 << $bit;
						$bin |= ($colors['red'] >> 3) << 10;
						$bin |= ($colors['green'] >> 3) << 5;
						$bin |= $colors['blue'] >> 3;
						$bmpData .= pack("v", $bin);
					} else {
						$bmpData .= pack("c*", $colors['blue'], $colors['green'], $colors['red']);
					}
				}
				$bmpData .= $extra;
			}
			$sizeQuad = 0;
			$sizeData = strlen($bmpData);
			$colorsNum = 0;
		}
		$fileHeader = 'BM' . pack('V3', 54 + $sizeQuad + $sizeData, 0, 54 + $sizeQuad);
		$infoHeader = pack('V3v2V*', 0x28, $width, $height, 1, $bit, $compression, $sizeData, 0, 0, $colorsNum, 0);
		if ($fileName != '') {
			$fp = fopen($fileName, 'wb');
			fwrite($fp, $fileHeader . $infoHeader . $rgbQuad . $bmpData);
			fclose($fp);
			return true;
		}
		echo $fileHeader . $infoHeader . $rgbQuad . $bmpData;
		return true;
	}
}

if (!function_exists('exif_imagetype')) {
	/**
	 * Workaround if exif_imagetype does not exist
	 *
	 * @link https://www.php.net/manual/en/function.exif-imagetype.php#80383
	 * @param string $fileName
	 * @return string|boolean
	 */
	function exif_imagetype($fileName) {
		if (($info = getimagesize($fileName)) !== false) {
			return $info[2];
		}
		return false;
	}
}
