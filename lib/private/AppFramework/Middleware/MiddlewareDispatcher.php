<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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

namespace OC\AppFramework\Middleware;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;

/**
 * This class is used to store and run all the middleware in correct order
 */
class MiddlewareDispatcher {

	/**
	 * @var array array containing all the middlewares
	 */
	private $middlewares;

	/**
	 * @var int counter which tells us what middlware was executed once an
	 *                  exception occurs
	 */
	private $middlewareCounter;


	/**
	 * Constructor
	 */
	public function __construct() {
		$this->middlewares = [];
		$this->middlewareCounter = 0;
	}


	/**
	 * Adds a new middleware
	 * @param Middleware $middleWare the middleware which will be added
	 */
	public function registerMiddleware(Middleware $middleWare) {
		$this->middlewares[] = $middleWare;
	}


	/**
	 * returns an array with all middleware elements
	 * @return array the middlewares
	 */
	public function getMiddlewares(): array {
		return $this->middlewares;
	}


	/**
	 * This is being run in normal order before the controller is being
	 * called which allows several modifications and checks
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 */
	public function beforeController(Controller $controller, string $methodName) {
		// we need to count so that we know which middlewares we have to ask in
		// case there is an exception
		$middlewareCount = \count($this->middlewares);
		for ($i = 0; $i < $middlewareCount; $i++) {
			$this->middlewareCounter++;
			$middleware = $this->middlewares[$i];
			$middleware->beforeController($controller, $methodName);
		}
	}


	/**
	 * This is being run when either the beforeController method or the
	 * controller method itself is throwing an exception. The middleware is asked
	 * in reverse order to handle the exception and to return a response.
	 * If the response is null, it is assumed that the exception could not be
	 * handled and the error will be thrown again
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                            the controller
	 * @param \Exception $exception the thrown exception
	 * @return Response a Response object if the middleware can handle the
	 * exception
	 * @throws \Exception the passed in exception if it can't handle it
	 */
	public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
		for ($i = $this->middlewareCounter - 1; $i >= 0; $i--) {
			$middleware = $this->middlewares[$i];
			try {
				return $middleware->afterException($controller, $methodName, $exception);
			} catch (\Exception $exception) {
				continue;
			}
		}
		throw $exception;
	}


	/**
	 * This is being run after a successful controllermethod call and allows
	 * the manipulation of a Response object. The middleware is run in reverse order
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                            the controller
	 * @param Response $response the generated response from the controller
	 * @return Response a Response object
	 */
	public function afterController(Controller $controller, string $methodName, Response $response): Response {
		for ($i = \count($this->middlewares) - 1; $i >= 0; $i--) {
			$middleware = $this->middlewares[$i];
			$response = $middleware->afterController($controller, $methodName, $response);
		}
		return $response;
	}


	/**
	 * This is being run after the response object has been rendered and
	 * allows the manipulation of the output. The middleware is run in reverse order
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param string $output the generated output from a response
	 * @return string the output that should be printed
	 */
	public function beforeOutput(Controller $controller, string $methodName, string $output): string {
		for ($i = \count($this->middlewares) - 1; $i >= 0; $i--) {
			$middleware = $this->middlewares[$i];
			$output = $middleware->beforeOutput($controller, $methodName, $output);
		}
		return $output;
	}
}
