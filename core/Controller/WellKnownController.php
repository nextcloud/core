<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OC\Http\WellKnown\RequestManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

class WellKnownController extends Controller {

	/** @var RequestManager */
	private $requestManager;

	public function __construct(IRequest $request,
								RequestManager $wellKnownManager) {
		parent::__construct('core', $request);
		$this->requestManager = $wellKnownManager;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return Response
	 */
	public function handle(string $service): Response {
		$response = $this->requestManager->process(
			$service,
			$this->request
		);

		if ($response === null) {
			$httpResponse = new JSONResponse(["message" => "$service not supported"], Http::STATUS_NOT_FOUND);
		} else {
			$httpResponse = $response->toHttpResponse();
		}

		// We add a custom header so that setup checks can detect if their requests are answered by this controller
		return $httpResponse->addHeader('X-NEXTCLOUD-WELL-KNOWN', '1');
	}
}
