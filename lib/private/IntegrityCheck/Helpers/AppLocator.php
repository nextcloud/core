<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\IntegrityCheck\Helpers;

/**
 * Class AppLocator provides a non-static helper for OC_App::getPath($appId)
 * it is not possible to use IAppManager at this point as IAppManager has a
 * dependency on a running ownCloud.
 *
 * @package OC\IntegrityCheck\Helpers
 */
class AppLocator {
	/**
	 * Provides \OC_App::getAppPath($appId)
	 *
	 * @param string $appId
	 * @return string
	 * @throws \Exception If the app cannot be found
	 */
	public function getAppPath(string $appId): string {
		$path = \OC_App::getAppPath($appId);
		if($path === false) {

			throw new \Exception('App not found');
		}
		return $path;
	}

	/**
	 * Providers \OC_App::getAllApps()
	 *
	 * @return array
	 */
	public function getAllApps(): array {
		return \OC_App::getAllApps();
	}
}
