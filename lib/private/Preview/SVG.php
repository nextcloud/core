<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Preview;

use OCP\Files\File;
use OCP\IImage;
use OCP\ILogger;

class SVG extends ProviderV2 {
	/**
	 * {@inheritDoc}
	 */
	public function getMimeType(): string {
		return '/image\/svg\+xml/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		try {
			$svg = new \Imagick();
			$svg->setBackgroundColor(new \ImagickPixel('transparent'));

			$content = stream_get_contents($file->fopen('r'));
			if (substr($content, 0, 5) !== '<?xml') {
				$content = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . $content;
			}

			// Do not parse SVG files with references
			if (stripos($content, 'xlink:href') !== false) {
				return null;
			}

			$svg->readImageBlob($content);
			$svg->setImageFormat('png32');
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return null;
		}

		//new image object
		$image = new \OC_Image();
		$image->loadFromData($svg);
		//check if image object is valid
		if ($image->valid()) {
			$image->scaleDownToFit($maxX, $maxY);

			return $image;
		}
		return null;
	}
}
