<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
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
 * App Class
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * This class provides functions to manage apps in ownCloud
 * @since 4.0.0
 * @deprecated 14.0.0
 */
class App {


	/**
	 * Register a Configuration Screen that should appear in the personal settings section.
	 * @param string $app appid
	 * @param string $page page to be included
	 * @return void
	 * @since 4.0.0
	 * @deprecated 14.0.0 Use settings section in appinfo.xml to register personal admin sections
	 */
	public static function registerPersonal($app, $page) {
		\OC_App::registerPersonal($app, $page);
	}

	/**
	 * Register a Configuration Screen that should appear in the Admin section.
	 * @param string $app string appid
	 * @param string $page string page to be included
	 * @return void
	 * @since 4.0.0
	 * @deprecated 14.0.0 Use settings section in appinfo.xml to register admin sections
	 */
	public static function registerAdmin($app, $page) {
		\OC_App::registerAdmin($app, $page);
	}

	/**
	 * Read app metadata from the info.xml file
	 * @param string $app id of the app or the path of the info.xml file
	 * @param boolean $path (optional)
	 * @return array|null
	 * @deprecated 14.0.0 ise \OC::$server->getAppManager()->getAppInfo($appId)
	 * @since 4.0.0
	 */
	public static function getAppInfo($app, $path=false) {
		return \OC_App::getAppInfo($app, $path);
	}

	/**
	 * checks whether or not an app is enabled
	 * @param string $app
	 * @return boolean
	 *
	 * This function checks whether or not an app is enabled.
	 * @since 4.0.0
	 * @deprecated 13.0.0 use \OC::$server->getAppManager()->isEnabledForUser($appId)
	 */
	public static function isEnabled($app) {
		return \OC::$server->getAppManager()->isEnabledForUser($app);
	}

	/**
	 * Get the last version of the app from appinfo/info.xml
	 * @param string $app
	 * @return string
	 * @since 4.0.0
	 * @deprecated 14.0.0 use \OC::$server->getAppManager()->getAppVersion($appId)
	 */
	public static function getAppVersion($app) {
		return \OC::$server->getAppManager()->getAppVersion($app);
	}
}
