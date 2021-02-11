<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Daniel Rudolf <github.com@daniel-rudolf.de>
 * @author Greta Doci <gretadoci@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\App;

use OCP\IGroup;
use OCP\IUser;

/**
 * Interface IAppManager
 *
 * @since 8.0.0
 */
interface IAppManager {

	/**
	 * Returns the app information from "appinfo/info.xml".
	 *
	 * @param string $appId
	 * @return mixed
	 * @since 14.0.0
	 */
	public function getAppInfo(string $appId, bool $path = false, $lang = null);

	/**
	 * Returns the app information from "appinfo/info.xml".
	 *
	 * @param string $appId
	 * @param bool $useCache
	 * @return string
	 * @since 14.0.0
	 */
	public function getAppVersion(string $appId, bool $useCache = true): string;

	/**
	 * Check if an app is enabled for user
	 *
	 * @param string $appId
	 * @param \OCP\IUser $user (optional) if not defined, the currently loggedin user will be used
	 * @return bool
	 * @since 8.0.0
	 */
	public function isEnabledForUser($appId, $user = null);

	/**
	 * Check if an app is enabled in the instance
	 *
	 * Notice: This actually checks if the app is enabled and not only if it is installed.
	 *
	 * @param string $appId
	 * @return bool
	 * @since 8.0.0
	 */
	public function isInstalled($appId);

	/**
	 * Enable an app for every user
	 *
	 * @param string $appId
	 * @param bool $forceEnable
	 * @throws AppPathNotFoundException
	 * @since 8.0.0
	 */
	public function enableApp(string $appId, bool $forceEnable = false): void;

	/**
	 * Whether a list of types contains a protected app type
	 *
	 * @param string[] $types
	 * @return bool
	 * @since 12.0.0
	 */
	public function hasProtectedAppType($types);

	/**
	 * Enable an app only for specific groups
	 *
	 * @param string $appId
	 * @param \OCP\IGroup[] $groups
	 * @param bool $forceEnable
	 * @throws \Exception
	 * @since 8.0.0
	 */
	public function enableAppForGroups(string $appId, array $groups, bool $forceEnable = false): void;

	/**
	 * Disable an app for every user
	 *
	 * @param string $appId
	 * @param bool $automaticDisabled
	 * @since 8.0.0
	 */
	public function disableApp($appId, $automaticDisabled = false);

	/**
	 * Get the directory for the given app.
	 *
	 * @param string $appId
	 * @return string
	 * @since 11.0.0
	 * @throws AppPathNotFoundException
	 */
	public function getAppPath($appId);

	/**
	 * Get the web path for the given app.
	 *
	 * @param string $appId
	 * @return string
	 * @since 18.0.0
	 * @throws AppPathNotFoundException
	 */
	public function getAppWebPath(string $appId): string;

	/**
	 * List all apps enabled for a user
	 *
	 * @param \OCP\IUser $user
	 * @return string[]
	 * @since 8.1.0
	 */
	public function getEnabledAppsForUser(IUser $user);

	/**
	 * List all installed apps
	 *
	 * @return string[]
	 * @since 8.1.0
	 */
	public function getInstalledApps();

	/**
	 * Clear the cached list of apps when enabling/disabling an app
	 * @since 8.1.0
	 */
	public function clearAppsCache();

	/**
	 * @param string $appId
	 * @return boolean
	 * @since 9.0.0
	 */
	public function isShipped($appId);

	/**
	 * @return string[]
	 * @since 9.0.0
	 */
	public function getAlwaysEnabledApps();

	/**
	 * @param \OCP\IGroup $group
	 * @return String[]
	 * @since 17.0.0
	 */
	public function getEnabledAppsForGroup(IGroup $group): array;

	/**
	 * @return array
	 * @since 17.0.0
	 */
	public function getAutoDisabledApps(): array;

	/**
	 * @param String $appId
	 * @return string[]
	 * @since 17.0.0
	 */
	public function getAppRestriction(string $appId): array;
}
