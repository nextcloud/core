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

namespace OC\Core\Controller;

use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\RemoteWipe;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class WipeController extends Controller {

	/** @var RemoteWipe */
	private $remoteWipe;

	public function __construct(string $appName,
								IRequest $request,
								RemoteWipe $remoteWipe) {
		parent::__construct($appName, $request);

		$this->remoteWipe = $remoteWipe;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @AnonRateThrottle(limit=10, period=300)
	 *
	 * @param string $token
	 *
	 * @return JSONResponse
	 */
	public function checkWipe(string $token): JSONResponse {
		try {
			if ($this->remoteWipe->start($token)) {
				return new JSONResponse([
					'wipe' => true
				]);
			}

			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		} catch (InvalidTokenException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @AnonRateThrottle(limit=10, period=300)
	 *
	 * @param string $token
	 *
	 * @return JSONResponse
	 */
	public function wipeDone(string $token): JSONResponse {
		try {
			if ($this->remoteWipe->finish($token)) {
				return new JSONResponse([]);
			}

			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		} catch (InvalidTokenException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
	}

}
