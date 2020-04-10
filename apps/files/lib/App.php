<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files;

class App {
	/**
	 * @var \OCP\INavigationManager
	 */
	private static $navigationManager;

	/**
	 * Returns the app's navigation manager
	 *
	 * @return \OCP\INavigationManager
	 */
	public static function getNavigationManager() {
		// TODO: move this into a service in the Application class
		if (self::$navigationManager === null) {
			self::$navigationManager = new \OC\NavigationManager(
				\OC::$server->getAppManager(),
				\OC::$server->getURLGenerator(),
				\OC::$server->getL10NFactory(),
				\OC::$server->getUserSession(),
				\OC::$server->getGroupManager(),
				\OC::$server->getConfig()
			);
			self::$navigationManager->clear(false);
		}
		return self::$navigationManager;
	}

	public static function extendJsConfig($settings) {
		$appConfig = json_decode($settings['array']['oc_appconfig'], true);

		$maxChunkSize = (int)\OC::$server->getConfig()->getAppValue('files', 'max_chunk_size', 10 * 1024 * 1024);
		$appConfig['files'] = [
			'max_chunk_size' => $maxChunkSize
		];

		$settings['array']['oc_appconfig'] = json_encode($appConfig);
	}
}
