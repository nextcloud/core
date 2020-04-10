<?php
/**
 * @copyright Copyright (c) 2017 Roger Szabo <roger.szabo@web.de>
 *
 * @author Roger Szabo <roger.szabo@web.de>
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

namespace OCA\User_LDAP\Controller;

use OC\HintException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

class RenewPasswordController extends Controller {
	/** @var IUserManager */
	private $userManager;
	/** @var IConfig */
	private $config;
	/** @var IL10N */
	protected $l10n;
	/** @var ISession */
	private $session;
	/** @var IURLGenerator */
	private $urlGenerator;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 * @param IURLGenerator $urlGenerator
	 */
	function __construct($appName, IRequest $request, IUserManager $userManager,
		IConfig $config, IL10N $l10n, ISession $session, IURLGenerator $urlGenerator) {
		parent::__construct($appName, $request);
		$this->userManager = $userManager;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->session = $session;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return RedirectResponse
	 */
	public function cancel() {
		return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('core.login.showLoginForm'));
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @param string $user
	 *
	 * @return TemplateResponse|RedirectResponse
	 */
	public function showRenewPasswordForm($user) {
		if($this->config->getUserValue($user, 'user_ldap', 'needsPasswordReset') !== 'true') {
			return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('core.login.showLoginForm'));
		}
		$parameters = [];
		$renewPasswordMessages = $this->session->get('renewPasswordMessages');
		$errors = [];
		$messages = [];
		if (is_array($renewPasswordMessages)) {
			list($errors, $messages) = $renewPasswordMessages;
		}
		$this->session->remove('renewPasswordMessages');
		foreach ($errors as $value) {
			$parameters[$value] = true;
		}

		$parameters['messages'] = $messages;
		$parameters['user'] = $user;

		$parameters['canResetPassword'] = true;
		$parameters['resetPasswordLink'] = $this->config->getSystemValue('lost_password_link', '');
		if (!$parameters['resetPasswordLink']) {
			$userObj = $this->userManager->get($user);
			if ($userObj instanceof IUser) {
				$parameters['canResetPassword'] = $userObj->canChangePassword();
			}
		}
		$parameters['cancelLink'] = $this->urlGenerator->linkToRouteAbsolute('core.login.showLoginForm');

		return new TemplateResponse(
			$this->appName, 'renewpassword', $parameters, 'guest'
		);
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param string $user
	 * @param string $oldPassword
	 * @param string $newPassword
	 *
	 * @return RedirectResponse
	 */
	public function tryRenewPassword($user, $oldPassword, $newPassword) {
		if($this->config->getUserValue($user, 'user_ldap', 'needsPasswordReset') !== 'true') {
			return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('core.login.showLoginForm'));
		}
		$args = !is_null($user) ? ['user' => $user] : [];
		$loginResult = $this->userManager->checkPassword($user, $oldPassword);
		if ($loginResult === false) {
			$this->session->set('renewPasswordMessages', [
				['invalidpassword'], []
			]);
			return new RedirectResponse($this->urlGenerator->linkToRoute('user_ldap.renewPassword.showRenewPasswordForm', $args));
		}
		
		try {
			if (!is_null($newPassword) && \OC_User::setPassword($user, $newPassword)) {
				$this->session->set('loginMessages', [
					[], [$this->l10n->t("Please login with the new password")]
				]);
				$this->config->setUserValue($user, 'user_ldap', 'needsPasswordReset', 'false');
				return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.showLoginForm', $args));
			} else {
				$this->session->set('renewPasswordMessages', [
					['internalexception'], []
				]);
			}
		} catch (HintException $e) {
			$this->session->set('renewPasswordMessages', [
				[], [$e->getHint()]
			]);
		}

		return new RedirectResponse($this->urlGenerator->linkToRoute('user_ldap.renewPassword.showRenewPasswordForm', $args));
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @return RedirectResponse
	 */
	public function showLoginFormInvalidPassword($user) {
		$args = !is_null($user) ? ['user' => $user] : [];
		$this->session->set('loginMessages', [
			['invalidpassword'], []
		]);
		return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.showLoginForm', $args));
	}

}
