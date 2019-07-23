<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Core\Controller;

use OC\Authentication\TwoFactorAuth\Manager;
use OC\Security\Bruteforce\Throttler;
use OC\HintException;
use \OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\Encryption\IEncryptionModule;
use OCP\Encryption\IManager;
use OCP\ILogger;
use \OCP\IURLGenerator;
use \OCP\IRequest;
use \OCP\IL10N;
use \OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\ISession;
use OCP\Util;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use function array_filter;
use function count;
use function reset;

/**
 * Class LostController
 *
 * Successfully changing a password will emit the post_passwordReset hook.
 *
 * @package OC\Core\Controller
 */
class LostController extends Controller {
	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var IUserManager */
	protected $userManager;
	/** @var Defaults */
	protected $defaults;
	/** @var IL10N */
	protected $l10n;
	/** @var string */
	protected $from;
	/** @var IManager */
	protected $encryptionManager;
	/** @var IConfig */
	protected $config;
	/** @var ISession */
	protected $session;
	/** @var ISecureRandom */
	protected $secureRandom;
	/** @var IMailer */
	protected $mailer;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var ICrypto */
	protected $crypto;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $twoFactorManager;
	/** @var Throttler */
	private $throttler;


	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IURLGenerator $urlGenerator
	 * @param IUserManager $userManager
	 * @param Defaults $defaults
	 * @param IL10N $l10n
	 * @param IConfig $config
 	 * @param ISession $session
	 * @param ISecureRandom $secureRandom
	 * @param string $defaultMailAddress
	 * @param IManager $encryptionManager
	 * @param IMailer $mailer
	 * @param ITimeFactory $timeFactory
	 * @param ICrypto $crypto
	 */
	public function __construct($appName,
								IRequest $request,
								IURLGenerator $urlGenerator,
								IUserManager $userManager,
								Defaults $defaults,
								IL10N $l10n,
								IConfig $config,
								ISession $session,								
								ISecureRandom $secureRandom,
								$defaultMailAddress,
								IManager $encryptionManager,
								IMailer $mailer,
								ITimeFactory $timeFactory,
								ICrypto $crypto,
								ILogger $logger,
								Manager $twoFactorManager,
								Throttler $throttler) {
		parent::__construct($appName, $request);
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->defaults = $defaults;
		$this->l10n = $l10n;
		$this->secureRandom = $secureRandom;
		$this->from = $defaultMailAddress;
		$this->encryptionManager = $encryptionManager;
		$this->config = $config;
		$this->session = $session;
		$this->mailer = $mailer;
		$this->timeFactory = $timeFactory;
		$this->crypto = $crypto;
		$this->logger = $logger;
		$this->twoFactorManager = $twoFactorManager;
		$this->throttler = $throttler;
	}

