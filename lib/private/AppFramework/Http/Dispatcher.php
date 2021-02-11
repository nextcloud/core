<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
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

namespace OC\AppFramework\Http;

use OC\AppFramework\Http;
use OC\AppFramework\Middleware\MiddlewareDispatcher;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OC\DB\ConnectionAdapter;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Class to dispatch the request to the middleware dispatcher
 */
class Dispatcher {

	/** @var MiddlewareDispatcher */
	private $middlewareDispatcher;

	/** @var Http */
	private $protocol;

	/** @var ControllerMethodReflector */
	private $reflector;

	/** @var IRequest */
	private $request;

	/** @var IConfig */
	private $config;

	/** @var ConnectionAdapter */
	private $connection;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param Http $protocol the http protocol with contains all status headers
	 * @param MiddlewareDispatcher $middlewareDispatcher the dispatcher which
	 * runs the middleware
	 * @param ControllerMethodReflector $reflector the reflector that is used to inject
	 * the arguments for the controller
	 * @param IRequest $request the incoming request
	 * @param IConfig $config
	 * @param ConnectionAdapter $connection
	 * @param LoggerInterface $logger
	 */
	public function __construct(Http $protocol,
								MiddlewareDispatcher $middlewareDispatcher,
								ControllerMethodReflector $reflector,
								IRequest $request,
								IConfig $config,
								ConnectionAdapter $connection,
								LoggerInterface $logger) {
		$this->protocol = $protocol;
		$this->middlewareDispatcher = $middlewareDispatcher;
		$this->reflector = $reflector;
		$this->request = $request;
		$this->config = $config;
		$this->connection = $connection;
		$this->logger = $logger;
	}


	/**
	 * Handles a request and calls the dispatcher on the controller
	 * @param Controller $controller the controller which will be called
	 * @param string $methodName the method name which will be called on
	 * the controller
	 * @return array $array[0] contains a string with the http main header,
	 * $array[1] contains headers in the form: $key => value, $array[2] contains
	 * the response output
	 * @throws \Exception
	 */
	public function dispatch(Controller $controller, string $methodName): array {
		$out = [null, [], null];

		try {
			// prefill reflector with everything thats needed for the
			// middlewares
			$this->reflector->reflect($controller, $methodName);

			$this->middlewareDispatcher->beforeController($controller,
				$methodName);

			$databaseStatsBefore = [];
			if ($this->config->getSystemValueBool('debug', false)) {
				$databaseStatsBefore = $this->connection->getInner()->getStats();
			}

			$response = $this->executeController($controller, $methodName);

			if (!empty($databaseStatsBefore)) {
				$databaseStatsAfter = $this->connection->getInner()->getStats();
				$numBuilt = $databaseStatsAfter['built'] - $databaseStatsBefore['built'];
				$numExecuted = $databaseStatsAfter['executed'] - $databaseStatsBefore['executed'];

				if ($numBuilt > 50) {
					$this->logger->debug('Controller {class}::{method} created {count} QueryBuilder objects, please check if they are created inside a loop by accident.' , [
						'class' => get_class($controller),
						'method' => $methodName,
						'count' => $numBuilt,
					]);
				}

				if ($numExecuted > 100) {
					$this->logger->warning('Controller {class}::{method} executed {count} queries.' , [
						'class' => get_class($controller),
						'method' => $methodName,
						'count' => $numExecuted,
					]);
				}
			}

			// if an exception appears, the middleware checks if it can handle the
			// exception and creates a response. If no response is created, it is
			// assumed that theres no middleware who can handle it and the error is
			// thrown again
		} catch (\Exception $exception) {
			$response = $this->middlewareDispatcher->afterException(
				$controller, $methodName, $exception);
		} catch (\Throwable $throwable) {
			$exception = new \Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
			$response = $this->middlewareDispatcher->afterException(
			$controller, $methodName, $exception);
		}

		$response = $this->middlewareDispatcher->afterController(
			$controller, $methodName, $response);

		// depending on the cache object the headers need to be changed
		$out[0] = $this->protocol->getStatusHeader($response->getStatus());
		$out[1] = array_merge($response->getHeaders());
		$out[2] = $response->getCookies();
		$out[3] = $this->middlewareDispatcher->beforeOutput(
			$controller, $methodName, $response->render()
		);
		$out[4] = $response;

		return $out;
	}


	/**
	 * Uses the reflected parameters, types and request parameters to execute
	 * the controller
	 * @param Controller $controller the controller to be executed
	 * @param string $methodName the method on the controller that should be executed
	 * @return Response
	 */
	private function executeController(Controller $controller, string $methodName): Response {
		$arguments = [];

		// valid types that will be casted
		$types = ['int', 'integer', 'bool', 'boolean', 'float'];

		foreach ($this->reflector->getParameters() as $param => $default) {

			// try to get the parameter from the request object and cast
			// it to the type annotated in the @param annotation
			$value = $this->request->getParam($param, $default);
			$type = $this->reflector->getType($param);

			// if this is submitted using GET or a POST form, 'false' should be
			// converted to false
			if (($type === 'bool' || $type === 'boolean') &&
				$value === 'false' &&
				(
					$this->request->method === 'GET' ||
					strpos($this->request->getHeader('Content-Type'),
						'application/x-www-form-urlencoded') !== false
				)
			) {
				$value = false;
			} elseif ($value !== null && \in_array($type, $types, true)) {
				settype($value, $type);
			}

			$arguments[] = $value;
		}

		$response = \call_user_func_array([$controller, $methodName], $arguments);

		// format response
		if ($response instanceof DataResponse || !($response instanceof Response)) {

			// get format from the url format or request format parameter
			$format = $this->request->getParam('format');

			// if none is given try the first Accept header
			if ($format === null) {
				$headers = $this->request->getHeader('Accept');
				$format = $controller->getResponderByHTTPHeader($headers, null);
			}

			if ($format !== null) {
				$response = $controller->buildResponse($response, $format);
			} else {
				$response = $controller->buildResponse($response);
			}
		}

		return $response;
	}
}
