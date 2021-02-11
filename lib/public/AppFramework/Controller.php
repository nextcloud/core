<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Donquixote <marjunebatac@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <vincent@nextcloud.com>
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

/**
 * Public interface of ownCloud for apps to use.
 * AppFramework\Controller class
 */

namespace OCP\AppFramework;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * Base class to inherit your controllers from
 * @since 6.0.0
 */
abstract class Controller {

	/**
	 * app name
	 * @var string
	 * @since 7.0.0
	 */
	protected $appName;

	/**
	 * current request
	 * @var \OCP\IRequest
	 * @since 6.0.0
	 */
	protected $request;

	/**
	 * @var array
	 * @since 7.0.0
	 */
	private $responders;

	/**
	 * constructor of the controller
	 * @param string $appName the name of the app
	 * @param IRequest $request an instance of the request
	 * @since 6.0.0 - parameter $appName was added in 7.0.0 - parameter $app was removed in 7.0.0
	 */
	public function __construct($appName,
								IRequest $request) {
		$this->appName = $appName;
		$this->request = $request;

		// default responders
		$this->responders = [
			'json' => function ($data) {
				if ($data instanceof DataResponse) {
					$response = new JSONResponse(
						$data->getData(),
						$data->getStatus()
					);
					$dataHeaders = $data->getHeaders();
					$headers = $response->getHeaders();
					// do not overwrite Content-Type if it already exists
					if (isset($dataHeaders['Content-Type'])) {
						unset($headers['Content-Type']);
					}
					$response->setHeaders(array_merge($dataHeaders, $headers));

					if ($data->getETag() !== null) {
						$response->setETag($data->getETag());
					}
					if ($data->getLastModified() !== null) {
						$response->setLastModified($data->getLastModified());
					}

					return $response;
				}
				return new JSONResponse($data);
			}
		];
	}


	/**
	 * Parses an HTTP accept header and returns the supported responder type
	 * @param string $acceptHeader
	 * @param string $default
	 * @return string the responder type
	 * @since 7.0.0
	 * @since 9.1.0 Added default parameter
	 */
	public function getResponderByHTTPHeader($acceptHeader, $default = 'json') {
		$headers = explode(',', $acceptHeader);

		// return the first matching responder
		foreach ($headers as $header) {
			$header = strtolower(trim($header));

			$responder = str_replace('application/', '', $header);

			if (array_key_exists($responder, $this->responders)) {
				return $responder;
			}
		}

		// no matching header return default
		return $default;
	}


	/**
	 * Registers a formatter for a type
	 * @param string $format
	 * @param \Closure $responder
	 * @since 7.0.0
	 */
	protected function registerResponder($format, \Closure $responder) {
		$this->responders[$format] = $responder;
	}


	/**
	 * Serializes and formats a response
	 * @param mixed $response the value that was returned from a controller and
	 * is not a Response instance
	 * @param string $format the format for which a formatter has been registered
	 * @throws \DomainException if format does not match a registered formatter
	 * @return Response
	 * @since 7.0.0
	 */
	public function buildResponse($response, $format = 'json') {
		if (array_key_exists($format, $this->responders)) {
			$responder = $this->responders[$format];

			return $responder($response);
		}
		throw new \DomainException('No responder registered for format '.
			$format . '!');
	}
}
