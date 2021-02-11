<?php
/**
 * @copyright Copyright (c) 2017, Sandro Lutz <sandro.lutz@temparus.ch>
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Felix Rupp <github@felixrupp.com>
 * @author Greta Doci <gretadoci@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lionel Elie Mamane <lionel@mamane.lu>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sandro Lutz <sandro.lutz@temparus.ch>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OC\User;

use OC;
use OC\Authentication\Exceptions\ExpiredTokenException;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Exceptions\PasswordlessTokenException;
use OC\Authentication\Exceptions\PasswordLoginForbiddenException;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;
use OC\Hooks\Emitter;
use OC\Hooks\PublicEmitter;
use OC_User;
use OC_Util;
use OCA\DAV\Connector\Sabre\Auth;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Lockdown\ILockdownManager;
use OCP\Security\ISecureRandom;
use OCP\Session\Exceptions\SessionNotAvailableException;
use OCP\User\Events\PostLoginEvent;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Session
 *
 * Hooks available in scope \OC\User:
 * - preSetPassword(\OC\User\User $user, string $password, string $recoverPassword)
 * - postSetPassword(\OC\User\User $user, string $password, string $recoverPassword)
 * - preDelete(\OC\User\User $user)
 * - postDelete(\OC\User\User $user)
 * - preCreateUser(string $uid, string $password)
 * - postCreateUser(\OC\User\User $user)
 * - assignedUserId(string $uid)
 * - preUnassignedUserId(string $uid)
 * - postUnassignedUserId(string $uid)
 * - preLogin(string $user, string $password)
 * - postLogin(\OC\User\User $user, string $loginName, string $password, boolean $isTokenLogin)
 * - preRememberedLogin(string $uid)
 * - postRememberedLogin(\OC\User\User $user)
 * - logout()
 * - postLogout()
 *
 * @package OC\User
 */
class Session implements IUserSession, Emitter {

	/** @var Manager|PublicEmitter $manager */
	private $manager;

	/** @var ISession $session */
	private $session;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var IProvider */
	private $tokenProvider;

	/** @var IConfig */
	private $config;

	/** @var User $activeUser */
	protected $activeUser;

	/** @var ISecureRandom */
	private $random;

	/** @var ILockdownManager  */
	private $lockdownManager;

	/** @var ILogger */
	private $logger;
	/** @var IEventDispatcher */
	private $dispatcher;

