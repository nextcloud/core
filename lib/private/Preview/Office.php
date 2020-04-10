<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tor Lillqvist <tml@collabora.com>
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

abstract class Office extends ProviderV2 {
	private $cmd;

	/**
	 * {@inheritDoc}
	 */
	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		$this->initCmd();
		if (is_null($this->cmd)) {
			return null;
		}

		$absPath = $this->getLocalFile($file);

		$tmpDir = \OC::$server->getTempManager()->getTempBaseDir();

		$defaultParameters = ' -env:UserInstallation=file://' . escapeshellarg($tmpDir . '/owncloud-' . \OC_Util::getInstanceId() . '/') . ' --headless --nologo --nofirststartwizard --invisible --norestore --convert-to png --outdir ';
		$clParameters = \OC::$server->getConfig()->getSystemValue('preview_office_cl_parameters', $defaultParameters);

		$exec = $this->cmd . $clParameters . escapeshellarg($tmpDir) . ' ' . escapeshellarg($absPath);

		shell_exec($exec);

		//create imagick object from png
		$pngPreview = null;
		try {
			list($dirname, , , $filename) = array_values(pathinfo($absPath));
			$pngPreview = $tmpDir . '/' . $filename . '.png';

			$png = new \imagick($pngPreview . '[0]');
			$png->setImageFormat('jpg');
		} catch (\Exception $e) {
			$this->cleanTmpFiles();
			unlink($pngPreview);
			\OC::$server->getLogger()->logException($e, [
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return null;
		}

		$image = new \OC_Image();
		$image->loadFromData($png);

		$this->cleanTmpFiles();
		unlink($pngPreview);

		if ($image->valid()) {
			$image->scaleDownToFit($maxX, $maxY);

			return $image;
		}
		return null;

	}

	private function initCmd() {
		$cmd = '';

		$libreOfficePath = \OC::$server->getConfig()->getSystemValue('preview_libreoffice_path', null);
		if (is_string($libreOfficePath)) {
			$cmd = $libreOfficePath;
		}

		$whichLibreOffice = shell_exec('command -v libreoffice');
		if ($cmd === '' && !empty($whichLibreOffice)) {
			$cmd = 'libreoffice';
		}

		$whichOpenOffice = shell_exec('command -v openoffice');
		if ($cmd === '' && !empty($whichOpenOffice)) {
			$cmd = 'openoffice';
		}

		if ($cmd === '') {
			$cmd = null;
		}

		$this->cmd = $cmd;
	}
}
