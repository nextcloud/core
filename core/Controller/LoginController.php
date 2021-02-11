<?php
/**
 * @copyright Copyright (c) 2017, Sandro Lutz <sandro.lutz@temparus.ch>
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Rayn0r <andrew@ilpss8.myfirewall.org>
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

namespace OC\Core\Controller;

use OC\AppFramework\Http\Request;
use OC\Authentication\Login\Chain;
use OC\Authentication\Login\LoginData;
use OC\Authentication\WebAuthn\Manager as WebAuthnManager;
use OC\Security\Bruteforce\Throttler;
use OC\User\Session;
use OC_App;
use OC_Util;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

class LoginController extends Controller {
	public const LOGIN_MSG_INVALIDPASSWORD = 'invalidpassword';
	public const LOGIN_MSG_USERDISABLED = 'userdisabled';

	/** @var IUserManager */
	private $userManager;
	/** @var IConfig */
	private $config;
	/** @var ISession */
	private $session;
	/** @var IUserSession|Session */
	private $userSession;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ILogger */
	private $logger;
	/** @var Defaults */
	private $defaults;
	/** @var Throttler */
	private $throttler;
	/** @var Chain */
	private $loginChain;
	/** @var IInitialStateService */
	private $initialStateService;
	/** @var WebAuthnManager */
	private $webAuthnManager;

	public function __construct(?string $appName,
								IRequest $request,
								IUserManager $userManager,
								IConfig $config,
								ISession $session,
								IUserSession $userSession,
								IURLGenerator $urlGenerator,
								ILogger $logger,
								Defaults $defaults,
								Throttler $throttler,
								Chain $loginChain,
								IInitialStateService $initialStateService,
								WebAuthnManager $webAuthnManager) {
		parent::__construct($appName, $request);
		$this->userManager = $userManager;
		$this->config = $config;
		$this->session = $session;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->defaults = $defaults;
		$this->throttler = $throttler;
		$this->loginChain = $loginChain;
		$this->initialStateService = $initialStateService;
		$this->webAuthnManager = $webAuthnManager;
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	 *
	 * @return RedirectResponse
	 */
	public function logout() {
		$loginToken = $this->request->getCookie('nc_token');
		if (!is_null($loginToken)) {
			$this->config->deleteUserValue($this->userSession->getUser()->getUID(), 'login_token', $loginToken);
		}
		$this->userSession->logout();

		$response = new RedirectResponse($this->urlGenerator->linkToRouteAbsolute(
			'core.login.showLoginForm',
			['clear' => true] // this param the the code in login.js may be removed when the "Clear-Site-Data" is working in the browsers
		));

		$this->session->set('clearingExecutionContexts', '1');
		$this->session->close();

		if (!$this->request->isUserAgent([Request::USER_AGENT_CHROME, Request::USER_AGENT_ANDROID_MOBILE_CHROME])) {
			$response->addHeader('Clear-Site-Data', '"cache", "storage"');
		}

		return $response;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @param string $user
	 * @param string $redirect_url
	 *
	 * @return TemplateResponse|RedirectResponse
	 */
	public function showLoginForm(string $user = null, string $redirect_url = null): Http\Response {
		if ($this->userSession->isLoggedIn()) {
			return new RedirectResponse(OC_Util::getDefaultPageUrl());
		}

		$loginMessages = $this->session->get('loginMessages');
		if (is_array($loginMessages)) {
			list($errors, $messages) = $loginMessages;
			$this->initialStateService->provideInitialState('core', 'loginMessages', $messages);
			$this->initialStateService->provideInitialState('core', 'loginErrors', $errors);
		}
		$this->session->remove('loginMessages');

		if ($user !== null && $user !== '') {
			$this->initialStateService->provideInitialState('core', 'loginUsername', $user);
		} else {
			$this->initialStateService->provideInitialState('core', 'loginUsername', '');
		}

		$this->initialStateService->provideInitialState(
			'core',
			'loginAutocomplete',
			$this->config->getSystemValue('login_form_autocomplete', true) === true
		);

		if (!empty($redirect_url)) {
			[$url, ] = explode('?', $redirect_url);
			if ($url !== $this->urlGenerator->linkToRoute('core.login.logout')) {
				$this->initialStateService->provideInitialState('core', 'loginRedirectUrl', $redirect_url);
			}
		}

		$this->initialStateService->provideInitialState(
			'core',
			'loginThrottleDelay',
			$this->throttler->getDelay($this->request->getRemoteAddress())
		);

		$this->setPasswordResetInitialState($user);

		$this->initialStateService->provideInitialState('core', 'webauthn-available', $this->webAuthnManager->isWebAuthnAvailable());

		// OpenGraph Support: http://ogp.me/
		Util::addHeader('meta', ['property' => 'og:title', 'content' => Util::sanitizeHTML($this->defaults->getName())]);
		Util::addHeader('meta', ['property' => 'og:description', 'content' => Util::sanitizeHTML($this->defaults->getSlogan())]);
		Util::addHeader('meta', ['property' => 'og:site_name', 'content' => Util::sanitizeHTML($this->defaults->getName())]);
		Util::addHeader('meta', ['property' => 'og:url', 'content' => $this->urlGenerator->getAbsoluteURL('/')]);
		Util::addHeader('meta', ['property' => 'og:type', 'content' => 'website']);
		Util::addHeader('meta', ['property' => 'og:image', 'content' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'favicon-touch.png'))]);

		$parameters = [
			'alt_login' => OC_App::getAlternativeLogIns(),
		];
		return new TemplateResponse(
			$this->appName, 'login', $parameters, 'guest'
		);
	}

	/**
	 * Sets the password reset state
	 *
	 * @param string $username
	 */
	private function setPasswordResetInitialState(?string $username): void {
		if ($username !== null && $username !== '') {
			$user = $this->userManager->get($username);
		} else {
			$user = null;
		}

		$passwordLink = $this->config->getSystemValue('lost_password_link', '');

		$this->initialStateService->provideInitialState(
			'core',
			'loginResetPasswordLink',
			$passwordLink
		);

		$this->initialStateService->provideInitialState(
			'core',
			'loginCanResetPassword',
			$this->canResetPassword($passwordLink, $user)
		);
	}

	/**
	 * @param string|null $passwordLink
	 * @param IUser|null $user
	 *
	 * Users may not change their passwords if:
	 * - The account is disabled
	 * - The backend doesn't support password resets
	 * - The password reset function is disabled
	 *
	 * @return bool
	 */
	private function canResetPassword(?string $passwordLink, ?IUser $user): bool {
		if ($passwordLink === 'disabled') {
			return false;
		}

		if (!$passwordLink && $user !== null) {
			return $user->canChangePassword();
		}

		if ($user !== null && $user->isEnabled() === false) {
			return false;
		}

		return true;
	}

	private function generateRedirect(?string $redirectUrl): RedirectResponse {
		if ($redirectUrl !== null && $this->userSession->isLoggedIn()) {
			$location = $this->urlGenerator->getAbsoluteURL($redirectUrl);
			// Deny the redirect if the URL contains a @
			// This prevents unvalidated redirects like ?redirect_url=:user@domain.com
			if (strpos($location, '@') === false) {
				return new RedirectResponse($location);
			}
		}
		return new RedirectResponse(OC_Util::getDefaultPageUrl());
	}

	/**
	 * @PublicPage
	 * @UseSession
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=login)
	 *
	 * @param string $user
	 * @param string $password
	 * @param string $redirect_url
	 * @param string $timezone
	 * @param string $timezone_offset
	 *
	 * @return RedirectResponse
	 */
	public function tryLogin(string $user,
							 string $password,
							 string $redirect_url = null,
							 string $timezone = '',
							 string $timezone_offset = ''): RedirectResponse {
		// If the user is already logged in and the CSRF check does not pass then
		// simply redirect the user to the correct page as required. This is the
		// case when an user has already logged-in, in another tab.
		if (!$this->request->passesCSRFCheck()) {
			return $this->generateRedirect($redirect_url);
		}

		$data = new LoginData(
			$this->request,
			trim($user),
			$password,
			$redirect_url,
			$timezone,
			$timezone_offset
		);
		$result = $this->loginChain->process($data);
		if (!$result->isSuccess()) {
			return $this->createLoginFailedResponse(
				$data->getUsername(),
				$user,
				$redirect_url,
				$result->getErrorMessage()
			);
		}

		if ($result->getRedirectUrl() !== null) {
			return new RedirectResponse($result->getRedirectUrl());
		}
		return $this->generateRedirect($redirect_url);
	}

	/**
	 * Creates a login failed response.
	 *
	 * @param string $user
	 * @param string $originalUser
	 * @param string $redirect_url
	 * @param string $loginMessage
	 *
	 * @return RedirectResponse
	 */
	private function createLoginFailedResponse(
		$user, $originalUser, $redirect_url, string $loginMessage) {
		// Read current user and append if possible we need to
		// return the unmodified user otherwise we will leak the login name
		$args = $user !== null ? ['user' => $originalUser, 'direct' => 1] : [];
		if ($redirect_url !== null) {
			$args['redirect_url'] = $redirect_url;
		}
		$response = new RedirectResponse(
			$this->urlGenerator->linkToRoute('core.login.showLoginForm', $args)
		);
		$response->throttle(['user' => substr($user, 0, 64)]);
		$this->session->set('loginMessages', [
			[$loginMessage], []
		]);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	 * @BruteForceProtection(action=sudo)
	 *
	 * @param string $password
	 *
	 * @return DataResponse
	 * @license GNU AGPL version 3 or any later version
	 *
	 */
	public function confirmPassword($password) {
		$loginName = $this->userSession->getLoginName();
		$loginResult = $this->userManager->checkPassword($loginName, $password);
		if ($loginResult === false) {
			$response = new DataResponse([], Http::STATUS_FORBIDDEN);
			$response->throttle();
			return $response;
		}

		$confirmTimestamp = time();
		$this->session->set('last-password-confirm', $confirmTimestamp);
		return new DataResponse(['lastLogin' => $confirmTimestamp], Http::STATUS_OK);
	}
}
