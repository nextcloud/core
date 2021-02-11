<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Stefan Weil <sw@weilnetz.de>
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

namespace OC\AppFramework\Middleware\Security;

use OC\AppFramework\Middleware\Security\Exceptions\SecurityException;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OC\Authentication\Exceptions\PasswordLoginForbiddenException;
use OC\Security\Bruteforce\Throttler;
use OC\User\Session;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IRequest;

/**
 * This middleware sets the correct CORS headers on a response if the
 * controller has the @CORS annotation. This is needed for webapps that want
 * to access an API and don't run on the same domain, see
 * https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS
 */
class CORSMiddleware extends Middleware {
	/** @var IRequest  */
	private $request;
	/** @var ControllerMethodReflector */
	private $reflector;
	/** @var Session */
	private $session;
	/** @var Throttler */
	private $throttler;

	/**
	 * @param IRequest $request
	 * @param ControllerMethodReflector $reflector
	 * @param Session $session
	 * @param Throttler $throttler
	 */
	public function __construct(IRequest $request,
								ControllerMethodReflector $reflector,
								Session $session,
								Throttler $throttler) {
		$this->request = $request;
		$this->reflector = $reflector;
		$this->session = $session;
		$this->throttler = $throttler;
	}

	/**
	 * This is being run in normal order before the controller is being
	 * called which allows several modifications and checks
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @throws SecurityException
	 * @since 6.0.0
	 */
	public function beforeController($controller, $methodName) {
		// ensure that @CORS annotated API routes are not used in conjunction
		// with session authentication since this enables CSRF attack vectors
		if ($this->reflector->hasAnnotation('CORS') &&
			!$this->reflector->hasAnnotation('PublicPage')) {
			$user = $this->request->server['PHP_AUTH_USER'];
			$pass = $this->request->server['PHP_AUTH_PW'];

			$this->session->logout();
			try {
				if (!$this->session->logClientIn($user, $pass, $this->request, $this->throttler)) {
					throw new SecurityException('CORS requires basic auth', Http::STATUS_UNAUTHORIZED);
				}
			} catch (PasswordLoginForbiddenException $ex) {
				throw new SecurityException('Password login forbidden, use token instead', Http::STATUS_UNAUTHORIZED);
			}
		}
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
	 * @throws SecurityException
	 */
	public function afterController($controller, $methodName, Response $response) {
		// only react if its a CORS request and if the request sends origin and

		if (isset($this->request->server['HTTP_ORIGIN']) &&
			$this->reflector->hasAnnotation('CORS')) {

			// allow credentials headers must not be true or CSRF is possible
			// otherwise
			foreach ($response->getHeaders() as $header => $value) {
				if (strtolower($header) === 'access-control-allow-credentials' &&
				   strtolower(trim($value)) === 'true') {
					$msg = 'Access-Control-Allow-Credentials must not be '.
						   'set to true in order to prevent CSRF';
					throw new SecurityException($msg);
				}
			}

			$origin = $this->request->server['HTTP_ORIGIN'];
			$response->addHeader('Access-Control-Allow-Origin', $origin);
		}
		return $response;
	}

	/**
	 * If an SecurityException is being caught return a JSON error response
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it can't handle it
	 * @return Response a Response object or null in case that the exception could not be handled
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($exception instanceof SecurityException) {
			$response = new JSONResponse(['message' => $exception->getMessage()]);
			if ($exception->getCode() !== 0) {
				$response->setStatus($exception->getCode());
			} else {
				$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			return $response;
		}

		throw $exception;
	}
}
