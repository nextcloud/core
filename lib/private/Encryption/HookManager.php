<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OC\Files\Filesystem;
use OC\Files\View;

class HookManager {
	/**
	 * @var Update
	 */
	private static $updater;

	public static function postShared($params) {
		self::getUpdate()->postShared($params);
	}
	public static function postUnshared($params) {
		self::getUpdate()->postUnshared($params);
	}

	public static function postRename($params) {
		self::getUpdate()->postRename($params);
	}

	public static function postRestore($params) {
		self::getUpdate()->postRestore($params);
	}

	/**
	 * @return Update
	 */
	private static function getUpdate() {
		if (is_null(self::$updater)) {
			$user = \OC::$server->getUserSession()->getUser();
			$uid = '';
			if ($user) {
				$uid = $user->getUID();
			}
			self::$updater = new Update(
				new View(),
				new Util(
					new View(),
					\OC::$server->getUserManager(),
					\OC::$server->getGroupManager(),
					\OC::$server->getConfig()),
				Filesystem::getMountManager(),
				\OC::$server->getEncryptionManager(),
				\OC::$server->getEncryptionFilesHelper(),
				$uid
			);
		}

		return self::$updater;
	}
}
