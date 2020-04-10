<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\App\CodeChecker;

class PrivateCheck extends AbstractCheck {
	/**
	 * @return string
	 */
	protected function getLocalDescription() {
		return 'private';
	}

	/**
	 * @return array
	 */
	public function getLocalClasses() {
		return [
			// classes replaced by the public api
			'OC_API' => '6.0.0',
			'OC_App' => '6.0.0',
			'OC_AppConfig' => '6.0.0',
			'OC_Avatar' => '6.0.0',
			'OC_BackgroundJob' => '6.0.0',
			'OC_Config' => '6.0.0',
			'OC_DB' => '6.0.0',
			'OC_Files' => '6.0.0',
			'OC_Helper' => '6.0.0',
			'OC_Hook' => '6.0.0',
			'OC_Image' => '6.0.0',
			'OC_JSON' => '6.0.0',
			'OC_L10N' => '6.0.0',
			'OC_Log' => '6.0.0',
			'OC_Mail' => '6.0.0',
			'OC_Preferences' => '6.0.0',
			'OC_Search_Provider' => '6.0.0',
			'OC_Search_Result' => '6.0.0',
			'OC_Request' => '6.0.0',
			'OC_Response' => '6.0.0',
			'OC_Template' => '6.0.0',
			'OC_User' => '6.0.0',
			'OC_Util' => '6.0.0',
		];
	}

	/**
	 * @return array
	 */
	public function getLocalConstants() {
		return [];
	}

	/**
	 * @return array
	 */
	public function getLocalFunctions() {
		return [];
	}

	/**
	 * @return array
	 */
	public function getLocalMethods() {
		return [];
	}
}
