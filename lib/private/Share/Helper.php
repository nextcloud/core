<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Miguel Prokop <miguel.prokop@vtu.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Share;

class Helper extends \OC\Share\Constants {

	/**
	 * get default expire settings defined by the admin
	 * @return array contains 'defaultExpireDateSet', 'enforceExpireDate', 'expireAfterDays'
	 */
	public static function getDefaultExpireSetting() {
		$config = \OC::$server->getConfig();

		$defaultExpireSettings = ['defaultExpireDateSet' => false];

		// get default expire settings
		$defaultExpireDate = $config->getAppValue('core', 'shareapi_default_expire_date', 'no');
		if ($defaultExpireDate === 'yes') {
			$enforceExpireDate = $config->getAppValue('core', 'shareapi_enforce_expire_date', 'no');
			$defaultExpireSettings['defaultExpireDateSet'] = true;
			$defaultExpireSettings['expireAfterDays'] = (int)$config->getAppValue('core', 'shareapi_expire_after_n_days', '7');
			$defaultExpireSettings['enforceExpireDate'] = $enforceExpireDate === 'yes';
		}

		return $defaultExpireSettings;
	}

	public static function calcExpireDate() {
		$expireAfter = \OC\Share\Share::getExpireInterval() * 24 * 60 * 60;
		$expireAt = time() + $expireAfter;
		$date = new \DateTime();
		$date->setTimestamp($expireAt);
		$date->setTime(0, 0, 0);
		//$dateString = $date->format('Y-m-d') . ' 00:00:00';

		return $date;
	}

	/**
	 * calculate expire date
	 * @param array $defaultExpireSettings contains 'defaultExpireDateSet', 'enforceExpireDate', 'expireAfterDays'
	 * @param int $creationTime timestamp when the share was created
	 * @param int $userExpireDate expire timestamp set by the user
	 * @return mixed integer timestamp or False
	 */
	public static function calculateExpireDate($defaultExpireSettings, $creationTime, $userExpireDate = null) {
		$expires = false;
		$defaultExpires = null;

		if (!empty($defaultExpireSettings['defaultExpireDateSet'])) {
			$defaultExpires = $creationTime + $defaultExpireSettings['expireAfterDays'] * 86400;
		}


		if (isset($userExpireDate)) {
			// if the admin decided to enforce the default expire date then we only take
			// the user defined expire date of it is before the default expire date
			if ($defaultExpires && !empty($defaultExpireSettings['enforceExpireDate'])) {
				$expires = min($userExpireDate, $defaultExpires);
			} else {
				$expires = $userExpireDate;
			}
		} elseif ($defaultExpires && !empty($defaultExpireSettings['enforceExpireDate'])) {
			$expires = $defaultExpires;
		}

		return $expires;
	}

	/**
	 * Strips away a potential file names and trailing slashes:
	 * - http://localhost
	 * - http://localhost/
	 * - http://localhost/index.php
	 * - http://localhost/index.php/s/{shareToken}
	 *
	 * all return: http://localhost
	 *
	 * @param string $remote
	 * @return string
	 */
	protected static function fixRemoteURL($remote) {
		$remote = str_replace('\\', '/', $remote);
		if ($fileNamePosition = strpos($remote, '/index.php')) {
			$remote = substr($remote, 0, $fileNamePosition);
		}
		$remote = rtrim($remote, '/');

		return $remote;
	}

	/**
	 * check if two federated cloud IDs refer to the same user
	 *
	 * @param string $user1
	 * @param string $server1
	 * @param string $user2
	 * @param string $server2
	 * @return bool true if both users and servers are the same
	 */
	public static function isSameUserOnSameServer($user1, $server1, $user2, $server2) {
		$normalizedServer1 = strtolower(\OC\Share\Share::removeProtocolFromUrl($server1));
		$normalizedServer2 = strtolower(\OC\Share\Share::removeProtocolFromUrl($server2));

		if (rtrim($normalizedServer1, '/') === rtrim($normalizedServer2, '/')) {
			// FIXME this should be a method in the user management instead
			\OCP\Util::emitHook(
					'\OCA\Files_Sharing\API\Server2Server',
					'preLoginNameUsedAsUserName',
					['uid' => &$user1]
			);
			\OCP\Util::emitHook(
					'\OCA\Files_Sharing\API\Server2Server',
					'preLoginNameUsedAsUserName',
					['uid' => &$user2]
			);

			if ($user1 === $user2) {
				return true;
			}
		}

		return false;
	}
}
