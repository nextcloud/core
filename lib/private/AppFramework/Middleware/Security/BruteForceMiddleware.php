<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\AppFramework\Middleware\Security;

use OC\AppFramework\Utility\ControllerMethodReflector;
use OC\Security\Bruteforce\Throttler;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TooManyRequestsResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\Security\Bruteforce\MaxDelayReached;

/**
 * Class BruteForceMiddleware performs the bruteforce protection for controllers
 * that are annotated with @BruteForceProtection(action=$action) whereas $action
 * is the action that should be logged within the database.
 *
 * @package OC\AppFramework\Middleware\Security
 */
class BruteForceMiddleware extends Middleware {
	/** @var ControllerMethodReflector */
	private $reflector;
	/** @var Throttler */
	private $throttler;
	/** @var IRequest */
	private $request;

	/**
	 * @param ControllerMethodReflector $controllerMethodReflector
	 * @param Throttler $throttler
	 * @param IRequest $request
	 */
	public function __construct(ControllerMethodReflector $controllerMethodReflector,
								Throttler $throttler,
								IRequest $request) {
		$this->reflector = $controllerMethodReflector;
		$this->throttler = $throttler;
		$this->request = $request;
	}

	/**
	 * {@inheritDoc}
	 */
	public function beforeController($controller, $methodName) {
		parent::beforeController($controller, $methodName);

		if ($this->reflector->hasAnnotation('BruteForceProtection')) {
			$action = $this->reflector->getAnnotationParameter('BruteForceProtection', 'action');
			$this->throttler->sleepDelayOrThrowOnMax($this->request->getRemoteAddress(), $action);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function afterController($controller, $methodName, Response $response) {
		if ($this->reflector->hasAnnotation('BruteForceProtection') && $response->isThrottled()) {
			$action = $this->reflector->getAnnotationParameter('BruteForceProtection', 'action');
			$ip = $this->request->getRemoteAddress();
			$this->throttler->sleepDelay($ip, $action);
			$this->throttler->registerAttempt($action, $ip, $response->getThrottleMetadata());
		}

		return parent::afterController($controller, $methodName, $response);
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @throws \Exception
	 * @return Response
	 */
	public function afterException($controller, $methodName, \Exception $exception): Response {
		if ($exception instanceof MaxDelayReached) {
			if ($controller instanceof OCSController) {
				throw new OCSException($exception->getMessage(), Http::STATUS_TOO_MANY_REQUESTS);
			}

			return new TooManyRequestsResponse();
		}

		throw $exception;
	}
}
