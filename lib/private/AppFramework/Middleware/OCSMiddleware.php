<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\AppFramework\Middleware;

use OC\AppFramework\Http;
use OC\AppFramework\OCS\BaseResponse;
use OC\AppFramework\OCS\V1Response;
use OC\AppFramework\OCS\V2Response;
use OCP\API;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class OCSMiddleware extends Middleware {

	/** @var IRequest */
	private $request;

	/** @var int */
	private $ocsVersion;

	/**
	 * @param IRequest $request
	 */
	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 */
	public function beforeController($controller, $methodName) {
		if ($controller instanceof OCSController) {
			if (substr_compare($this->request->getScriptName(), '/ocs/v2.php', -strlen('/ocs/v2.php')) === 0) {
				$this->ocsVersion = 2;
			} else {
				$this->ocsVersion = 1;
			}
			$controller->setOCSVersion($this->ocsVersion);
		}
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @throws \Exception
	 * @return BaseResponse
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($controller instanceof OCSController && $exception instanceof OCSException) {
			$code = $exception->getCode();
			if ($code === 0) {
				$code = API::RESPOND_UNKNOWN_ERROR;
			}

			return $this->buildNewResponse($controller, $code, $exception->getMessage());
		}

		throw $exception;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param Response $response
	 * @return \OCP\AppFramework\Http\Response
	 */
	public function afterController($controller, $methodName, Response $response) {
		/*
		 * If a different middleware has detected that a request unauthorized or forbidden
		 * we need to catch the response and convert it to a proper OCS response.
		 */
		if ($controller instanceof OCSController && !($response instanceof BaseResponse)) {
			if ($response->getStatus() === Http::STATUS_UNAUTHORIZED ||
				$response->getStatus() === Http::STATUS_FORBIDDEN) {
				$message = '';
				if ($response instanceof JSONResponse) {
					/** @var DataResponse $response */
					$message = $response->getData()['message'];
				} elseif ($response instanceof TemplateResponse) {
					$message = $response->getParams()['message'];
				}

				return $this->buildNewResponse($controller, API::RESPOND_UNAUTHORISED, $message);
			}
		}

		return $response;
	}

	/**
	 * @param Controller $controller
	 * @param int $code
	 * @param string $message
	 * @return V1Response|V2Response
	 */
	private function buildNewResponse(Controller $controller, $code, $message) {
		$format = $this->getFormat($controller);

		$data = new DataResponse();
		$data->setStatus($code);
		if ($this->ocsVersion === 1) {
			$response = new V1Response($data, $format, $message);
		} else {
			$response = new V2Response($data, $format, $message);
		}

		return $response;
	}

	/**
	 * @param Controller $controller
	 * @return string
	 */
	private function getFormat(Controller $controller) {
		// get format from the url format or request format parameter
		$format = $this->request->getParam('format');

		// if none is given try the first Accept header
		if ($format === null) {
			$headers = $this->request->getHeader('Accept');
			$format = $controller->getResponderByHTTPHeader($headers, 'xml');
		}

		return $format;
	}
}
