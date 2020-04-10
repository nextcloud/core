<?php
/**
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 *
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

namespace OCA\Testing;

/**
 * Alternative home user backend.
 *
 * It returns a md5 of the home folder instead of the user id.
 * To configure, need to add this in config.php:
 *	'user_backends' => [
 *			'default' => false, [
 *				'class' => '\\OCA\\Testing\\AlternativeHomeUserBackend',
 *				'arguments' => [],
 *			],
 *	]
 */
class AlternativeHomeUserBackend extends \OC\User\Database {
	public function __construct() {
		parent::__construct();
	}
	/**
	 * get the user's home directory
	 * @param string $uid the username
	 * @return string|false
	 */
	public function getHome($uid) {
		if ($this->userExists($uid)) {
			// workaround to avoid killing the admin
			if ($uid !== 'admin') {
				$uid = md5($uid);
			}
			return \OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data') . '/' . $uid;
		}

		return false;
	}
}