	/**
	 * Someone wants to reset their password:
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 * @param string $userId
	 * @return TemplateResponse
	 */
	public function resetform($token, $userId) {
		if ($this->config->getSystemValue('lost_password_link', '') !== '') {
			return new TemplateResponse('core', 'error', [
					'errors' => [['error' => $this->l10n->t('Password reset is disabled')]]
				],
				'guest'
			);
		}

		try {
			$this->checkPasswordResetToken($token, $userId);
		} catch (\Exception $e) {
			return new TemplateResponse(
				'core', 'error', [
					"errors" => array(array("error" => $e->getMessage()))
				],
				'guest'
			);
		}

		return new TemplateResponse(
			'core',
			'lostpassword/resetpassword',
			array(
				'link' => $this->urlGenerator->linkToRouteAbsolute('core.lost.setPassword', array('userId' => $userId, 'token' => $token)),
			),
			'guest'
		);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=passwordResetEmail)
	 * @AnonRateThrottle(limit=10, period=300)
	 * @UseSession
	 *
	 * @param string $userId
	 *
	 * @return TemplateResponse
	 */
	public function showPasswordEmailForm($userId) : Http\Response {
		return $this->showNewPasswordForm($userId);
	}		
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=passwordResetEmail)
	 * @AnonRateThrottle(limit=10, period=300)
	 * @UseSession
	 *
	 * @param string $user
	 *
	 * @return TemplateResponse
	 */
	public function showNewPasswordForm($user = null) : Http\Response {

		$parameters = [];
		$errors = [];
		$messages = [];

		$renewPasswordMessages = $this->session->get('loginMessages');
		if (is_array($renewPasswordMessages)) {
			[$errors, $messages] = $renewPasswordMessages;
		}
		$this->session->remove('loginMessages');
		foreach ($errors as $value) {
			$parameters[$value] = true;
		}
		$parameters['messages'] = $messages;

		$parameters['resetPasswordLink'] = $this->config
		->getSystemValue('lost_password_link', '');

		// disable the form if setting 'password reset' is disabled
		if ($parameters['resetPasswordLink'] !== '') {
			return new TemplateResponse('core', 'error', [
					'errors' => [['error' => $this->l10n->t('Password reset is disabled')]]
				],
				'guest'
			);
		}
		
		$userObj = null;
		if ($user !== null && $user !== '') {
			try {
				$userObj = $this->findUserByIdOrMail($user);
				$parameters['displayName'] = $userObj->getDisplayName();
				$parameters['loginName'] = $userObj->getEMailAddress();
				//the timestamp of the user's last login or 0 if the user did never
				$parameters['last_login'] = $userObj->getLastLogin();
				$parameters['user_autofocus'] = false;
			} catch(\InvalidArgumentException $exception){
				// $user parameter is unknown or desactivated				
				$parameters['messages'][] =  $user . ' :' . $this->l10n->t('unknown text');
				$parameters['loginName'] = null;
				$parameters['displayName'] = null;
				$parameters['last_login'] = null;
				$parameters['user_autofocus'] = true;
			}
		}
		
		$parameters = $this->setPasswordResetParameters($userObj, $parameters);
		// the administrator_email value, if set in confif.php makes possible mailto:// and customized messages in front pages
		$parameters['administrator_email'] = $this->config->getSystemValue('administrator_email',null);
		$parameters['login_form_autocomplete'] = 'on';
		$parameters['throttle_delay'] = $this->throttler->getDelay($this->request->getRemoteAddress());

		// OpenGraph Support: http://ogp.me/
		Util::addHeader('meta', ['property' => 'og:title', 'content' => Util::sanitizeHTML($this->defaults->getName())]);
		Util::addHeader('meta', ['property' => 'og:description', 'content' => Util::sanitizeHTML($this->defaults->getSlogan())]);
		Util::addHeader('meta', ['property' => 'og:site_name', 'content' => Util::sanitizeHTML($this->defaults->getName())]);
		Util::addHeader('meta', ['property' => 'og:url', 'content' => $this->urlGenerator->getAbsoluteURL('/')]);
		Util::addHeader('meta', ['property' => 'og:type', 'content' => 'website']);
		Util::addHeader('meta', ['property' => 'og:image', 'content' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'favicon-touch.png'))]);

		return new TemplateResponse(
			$this->appName, 'lostpassword/newpassword', $parameters, 'guest'
		);
	}

	/**
	 * @param string $token
	 * @param string $userId
	 * @throws \Exception
	 */
	protected function checkPasswordResetToken($token, $userId) {
		$user = $this->userManager->get($userId);
		if($user === null || !$user->isEnabled()) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is invalid'));
		}

		try {
			$encryptedToken = $this->config->getUserValue($userId, 'core', 'lostpassword', null);
			$mailAddress = !is_null($user->getEMailAddress()) ? $user->getEMailAddress() : '';
			$decryptedToken = $this->crypto->decrypt($encryptedToken, $mailAddress.$this->config->getSystemValue('secret'));
		} catch (\Exception $e) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is invalid'));
		}

		$splittedToken = explode(':', $decryptedToken);
		if(count($splittedToken) !== 2) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is invalid'));
		}

		if ($splittedToken[0] < ($this->timeFactory->getTime() - 60*60*24*7) ||
			$user->getLastLogin() > $splittedToken[0]) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is expired'));
		}

		if (!hash_equals($splittedToken[1], $token)) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is invalid'));
		}
	}

	/**
	 * @param $message
	 * @param array $additional
	 * @return array
	 */
	private function error($message, array $additional=array()) {
		return array_merge(array('status' => 'error', 'msg' => $message), $additional);
	}

	/**
	 * @param array $data
	 * @return array
	 */
	private function success($data = []) {
		return array_merge($data, ['status'=>'success']);
	}

	/**
	 * @PublicPage
	 * @BruteForceProtection(action=passwordResetEmail)
	 * @AnonRateThrottle(limit=10, period=300)
	 *
	 * @param string $user
	 * @param string $action optional
	 * @return JSONResponse
	 */
	public function email($user, string $action = null){
		if ($this->config->getSystemValue('lost_password_link', '') !== '') {
			return new JSONResponse($this->error($this->l10n->t('Password reset is disabled')));
		}

		\OCP\Util::emitHook(
			'\OCA\Files_Sharing\API\Server2Server',
			'preLoginNameUsedAsUserName',
			['uid' => &$user]
		);

		// FIXME: use HTTP error codes
		try {
			$this->sendEmail($user, $action);
		} catch (\Exception $e) {
			// Ignore the error since we do not want to leak this info
			$this->logger->logException($e, [
				'level' => ILogger::WARN
			]);
		}

		$response = new JSONResponse($this->success());
		$response->throttle();
		return $response;
	}

	/**
	 * @PublicPage
	 * @param string $token
	 * @param string $userId
	 * @param string $password
	 * @param boolean $proceed
	 * @return array
	 */
	public function setPassword($token, $userId, $password, $proceed) {
		if ($this->config->getSystemValue('lost_password_link', '') !== '') {
			return $this->error($this->l10n->t('Password reset is disabled'));
		}

		if ($this->encryptionManager->isEnabled() && !$proceed) {
			$encryptionModules = $this->encryptionManager->getEncryptionModules();
			foreach ($encryptionModules as $module) {
				/** @var IEncryptionModule $instance */
				$instance = call_user_func($module['callback']);
				// this way we can find out whether per-user keys are used or a system wide encryption key
				if ($instance->needDetailedAccessList()) {
					return $this->error('', array('encryption' => true));
				}
			}
		}

		try {
			$this->checkPasswordResetToken($token, $userId);
			$user = $this->userManager->get($userId);

			\OC_Hook::emit('\OC\Core\LostPassword\Controller\LostController', 'pre_passwordReset', array('uid' => $userId, 'password' => $password));

			if (!$user->setPassword($password)) {
				throw new \Exception();
			}

			\OC_Hook::emit('\OC\Core\LostPassword\Controller\LostController', 'post_passwordReset', array('uid' => $userId, 'password' => $password));

			$this->twoFactorManager->clearTwoFactorPending($userId);

			$this->config->deleteUserValue($userId, 'core', 'lostpassword');
			@\OC::$server->getUserSession()->unsetMagicInCookie();
		} catch (HintException $e){
			return $this->error($e->getHint());
		} catch (\Exception $e){
			return $this->error($e->getMessage());
		}

		return $this->success(['user' => $userId]);
	}

	/**
	 * @param string $input
	 * @param string $action
	 * @throws \Exception
	 */
	protected function sendEmail($input, string $action = null) {
		$user = $this->findUserByIdOrMail($input);
		$email = $user->getEMailAddress();

		if (empty($email)) {
			throw new \Exception(
				$this->l10n->t('Could not send reset email because there is no email address for this username. Please contact your administrator.')
			);
		}

		// Generate the token. It is stored encrypted in the database with the
		// secret being the users' email address appended with the system secret.
		// This makes the token automatically invalidate once the user changes
		// their email address.
		$token = $this->secureRandom->generate(
			21,
			ISecureRandom::CHAR_DIGITS.
			ISecureRandom::CHAR_LOWER.
			ISecureRandom::CHAR_UPPER
		);
		$tokenValue = $this->timeFactory->getTime() .':'. $token;
		$encryptedValue = $this->crypto->encrypt($tokenValue, $email . $this->config->getSystemValue('secret'));
		$this->config->setUserValue($user->getUID(), 'core', 'lostpassword', $encryptedValue);

		$link = $this->urlGenerator->linkToRouteAbsolute('core.lost.resetform', array('userId' => $user->getUID(), 'token' => $token));

		$emailTemplate = $this->mailer->createEMailTemplate('core.ResetPassword', [
			'link' => $link,
		]);

		if(empty($action) || $action === 'RESET') {
			$emailTemplate->setSubject($this->l10n->t('%s password reset', [$this->defaults->getName()]));
			$emailTemplate->addHeader();
			$emailTemplate->addHeading($this->l10n->t('Password reset'));

			$emailTemplate->addBodyText(
				htmlspecialchars($this->l10n->t('Click the following button to reset your password. If you have not requested the password reset, then ignore this email.')),
				$this->l10n->t('Click the following link to reset your password. If you have not requested the password reset, then ignore this email.')
			);

			$emailTemplate->addBodyButton(
				htmlspecialchars($this->l10n->t('Reset your password')),
				$link,
				false
			);
		} else if($action === 'NEW'){
			$emailTemplate->setSubject($this->l10n->t('%s activate and choose a password', [$this->defaults->getName()]));
			$emailTemplate->addHeader();
			$emailTemplate->addHeading($this->l10n->t('Activate and choose a password'));

			$emailTemplate->addBodyText(
				htmlspecialchars($this->l10n->t('Click the following button to activate and choose a new password. If you have not requested the new password, then ignore this email.')),
				$this->l10n->t('Click the following link to activate and choose a new password. If you have not requested the new password, then ignore this email.')
			);

			$emailTemplate->addBodyButton(
				htmlspecialchars($this->l10n->t('Activate and choose your new password')),
				$link,
				false
			);

		} else {
			throw new \Exception($this->l10n->t(
				'Couldn\'t send reset email. Please contact your administrator.'
			));
		}

			$emailTemplate->addFooter();
		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$email => $user->getUID()]);
			$message->setFrom([$this->from => $this->defaults->getName()]);
			$message->useTemplate($emailTemplate);
			$this->mailer->send($message);
		} catch (\Exception $e) {
			throw new \Exception($this->l10n->t(
				'Couldn\'t send reset email. Please contact your administrator.'
			));
		}
	}

	/**
	 * @param string $input
	 * @return IUser
	 * @throws \InvalidArgumentException
	 */
	protected function findUserByIdOrMail($input) {
		$userNotFound = new \InvalidArgumentException(
			$this->l10n->t('Couldn\'t send reset email. Please make sure your username is correct.')
		);

		$user = $this->userManager->get($input);
		if ($user instanceof IUser) {
			if (!$user->isEnabled()) {
				throw $userNotFound;
			}

			return $user;
		}

		$users = array_filter($this->userManager->getByEmail($input), function (IUser $user) {
			return $user->isEnabled();
		});

		if (count($users) === 1) {
			return reset($users);
		}

		throw $userNotFound;
	}

	/**
	 * Sets the password reset params.
	 *
	 * Users may not change their passwords if:
	 * - The account is disabled
	 * - The backend doesn't support password resets
	 * - The password reset function is disabled
	 *
	 * @param IUser $userObj
	 * @param array $parameters
	 * @return array
	 */
	protected function setPasswordResetParameters(
		IUser $userObj = null, array $parameters): array {

		if ($parameters['resetPasswordLink'] === 'disabled') {
			$parameters['canResetPassword'] = false;
		} else if (!$parameters['resetPasswordLink'] && $userObj !== null) {
			$parameters['canResetPassword'] = $userObj->canChangePassword();
		} else if ($userObj !== null && $userObj->isEnabled() === false) {
			$parameters['canResetPassword'] = false;
		} else {
			$parameters['canResetPassword'] = true;
		}

		return $parameters;
	}
}