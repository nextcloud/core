<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Rémy Jacquin <remy@remyj.fr>
 * @author Robin Appelman <robin@icewind.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Core\Controller;

use function array_filter;
use function count;
use OC\Authentication\TwoFactorAuth\Manager;
use OC\Core\Exception\ResetPasswordException;
use OC\HintException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\Encryption\IEncryptionModule;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
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
	/** @var IInitialStateService */
	private $initialStateService;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IURLGenerator $urlGenerator
	 * @param IUserManager $userManager
	 * @param Defaults $defaults
	 * @param IL10N $l10n
	 * @param IConfig $config
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
								ISecureRandom $secureRandom,
								$defaultMailAddress,
								IManager $encryptionManager,
								IMailer $mailer,
								ITimeFactory $timeFactory,
								ICrypto $crypto,
								ILogger $logger,
								Manager $twoFactorManager,
								IInitialStateService $initialStateService) {
		parent::__construct($appName, $request);
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->defaults = $defaults;
		$this->l10n = $l10n;
		$this->secureRandom = $secureRandom;
		$this->from = $defaultMailAddress;
		$this->encryptionManager = $encryptionManager;
		$this->config = $config;
		$this->mailer = $mailer;
		$this->timeFactory = $timeFactory;
		$this->crypto = $crypto;
		$this->logger = $logger;
		$this->twoFactorManager = $twoFactorManager;
		$this->initialStateService = $initialStateService;
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
					"errors" => [["error" => $e->getMessage()]]
				],
				'guest'
			);
		}
		$this->initialStateService->provideInitialState('core', 'resetPasswordUser', $userId);
		$this->initialStateService->provideInitialState('core', 'resetPasswordTarget',
			$this->urlGenerator->linkToRouteAbsolute('core.lost.setPassword', ['userId' => $userId, 'token' => $token])
		);

		return new TemplateResponse(
			'core',
			'login',
			[],
			'guest'
		);
	}

	/**
	 * @param string $token
	 * @param string $userId
	 * @throws \Exception
	 */
	protected function checkPasswordResetToken($token, $userId) {
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled()) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is invalid'));
		}

		$encryptedToken = $this->config->getUserValue($userId, 'core', 'lostpassword', null);
		if ($encryptedToken === null) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is invalid'));
		}

		try {
			$mailAddress = !is_null($user->getEMailAddress()) ? $user->getEMailAddress() : '';
			$decryptedToken = $this->crypto->decrypt($encryptedToken, $mailAddress.$this->config->getSystemValue('secret'));
		} catch (\Exception $e) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is invalid'));
		}

		$splittedToken = explode(':', $decryptedToken);
		if (count($splittedToken) !== 2) {
			throw new \Exception($this->l10n->t('Couldn\'t reset password because the token is invalid'));
		}

		if ($splittedToken[0] < ($this->timeFactory->getTime() - 60 * 60 * 24 * 7) ||
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
	private function error($message, array $additional = []) {
		return array_merge(['status' => 'error', 'msg' => $message], $additional);
	}

	/**
	 * @param array $data
	 * @return array
	 */
	private function success($data = []) {
		return array_merge($data, ['status' => 'success']);
	}

	/**
	 * @PublicPage
	 * @BruteForceProtection(action=passwordResetEmail)
	 * @AnonRateThrottle(limit=10, period=300)
	 *
	 * @param string $user
	 * @return JSONResponse
	 */
	public function email($user) {
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
			$this->sendEmail($user);
		} catch (ResetPasswordException $e) {
			// Ignore the error since we do not want to leak this info
			$this->logger->warning('Could not send password reset email: ' . $e->getMessage());
		} catch (\Exception $e) {
			$this->logger->logException($e);
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
					return $this->error('', ['encryption' => true]);
				}
			}
		}

		try {
			$this->checkPasswordResetToken($token, $userId);
			$user = $this->userManager->get($userId);

			\OC_Hook::emit('\OC\Core\LostPassword\Controller\LostController', 'pre_passwordReset', ['uid' => $userId, 'password' => $password]);

			if (!$user->setPassword($password)) {
				throw new \Exception();
			}

			\OC_Hook::emit('\OC\Core\LostPassword\Controller\LostController', 'post_passwordReset', ['uid' => $userId, 'password' => $password]);

			$this->twoFactorManager->clearTwoFactorPending($userId);

			$this->config->deleteUserValue($userId, 'core', 'lostpassword');
			@\OC::$server->getUserSession()->unsetMagicInCookie();
		} catch (HintException $e) {
			return $this->error($e->getHint());
		} catch (\Exception $e) {
			return $this->error($e->getMessage());
		}

		return $this->success(['user' => $userId]);
	}

	/**
	 * @param string $input
	 * @throws ResetPasswordException
	 * @throws \OCP\PreConditionNotMetException
	 */
	protected function sendEmail($input) {
		$user = $this->findUserByIdOrMail($input);
		$email = $user->getEMailAddress();

		if (empty($email)) {
			throw new ResetPasswordException('Could not send reset e-mail since there is no email for username ' . $input);
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

		$link = $this->urlGenerator->linkToRouteAbsolute('core.lost.resetform', ['userId' => $user->getUID(), 'token' => $token]);

		$emailTemplate = $this->mailer->createEMailTemplate('core.ResetPassword', [
			'link' => $link,
		]);

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
		$emailTemplate->addFooter();

		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$email => $user->getUID()]);
			$message->setFrom([$this->from => $this->defaults->getName()]);
			$message->useTemplate($emailTemplate);
			$this->mailer->send($message);
		} catch (\Exception $e) {
			// Log the exception and continue
			$this->logger->logException($e);
		}
	}

	/**
	 * @param string $input
	 * @return IUser
	 * @throws ResetPasswordException
	 */
	protected function findUserByIdOrMail($input) {
		$user = $this->userManager->get($input);
		if ($user instanceof IUser) {
			if (!$user->isEnabled()) {
				throw new ResetPasswordException('User is disabled');
			}

			return $user;
		}

		$users = array_filter($this->userManager->getByEmail($input), function (IUser $user) {
			return $user->isEnabled();
		});

		if (count($users) === 1) {
			return reset($users);
		}

		throw new ResetPasswordException('Could not find user');
	}
}
