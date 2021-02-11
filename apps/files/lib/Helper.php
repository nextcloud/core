<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author brumsel <brumsel@losecatcher.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Jobst <mjobst+github@tecratech.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files;

use OCP\Files\FileInfo;
use OCP\ITagManager;

/**
 * Helper class for manipulating file information
 */
class Helper {
	/**
	 * @param string $dir
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 */
	public static function buildFileStorageStatistics($dir) {
		// information about storage capacities
		$storageInfo = \OC_Helper::getStorageInfo($dir);
		$l = \OC::$server->getL10N('files');
		$maxUploadFileSize = \OCP\Util::maxUploadFilesize($dir, $storageInfo['free']);
		$maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);
		$maxHumanFileSize = $l->t('Upload (max. %s)', [$maxHumanFileSize]);

		return [
			'uploadMaxFilesize' => $maxUploadFileSize,
			'maxHumanFilesize' => $maxHumanFileSize,
			'freeSpace' => $storageInfo['free'],
			'quota' => $storageInfo['quota'],
			'used' => $storageInfo['used'],
			'usedSpacePercent' => (int)$storageInfo['relative'],
			'owner' => $storageInfo['owner'],
			'ownerDisplayName' => $storageInfo['ownerDisplayName'],
			'mountType' => $storageInfo['mountType'],
			'mountPoint' => $storageInfo['mountPoint'],
		];
	}

	/**
	 * Determine icon for a given file
	 *
	 * @param \OCP\Files\FileInfo $file file info
	 * @return string icon URL
	 */
	public static function determineIcon($file) {
		if ($file['type'] === 'dir') {
			$icon = \OC::$server->getMimeTypeDetector()->mimeTypeIcon('dir');
			// TODO: move this part to the client side, using mountType
			if ($file->isShared()) {
				$icon = \OC::$server->getMimeTypeDetector()->mimeTypeIcon('dir-shared');
			} elseif ($file->isMounted()) {
				$icon = \OC::$server->getMimeTypeDetector()->mimeTypeIcon('dir-external');
			}
		} else {
			$icon = \OC::$server->getMimeTypeDetector()->mimeTypeIcon($file->getMimetype());
		}

		return substr($icon, 0, -3) . 'svg';
	}

	/**
	 * Comparator function to sort files alphabetically and have
	 * the directories appear first
	 *
	 * @param \OCP\Files\FileInfo $a file
	 * @param \OCP\Files\FileInfo $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public static function compareFileNames(FileInfo $a, FileInfo $b) {
		$aType = $a->getType();
		$bType = $b->getType();
		if ($aType === 'dir' and $bType !== 'dir') {
			return -1;
		} elseif ($aType !== 'dir' and $bType === 'dir') {
			return 1;
		} else {
			return \OCP\Util::naturalSortCompare($a->getName(), $b->getName());
		}
	}

	/**
	 * Comparator function to sort files by date
	 *
	 * @param \OCP\Files\FileInfo $a file
	 * @param \OCP\Files\FileInfo $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public static function compareTimestamp(FileInfo $a, FileInfo $b) {
		$aTime = $a->getMTime();
		$bTime = $b->getMTime();
		return ($aTime < $bTime) ? -1 : 1;
	}

	/**
	 * Comparator function to sort files by size
	 *
	 * @param \OCP\Files\FileInfo $a file
	 * @param \OCP\Files\FileInfo $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public static function compareSize(FileInfo $a, FileInfo $b) {
		$aSize = $a->getSize();
		$bSize = $b->getSize();
		return ($aSize < $bSize) ? -1 : 1;
	}

	/**
	 * Formats the file info to be returned as JSON to the client.
	 *
	 * @param \OCP\Files\FileInfo $i
	 * @return array formatted file info
	 */
	public static function formatFileInfo(FileInfo $i) {
		$entry = [];

		$entry['id'] = $i['fileid'];
		$entry['parentId'] = $i['parent'];
		$entry['mtime'] = $i['mtime'] * 1000;
		// only pick out the needed attributes
		$entry['name'] = $i->getName();
		$entry['permissions'] = $i['permissions'];
		$entry['mimetype'] = $i['mimetype'];
		$entry['size'] = $i['size'];
		$entry['type'] = $i['type'];
		$entry['etag'] = $i['etag'];
		if (isset($i['tags'])) {
			$entry['tags'] = $i['tags'];
		}
		if (isset($i['displayname_owner'])) {
			$entry['shareOwner'] = $i['displayname_owner'];
		}
		if (isset($i['is_share_mount_point'])) {
			$entry['isShareMountPoint'] = $i['is_share_mount_point'];
		}
		$mountType = null;
		$mount = $i->getMountPoint();
		$mountType = $mount->getMountType();
		if ($mountType !== '') {
			if ($i->getInternalPath() === '') {
				$mountType .= '-root';
			}
			$entry['mountType'] = $mountType;
		}
		if (isset($i['extraData'])) {
			$entry['extraData'] = $i['extraData'];
		}
		return $entry;
	}

	/**
	 * Format file info for JSON
	 * @param \OCP\Files\FileInfo[] $fileInfos file infos
	 * @return array
	 */
	public static function formatFileInfos($fileInfos) {
		$files = [];
		foreach ($fileInfos as $i) {
			$files[] = self::formatFileInfo($i);
		}

		return $files;
	}

	/**
	 * Retrieves the contents of the given directory and
	 * returns it as a sorted array of FileInfo.
	 *
	 * @param string $dir path to the directory
	 * @param string $sortAttribute attribute to sort on
	 * @param bool $sortDescending true for descending sort, false otherwise
	 * @param string $mimetypeFilter limit returned content to this mimetype or mimepart
	 * @return \OCP\Files\FileInfo[] files
	 */
	public static function getFiles($dir, $sortAttribute = 'name', $sortDescending = false, $mimetypeFilter = '') {
		$content = \OC\Files\Filesystem::getDirectoryContent($dir, $mimetypeFilter);

		return self::sortFiles($content, $sortAttribute, $sortDescending);
	}

	/**
	 * Populate the result set with file tags
	 *
	 * @param array $fileList
	 * @param string $fileIdentifier identifier attribute name for values in $fileList
	 * @param ITagManager $tagManager
	 * @return array file list populated with tags
	 */
	public static function populateTags(array $fileList, $fileIdentifier = 'fileid', ITagManager $tagManager) {
		$ids = [];
		foreach ($fileList as $fileData) {
			$ids[] = $fileData[$fileIdentifier];
		}
		$tagger = $tagManager->load('files');
		$tags = $tagger->getTagsForObjects($ids);

		if (!is_array($tags)) {
			throw new \UnexpectedValueException('$tags must be an array');
		}

		// Set empty tag array
		foreach ($fileList as $key => $fileData) {
			$fileList[$key]['tags'] = [];
		}

		if (!empty($tags)) {
			foreach ($tags as $fileId => $fileTags) {
				foreach ($fileList as $key => $fileData) {
					if ($fileId !== $fileData[$fileIdentifier]) {
						continue;
					}

					$fileList[$key]['tags'] = $fileTags;
				}
			}
		}

		return $fileList;
	}

	/**
	 * Sort the given file info array
	 *
	 * @param \OCP\Files\FileInfo[] $files files to sort
	 * @param string $sortAttribute attribute to sort on
	 * @param bool $sortDescending true for descending sort, false otherwise
	 * @return \OCP\Files\FileInfo[] sorted files
	 */
	public static function sortFiles($files, $sortAttribute = 'name', $sortDescending = false) {
		$sortFunc = 'compareFileNames';
		if ($sortAttribute === 'mtime') {
			$sortFunc = 'compareTimestamp';
		} elseif ($sortAttribute === 'size') {
			$sortFunc = 'compareSize';
		}
		usort($files, [Helper::class, $sortFunc]);
		if ($sortDescending) {
			$files = array_reverse($files);
		}
		return $files;
	}
}
