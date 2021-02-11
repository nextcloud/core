<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

/**
 * Public interface of ownCloud for apps to use.
 * Share Class
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * This class provides the ability for apps to share their content between users.
 * Apps must create a backend class that implements OCP\Share_Backend and register it with this class.
 *
 * It provides the following hooks:
 *  - post_shared
 * @since 5.0.0
 * @deprecated 17.0.0
 */
class Share extends \OC\Share\Constants {

	/**
	 * Get the item of item type shared with a given user by source
	 * @param string $itemType
	 * @param string $itemSource
	 * @param string $user User to whom the item was shared
	 * @param string $owner Owner of the share
	 * @return array Return list of items with file_target, permissions and expiration
	 * @since 6.0.0 - parameter $owner was added in 8.0.0
	 * @deprecated 17.0.0
	 */
	public static function getItemSharedWithUser($itemType, $itemSource, $user, $owner = null) {
		return \OC\Share\Share::getItemSharedWithUser($itemType, $itemSource, $user, $owner);
	}

	/**
	 * Get the item of item type shared with the current user by source
	 * @param string $itemType
	 * @param string $itemSource
	 * @param int $format (optional) Format type must be defined by the backend
	 * @param mixed $parameters
	 * @param bool $includeCollections
	 * @return array
	 * @since 5.0.0
	 * @deprecated 17.0.0
	 */
	public static function getItemSharedWithBySource($itemType, $itemSource, $format = self::FORMAT_NONE,
		$parameters = null, $includeCollections = false) {
		// not used by any app - only here to not break apps syntax
	}

	/**
	 * Based on the given token the share information will be returned - password protected shares will be verified
	 * @param string $token
	 * @param bool $checkPasswordProtection
	 * @return array|bool false will be returned in case the token is unknown or unauthorized
	 * @since 5.0.0 - parameter $checkPasswordProtection was added in 7.0.0
	 * @deprecated 17.0.0
	 */
	public static function getShareByToken($token, $checkPasswordProtection = true) {
		// not used by any app - only here to not break apps syntax
	}


	/**
	 * Get the shared items of item type owned by the current user
	 * @param string $itemType
	 * @param int $format (optional) Format type must be defined by the backend
	 * @param mixed $parameters
	 * @param int $limit Number of items to return (optional) Returns all by default
	 * @param bool $includeCollections
	 * @return mixed Return depends on format
	 * @since 5.0.0
	 * @deprecated 17.0.0
	 */
	public static function getItemsShared($itemType, $format = self::FORMAT_NONE, $parameters = null,
		$limit = -1, $includeCollections = false) {

		// only used by AppVNCSafe app (https://github.com/vnc-biz/nextcloud-appvncsafe/issues/2) - only here to not break apps syntax
	}

	/**
	 * Get the shared item of item type owned by the current user
	 * @param string $itemType
	 * @param string $itemSource
	 * @param int $format (optional) Format type must be defined by the backend
	 * @param mixed $parameters
	 * @param bool $includeCollections
	 * @return mixed Return depends on format
	 * @since 5.0.0
	 * @deprecated 17.0.0
	 *
	 * Refactoring notes:
	 *   * defacto $parameters and $format is always the default and therefore is removed in the subsequent call
	 */
	public static function getItemShared($itemType, $itemSource, $format = self::FORMAT_NONE,
										 $parameters = null, $includeCollections = false) {
		return \OC\Share\Share::getItemShared($itemType, $itemSource, self::FORMAT_NONE, null, $includeCollections);
	}

	/**
	 * sent status if users got informed by mail about share
	 * @param string $itemType
	 * @param string $itemSource
	 * @param int $shareType SHARE_TYPE_USER, SHARE_TYPE_GROUP, or SHARE_TYPE_LINK
	 * @param string $recipient with whom was the item shared
	 * @param bool $status
	 * @since 6.0.0 - parameter $originIsSource was added in 8.0.0
	 * @deprecated 17.0.0
	 */
	public static function setSendMailStatus($itemType, $itemSource, $shareType, $recipient, $status) {
		// not used by any app - only here to not break apps syntax
	}
}
