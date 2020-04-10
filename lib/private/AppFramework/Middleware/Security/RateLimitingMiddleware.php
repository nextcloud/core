<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
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
use OC\Security\RateLimiting\Exception\RateLimitExceededException;
use OC\Security\RateLimiting\Limiter;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Class RateLimitingMiddleware is the middleware responsible for implementing the
 * ratelimiting in Nextcloud.
 *
 * It parses annotations such as:
 *
 * @UserRateThrottle(limit=5, period=100)
 * @AnonRateThrottle(limit=1, period=100)
 *
 * Those annotations above would mean that logged-in users can access the page 5
 * times within 100 seconds, and anonymous users 1 time within 100 seconds. If
 * only an AnonRateThrottle is specified that one will also be applied to logged-in
 * users.
 *
 * @package OC\AppFramework\Middleware\Security
 */
class RateLimitingMiddleware extends Middleware {
	/** @var IRequest $request */
	private $request;
	/** @var IUserSession */
	private $userSession;
	/** @var ControllerMethodReflector */
	private $reflector;
	/** @var Limiter */
	private $limiter;

	/**
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param ControllerMethodReflector $reflector
	 * @param Limiter $limiter
	 */
	public function __construct(IRequest $request,
								IUserSession $userSession,
								ControllerMethodReflector $reflector,
								Limiter $limiter) {
		$this->request = $request;
		$this->userSession = $userSession;
		$this->reflector = $reflector;
		$this->limiter = $limiter;
	}

	/**
	 * {@inheritDoc}
	 * @throws RateLimitExceededException
	 */
	public function beforeController($controller, $methodName) {
		parent::beforeController($controller, $methodName);

		$anonLimit = $this->reflector->getAnnotationParameter('AnonRateThrottle', 'limit');
		$anonPeriod = $this->reflector->getAnnotationParameter('AnonRateThrottle', 'period');
		$userLimit = $this->reflector->getAnnotationParameter('UserRateThrottle', 'limit');
		$userPeriod = $this->reflector->getAnnotationParameter('UserRateThrottle', 'period');
		$rateLimitIdentifier = get_class($controller) . '::' . $methodName;
		if($userLimit !== '' && $userPeriod !== '' && $this->userSession->isLoggedIn()) {
			$this->limiter->registerUserRequest(
				$rateLimitIdentifier,
				$userLimit,
				$userPeriod,
				$this->userSession->getUser()
			);
		} elseif ($anonLimit !== '' && $anonPeriod !== '') {
			$this->limiter->registerAnonRequest(
				$rateLimitIdentifier,
				$anonLimit,
				$anonPeriod,
				$this->request->getRemoteAddress()
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if($exception instanceof RateLimitExceededException) {
			if (stripos($this->request->getHeader('Accept'),'html') === false) {
				$response = new JSONResponse(
					[
						'message' => $exception->getMessage(),
					],
					$exception->getCode()
				);
			} else {
					$response = new TemplateResponse(
						'core',
						'403',
							[
								'file' => $exception->getMessage()
							],
						'guest'
					);
					$response->setStatus($exception->getCode());
			}

			return $response;
		}

		throw $exception;
	}
}
