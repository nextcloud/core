<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Miguel Prokop <miguel.prokop@vtu.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Share;

use OC\HintException;

class Helper extends \OC\Share\Constants {

	/**
	 * Generate a unique target for the item
	 * @param string $itemType
	 * @param string $itemSource
	 * @param int $shareType SHARE_TYPE_USER, SHARE_TYPE_GROUP, or SHARE_TYPE_LINK
	 * @param string $shareWith User or group the item is being shared with
	 * @param string $uidOwner User that is the owner of shared item
	 * @param string $suggestedTarget The suggested target originating from a reshare (optional)
	 * @param int $groupParent The id of the parent group share (optional)
	 * @throws \Exception
	 * @return string Item target
	 */
	public static function generateTarget($itemType, $itemSource, $shareType, $shareWith, $uidOwner, $suggestedTarget = null, $groupParent = null) {
		// FIXME: $uidOwner and $groupParent seems to be unused
		$backend = \OC\Share\Share::getBackend($itemType);
		if ($shareType === self::SHARE_TYPE_LINK || $shareType === self::SHARE_TYPE_REMOTE) {
			if (isset($suggestedTarget)) {
				return $suggestedTarget;
			}
			return $backend->generateTarget($itemSource, false);
		} else {
			if ($shareType == self::SHARE_TYPE_USER) {
				// Share with is a user, so set share type to user and groups
				$shareType = self::$shareTypeUserAndGroups;
			}

			// Check if suggested target exists first
			if (!isset($suggestedTarget)) {
				$suggestedTarget = $itemSource;
			}
			if ($shareType == self::SHARE_TYPE_GROUP) {
				$target = $backend->generateTarget($suggestedTarget, false);
			} else {
				$target = $backend->generateTarget($suggestedTarget, $shareWith);
			}

			return $target;
		}
	}

	/**
	 * Delete all reshares and group share children of an item
	 * @param int $parent Id of item to delete
	 * @param bool $excludeParent If true, exclude the parent from the delete (optional)
	 * @param string $uidOwner The user that the parent was shared with (optional)
	 * @param int $newParent new parent for the childrens
	 * @param bool $excludeGroupChildren exclude group children elements
	 */
	public static function delete($parent, $excludeParent = false, $uidOwner = null, $newParent = null, $excludeGroupChildren = false) {
		$ids = [$parent];
		$deletedItems = [];
		$changeParent = [];
		$parents = [$parent];
		while (!empty($parents)) {
			$parents = "'".implode("','", $parents)."'";
			// Check the owner on the first search of reshares, useful for
			// finding and deleting the reshares by a single user of a group share
			$params = [];
			if (count($ids) == 1 && isset($uidOwner)) {
				// FIXME: don't concat $parents, use Docrine's PARAM_INT_ARRAY approach
				$queryString = 'SELECT `id`, `share_with`, `item_type`, `share_type`, ' .
					'`item_target`, `file_target`, `parent` ' .
					'FROM `*PREFIX*share` ' .
					'WHERE `parent` IN ('.$parents.') AND `uid_owner` = ? ';
				$params[] = $uidOwner;
			} else {
				$queryString = 'SELECT `id`, `share_with`, `item_type`, `share_type`, ' .
					'`item_target`, `file_target`, `parent`, `uid_owner` ' .
					'FROM `*PREFIX*share` WHERE `parent` IN ('.$parents.') ';
			}
			if ($excludeGroupChildren) {
				$queryString .= ' AND `share_type` != ?';
				$params[] = self::$shareTypeGroupUserUnique;
			}
			$query = \OC_DB::prepare($queryString);
			$result = $query->execute($params);
			// Reset parents array, only go through loop again if items are found
			$parents = [];
			while ($item = $result->fetchRow()) {
				$tmpItem = [
					'id' => $item['id'],
					'shareWith' => $item['share_with'],
					'itemTarget' => $item['item_target'],
					'itemType' => $item['item_type'],
					'shareType' => (int)$item['share_type'],
				];
				if (isset($item['file_target'])) {
					$tmpItem['fileTarget'] = $item['file_target'];
				}
				// if we have a new parent for the child we remember the child
				// to update the parent, if not we add it to the list of items
				// which should be deleted
				if ($newParent !== null) {
					$changeParent[] = $item['id'];
				} else {
					$deletedItems[] = $tmpItem;
					$ids[] = $item['id'];
					$parents[] = $item['id'];
				}
			}
		}
		if ($excludeParent) {
			unset($ids[0]);
		}

		if (!empty($changeParent)) {
			$idList = "'".implode("','", $changeParent)."'";
			$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `parent` = ? WHERE `id` IN ('.$idList.')');
			$query->execute([$newParent]);
		}

		if (!empty($ids)) {
			$idList = "'".implode("','", $ids)."'";
			$query = \OC_DB::prepare('DELETE FROM `*PREFIX*share` WHERE `id` IN ('.$idList.')');
			$query->execute();
		}

		return $deletedItems;
	}

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
		} else if ($defaultExpires && !empty($defaultExpireSettings['enforceExpireDate'])) {
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
	 * split user and remote from federated cloud id
	 *
	 * @param string $id
	 * @return string[]
	 * @throws HintException
	 */
	public static function splitUserRemote($id) {
		try {
			$cloudId = \OC::$server->getCloudIdManager()->resolveCloudId($id);
			return [$cloudId->getUser(), $cloudId->getRemote()];
		} catch (\InvalidArgumentException $e) {
			$l = \OC::$server->getL10N('core');
			$hint = $l->t('Invalid Federated Cloud ID');
			throw new HintException('Invalid Federated Cloud ID', $hint, 0, $e);
		}
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
