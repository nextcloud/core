<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Alexander A. Klimov <grandmaster@al2klimov.de>
 * @author Daniel Schneider <daniel@schneidoa.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
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
use OCP\IImage;

class Movie extends ProviderV2 {
	public static $avconvBinary;
	public static $ffmpegBinary;

	/**
	 * {@inheritDoc}
	 */
	public function getMimeType(): string {
		return '/video\/.*/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		// TODO: use proc_open() and stream the source file ?

		$absPath = $this->getLocalFile($file, 5242880); // only use the first 5MB

		$result = $this->generateThumbNail($maxX, $maxY, $absPath, 5);
		if ($result === null) {
			$result = $this->generateThumbNail($maxX, $maxY, $absPath, 1);
			if ($result === null) {
				$result = $this->generateThumbNail($maxX, $maxY, $absPath, 0);
			}
		}

		$this->cleanTmpFiles();

		return $result;
	}

	/**
	 * @param int $maxX
	 * @param int $maxY
	 * @param string $absPath
	 * @param int $second
	 * @return null|\OCP\IImage
	 */
	private function generateThumbNail($maxX, $maxY, $absPath, $second): ?IImage {
		$tmpPath = \OC::$server->getTempManager()->getTemporaryFile();

		if (self::$avconvBinary) {
			$cmd = self::$avconvBinary . ' -y -ss ' . escapeshellarg($second) .
				' -i ' . escapeshellarg($absPath) .
				' -an -f mjpeg -vframes 1 -vsync 1 ' . escapeshellarg($tmpPath) .
				' > /dev/null 2>&1';
		} else {
			$cmd = self::$ffmpegBinary . ' -y -ss ' . escapeshellarg($second) .
				' -i ' . escapeshellarg($absPath) .
				' -f mjpeg -vframes 1' .
				' ' . escapeshellarg($tmpPath) .
				' > /dev/null 2>&1';
		}

		exec($cmd, $output, $returnCode);

		if ($returnCode === 0) {
			$image = new \OC_Image();
			$image->loadFromFile($tmpPath);
			if ($image->valid()) {
				unlink($tmpPath);
				$image->scaleDownToFit($maxX, $maxY);

				return $image;
			}
		}
		unlink($tmpPath);
		return null;
	}
}
