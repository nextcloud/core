<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tom Needham <tom@owncloud.com>
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

namespace OCA\Provisioning_API\Controller;

use OC_App;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class AppsController extends OCSController {
	/** @var \OCP\App\IAppManager */
	private $appManager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IAppManager $appManager
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IAppManager $appManager
	) {
		parent::__construct($appName, $request);

		$this->appManager = $appManager;
	}

	/**
	 * @param string $filter
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getApps(string $filter = null): DataResponse {
		$apps = (new OC_App())->listAllApps();
		$list = [];
		foreach($apps as $app) {
			$list[] = $app['id'];
		}
		if($filter){
			switch($filter){
				case 'enabled':
					return new DataResponse(['apps' => \OC_App::getEnabledApps()]);
					break;
				case 'disabled':
					$enabled = OC_App::getEnabledApps();
					return new DataResponse(['apps' => array_diff($list, $enabled)]);
					break;
				default:
					// Invalid filter variable
					throw new OCSException('', 101);
			}

		} else {
			return new DataResponse(['apps' => $list]);
		}
	}

	/**
	 * @param string $app
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getAppInfo(string $app): DataResponse {
		$info = \OCP\App::getAppInfo($app);
		if(!is_null($info)) {
			return new DataResponse(OC_App::getAppInfo($app));
		}

		throw new OCSException('The request app was not found', \OCP\API::RESPOND_NOT_FOUND);
	}

	/**
	 * @PasswordConfirmationRequired
	 * @param string $app
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function enable(string $app): DataResponse {
		try {
			$this->appManager->enableApp($app);
		} catch (AppPathNotFoundException $e) {
			throw new OCSException('The request app was not found', \OCP\API::RESPOND_NOT_FOUND);
		}
		return new DataResponse();
	}

	/**
	 * @PasswordConfirmationRequired
	 * @param string $app
	 * @return DataResponse
	 */
	public function disable(string $app): DataResponse {
		$this->appManager->disableApp($app);
		return new DataResponse();
	}

}
