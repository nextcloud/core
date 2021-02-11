<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

class MarkDown extends TXT {
	/**
	 * {@inheritDoc}
	 */
	public function getMimeType(): string {
		return '/text\/(x-)?markdown/';
	}

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

		// Merge text paragraph lines that might belong together
		$content = preg_replace('/^(\s*)\*\s/mU', '$1- ', $content);

		$content = preg_replace('/((?!^(\s*-|#)).*)(\w|\\|\.)(\r\n|\n|\r)(\w|\*)/mU', '$1 $3', $content);

		// Remove markdown symbols that we cannot easily represent in rendered text in the preview
		$content = preg_replace('/\*\*(.*)\*\*/U', '$1', $content);
		$content = preg_replace('/\*(.*)\*/U', '$1', $content);
		$content = preg_replace('/\_\_(.*)\_\_/U', '$1', $content);
		$content = preg_replace('/\_(.*)\_/U', '$1', $content);
		$content = preg_replace('/\~\~(.*)\~\~/U', '$1', $content);

		$content = preg_replace('/\!?\[((.|\n)*)\]\((.*)\)/mU', '$1 ($3)', $content);
		$content = preg_replace('/\n\n+/', "\n", $content);

		$content = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $content);

		$lines = preg_split("/\r\n|\n|\r/", $content);

		// Define text size of text file preview
		$fontSize = $maxX ? (int) ((1 / ($maxX >= 512 ? 60 : 40) * $maxX)) : 10;

		$image = imagecreate($maxX, $maxY);
		imagecolorallocate($image, 255, 255, 255);
		$textColor = imagecolorallocate($image, 0, 0, 0);

		$fontFile  = __DIR__ . '/../../../core/fonts/NotoSans-Regular.ttf';
		$fontFileBold  = __DIR__ . '/../../../core/fonts/NotoSans-Bold.ttf';

		$canUseTTF = function_exists('imagettftext');

		$textOffset = (int)min($maxX * 0.05, $maxY * 0.05);
		$nextLineStart = 0;
		$y = $textOffset;
		foreach ($lines as $line) {
			$actualFontSize = $fontSize;
			if (mb_strpos($line, '# ') === 0) {
				$actualFontSize *= 2;
			}
			if (mb_strpos($line, '## ') === 0) {
				$actualFontSize *= 1.8;
			}
			if (mb_strpos($line, '### ') === 0) {
				$actualFontSize *= 1.6;
			}
			if (mb_strpos($line, '#### ') === 0) {
				$actualFontSize *= 1.4;
			}
			if (mb_strpos($line, '##### ') === 0) {
				$actualFontSize *= 1.2;
			}
			if (mb_strpos($line, '###### ') === 0) {
				$actualFontSize *= 1.1;
			}

			// Add spacing before headlines
			if ($actualFontSize !== $fontSize && $y !== $textOffset) {
				$y += (int)($actualFontSize * 2);
			}

			$x = $textOffset;
			$y += (int)($nextLineStart + $actualFontSize);

			if ($canUseTTF === true) {
				$wordWrap = (int)((1 / $actualFontSize * 1.3) * $maxX);

				// Get rid of markdown symbols that we still needed for the font size
				$line = preg_replace('/^#*\s/', '', $line);

				$wrappedText = wordwrap($line, $wordWrap,"\n");
				$linesWrapped = count(explode("\n", $wrappedText));
				imagettftext($image, $actualFontSize, 0, $x, $y, $textColor, $actualFontSize === $fontSize ? $fontFile : $fontFileBold, $wrappedText);
				$nextLineStart = (int)($linesWrapped * ceil($actualFontSize * 2));
				if ($actualFontSize !== $fontSize && $y !== $textOffset) {
					$nextLineStart -= $actualFontSize;
				}
			} else {
				$y -= (int)$fontSize;
				imagestring($image, 1, $x, $y, $line, $textColor);
				$nextLineStart = $fontSize;
			}

			if ($y >= $maxY) {
				break;
			}
		}

		$imageObject = new \OC_Image();
		$imageObject->setResource($image);

		return $imageObject->valid() ? $imageObject : null;
	}
}
