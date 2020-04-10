<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OC\AppFramework\Middleware\Security;

use OC\AppFramework\Middleware\Security\Exceptions\ReloadExecutionException;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Middleware;
use OCP\ISession;
use OCP\IURLGenerator;

/**
 * Simple middleware to handle the clearing of the execution context. This will trigger
 * a reload but if the session variable is set we properly redirect to the login page.
 */
class ReloadExecutionMiddleware extends Middleware {
	/** @var ISession */
	private $session;
	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(ISession $session, IURLGenerator $urlGenerator) {
		$this->session = $session;
		$this->urlGenerator = $urlGenerator;
	}

	public function beforeController($controller, $methodName) {
		if ($this->session->exists('clearingExecutionContexts')) {
			throw new ReloadExecutionException();
		}
	}

	public function afterException($controller, $methodName, \Exception $exception) {
		if ($exception instanceof ReloadExecutionException) {
			$this->session->remove('clearingExecutionContexts');

			return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute(
				'core.login.showLoginForm',
				['clear' => true] // this param the the code in login.js may be removed when the "Clear-Site-Data" is working in the browsers
			));
		}

		return parent::afterException($controller, $methodName, $exception);
	}


}
