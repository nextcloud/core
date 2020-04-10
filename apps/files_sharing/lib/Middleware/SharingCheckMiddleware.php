<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Files_Sharing\Middleware;

use OCA\Files_Sharing\Controller\ExternalSharesController;
use OCA\Files_Sharing\Exceptions\S2SException;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

/**
 * Checks whether the "sharing check" is enabled
 *
 * @package OCA\Files_Sharing\Middleware
 */
class SharingCheckMiddleware extends Middleware {

	/** @var string */
	protected $appName;
	/** @var IConfig */
	protected $config;
	/** @var IAppManager */
	protected $appManager;
	/** @var IControllerMethodReflector */
	protected $reflector;
	/** @var IManager */
	protected $shareManager;
	/** @var IRequest */
	protected $request;

	/***
	 * @param string $appName
	 * @param IConfig $config
	 * @param IAppManager $appManager
	 * @param IControllerMethodReflector $reflector
	 * @param IManager $shareManager
	 * @param IRequest $request
	 */
	public function __construct($appName,
								IConfig $config,
								IAppManager $appManager,
								IControllerMethodReflector $reflector,
								IManager $shareManager,
								IRequest $request
								) {
		$this->appName = $appName;
		$this->config = $config;
		$this->appManager = $appManager;
		$this->reflector = $reflector;
		$this->shareManager = $shareManager;
		$this->request = $request;
	}

	/**
	 * Check if sharing is enabled before the controllers is executed
	 *
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws NotFoundException
	 * @throws S2SException
	 * @throws ShareNotFound
	 */
	public function beforeController($controller, $methodName) {
		if(!$this->isSharingEnabled()) {
			throw new NotFoundException('Sharing is disabled.');
		}

		if ($controller instanceof ExternalSharesController &&
			!$this->externalSharesChecks()) {
			throw new S2SException('Federated sharing not allowed');
		}
	}

	/**
	 * Return 404 page in case of a not found exception
	 *
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @return NotFoundResponse
	 * @throws \Exception
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if(is_a($exception, NotFoundException::class)) {
			return new NotFoundResponse();
		}

		if (is_a($exception, S2SException::class)) {
			return new JSONResponse($exception->getMessage(), 405);
		}

		throw $exception;
	}

	/**
	 * Checks for externalshares controller
	 * @return bool
	 */
	private function externalSharesChecks() {

		if (!$this->reflector->hasAnnotation('NoIncomingFederatedSharingRequired') &&
			$this->config->getAppValue('files_sharing', 'incoming_server2server_share_enabled', 'yes') !== 'yes') {
			return false;
		}

		if (!$this->reflector->hasAnnotation('NoOutgoingFederatedSharingRequired') &&
			$this->config->getAppValue('files_sharing', 'outgoing_server2server_share_enabled', 'yes') !== 'yes') {
			return false;
		}

		return true;
	}

	/**
	 * Check whether sharing is enabled
	 * @return bool
	 */
	private function isSharingEnabled() {
		// FIXME: This check is done here since the route is globally defined and not inside the files_sharing app
		// Check whether the sharing application is enabled
		if(!$this->appManager->isEnabledForUser($this->appName)) {
			return false;
		}

		return true;
	}



}
