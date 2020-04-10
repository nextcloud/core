<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Rello <Rello@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Ebert <thomas.ebert@usability.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OC\Repair;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RepairMimeTypes implements IRepairStep {
	/**
	 * @var \OCP\IConfig
	 */
	protected $config;

	/**
	 * @var int
	 */
	protected $folderMimeTypeId;

	/**
	 * @param \OCP\IConfig $config
	 */
	public function __construct($config) {
		$this->config = $config;
	}

	public function getName() {
		return 'Repair mime types';
	}

	private static function existsStmt() {
		return \OC_DB::prepare('
			SELECT count(`mimetype`)
			FROM   `*PREFIX*mimetypes`
			WHERE  `mimetype` = ?
		');
	}

	private static function getIdStmt() {
		return \OC_DB::prepare('
			SELECT `id`
			FROM   `*PREFIX*mimetypes`
			WHERE  `mimetype` = ?
		');
	}

	private static function insertStmt() {
		return \OC_DB::prepare('
			INSERT INTO `*PREFIX*mimetypes` ( `mimetype` )
			VALUES ( ? )
		');
	}

	private static function updateByNameStmt() {
		return \OC_DB::prepare('
			UPDATE `*PREFIX*filecache`
			SET `mimetype` = ?
			WHERE `mimetype` <> ? AND `mimetype` <> ? AND `name` ILIKE ?
		');
	}

	private function updateMimetypes($updatedMimetypes) {
		if (empty($this->folderMimeTypeId)) {
			$result = \OC_DB::executeAudited(self::getIdStmt(), ['httpd/unix-directory']);
			$this->folderMimeTypeId = (int)$result->fetchOne();
		}

		$count = 0;
		foreach ($updatedMimetypes as $extension => $mimetype) {
			$result = \OC_DB::executeAudited(self::existsStmt(), [$mimetype]);
			$exists = $result->fetchOne();

			if (!$exists) {
				// insert mimetype
				\OC_DB::executeAudited(self::insertStmt(), [$mimetype]);
			}

			// get target mimetype id
			$result = \OC_DB::executeAudited(self::getIdStmt(), [$mimetype]);
			$mimetypeId = $result->fetchOne();

			// change mimetype for files with x extension
			$count += \OC_DB::executeAudited(self::updateByNameStmt(), [$mimetypeId, $this->folderMimeTypeId, $mimetypeId, '%.' . $extension]);
		}

		return $count;
	}

	private function introduceImageTypes() {
		$updatedMimetypes = [
			'jp2' => 'image/jp2',
			'webp' => 'image/webp',
		];

		return $this->updateMimetypes($updatedMimetypes);
	}

	private function introduceWindowsProgramTypes() {
		$updatedMimetypes = [
			'htaccess' => 'text/plain',
			'bat' => 'application/x-msdos-program',
			'cmd' => 'application/cmd',
		];

		return $this->updateMimetypes($updatedMimetypes);
	}

	private function introduceLocationTypes() {
		$updatedMimetypes = [
			'gpx' => 'application/gpx+xml',
			'kml' => 'application/vnd.google-earth.kml+xml',
			'kmz' => 'application/vnd.google-earth.kmz',
			'tcx' => 'application/vnd.garmin.tcx+xml',
		];

		return $this->updateMimetypes($updatedMimetypes);
	}

	private function introduceInternetShortcutTypes() {
		$updatedMimetypes = [
			'url' => 'application/internet-shortcut',
			'webloc' => 'application/internet-shortcut'
		];

		return $this->updateMimetypes($updatedMimetypes);
	}

	private function introduceStreamingTypes() {
		$updatedMimetypes = [
			'm3u' => 'audio/mpegurl',
			'm3u8' => 'audio/mpegurl',
			'pls' => 'audio/x-scpls'
		];

		return $this->updateMimetypes($updatedMimetypes);
	}

	private function introduceVisioTypes() {
		$updatedMimetypes = [
			'vsdm' => 'application/vnd.visio',
			'vsdx' => 'application/vnd.visio',
			'vssm' => 'application/vnd.visio',
			'vssx' => 'application/vnd.visio',
			'vstm' => 'application/vnd.visio',
			'vstx' => 'application/vnd.visio',
		];

		return $this->updateMimetypes($updatedMimetypes);
	}

	private function introduceComicbookTypes() {
		$updatedMimetypes = [
			'cb7' => 'application/comicbook+7z',
			'cba' => 'application/comicbook+ace',
			'cbr' => 'application/comicbook+rar',
			'cbt' => 'application/comicbook+tar',
			'cbtc' => 'application/comicbook+truecrypt',
			'cbz' => 'application/comicbook+zip',
		];

		return $this->updateMimetypes($updatedMimetypes);
	}

	/**
	 * Fix mime types
	 */
	public function run(IOutput $out) {

		$ocVersionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0');

		// NOTE TO DEVELOPERS: when adding new mime types, please make sure to
		// add a version comparison to avoid doing it every time

		if (version_compare($ocVersionFromBeforeUpdate, '12.0.0.14', '<') && $this->introduceImageTypes()) {
			$out->info('Fixed image mime types');
		}

		if (version_compare($ocVersionFromBeforeUpdate, '12.0.0.13', '<') && $this->introduceWindowsProgramTypes()) {
			$out->info('Fixed windows program mime types');
		}

		if (version_compare($ocVersionFromBeforeUpdate, '13.0.0.0', '<') && $this->introduceLocationTypes()) {
			$out->info('Fixed geospatial mime types');
		}

		if (version_compare($ocVersionFromBeforeUpdate, '13.0.0.3', '<') && $this->introduceInternetShortcutTypes()) {
			$out->info('Fixed internet-shortcut mime types');
		}

		if (version_compare($ocVersionFromBeforeUpdate, '13.0.0.6', '<') && $this->introduceStreamingTypes()) {
			$out->info('Fixed streaming mime types');
		}

		if (version_compare($ocVersionFromBeforeUpdate, '14.0.0.8', '<') && $this->introduceVisioTypes()) {
			$out->info('Fixed visio mime types');
		}

		if (version_compare($ocVersionFromBeforeUpdate, '14.0.0.10', '<') && $this->introduceComicbookTypes()) {
			$out->info('Fixed comicbook mime types');
		}
	}
}
