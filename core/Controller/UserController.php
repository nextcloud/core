<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Core\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;

class UserController extends Controller {
	/**
	 * @var IUserManager
	 */
	protected $userManager;

	public function __construct($appName,
								IRequest $request,
								IUserManager $userManager
	) {
		parent::__construct($appName, $request);
		$this->userManager = $userManager;
	}

	/**
	 * Lookup user display names
	 *
	 * @NoAdminRequired
	 *
	 * @param array $users
	 *
	 * @return JSONResponse
	 */
	public function getDisplayNames($users) {
		$result = [];

		foreach ($users as $user) {
			$userObject = $this->userManager->get($user);
			if (is_object($userObject)) {
				$result[$user] = $userObject->getDisplayName();
			} else {
				$result[$user] = $user;
			}
		}

		$json = [
			'users' => $result,
			'status' => 'success'
		];

		return new JSONResponse($json);

	}
}
