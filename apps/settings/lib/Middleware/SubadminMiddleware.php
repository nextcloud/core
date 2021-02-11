<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Settings\Middleware;

use OC\AppFramework\Http;
use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IL10N;

/**
 * Verifies whether an user has at least subadmin rights.
 * To bypass use the `@NoSubAdminRequired` annotation
 */
class SubadminMiddleware extends Middleware {
	/** @var bool */
	protected $isSubAdmin;
	/** @var ControllerMethodReflector */
	protected $reflector;
	/** @var IL10N */
	private $l10n;

	/**
	 * @param ControllerMethodReflector $reflector
	 * @param bool $isSubAdmin
	 * @param IL10N $l10n
	 */
	public function __construct(ControllerMethodReflector $reflector,
								$isSubAdmin,
								IL10N $l10n) {
		$this->reflector = $reflector;
		$this->isSubAdmin = $isSubAdmin;
		$this->l10n = $l10n;
	}

	/**
	 * Check if sharing is enabled before the controllers is executed
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws \Exception
	 */
	public function beforeController($controller, $methodName) {
		if (!$this->reflector->hasAnnotation('NoSubAdminRequired')) {
			if (!$this->isSubAdmin) {
				throw new NotAdminException($this->l10n->t('Logged in user must be a subadmin'));
			}
		}
	}

	/**
	 * Return 403 page in case of an exception
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @return TemplateResponse
	 * @throws \Exception
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($exception instanceof NotAdminException) {
			$response = new TemplateResponse('core', '403', [], 'guest');
			$response->setStatus(Http::STATUS_FORBIDDEN);
			return $response;
		}

		throw $exception;
	}
}
