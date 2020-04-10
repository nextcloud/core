<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_Sharing\Controller;

use OCA\Files_Sharing\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {

	/** @var IConfig */
	private $config;
	/** @var string */
	private $userId;

	public function __construct(IRequest $request, IConfig $config, string $userId) {
		parent::__construct(Application::APP_ID, $request);

		$this->config = $config;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setDefaultAccept(bool $accept): JSONResponse {
		$this->config->setUserValue($this->userId, Application::APP_ID, 'default_accept', $accept ? 'yes' : 'no');
		return new JSONResponse();
	}
}
