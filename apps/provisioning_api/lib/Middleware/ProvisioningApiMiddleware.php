<?php

declare(strict_types=1);

/**
 *
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Provisioning_API\Middleware;

use OCA\Provisioning_API\Middleware\Exceptions\NotSubAdminException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\Utility\IControllerMethodReflector;

class ProvisioningApiMiddleware extends Middleware {

	/** @var IControllerMethodReflector */
	private $reflector;

	/** @var bool */
	private $isAdmin;

	/** @var bool */
	private $isSubAdmin;

	/**
	 * ProvisioningApiMiddleware constructor.
	 *
	 * @param IControllerMethodReflector $reflector
	 * @param bool $isAdmin
	 * @param bool $isSubAdmin
	 */
	public function __construct(
		IControllerMethodReflector $reflector,
		bool $isAdmin,
		bool $isSubAdmin) {
		$this->reflector = $reflector;
		$this->isAdmin = $isAdmin;
		$this->isSubAdmin = $isSubAdmin;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 *
	 * @throws NotSubAdminException
	 */
	public function beforeController($controller, $methodName) {
		if (!$this->isAdmin && !$this->reflector->hasAnnotation('NoSubAdminRequired') && !$this->isSubAdmin) {
			throw new NotSubAdminException();
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
		if ($exception instanceof NotSubAdminException) {
			throw new OCSException($exception->getMessage(), \OCP\API::RESPOND_UNAUTHORISED);
		}

		throw $exception;
	}
}
