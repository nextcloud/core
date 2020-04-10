<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\Cache;

use OCP\Files\Cache\ICacheEntry;

class HomeCache extends Cache {
	/**
	 * get the size of a folder and set it in the cache
	 *
	 * @param string $path
	 * @param array $entry (optional) meta data of the folder
	 * @return int
	 */
	public function calculateFolderSize($path, $entry = null) {
		if ($path !== '/' and $path !== '' and $path !== 'files' and $path !== 'files_trashbin' and $path !== 'files_versions') {
			return parent::calculateFolderSize($path, $entry);
		} elseif ($path === '' or $path === '/') {
			// since the size of / isn't used (the size of /files is used instead) there is no use in calculating it
			return 0;
		}

		$totalSize = 0;
		if (is_null($entry)) {
			$entry = $this->get($path);
		}
		if ($entry && $entry['mimetype'] === 'httpd/unix-directory') {
			$id = $entry['fileid'];
			$sql = 'SELECT SUM(`size`) AS f1 ' .
			   'FROM `*PREFIX*filecache` ' .
				'WHERE `parent` = ? AND `storage` = ? AND `size` >= 0';
			$result = \OC_DB::executeAudited($sql, [$id, $this->getNumericStorageId()]);
			if ($row = $result->fetchRow()) {
				$result->closeCursor();
				list($sum) = array_values($row);
				$totalSize = 0 + $sum;
				$entry['size'] += 0;
				if ($entry['size'] !== $totalSize) {
					$this->update($id, ['size' => $totalSize]);
				}
			}
		}
		return $totalSize;
	}

	/**
	 * @param string $path
	 * @return ICacheEntry
	 */
	public function get($path) {
		$data = parent::get($path);
		if ($path === '' or $path === '/') {
			// only the size of the "files" dir counts
			$filesData = parent::get('files');

			if (isset($filesData['size'])) {
				$data['size'] = $filesData['size'];
			}
		}
		return $data;
	}
}
