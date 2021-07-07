<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Testing\Controller;

use OC\User\UsernameDuplicationPreventionManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class FlushUsedUserNamesController extends OCSController {

	/** @var UsernameDuplicationPreventionManager */
	private $usernameDuplicationPreventionManager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param UsernameDuplicationPreventionManager $usernameDuplicationPreventionManager
	 */
	public function __construct($appName,
								IRequest $request,
								UsernameDuplicationPreventionManager $usernameDuplicationPreventionManager) {
		parent::__construct($appName, $request);
		$this->usernameDuplicationPreventionManager = $usernameDuplicationPreventionManager;
	}

	public function executeFlush() : DataResponse {
		$this->usernameDuplicationPreventionManager->cleanUp();
		return new DataResponse();
	}
}
