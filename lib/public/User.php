<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sebastian Wessalowski <sebastian@wessalowski.org>
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
 * User Class
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * This class provides access to the user management. You can get information
 * about the currently logged in user and the permissions for example
 * @since 5.0.0
 * @deprecated 13.0.0
 */
class User {
	/**
	 * Get the user id of the user currently logged in.
	 * @return string uid or false
	 * @deprecated 8.0.0 Use \OC::$server->getUserSession()->getUser()->getUID()
	 * @since 5.0.0
	 */
	public static function getUser() {
		return \OC_User::getUser();
	}

	/**
	 * Check if the user is logged in
	 * @return boolean
	 * @since 5.0.0
	 * @deprecated 13.0.0 Use annotation based ACLs from the AppFramework instead
	 */
	public static function isLoggedIn() {
		return \OC::$server->getUserSession()->isLoggedIn();
	}

	/**
	 * Check if the user is a admin, redirects to home if not
	 * @since 5.0.0
	 * @deprecated 13.0.0 Use annotation based ACLs from the AppFramework instead
	 */
	public static function checkAdminUser() {
		\OC_Util::checkAdminUser();
	}

	/**
	 * Check if the user is logged in, redirects to home if not. With
	 * redirect URL parameter to the request URI.
	 * @since 5.0.0
	 * @deprecated 13.0.0 Use annotation based ACLs from the AppFramework instead
	 */
	public static function checkLoggedIn() {
		\OC_Util::checkLoggedIn();
	}
}
