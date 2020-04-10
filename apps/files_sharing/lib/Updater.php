<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Sharing;

class Updater {

	/**
	 * @param array $params
	 */
	static public function renameHook($params) {
		self::renameChildren($params['oldpath'], $params['newpath']);
		self::moveShareToShare($params['newpath']);
	}

	/**
	 * Fix for https://github.com/owncloud/core/issues/20769
	 *
	 * The owner is allowed to move their files (if they are shared) into a receiving folder
	 * In this case we need to update the parent of the moved share. Since they are
	 * effectively handing over ownership of the file the rest of the code needs to know
	 * they need to build up the reshare tree.
	 *
	 * @param string $path
	 */
	static private function moveShareToShare($path) {
		$userFolder = \OC::$server->getUserFolder();

		// If the user folder can't be constructed (e.g. link share) just return.
		if ($userFolder === null) {
			return;
		}

		$src = $userFolder->get($path);

		$shareManager = \OC::$server->getShareManager();

		$shares = $shareManager->getSharesBy($userFolder->getOwner()->getUID(), \OCP\Share::SHARE_TYPE_USER, $src, false, -1);
		$shares = array_merge($shares, $shareManager->getSharesBy($userFolder->getOwner()->getUID(), \OCP\Share::SHARE_TYPE_GROUP, $src, false, -1));
		$shares = array_merge($shares, $shareManager->getSharesBy($userFolder->getOwner()->getUID(), \OCP\Share::SHARE_TYPE_ROOM, $src, false, -1));

		// If the path we move is not a share we don't care
		if (empty($shares)) {
			return;
		}

		// Check if the destination is inside a share
		$mountManager = \OC::$server->getMountManager();
		$dstMount = $mountManager->find($src->getPath());
		if (!($dstMount instanceof \OCA\Files_Sharing\SharedMount)) {
			return;
		}

		$newOwner = $dstMount->getShare()->getShareOwner();

		//Ownership is moved over
		foreach ($shares as $share) {
			/** @var \OCP\Share\IShare $share */
			$share->setShareOwner($newOwner);
			$shareManager->updateShare($share);
		}
	}

	/**
	 * rename mount point from the children if the parent was renamed
	 *
	 * @param string $oldPath old path relative to data/user/files
	 * @param string $newPath new path relative to data/user/files
	 */
	static private function renameChildren($oldPath, $newPath) {

		$absNewPath =  \OC\Files\Filesystem::normalizePath('/' . \OCP\User::getUser() . '/files/' . $newPath);
		$absOldPath =  \OC\Files\Filesystem::normalizePath('/' . \OCP\User::getUser() . '/files/' . $oldPath);

		$mountManager = \OC\Files\Filesystem::getMountManager();
		$mountedShares = $mountManager->findIn('/' . \OCP\User::getUser() . '/files/' . $oldPath);
		foreach ($mountedShares as $mount) {
			if ($mount->getStorage()->instanceOfStorage(ISharedStorage::class)) {
				$mountPoint = $mount->getMountPoint();
				$target = str_replace($absOldPath, $absNewPath, $mountPoint);
				$mount->moveMount($target);
			}
		}
	}

}
