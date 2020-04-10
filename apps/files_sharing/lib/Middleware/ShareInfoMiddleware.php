<?php
/**
 *
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files_Sharing\Middleware;

use OCA\Files_Sharing\Controller\ShareInfoController;
use OCA\Files_Sharing\Exceptions\S2SException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\Share\IManager;

class ShareInfoMiddleware extends Middleware {
	/** @var IManager */
	private $shareManager;

	public function __construct(IManager $shareManager) {
		$this->shareManager = $shareManager;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws S2SException
	 */
	public function beforeController($controller, $methodName) {
		if (!($controller instanceof ShareInfoController)) {
			return;
		}

		if (!$this->shareManager->outgoingServer2ServerSharesAllowed()) {
			throw new S2SException();
		}
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @throws \Exception
	 * @return Response
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if (!($controller instanceof ShareInfoController)) {
			throw $exception;
		}

		if ($exception instanceof S2SException) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		throw $exception;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param Response $response
	 * @return Response
	 */
	public function afterController($controller, $methodName, Response $response) {
		if (!($controller instanceof ShareInfoController)) {
			return $response;
		}

		if (!($response instanceof JSONResponse)) {
			return $response;
		}

		$data = $response->getData();
		$status = 'error';

		if ($response->getStatus() === Http::STATUS_OK) {
			$status = 'success';
		}

		$response->setData([
			'data' => $data,
			'status' => $status,
		]);

		return $response;
	}
}
