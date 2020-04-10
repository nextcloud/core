<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

/**
 * Public interface of ownCloud for apps to use.
 * AppFramework\Middleware class
 */

namespace OCP\AppFramework;

use OCP\AppFramework\Http\Response;

/**
 * Middleware is used to provide hooks before or after controller methods and
 * deal with possible exceptions raised in the controller methods.
 * They're modeled after Django's middleware system:
 * https://docs.djangoproject.com/en/dev/topics/http/middleware/
 * @since 6.0.0
 */
abstract class Middleware {


	/**
	 * This is being run in normal order before the controller is being
	 * called which allows several modifications and checks
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @since 6.0.0
	 */
	public function beforeController($controller, $methodName) {

	}


	/**
	 * This is being run when either the beforeController method or the
	 * controller method itself is throwing an exception. The middleware is
	 * asked in reverse order to handle the exception and to return a response.
	 * If the response is null, it is assumed that the exception could not be
	 * handled and the error will be thrown again
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it can't handle it
	 * @return Response a Response object in case that the exception was handled
	 * @since 6.0.0
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		throw $exception;
	}


	/**
	 * This is being run after a successful controllermethod call and allows
	 * the manipulation of a Response object. The middleware is run in reverse order
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param Response $response the generated response from the controller
	 * @return Response a Response object
	 * @since 6.0.0
	 */
	public function afterController($controller, $methodName, Response $response) {
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
	 * @since 6.0.0
	 */
	public function beforeOutput($controller, $methodName, $output) {
		return $output;
	}

}
