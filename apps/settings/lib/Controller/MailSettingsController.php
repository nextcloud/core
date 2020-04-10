<?php
/**
 * @copyright Copyright (c) 2017  Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OCA\Settings\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Mail\IMailer;

class MailSettingsController extends Controller {

	/** @var IL10N */
	private $l10n;
	/** @var IConfig */
	private $config;
	/** @var IUserSession */
	private $userSession;
	/** @var IMailer */
	private $mailer;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param IUserSession $userSession
	 * @param IMailer $mailer
	 */
	public function __construct($appName,
								IRequest $request,
								IL10N $l10n,
								IConfig $config,
								IUserSession $userSession,
								IMailer $mailer) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->config = $config;
		$this->userSession = $userSession;
		$this->mailer = $mailer;
	}

	/**
	 * Sets the email settings
	 *
	 * @PasswordConfirmationRequired
	 *
	 * @param string $mail_domain
	 * @param string $mail_from_address
	 * @param string $mail_smtpmode
	 * @param string $mail_smtpsecure
	 * @param string $mail_smtphost
	 * @param string $mail_smtpauthtype
	 * @param int $mail_smtpauth
	 * @param string $mail_smtpport
	 * @return DataResponse
	 */
	public function setMailSettings($mail_domain,
									$mail_from_address,
									$mail_smtpmode,
									$mail_smtpsecure,
									$mail_smtphost,
									$mail_smtpauthtype,
									$mail_smtpauth,
									$mail_smtpport,
									$mail_sendmailmode) {

		$params = get_defined_vars();
		$configs = [];
		foreach($params as $key => $value) {
			$configs[$key] = empty($value) ? null : $value;
		}

		// Delete passwords from config in case no auth is specified
		if ($params['mail_smtpauth'] !== 1) {
			$configs['mail_smtpname'] = null;
			$configs['mail_smtppassword'] = null;
		}

		$this->config->setSystemValues($configs);

		return new DataResponse();
	}

	/**
	 * Store the credentials used for SMTP in the config
	 *
	 * @PasswordConfirmationRequired
	 *
	 * @param string $mail_smtpname
	 * @param string $mail_smtppassword
	 * @return DataResponse
	 */
	public function storeCredentials($mail_smtpname, $mail_smtppassword) {
		if ($mail_smtppassword === '********') {
			return new DataResponse($this->l10n->t('Invalid SMTP password.'), Http::STATUS_BAD_REQUEST);
		}

		$this->config->setSystemValues([
			'mail_smtpname'		=> $mail_smtpname,
			'mail_smtppassword'	=> $mail_smtppassword,
		]);

		return new DataResponse();
	}

	/**
	 * Send a mail to test the settings
	 * @return DataResponse
	 */
	public function sendTestMail() {
		$email = $this->config->getUserValue($this->userSession->getUser()->getUID(), $this->appName, 'email', '');
		if (!empty($email)) {
			try {
				$displayName = $this->userSession->getUser()->getDisplayName();

				$template = $this->mailer->createEMailTemplate('settings.TestEmail', [
					'displayname' => $displayName,
				]);

				$template->setSubject($this->l10n->t('Email setting test'));
				$template->addHeader();
				$template->addHeading($this->l10n->t('Well done, %s!', [$displayName]));
				$template->addBodyText($this->l10n->t('If you received this email, the email configuration seems to be correct.'));
				$template->addFooter();

				$message = $this->mailer->createMessage();
				$message->setTo([$email => $displayName]);
				$message->useTemplate($template);
				$errors = $this->mailer->send($message);
				if (!empty($errors)) {
					throw new \RuntimeException($this->l10n->t('Email could not be sent. Check your mail server log'));
				}
				return new DataResponse();
			} catch (\Exception $e) {
				return new DataResponse($this->l10n->t('A problem occurred while sending the email. Please revise your settings. (Error: %s)', [$e->getMessage()]), Http::STATUS_BAD_REQUEST);
			}
		}

		return new DataResponse($this->l10n->t('You need to set your user email before being able to send test emails.'), Http::STATUS_BAD_REQUEST);
	}

}
