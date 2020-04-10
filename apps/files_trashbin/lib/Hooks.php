<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

/**
 * This class contains all hooks.
 */

namespace OCA\Files_Trashbin;

class Hooks {

	/**
	 * clean up user specific settings if user gets deleted
	 * @param array $params array with uid
	 *
	 * This function is connected to the pre_deleteUser signal of OC_Users
	 * to remove the used space for the trash bin stored in the database
	 */
	public static function deleteUser_hook($params) {
		$uid = $params['uid'];
		Trashbin::deleteUser($uid);
	}

	public static function post_write_hook($params) {
		$user = \OCP\User::getUser();
		if (!empty($user)) {
			Trashbin::resizeTrash($user);
		}
	}
}
