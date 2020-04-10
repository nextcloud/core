<?php

declare(strict_types=1);

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OC\Security\CSRF\CsrfTokenManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class CSRFTokenController extends Controller {

	/** @var CsrfTokenManager */
	private $tokenManager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param CsrfTokenManager $tokenManager
	 */
	public function __construct(string $appName, IRequest $request,
		CsrfTokenManager $tokenManager) {
		parent::__construct($appName, $request);
		$this->tokenManager = $tokenManager;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @return JSONResponse
	 */
	public function index(): JSONResponse {
		if (!$this->request->passesStrictCookieCheck()) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$requestToken = $this->tokenManager->getToken();

		return new JSONResponse([
			'token' => $requestToken->getEncryptedValue(),
		]);
	}

}
