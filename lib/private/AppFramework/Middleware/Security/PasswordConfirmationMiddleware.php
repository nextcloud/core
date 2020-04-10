<?php
/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

use OC\AppFramework\Middleware\Security\Exceptions\NotConfirmedException;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ISession;
use OCP\IUserSession;
use OCP\User\Backend\IPasswordConfirmationBackend;

class PasswordConfirmationMiddleware extends Middleware {
	/** @var ControllerMethodReflector */
	private $reflector;
	/** @var ISession */
	private $session;
	/** @var IUserSession */
	private $userSession;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var array */
	private $excludedUserBackEnds = ['user_saml' => true, 'user_globalsiteselector' => true];

	/**
	 * PasswordConfirmationMiddleware constructor.
	 *
	 * @param ControllerMethodReflector $reflector
	 * @param ISession $session
	 * @param IUserSession $userSession
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(ControllerMethodReflector $reflector,
								ISession $session,
								IUserSession $userSession,
								ITimeFactory $timeFactory) {
		$this->reflector = $reflector;
		$this->session = $session;
		$this->userSession = $userSession;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws NotConfirmedException
	 */
	public function beforeController($controller, $methodName) {
		if ($this->reflector->hasAnnotation('PasswordConfirmationRequired')) {
			$user = $this->userSession->getUser();
			$backendClassName = '';
			if ($user !== null) {
				$backend = $user->getBackend();
				if ($backend instanceof IPasswordConfirmationBackend) {
					if (!$backend->canConfirmPassword($user->getUID())) {
						return;
					}
				}

				$backendClassName = $user->getBackendClassName();
			}

			$lastConfirm = (int) $this->session->get('last-password-confirm');
			// we can't check the password against a SAML backend, so skip password confirmation in this case
			if (!isset($this->excludedUserBackEnds[$backendClassName]) && $lastConfirm < ($this->timeFactory->getTime() - (30 * 60 + 15))) { // allow 15 seconds delay
				throw new NotConfirmedException();
			}
		}
	}
}
