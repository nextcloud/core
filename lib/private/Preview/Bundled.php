<?php
/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Preview;

use OC\Archive\ZIP;
use OCP\Files\File;
use OCP\IImage;

/**
 * Extracts a preview from files that embed them in an ZIP archive
 */
abstract class Bundled extends ProviderV2 {

	protected function extractThumbnail(File $file, $path): ?IImage {
		$sourceTmp = \OC::$server->getTempManager()->getTemporaryFile();
		$targetTmp = \OC::$server->getTempManager()->getTemporaryFile();

		try {
			$content = $file->fopen('r');
			file_put_contents($sourceTmp, $content);

			$zip = new ZIP($sourceTmp);
			$zip->extractFile($path, $targetTmp);

			$image = new \OC_Image();
			$image->loadFromFile($targetTmp);
			$image->fixOrientation();

			return $image;
		} catch (\Exception $e) {
			return null;
		}
	}

}
