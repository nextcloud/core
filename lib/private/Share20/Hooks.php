<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OC\Share20;

class Hooks {
	public static function post_deleteUser($arguments) {
		\OC::$server->getShareManager()->userDeleted($arguments['uid']);
	}

	public static function post_deleteGroup($arguments) {
		\OC::$server->getShareManager()->groupDeleted($arguments['gid']);
	}

	public static function post_removeFromGroup($arguments) {
		\OC::$server->getShareManager()->userDeletedFromGroup($arguments['uid'], $arguments['gid']);
	}
}
