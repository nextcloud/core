<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCP;

/**
 * This class provides an easy way for apps to store config values in the
 * database.
 * @since 7.0.0
 */
interface IAppConfig {
	/**
	 * check if a key is set in the appconfig
	 * @param string $app
	 * @param string $key
	 * @return bool
	 * @since 7.0.0
	 */
	public function hasKey($app, $key);

	/**
	 * get multiply values, either the app or key can be used as wildcard by setting it to false
	 *
	 * @param string|false $key
	 * @param string|false $app
	 * @return array|false
	 * @since 7.0.0
	 */
	public function getValues($app, $key);

	/**
	 * get all values of the app or and filters out sensitive data
	 *
	 * @param string $app
	 * @return array
	 * @since 12.0.0
	 */
	public function getFilteredValues($app);

	/**
	 * Get all apps using the config
	 * @return array an array of app ids
	 *
	 * This function returns a list of all apps that have at least one
	 * entry in the appconfig table.
	 * @since 7.0.0
	 */
	public function getApps();
}
