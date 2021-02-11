<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Theming;

use Imagick;
use ImagickPixel;
use OCP\App\AppPathNotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;

class IconBuilder {
	/** @var ThemingDefaults */
	private $themingDefaults;
	/** @var Util */
	private $util;
	/** @var ImageManager */
	private $imageManager;

	/**
	 * IconBuilder constructor.
	 *
	 * @param ThemingDefaults $themingDefaults
	 * @param Util $util
	 * @param ImageManager $imageManager
	 */
	public function __construct(
		ThemingDefaults $themingDefaults,
		Util $util,
		ImageManager $imageManager
	) {
		$this->themingDefaults = $themingDefaults;
		$this->util = $util;
		$this->imageManager = $imageManager;
	}

	/**
	 * @param $app string app name
	 * @return string|false image blob
	 */
	public function getFavicon($app) {
		if (!$this->imageManager->shouldReplaceIcons()) {
			return false;
		}
		try {
			$favicon = new Imagick();
			$favicon->setFormat("ico");
			$icon = $this->renderAppIcon($app, 128);
			if ($icon === false) {
				return false;
			}
			$icon->setImageFormat("png32");

			$clone = clone $icon;
			$clone->scaleImage(16,0);
			$favicon->addImage($clone);

			$clone = clone $icon;
			$clone->scaleImage(32,0);
			$favicon->addImage($clone);

			$clone = clone $icon;
			$clone->scaleImage(64,0);
			$favicon->addImage($clone);

			$clone = clone $icon;
			$clone->scaleImage(128,0);
			$favicon->addImage($clone);

			$data = $favicon->getImagesBlob();
			$favicon->destroy();
			$icon->destroy();
			$clone->destroy();
			return $data;
		} catch (\ImagickException $e) {
			return false;
		}
	}

	/**
	 * @param $app string app name
	 * @return string|false image blob
	 */
	public function getTouchIcon($app) {
		try {
			$icon = $this->renderAppIcon($app, 512);
			if ($icon === false) {
				return false;
			}
			$icon->setImageFormat("png32");
			$data = $icon->getImageBlob();
			$icon->destroy();
			return $data;
		} catch (\ImagickException $e) {
			return false;
		}
	}

	/**
	 * Render app icon on themed background color
	 * fallback to logo
	 *
	 * @param $app string app name
	 * @param $size int size of the icon in px
	 * @return Imagick|false
	 */
	public function renderAppIcon($app, $size) {
		$appIcon = $this->util->getAppIcon($app);
		if ($appIcon === false) {
			return false;
		}
		if ($appIcon instanceof ISimpleFile) {
			$appIconContent = $appIcon->getContent();
			$mime = $appIcon->getMimeType();
		} else {
			$appIconContent = file_get_contents($appIcon);
			$mime = mime_content_type($appIcon);
		}

		if ($appIconContent === false || $appIconContent === "") {
			return false;
		}

		$color = $this->themingDefaults->getColorPrimary();

		// generate background image with rounded corners
		$background = '<?xml version="1.0" encoding="UTF-8"?>' .
			'<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:cc="http://creativecommons.org/ns#" width="512" height="512" xmlns:xlink="http://www.w3.org/1999/xlink">' .
			'<rect x="0" y="0" rx="100" ry="100" width="512" height="512" style="fill:' . $color . ';" />' .
			'</svg>';
		// resize svg magic as this seems broken in Imagemagick
		if ($mime === "image/svg+xml" || substr($appIconContent, 0, 4) === "<svg") {
			if (substr($appIconContent, 0, 5) !== "<?xml") {
				$svg = "<?xml version=\"1.0\"?>".$appIconContent;
			} else {
				$svg = $appIconContent;
			}
			$tmp = new Imagick();
			$tmp->readImageBlob($svg);
			$x = $tmp->getImageWidth();
			$y = $tmp->getImageHeight();
			$res = $tmp->getImageResolution();
			$tmp->destroy();

			if ($x > $y) {
				$max = $x;
			} else {
				$max = $y;
			}

			// convert svg to resized image
			$appIconFile = new Imagick();
			$resX = (int)(512 * $res['x'] / $max * 2.53);
			$resY = (int)(512 * $res['y'] / $max * 2.53);
			$appIconFile->setResolution($resX, $resY);
			$appIconFile->setBackgroundColor(new ImagickPixel('transparent'));
			$appIconFile->readImageBlob($svg);

			/**
			 * invert app icons for bright primary colors
			 * the default nextcloud logo will not be inverted to black
			 */
			if ($this->util->invertTextColor($color)
				&& !$appIcon instanceof ISimpleFile
				&& $app !== "core"
			) {
				$appIconFile->negateImage(false);
			}
			$appIconFile->scaleImage(512, 512, true);
		} else {
			$appIconFile = new Imagick();
			$appIconFile->setBackgroundColor(new ImagickPixel('transparent'));
			$appIconFile->readImageBlob($appIconContent);
			$appIconFile->scaleImage(512, 512, true);
		}
		// offset for icon positioning
		$border_w = (int)($appIconFile->getImageWidth() * 0.05);
		$border_h = (int)($appIconFile->getImageHeight() * 0.05);
		$innerWidth = ($appIconFile->getImageWidth() - $border_w * 2);
		$innerHeight = ($appIconFile->getImageHeight() - $border_h * 2);
		$appIconFile->adaptiveResizeImage($innerWidth, $innerHeight);
		// center icon
		$offset_w = 512 / 2 - $innerWidth / 2;
		$offset_h = 512 / 2 - $innerHeight / 2;

		$finalIconFile = new Imagick();
		$finalIconFile->setBackgroundColor(new ImagickPixel('transparent'));
		$finalIconFile->readImageBlob($background);
		$finalIconFile->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
		$finalIconFile->setImageArtifact('compose:args', "1,0,-0.5,0.5");
		$finalIconFile->compositeImage($appIconFile, Imagick::COMPOSITE_ATOP, $offset_w, $offset_h);
		$finalIconFile->setImageFormat('png24');
		if (defined("Imagick::INTERPOLATE_BICUBIC") === true) {
			$filter = Imagick::INTERPOLATE_BICUBIC;
		} else {
			$filter = Imagick::FILTER_LANCZOS;
		}
		$finalIconFile->resizeImage($size, $size, $filter, 1, false);

		$appIconFile->destroy();
		return $finalIconFile;
	}

	public function colorSvg($app, $image) {
		try {
			$imageFile = $this->util->getAppImage($app, $image);
		} catch (AppPathNotFoundException $e) {
			return false;
		}
		$svg = file_get_contents($imageFile);
		if ($svg !== false && $svg !== "") {
			$color = $this->util->elementColor($this->themingDefaults->getColorPrimary());
			$svg = $this->util->colorizeSvg($svg, $color);
			return $svg;
		} else {
			return false;
		}
	}
}
