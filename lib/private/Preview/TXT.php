<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Nmz <nemesiz@nmz.lt>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
use OCP\Files\FileInfo;
use OCP\IImage;

class TXT extends ProviderV2 {
	/**
	 * {@inheritDoc}
	 */
	public function getMimeType(): string {
		return '/text\/plain/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAvailable(FileInfo $file): bool {
		return $file->getSize() > 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		$content = $file->fopen('r');

		if ($content === false) {
			return null;
		}

		$content = stream_get_contents($content,3000);

		//don't create previews of empty text files
		if (trim($content) === '') {
			return null;
		}

		$lines = preg_split("/\r\n|\n|\r/", $content);

		// Define text size of text file preview
		$fontSize = $maxX ? (int) ((1 / 32) * $maxX) : 5; //5px
		$lineSize = ceil($fontSize * 1.5);

		$image = imagecreate($maxX, $maxY);
		imagecolorallocate($image, 255, 255, 255);
		$textColor = imagecolorallocate($image, 0, 0, 0);

		$fontFile = __DIR__;
		$fontFile .= '/../../../core';
		$fontFile .= '/fonts/NotoSans-Regular.ttf';

		$canUseTTF = function_exists('imagettftext');

		foreach ($lines as $index => $line) {
			$index = $index + 1;

			$x = 1;
			$y = (int) ($index * $lineSize);

			if ($canUseTTF === true) {
				imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontFile, $line);
			} else {
				$y -= $fontSize;
				imagestring($image, 1, $x, $y, $line, $textColor);
			}

			if (($index * $lineSize) >= $maxY) {
				break;
			}
		}

		$imageObject = new \OC_Image();
		$imageObject->setResource($image);

		return $imageObject->valid() ? $imageObject : null;
	}
}
