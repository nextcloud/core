<?php
/**
 * @author Olivier Paroz <owncloud@interfasys.ch>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

namespace Test\Preview;

/**
 * Class MovieTest
 *
 * @group DB
 *
 * @package Test\Preview
 */
class MovieTest extends Provider {

	protected function setUp(): void {
		$avconvBinary = \OC_Helper::findBinaryPath('avconv');
		$ffmpegBinary = ($avconvBinary) ? null : \OC_Helper::findBinaryPath('ffmpeg');

		if ($avconvBinary || $ffmpegBinary) {
			parent::setUp();

			\OC\Preview\Movie::$avconvBinary = $avconvBinary;
			\OC\Preview\Movie::$ffmpegBinary = $ffmpegBinary;

			$fileName = 'testimage.mp4';
			$this->imgPath = $this->prepareTestFile($fileName, \OC::$SERVERROOT . '/tests/data/' . $fileName);
			$this->width = 560;
			$this->height = 320;
			$this->provider = new \OC\Preview\Movie;
		} else {
			$this->markTestSkipped('No Movie provider present');
		}
	}

}
