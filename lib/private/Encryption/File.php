<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Encryption;

use OC\Cache\CappedMemoryCache;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Share\IManager;

class File implements \OCP\Encryption\IFile {

	/** @var Util */
	protected $util;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IManager */
	private $shareManager;

	/**
	 * cache results of already checked folders
	 *
	 * @var array
	 */
	protected $cache;

	public function __construct(Util $util,
								IRootFolder $rootFolder,
								IManager $shareManager) {
		$this->util = $util;
		$this->cache = new CappedMemoryCache();
		$this->rootFolder = $rootFolder;
		$this->shareManager = $shareManager;
	}


	/**
	 * get list of users with access to the file
	 *
	 * @param string $path to the file
	 * @return array  ['users' => $uniqueUserIds, 'public' => $public]
	 */
	public function getAccessList($path) {

		// Make sure that a share key is generated for the owner too
		list($owner, $ownerPath) = $this->util->getUidAndFilename($path);

		// always add owner to the list of users with access to the file
		$userIds = [$owner];

		if (!$this->util->isFile($owner . '/' . $ownerPath)) {
			return ['users' => $userIds, 'public' => false];
		}

		$ownerPath = substr($ownerPath, strlen('/files'));
		$userFolder = $this->rootFolder->getUserFolder($owner);
		try {
			$file = $userFolder->get($ownerPath);
		} catch (NotFoundException $e) {
			$file = null;
		}
		$ownerPath = $this->util->stripPartialFileExtension($ownerPath);

		// first get the shares for the parent and cache the result so that we don't
		// need to check all parents for every file
		$parent = dirname($ownerPath);
		$parentNode = $userFolder->get($parent);
		if (isset($this->cache[$parent])) {
			$resultForParents = $this->cache[$parent];
		} else {
			$resultForParents = $this->shareManager->getAccessList($parentNode);
			$this->cache[$parent] = $resultForParents;
		}
		$userIds = array_merge($userIds, $resultForParents['users']);
		$public = $resultForParents['public'] || $resultForParents['remote'];


		// Find out who, if anyone, is sharing the file
		if ($file !== null) {
			$resultForFile = $this->shareManager->getAccessList($file, false);
			$userIds = array_merge($userIds, $resultForFile['users']);
			$public = $resultForFile['public'] || $resultForFile['remote'] || $public;
		}

		// check if it is a group mount
		if (\OCP\App::isEnabled("files_external")) {
			$mounts = \OCA\Files_External\MountConfig::getSystemMountPoints();
			foreach ($mounts as $mount) {
				if ($mount['mountpoint'] == substr($ownerPath, 1, strlen($mount['mountpoint']))) {
					$mountedFor = $this->util->getUserWithAccessToMountPoint($mount['applicable']['users'], $mount['applicable']['groups']);
					$userIds = array_merge($userIds, $mountedFor);
				}
			}
		}

		// Remove duplicate UIDs
		$uniqueUserIds = array_unique($userIds);

		return ['users' => $uniqueUserIds, 'public' => $public];
	}
}
