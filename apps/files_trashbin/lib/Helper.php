<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\Files_Trashbin;

use OC\Files\FileInfo;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;

class Helper {
	/**
	 * Retrieves the contents of a trash bin directory.
	 *
	 * @param string $dir path to the directory inside the trashbin
	 * or empty to retrieve the root of the trashbin
	 * @param string $user
	 * @param string $sortAttribute attribute to sort on or empty to disable sorting
	 * @param bool $sortDescending true for descending sort, false otherwise
	 * @return \OCP\Files\FileInfo[]
	 */
	public static function getTrashFiles($dir, $user, $sortAttribute = '', $sortDescending = false) {
		$result = [];
		$timestamp = null;

		$view = new \OC\Files\View('/' . $user . '/files_trashbin/files');

		if (ltrim($dir, '/') !== '' && !$view->is_dir($dir)) {
			throw new \Exception('Directory does not exists');
		}

		$mount = $view->getMount($dir);
		$storage = $mount->getStorage();
		$absoluteDir = $view->getAbsolutePath($dir);
		$internalPath = $mount->getInternalPath($absoluteDir);

		$originalLocations = \OCA\Files_Trashbin\Trashbin::getLocations($user);
		$dirContent = $storage->getCache()->getFolderContents($mount->getInternalPath($view->getAbsolutePath($dir)));
		foreach ($dirContent as $entry) {
			$entryName = $entry->getName();
			$name = $entryName;
			if ($dir === '' || $dir === '/') {
				$pathparts = pathinfo($entryName);
				$timestamp = substr($pathparts['extension'], 1);
				$name = $pathparts['filename'];
			} elseif ($timestamp === null) {
				// for subfolders we need to calculate the timestamp only once
				$parts = explode('/', ltrim($dir, '/'));
				$timestamp = substr(pathinfo($parts[0], PATHINFO_EXTENSION), 1);
			}
			$originalPath = '';
			$originalName = substr($entryName, 0, -strlen($timestamp) - 2);
			if (isset($originalLocations[$originalName][$timestamp])) {
				$originalPath = $originalLocations[$originalName][$timestamp];
				if (substr($originalPath, -1) === '/') {
					$originalPath = substr($originalPath, 0, -1);
				}
			}
			$type = $entry->getMimeType() === ICacheEntry::DIRECTORY_MIMETYPE ? 'dir' : 'file';
			$i = [
				'name' => $name,
				'mtime' => $timestamp,
				'mimetype' => $type === 'dir' ? 'httpd/unix-directory' : \OC::$server->getMimeTypeDetector()->detectPath($name),
				'type' => $type,
				'directory' => ($dir === '/') ? '' : $dir,
				'size' => $entry->getSize(),
				'etag' => '',
				'permissions' => Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE,
				'fileid' => $entry->getId(),
			];
			if ($originalPath) {
				if ($originalPath !== '.') {
					$i['extraData'] = $originalPath . '/' . $originalName;
				} else {
					$i['extraData'] = $originalName;
				}
			}
			$result[] = new FileInfo($absoluteDir . '/' . $i['name'], $storage, $internalPath . '/' . $i['name'], $i, $mount);
		}

		if ($sortAttribute !== '') {
			return \OCA\Files\Helper::sortFiles($result, $sortAttribute, $sortDescending);
		}
		return $result;
	}

	/**
	 * Format file infos for JSON
	 *
	 * @param \OCP\Files\FileInfo[] $fileInfos file infos
	 */
	public static function formatFileInfos($fileInfos) {
		$files = [];
		foreach ($fileInfos as $i) {
			$entry = \OCA\Files\Helper::formatFileInfo($i);
			$entry['id'] = $i->getId();
			$entry['etag'] = $entry['mtime']; // add fake etag, it is only needed to identify the preview image
			$entry['permissions'] = \OCP\Constants::PERMISSION_READ;
			$files[] = $entry;
		}
		return $files;
	}
}
