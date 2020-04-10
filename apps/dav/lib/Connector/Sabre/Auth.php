<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Markus Goetz <markus@woboq.com>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\DAV\Connector\Sabre;

use Exception;
use OC\Authentication\Exceptions\PasswordLoginForbiddenException;
use OC\Authentication\TwoFactorAuth\Manager;
use OC\Security\Bruteforce\Throttler;
use OC\User\Session;
use OCA\DAV\Connector\Sabre\Exception\PasswordLoginForbidden;
use OCP\IRequest;
use OCP\ISession;
use Sabre\DAV\Auth\Backend\AbstractBasic;
use Sabre\DAV\Exception\NotAuthenticated;
use Sabre\DAV\Exception\ServiceUnavailable;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class Auth extends AbstractBasic {


	const DAV_AUTHENTICATED = 'AUTHENTICATED_TO_DAV_BACKEND';

	/** @var ISession */
	private $session;
	/** @var Session */
	private $userSession;
	/** @var IRequest */
	private $request;
	/** @var string */
	private $currentUser;
	/** @var Manager */
	private $twoFactorManager;
	/** @var Throttler */
	private $throttler;

	/**
	 * @param ISession $session
	 * @param Session $userSession
	 * @param IRequest $request
	 * @param Manager $twoFactorManager
	 * @param Throttler $throttler
	 * @param string $principalPrefix
	 */
	public function __construct(ISession $session,
								Session $userSession,
								IRequest $request,
								Manager $twoFactorManager,
								Throttler $throttler,
								$principalPrefix = 'principals/users/') {
		$this->session = $session;
		$this->userSession = $userSession;
		$this->twoFactorManager = $twoFactorManager;
		$this->request = $request;
		$this->throttler = $throttler;
		$this->principalPrefix = $principalPrefix;

		// setup realm
		$defaults = new \OCP\Defaults();
		$this->realm = $defaults->getName();
	}

	/**
	 * Whether the user has initially authenticated via DAV
	 *
	 * This is required for WebDAV clients that resent the cookies even when the
	 * account was changed.
	 *
	 * @see https://github.com/owncloud/core/issues/13245
	 *
	 * @param string $username
	 * @return bool
	 */
	public function isDavAuthenticated($username) {
		return !is_null($this->session->get(self::DAV_AUTHENTICATED)) &&
		$this->session->get(self::DAV_AUTHENTICATED) === $username;
	}

	/**
	 * Validates a username and password
	 *
	 * This method should return true or false depending on if login
	 * succeeded.
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool
	 * @throws PasswordLoginForbidden
	 */
	protected function validateUserPass($username, $password) {
		if ($this->userSession->isLoggedIn() &&
			$this->isDavAuthenticated($this->userSession->getUser()->getUID())
		) {
			\OC_Util::setupFS($this->userSession->getUser()->getUID());
			$this->session->close();
			return true;
		} else {
			\OC_Util::setupFS(); //login hooks may need early access to the filesystem
			try {
				if ($this->userSession->logClientIn($username, $password, $this->request, $this->throttler)) {
					\OC_Util::setupFS($this->userSession->getUser()->getUID());
					$this->session->set(self::DAV_AUTHENTICATED, $this->userSession->getUser()->getUID());
					$this->session->close();
					return true;
				} else {
					$this->session->close();
					return false;
				}
			} catch (PasswordLoginForbiddenException $ex) {
				$this->session->close();
				throw new PasswordLoginForbidden();
			}
		}
	}

	/**
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return array
	 * @throws NotAuthenticated
	 * @throws ServiceUnavailable
	 */
	function check(RequestInterface $request, ResponseInterface $response) {
		try {
			return $this->auth($request, $response);
		} catch (NotAuthenticated $e) {
			throw $e;
		} catch (Exception $e) {
			$class = get_class($e);
			$msg = $e->getMessage();
			\OC::$server->getLogger()->logException($e);
			throw new ServiceUnavailable("$class: $msg");
		}
	}

	/**
	 * Checks whether a CSRF check is required on the request
	 *
	 * @return bool
	 */
	private function requiresCSRFCheck() {
		// GET requires no check at all
		if($this->request->getMethod() === 'GET') {
			return false;
		}

		// Official Nextcloud clients require no checks
		if($this->request->isUserAgent([
			IRequest::USER_AGENT_CLIENT_DESKTOP,
			IRequest::USER_AGENT_CLIENT_ANDROID,
			IRequest::USER_AGENT_CLIENT_IOS,
		])) {
			return false;
		}

		// If not logged-in no check is required
		if(!$this->userSession->isLoggedIn()) {
			return false;
		}

		// POST always requires a check
		if($this->request->getMethod() === 'POST') {
			return true;
		}

		// If logged-in AND DAV authenticated no check is required
		if($this->userSession->isLoggedIn() &&
			$this->isDavAuthenticated($this->userSession->getUser()->getUID())) {
			return false;
		}

		return true;
	}

	/**
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return array
	 * @throws NotAuthenticated
	 */
	private function auth(RequestInterface $request, ResponseInterface $response) {
		$forcedLogout = false;

		if(!$this->request->passesCSRFCheck() &&
			$this->requiresCSRFCheck()) {
			// In case of a fail with POST we need to recheck the credentials
			if($this->request->getMethod() === 'POST') {
				$forcedLogout = true;
			} else {
				$response->setStatus(401);
				throw new \Sabre\DAV\Exception\NotAuthenticated('CSRF check not passed.');
			}
		}

		if($forcedLogout) {
			$this->userSession->logout();
		} else {
			if($this->twoFactorManager->needsSecondFactor($this->userSession->getUser())) {
				throw new \Sabre\DAV\Exception\NotAuthenticated('2FA challenge not passed.');
			}
			if (
				//Fix for broken webdav clients
				($this->userSession->isLoggedIn() && is_null($this->session->get(self::DAV_AUTHENTICATED))) ||
				//Well behaved clients that only send the cookie are allowed
				($this->userSession->isLoggedIn() && $this->session->get(self::DAV_AUTHENTICATED) === $this->userSession->getUser()->getUID() && $request->getHeader('Authorization') === null) ||
				\OC_User::handleApacheAuth()
			) {
				$user = $this->userSession->getUser()->getUID();
				\OC_Util::setupFS($user);
				$this->currentUser = $user;
				$this->session->close();
				return [true, $this->principalPrefix . $user];
			}
		}

		if (!$this->userSession->isLoggedIn() && in_array('XMLHttpRequest', explode(',', $request->getHeader('X-Requested-With')))) {
			// do not re-authenticate over ajax, use dummy auth name to prevent browser popup
			$response->addHeader('WWW-Authenticate','DummyBasic realm="' . $this->realm . '"');
			$response->setStatus(401);
			throw new \Sabre\DAV\Exception\NotAuthenticated('Cannot authenticate over ajax calls');
		}

		$data = parent::check($request, $response);
		if($data[0] === true) {
			$startPos = strrpos($data[1], '/') + 1;
			$user = $this->userSession->getUser()->getUID();
			$data[1] = substr_replace($data[1], $user, $startPos);
		}
		return $data;
	}
}
