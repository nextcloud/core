<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author nik gaffney <nik@fo.am>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Rello <Rello@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Ebert <thomas.ebert@usability.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RepairMimeTypes implements IRepairStep {
	/** @var IConfig */
	protected $config;
	/** @var IDBConnection */
	protected $connection;

	/** @var int */
	protected $folderMimeTypeId;

	public function __construct(IConfig $config,
								IDBConnection $connection) {
		$this->config = $config;
		$this->connection = $connection;
	}

	public function getName() {
		return 'Repair mime types';
	}

	private function updateMimetypes($updatedMimetypes) {
		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('mimetypes')
			->where($query->expr()->eq('mimetype', $query->createParameter('mimetype'), IQueryBuilder::PARAM_INT));
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('mimetypes')
			->setValue('mimetype', $insert->createParameter('mimetype'));

		if (empty($this->folderMimeTypeId)) {
			$query->setParameter('mimetype', 'httpd/unix-directory');
			$result = $query->execute();
			$this->folderMimeTypeId = (int)$result->fetchOne();
			$result->closeCursor();
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('filecache')
			->set('mimetype', $update->createParameter('mimetype'))
			->where($update->expr()->neq('mimetype', $update->createParameter('mimetype'), IQueryBuilder::PARAM_INT))
			->andWhere($update->expr()->neq('mimetype', $update->createParameter('folder'), IQueryBuilder::PARAM_INT))
			->andWhere($update->expr()->iLike('name', $update->createParameter('name')))
			->setParameter('folder', $this->folderMimeTypeId);

		$count = 0;
		foreach ($updatedMimetypes as $extension => $mimetype) {
			// get target mimetype id
			$query->setParameter('mimetype', $mimetype);
			$result = $query->execute();
			$mimetypeId = (int)$result->fetchOne();
			$result->closeCursor();

			if (!$mimetypeId) {
				// insert mimetype
				$insert->setParameter('mimetype', $mimetype);
				$insert->execute();
				$mimetypeId = $insert->getLastInsertId();
			}

			// change mimetype for files with x extension
			$update->setParameter('mimetype', $mimetypeId)
				->setParameter('name', '%' . $this->connection->escapeLikeParameter('.' . $extension));
			$count += $update->execute();
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

	private function introduceOpenDocumentTemplates() {
		$updatedMimetypes = [
			'ott' => 'application/vnd.oasis.opendocument.text-template',
			'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
			'otp' => 'application/vnd.oasis.opendocument.presentation-template',
			'otg' => 'application/vnd.oasis.opendocument.graphics-template',
		];

		return $this->updateMimetypes($updatedMimetypes);
	}

	private function introduceOrgModeType() {
		$updatedMimetypes = [
			'org' => 'text/org'
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

		if (version_compare($ocVersionFromBeforeUpdate, '20.0.0.5', '<') && $this->introduceOpenDocumentTemplates()) {
			$out->info('Fixed OpenDocument template mime types');
		}

		if (version_compare($ocVersionFromBeforeUpdate, '21.0.0.7', '<') && $this->introduceOrgModeType()) {
			$out->info('Fixed orgmode mime types');
		}
	}
}
