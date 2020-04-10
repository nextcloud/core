<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use OCP\Files\StorageNotAvailableException;
use OCP\Files\StorageInvalidException;

\OC_JSON::checkLoggedIn();
\OC::$server->getSession()->close();
$l = \OC::$server->getL10N('files');

// Load the files
$dir = isset($_GET['dir']) ? (string)$_GET['dir'] : '';
$dir = \OC\Files\Filesystem::normalizePath($dir);

try {
	$dirInfo = \OC\Files\Filesystem::getFileInfo($dir);
	if (!$dirInfo || !$dirInfo->getType() === 'dir') {
		http_response_code(404);
		exit();
	}

	$data = [];
	$baseUrl = \OC::$server->getURLGenerator()->linkTo('files', 'index.php') . '?dir=';

	$permissions = $dirInfo->getPermissions();

	$sortAttribute = isset($_GET['sort']) ? (string)$_GET['sort'] : 'name';
	$sortDirection = isset($_GET['sortdirection']) ? ($_GET['sortdirection'] === 'desc') : false;
	$mimetypeFilters = isset($_GET['mimetypes']) ? json_decode($_GET['mimetypes']) : '';

	$files = [];
	// Clean up duplicates from array
	if (is_array($mimetypeFilters) && count($mimetypeFilters)) {
		$mimetypeFilters = array_unique($mimetypeFilters);

		if (!in_array('httpd/unix-directory', $mimetypeFilters)) {
			// append folder filter to be able to browse folders
			$mimetypeFilters[] = 'httpd/unix-directory';
		}

		// create filelist with mimetype filter - as getFiles only supports on
		// mimetype filter at once we will filter this folder for each
		// mimetypeFilter
		foreach ($mimetypeFilters as $mimetypeFilter) {
			$files = array_merge($files, \OCA\Files\Helper::getFiles($dir, $sortAttribute, $sortDirection, $mimetypeFilter));
		}

		// sort the files accordingly
		$files = \OCA\Files\Helper::sortFiles($files, $sortAttribute, $sortDirection);
	} else {
		// create file list without mimetype filter
		$files = \OCA\Files\Helper::getFiles($dir, $sortAttribute, $sortDirection);
	}

	$data['directory'] = $dir;
	$data['files'] = \OCA\Files\Helper::formatFileInfos($files);
	$data['permissions'] = $permissions;

	\OC_JSON::success(['data' => $data]);
} catch (\OCP\Files\StorageNotAvailableException $e) {
	\OC::$server->getLogger()->logException($e, ['app' => 'files']);
	\OC_JSON::error([
		'data' => [
			'exception' => StorageNotAvailableException::class,
			'message' => $l->t('Storage is temporarily not available')
		]
	]);
} catch (\OCP\Files\StorageInvalidException $e) {
	\OC::$server->getLogger()->logException($e, ['app' => 'files']);
	\OC_JSON::error([
		'data' => [
			'exception' => StorageInvalidException::class,
			'message' => $l->t('Storage invalid')
		]
	]);
} catch (\Exception $e) {
	\OC::$server->getLogger()->logException($e, ['app' => 'files']);
	\OC_JSON::error([
		'data' => [
			'exception' => \Exception::class,
			'message' => $l->t('Unknown error')
		]
	]);
}