	/**
	 * @param Manager $manager
	 * @param ISession $session
	 * @param ITimeFactory $timeFactory
	 * @param IProvider $tokenProvider
	 * @param IConfig $config
	 * @param ISecureRandom $random
	 * @param ILockdownManager $lockdownManager
	 * @param ILogger $logger
	 */
	public function __construct(Manager $manager,
								ISession $session,
								ITimeFactory $timeFactory,
								$tokenProvider,
								IConfig $config,
								ISecureRandom $random,
								ILockdownManager $lockdownManager,
								ILogger $logger,
								IEventDispatcher $dispatcher
	) {
		$this->manager = $manager;
		$this->session = $session;
		$this->timeFactory = $timeFactory;
		$this->tokenProvider = $tokenProvider;
		$this->config = $config;
		$this->random = $random;
		$this->lockdownManager = $lockdownManager;
		$this->logger = $logger;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @param IProvider $provider
	 */
	public function setTokenProvider(IProvider $provider) {
		$this->tokenProvider = $provider;
	}

	/**
	 * @param string $scope
	 * @param string $method
	 * @param callable $callback
	 */
	public function listen($scope, $method, callable $callback) {
		$this->manager->listen($scope, $method, $callback);
	}

	/**
	 * @param string $scope optional
	 * @param string $method optional
	 * @param callable $callback optional
	 */
	public function removeListener($scope = null, $method = null, callable $callback = null) {
		$this->manager->removeListener($scope, $method, $callback);
	}

	/**
	 * get the manager object
	 *
	 * @return Manager|PublicEmitter
	 */
	public function getManager() {
		return $this->manager;
	}

	/**
	 * get the session object
	 *
	 * @return ISession
	 */
	public function getSession() {
		return $this->session;
	}

	/**
	 * set the session object
	 *
	 * @param ISession $session
	 */
	public function setSession(ISession $session) {
		if ($this->session instanceof ISession) {
			$this->session->close();
		}
		$this->session = $session;
		$this->activeUser = null;
	}

	/**
	 * set the currently active user
	 *
	 * @param IUser|null $user
	 */
	public function setUser($user) {
		if (is_null($user)) {
			$this->session->remove('user_id');
		} else {
			$this->session->set('user_id', $user->getUID());
		}
		$this->activeUser = $user;
	}

	/**
	 * get the current active user
	 *
	 * @return IUser|null Current user, otherwise null
	 */
	public function getUser() {
		// FIXME: This is a quick'n dirty work-around for the incognito mode as
		// described at https://github.com/owncloud/core/pull/12912#issuecomment-67391155
		if (OC_User::isIncognitoMode()) {
			return null;
		}
		if (is_null($this->activeUser)) {
			$uid = $this->session->get('user_id');
			if (is_null($uid)) {
				return null;
			}
			$this->activeUser = $this->manager->get($uid);
			if (is_null($this->activeUser)) {
				return null;
			}
			$this->validateSession();
		}
		return $this->activeUser;
	}

	/**
	 * Validate whether the current session is valid
	 *
	 * - For token-authenticated clients, the token validity is checked
	 * - For browsers, the session token validity is checked
	 */
	protected function validateSession() {
		$token = null;
		$appPassword = $this->session->get('app_password');

		if (is_null($appPassword)) {
			try {
				$token = $this->session->getId();
			} catch (SessionNotAvailableException $ex) {
				return;
			}
		} else {
			$token = $appPassword;
		}

		if (!$this->validateToken($token)) {
			// Session was invalidated
			$this->logout();
		}
	}

	/**
	 * Checks whether the user is logged in
	 *
	 * @return bool if logged in
	 */
	public function isLoggedIn() {
		$user = $this->getUser();
		if (is_null($user)) {
			return false;
		}

		return $user->isEnabled();
	}

	/**
	 * set the login name
	 *
	 * @param string|null $loginName for the logged in user
	 */
	public function setLoginName($loginName) {
		if (is_null($loginName)) {
			$this->session->remove('loginname');
		} else {
			$this->session->set('loginname', $loginName);
		}
	}

	/**
	 * get the login name of the current user
	 *
	 * @return string
	 */
	public function getLoginName() {
		if ($this->activeUser) {
			return $this->session->get('loginname');
		}

		$uid = $this->session->get('user_id');
		if ($uid) {
			$this->activeUser = $this->manager->get($uid);
			return $this->session->get('loginname');
		}

		return null;
	}

	/**
	 * @return null|string
	 */
	public function getImpersonatingUserID(): ?string {
		return $this->session->get('oldUserId');
	}

	public function setImpersonatingUserID(bool $useCurrentUser = true): void {
		if ($useCurrentUser === false) {
			$this->session->remove('oldUserId');
			return;
		}

		$currentUser = $this->getUser();

		if ($currentUser === null) {
			throw new \OC\User\NoUserException();
		}
		$this->session->set('oldUserId', $currentUser->getUID());
	}
	/**
	 * set the token id
	 *
	 * @param int|null $token that was used to log in
	 */
	protected function setToken($token) {
		if ($token === null) {
			$this->session->remove('token-id');
		} else {
			$this->session->set('token-id', $token);
		}
	}

	/**
	 * try to log in with the provided credentials
	 *
	 * @param string $uid
	 * @param string $password
	 * @return boolean|null
	 * @throws LoginException
	 */
	public function login($uid, $password) {
		$this->session->regenerateId();
		if ($this->validateToken($password, $uid)) {
			return $this->loginWithToken($password);
		}
		return $this->loginWithPassword($uid, $password);
	}

	/**
	 * @param IUser $user
	 * @param array $loginDetails
	 * @param bool $regenerateSessionId
	 * @return true returns true if login successful or an exception otherwise
	 * @throws LoginException
	 */
	public function completeLogin(IUser $user, array $loginDetails, $regenerateSessionId = true) {
		if (!$user->isEnabled()) {
			// disabled users can not log in
			// injecting l10n does not work - there is a circular dependency between session and \OCP\L10N\IFactory
			$message = \OC::$server->getL10N('lib')->t('User disabled');
			throw new LoginException($message);
		}

		if ($regenerateSessionId) {
			$this->session->regenerateId();
		}

		$this->setUser($user);
		$this->setLoginName($loginDetails['loginName']);

		$isToken = isset($loginDetails['token']) && $loginDetails['token'] instanceof IToken;
		if ($isToken) {
			$this->setToken($loginDetails['token']->getId());
			$this->lockdownManager->setToken($loginDetails['token']);
			$firstTimeLogin = false;
		} else {
			$this->setToken(null);
			$firstTimeLogin = $user->updateLastLoginTimestamp();
		}

		$this->dispatcher->dispatchTyped(new PostLoginEvent(
			$user,
			$loginDetails['loginName'],
			$loginDetails['password'],
			$isToken
		));
		$this->manager->emit('\OC\User', 'postLogin', [
			$user,
			$loginDetails['loginName'],
			$loginDetails['password'],
			$isToken,
		]);
		if ($this->isLoggedIn()) {
			$this->prepareUserLogin($firstTimeLogin, $regenerateSessionId);
			return true;
		}

		$message = \OC::$server->getL10N('lib')->t('Login canceled by app');
		throw new LoginException($message);
	}

	/**
	 * Tries to log in a client
	 *
	 * Checks token auth enforced
	 * Checks 2FA enabled
	 *
	 * @param string $user
	 * @param string $password
	 * @param IRequest $request
	 * @param OC\Security\Bruteforce\Throttler $throttler
	 * @throws LoginException
	 * @throws PasswordLoginForbiddenException
	 * @return boolean
	 */
	public function logClientIn($user,
								$password,
								IRequest $request,
								OC\Security\Bruteforce\Throttler $throttler) {
		$currentDelay = $throttler->sleepDelay($request->getRemoteAddress(), 'login');

		if ($this->manager instanceof PublicEmitter) {
			$this->manager->emit('\OC\User', 'preLogin', [$user, $password]);
		}

		try {
			$isTokenPassword = $this->isTokenPassword($password);
		} catch (ExpiredTokenException $e) {
			// Just return on an expired token no need to check further or record a failed login
			return false;
		}

		if (!$isTokenPassword && $this->isTokenAuthEnforced()) {
			throw new PasswordLoginForbiddenException();
		}
		if (!$isTokenPassword && $this->isTwoFactorEnforced($user)) {
			throw new PasswordLoginForbiddenException();
		}

		// Try to login with this username and password
		if (!$this->login($user, $password)) {

			// Failed, maybe the user used their email address
			$users = $this->manager->getByEmail($user);
			if (!(\count($users) === 1 && $this->login($users[0]->getUID(), $password))) {
				$this->logger->warning('Login failed: \'' . $user . '\' (Remote IP: \'' . \OC::$server->getRequest()->getRemoteAddress() . '\')', ['app' => 'core']);

				$throttler->registerAttempt('login', $request->getRemoteAddress(), ['user' => $user]);

				$this->dispatcher->dispatchTyped(new OC\Authentication\Events\LoginFailed($user));

				if ($currentDelay === 0) {
					$throttler->sleepDelay($request->getRemoteAddress(), 'login');
				}
				return false;
			}
		}

		if ($isTokenPassword) {
			$this->session->set('app_password', $password);
		} elseif ($this->supportsCookies($request)) {
			// Password login, but cookies supported -> create (browser) session token
			$this->createSessionToken($request, $this->getUser()->getUID(), $user, $password);
		}

		return true;
	}

	protected function supportsCookies(IRequest $request) {
		if (!is_null($request->getCookie('cookie_test'))) {
			return true;
		}
		setcookie('cookie_test', 'test', $this->timeFactory->getTime() + 3600);
		return false;
	}

	private function isTokenAuthEnforced() {
		return $this->config->getSystemValue('token_auth_enforced', false);
	}

	protected function isTwoFactorEnforced($username) {
		Util::emitHook(
			'\OCA\Files_Sharing\API\Server2Server',
			'preLoginNameUsedAsUserName',
			['uid' => &$username]
		);
		$user = $this->manager->get($username);
		if (is_null($user)) {
			$users = $this->manager->getByEmail($username);
			if (empty($users)) {
				return false;
			}
			if (count($users) !== 1) {
				return true;
			}
			$user = $users[0];
		}
		// DI not possible due to cyclic dependencies :'-/
		return OC::$server->getTwoFactorAuthManager()->isTwoFactorAuthenticated($user);
	}

	/**
	 * Check if the given 'password' is actually a device token
	 *
	 * @param string $password
	 * @return boolean
	 * @throws ExpiredTokenException
	 */
	public function isTokenPassword($password) {
		try {
			$this->tokenProvider->getToken($password);
			return true;
		} catch (ExpiredTokenException $e) {
			throw $e;
		} catch (InvalidTokenException $ex) {
			$this->logger->logException($ex, [
				'level' => ILogger::DEBUG,
				'message' => 'Token is not valid: ' . $ex->getMessage(),
			]);
			return false;
		}
	}

	protected function prepareUserLogin($firstTimeLogin, $refreshCsrfToken = true) {
		if ($refreshCsrfToken) {
			// TODO: mock/inject/use non-static
			// Refresh the token
			\OC::$server->getCsrfTokenManager()->refreshToken();
		}

		//we need to pass the user name, which may differ from login name
		$user = $this->getUser()->getUID();
		OC_Util::setupFS($user);

		if ($firstTimeLogin) {
			// TODO: lock necessary?
			//trigger creation of user home and /files folder
			$userFolder = \OC::$server->getUserFolder($user);

			try {
				// copy skeleton
				\OC_Util::copySkeleton($user, $userFolder);
			} catch (NotPermittedException $ex) {
				// read only uses
			}

			// trigger any other initialization
			\OC::$server->getEventDispatcher()->dispatch(IUser::class . '::firstLogin', new GenericEvent($this->getUser()));
		}
	}

	/**
	 * Tries to login the user with HTTP Basic Authentication
	 *
	 * @todo do not allow basic auth if the user is 2FA enforced
	 * @param IRequest $request
	 * @param OC\Security\Bruteforce\Throttler $throttler
	 * @return boolean if the login was successful
	 */
	public function tryBasicAuthLogin(IRequest $request,
									  OC\Security\Bruteforce\Throttler $throttler) {
		if (!empty($request->server['PHP_AUTH_USER']) && !empty($request->server['PHP_AUTH_PW'])) {
			try {
				if ($this->logClientIn($request->server['PHP_AUTH_USER'], $request->server['PHP_AUTH_PW'], $request, $throttler)) {
					/**
					 * Add DAV authenticated. This should in an ideal world not be
					 * necessary but the iOS App reads cookies from anywhere instead
					 * only the DAV endpoint.
					 * This makes sure that the cookies will be valid for the whole scope
					 * @see https://github.com/owncloud/core/issues/22893
					 */
					$this->session->set(
						Auth::DAV_AUTHENTICATED, $this->getUser()->getUID()
					);

					// Set the last-password-confirm session to make the sudo mode work
					$this->session->set('last-password-confirm', $this->timeFactory->getTime());

					return true;
				}
			} catch (PasswordLoginForbiddenException $ex) {
				// Nothing to do
			}
		}
		return false;
	}

	/**
	 * Log an user in via login name and password
	 *
	 * @param string $uid
	 * @param string $password
	 * @return boolean
	 * @throws LoginException if an app canceld the login process or the user is not enabled
	 */
	private function loginWithPassword($uid, $password) {
		$user = $this->manager->checkPasswordNoLogging($uid, $password);
		if ($user === false) {
			// Password check failed
			return false;
		}

		return $this->completeLogin($user, ['loginName' => $uid, 'password' => $password], false);
	}

	/**
	 * Log an user in with a given token (id)
	 *
	 * @param string $token
	 * @return boolean
	 * @throws LoginException if an app canceled the login process or the user is not enabled
	 */
	private function loginWithToken($token) {
		try {
			$dbToken = $this->tokenProvider->getToken($token);
		} catch (InvalidTokenException $ex) {
			return false;
		}
		$uid = $dbToken->getUID();

		// When logging in with token, the password must be decrypted first before passing to login hook
		$password = '';
		try {
			$password = $this->tokenProvider->getPassword($dbToken, $token);
		} catch (PasswordlessTokenException $ex) {
			// Ignore and use empty string instead
		}

		$this->manager->emit('\OC\User', 'preLogin', [$uid, $password]);

		$user = $this->manager->get($uid);
		if (is_null($user)) {
			// user does not exist
			return false;
		}

		return $this->completeLogin(
			$user,
			[
				'loginName' => $dbToken->getLoginName(),
				'password' => $password,
				'token' => $dbToken
			],
			false);
	}

	/**
	 * Create a new session token for the given user credentials
	 *
	 * @param IRequest $request
	 * @param string $uid user UID
	 * @param string $loginName login name
	 * @param string $password
	 * @param int $remember
	 * @return boolean
	 */
	public function createSessionToken(IRequest $request, $uid, $loginName, $password = null, $remember = IToken::DO_NOT_REMEMBER) {
		if (is_null($this->manager->get($uid))) {
			// User does not exist
			return false;
		}
		$name = isset($request->server['HTTP_USER_AGENT']) ? $request->server['HTTP_USER_AGENT'] : 'unknown browser';
		try {
			$sessionId = $this->session->getId();
			$pwd = $this->getPassword($password);
			// Make sure the current sessionId has no leftover tokens
			$this->tokenProvider->invalidateToken($sessionId);
			$this->tokenProvider->generateToken($sessionId, $uid, $loginName, $pwd, $name, IToken::TEMPORARY_TOKEN, $remember);
			return true;
		} catch (SessionNotAvailableException $ex) {
			// This can happen with OCC, where a memory session is used
			// if a memory session is used, we shouldn't create a session token anyway
			return false;
		}
	}

	/**
	 * Checks if the given password is a token.
	 * If yes, the password is extracted from the token.
	 * If no, the same password is returned.
	 *
	 * @param string $password either the login password or a device token
	 * @return string|null the password or null if none was set in the token
	 */
	private function getPassword($password) {
		if (is_null($password)) {
			// This is surely no token ;-)
			return null;
		}
		try {
			$token = $this->tokenProvider->getToken($password);
			try {
				return $this->tokenProvider->getPassword($token, $password);
			} catch (PasswordlessTokenException $ex) {
				return null;
			}
		} catch (InvalidTokenException $ex) {
			return $password;
		}
	}

	/**
	 * @param IToken $dbToken
	 * @param string $token
	 * @return boolean
	 */
	private function checkTokenCredentials(IToken $dbToken, $token) {
		// Check whether login credentials are still valid and the user was not disabled
		// This check is performed each 5 minutes
		$lastCheck = $dbToken->getLastCheck() ? : 0;
		$now = $this->timeFactory->getTime();
		if ($lastCheck > ($now - 60 * 5)) {
			// Checked performed recently, nothing to do now
			return true;
		}

		try {
			$pwd = $this->tokenProvider->getPassword($dbToken, $token);
		} catch (InvalidTokenException $ex) {
			// An invalid token password was used -> log user out
			return false;
		} catch (PasswordlessTokenException $ex) {
			// Token has no password

			if (!is_null($this->activeUser) && !$this->activeUser->isEnabled()) {
				$this->tokenProvider->invalidateToken($token);
				return false;
			}

			$dbToken->setLastCheck($now);
			return true;
		}

		// Invalidate token if the user is no longer active
		if (!is_null($this->activeUser) && !$this->activeUser->isEnabled()) {
			$this->tokenProvider->invalidateToken($token);
			return false;
		}

		// If the token password is no longer valid mark it as such
		if ($this->manager->checkPassword($dbToken->getLoginName(), $pwd) === false) {
			$this->tokenProvider->markPasswordInvalid($dbToken, $token);
			// User is logged out
			return false;
		}

		$dbToken->setLastCheck($now);
		return true;
	}

	/**
	 * Check if the given token exists and performs password/user-enabled checks
	 *
	 * Invalidates the token if checks fail
	 *
	 * @param string $token
	 * @param string $user login name
	 * @return boolean
	 */
	private function validateToken($token, $user = null) {
		try {
			$dbToken = $this->tokenProvider->getToken($token);
		} catch (InvalidTokenException $ex) {
			return false;
		}

		// Check if login names match
		if (!is_null($user) && $dbToken->getLoginName() !== $user) {
			// TODO: this makes it imposssible to use different login names on browser and client
			// e.g. login by e-mail 'user@example.com' on browser for generating the token will not
			//      allow to use the client token with the login name 'user'.
			return false;
		}

		if (!$this->checkTokenCredentials($dbToken, $token)) {
			return false;
		}

		// Update token scope
		$this->lockdownManager->setToken($dbToken);

		$this->tokenProvider->updateTokenActivity($dbToken);

		return true;
	}

	/**
	 * Tries to login the user with auth token header
	 *
	 * @param IRequest $request
	 * @todo check remember me cookie
	 * @return boolean
	 */
	public function tryTokenLogin(IRequest $request) {
		$authHeader = $request->getHeader('Authorization');
		if (strpos($authHeader, 'Bearer ') === 0) {
			$token = substr($authHeader, 7);
		} else {
			// No auth header, let's try session id
			try {
				$token = $this->session->getId();
			} catch (SessionNotAvailableException $ex) {
				return false;
			}
		}

		if (!$this->loginWithToken($token)) {
			return false;
		}
		if (!$this->validateToken($token)) {
			return false;
		}

		try {
			$dbToken = $this->tokenProvider->getToken($token);
		} catch (InvalidTokenException $e) {
			// Can't really happen but better save than sorry
			return true;
		}

		// Remember me tokens are not app_passwords
		if ($dbToken->getRemember() === IToken::DO_NOT_REMEMBER) {
			// Set the session variable so we know this is an app password
			$this->session->set('app_password', $token);
		}

		return true;
	}

	/**
	 * perform login using the magic cookie (remember login)
	 *
	 * @param string $uid the username
	 * @param string $currentToken
	 * @param string $oldSessionId
	 * @return bool
	 */
	public function loginWithCookie($uid, $currentToken, $oldSessionId) {
		$this->session->regenerateId();
		$this->manager->emit('\OC\User', 'preRememberedLogin', [$uid]);
		$user = $this->manager->get($uid);
		if (is_null($user)) {
			// user does not exist
			return false;
		}

		// get stored tokens
		$tokens = $this->config->getUserKeys($uid, 'login_token');
		// test cookies token against stored tokens
		if (!in_array($currentToken, $tokens, true)) {
			return false;
		}
		// replace successfully used token with a new one
		$this->config->deleteUserValue($uid, 'login_token', $currentToken);
		$newToken = $this->random->generate(32);
		$this->config->setUserValue($uid, 'login_token', $newToken, $this->timeFactory->getTime());

		try {
			$sessionId = $this->session->getId();
			$token = $this->tokenProvider->renewSessionToken($oldSessionId, $sessionId);
		} catch (SessionNotAvailableException $ex) {
			return false;
		} catch (InvalidTokenException $ex) {
			\OC::$server->getLogger()->warning('Renewing session token failed', ['app' => 'core']);
			return false;
		}

		$this->setMagicInCookie($user->getUID(), $newToken);

		//login
		$this->setUser($user);
		$this->setLoginName($token->getLoginName());
		$this->setToken($token->getId());
		$this->lockdownManager->setToken($token);
		$user->updateLastLoginTimestamp();
		$password = null;
		try {
			$password = $this->tokenProvider->getPassword($token, $sessionId);
		} catch (PasswordlessTokenException $ex) {
			// Ignore
		}
		$this->manager->emit('\OC\User', 'postRememberedLogin', [$user, $password]);
		return true;
	}

	/**
	 * @param IUser $user
	 */
	public function createRememberMeToken(IUser $user) {
		$token = $this->random->generate(32);
		$this->config->setUserValue($user->getUID(), 'login_token', $token, $this->timeFactory->getTime());
		$this->setMagicInCookie($user->getUID(), $token);
	}

	/**
	 * logout the user from the session
	 */
	public function logout() {
		$user = $this->getUser();
		$this->manager->emit('\OC\User', 'logout', [$user]);
		if ($user !== null) {
			try {
				$this->tokenProvider->invalidateToken($this->session->getId());
			} catch (SessionNotAvailableException $ex) {
			}
		}
		$this->setUser(null);
		$this->setLoginName(null);
		$this->setToken(null);
		$this->unsetMagicInCookie();
		$this->session->clear();
		$this->manager->emit('\OC\User', 'postLogout', [$user]);
	}

	/**
	 * Set cookie value to use in next page load
	 *
	 * @param string $username username to be set
	 * @param string $token
	 */
	public function setMagicInCookie($username, $token) {
		$secureCookie = OC::$server->getRequest()->getServerProtocol() === 'https';
		$webRoot = \OC::$WEBROOT;
		if ($webRoot === '') {
			$webRoot = '/';
		}

		$maxAge = $this->config->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
		\OC\Http\CookieHelper::setCookie(
			'nc_username',
			$username,
			$maxAge,
			$webRoot,
			'',
			$secureCookie,
			true,
			\OC\Http\CookieHelper::SAMESITE_LAX
		);
		\OC\Http\CookieHelper::setCookie(
			'nc_token',
			$token,
			$maxAge,
			$webRoot,
			'',
			$secureCookie,
			true,
			\OC\Http\CookieHelper::SAMESITE_LAX
		);
		try {
			\OC\Http\CookieHelper::setCookie(
				'nc_session_id',
				$this->session->getId(),
				$maxAge,
				$webRoot,
				'',
				$secureCookie,
				true,
				\OC\Http\CookieHelper::SAMESITE_LAX
			);
		} catch (SessionNotAvailableException $ex) {
			// ignore
		}
	}

	/**
	 * Remove cookie for "remember username"
	 */
	public function unsetMagicInCookie() {
		//TODO: DI for cookies and IRequest
		$secureCookie = OC::$server->getRequest()->getServerProtocol() === 'https';

		unset($_COOKIE['nc_username']); //TODO: DI
		unset($_COOKIE['nc_token']);
		unset($_COOKIE['nc_session_id']);
		setcookie('nc_username', '', $this->timeFactory->getTime() - 3600, OC::$WEBROOT, '', $secureCookie, true);
		setcookie('nc_token', '', $this->timeFactory->getTime() - 3600, OC::$WEBROOT, '', $secureCookie, true);
		setcookie('nc_session_id', '', $this->timeFactory->getTime() - 3600, OC::$WEBROOT, '', $secureCookie, true);
		// old cookies might be stored under /webroot/ instead of /webroot
		// and Firefox doesn't like it!
		setcookie('nc_username', '', $this->timeFactory->getTime() - 3600, OC::$WEBROOT . '/', '', $secureCookie, true);
		setcookie('nc_token', '', $this->timeFactory->getTime() - 3600, OC::$WEBROOT . '/', '', $secureCookie, true);
		setcookie('nc_session_id', '', $this->timeFactory->getTime() - 3600, OC::$WEBROOT . '/', '', $secureCookie, true);
	}

	/**
	 * Update password of the browser session token if there is one
	 *
	 * @param string $password
	 */
	public function updateSessionTokenPassword($password) {
		try {
			$sessionId = $this->session->getId();
			$token = $this->tokenProvider->getToken($sessionId);
			$this->tokenProvider->setPassword($token, $sessionId, $password);
		} catch (SessionNotAvailableException $ex) {
			// Nothing to do
		} catch (InvalidTokenException $ex) {
			// Nothing to do
		}
	}

	public function updateTokens(string $uid, string $password) {
		$this->tokenProvider->updatePasswords($uid, $password);
	}
}
