<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Testing\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;

class ConfigController extends OCSController {

	/** @var IConfig */
	private $config;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 */
	public function __construct($appName,
								IRequest $request,
								IConfig $config) {
		parent::__construct($appName, $request);
		$this->config = $config;
	}

	/**
	 * @param string $appid
	 * @param string $configkey
	 * @param string $value
	 * @return DataResponse
	 */
	public function setAppValue($appid, $configkey, $value) {
		$this->config->setAppValue($appid, $configkey, $value);
		return new DataResponse();
	}

	/**
	 * @param string $appid
	 * @param string $configkey
	 * @return DataResponse
	 */
	public function deleteAppValue($appid, $configkey) {
		$this->config->deleteAppValue($appid, $configkey);
		return new DataResponse();
	}
}
