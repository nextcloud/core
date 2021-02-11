<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author zulan <git@zulan.net>
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

namespace OCA\Settings\Mailer;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

class NewUserMailHelper {
	/** @var Defaults */
	private $themingDefaults;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IFactory */
	private $l10nFactory;
	/** @var IMailer */
	private $mailer;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IConfig */
	private $config;
	/** @var ICrypto */
	private $crypto;
	/** @var string */
	private $fromAddress;

	/**
	 * @param Defaults $themingDefaults
	 * @param IURLGenerator $urlGenerator
	 * @param IFactory $l10nFactory
	 * @param IMailer $mailer
	 * @param ISecureRandom $secureRandom
	 * @param ITimeFactory $timeFactory
	 * @param IConfig $config
	 * @param ICrypto $crypto
	 * @param string $fromAddress
	 */
	public function __construct(Defaults $themingDefaults,
								IURLGenerator $urlGenerator,
								IFactory $l10nFactory,
								IMailer $mailer,
								ISecureRandom $secureRandom,
								ITimeFactory $timeFactory,
								IConfig $config,
								ICrypto $crypto,
								$fromAddress) {
		$this->themingDefaults = $themingDefaults;
		$this->urlGenerator = $urlGenerator;
		$this->l10nFactory = $l10nFactory;
		$this->mailer = $mailer;
		$this->secureRandom = $secureRandom;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->crypto = $crypto;
		$this->fromAddress = $fromAddress;
	}

	/**
	 * @param IUser $user
	 * @param bool $generatePasswordResetToken
	 * @return IEMailTemplate
	 */
	public function generateTemplate(IUser $user, $generatePasswordResetToken = false) {
		$userId = $user->getUID();
		$lang = $this->l10nFactory->getUserLanguage($user);
		$l10n = $this->l10nFactory->get('settings', $lang);

		if ($generatePasswordResetToken) {
			$token = $this->secureRandom->generate(
				21,
				ISecureRandom::CHAR_DIGITS .
				ISecureRandom::CHAR_LOWER .
				ISecureRandom::CHAR_UPPER
			);
			$tokenValue = $this->timeFactory->getTime() . ':' . $token;
			$mailAddress = (null !== $user->getEMailAddress()) ? $user->getEMailAddress() : '';
			$encryptedValue = $this->crypto->encrypt($tokenValue, $mailAddress . $this->config->getSystemValue('secret'));
			$this->config->setUserValue($user->getUID(), 'core', 'lostpassword', $encryptedValue);
			$link = $this->urlGenerator->linkToRouteAbsolute('core.lost.resetform', ['userId' => $user->getUID(), 'token' => $token]);
		} else {
			$link = $this->urlGenerator->getAbsoluteURL('/');
		}
		$displayName = $user->getDisplayName();

		$emailTemplate = $this->mailer->createEMailTemplate('settings.Welcome', [
			'link' => $link,
			'displayname' => $displayName,
			'userid' => $userId,
			'instancename' => $this->themingDefaults->getName(),
			'resetTokenGenerated' => $generatePasswordResetToken,
		]);

		$emailTemplate->setSubject($l10n->t('Your %s account was created', [$this->themingDefaults->getName()]));
		$emailTemplate->addHeader();
		if ($displayName === $userId) {
			$emailTemplate->addHeading($l10n->t('Welcome aboard'));
		} else {
			$emailTemplate->addHeading($l10n->t('Welcome aboard %s', [$displayName]));
		}
		$emailTemplate->addBodyText($l10n->t('Welcome to your %s account, you can add, protect, and share your data.', [$this->themingDefaults->getName()]));
		if ($user->getBackendClassName() !== 'LDAP') {
			$emailTemplate->addBodyText($l10n->t('Your username is: %s', [$userId]));
		}
		if ($generatePasswordResetToken) {
			$leftButtonText = $l10n->t('Set your password');
		} else {
			$leftButtonText = $l10n->t('Go to %s', [$this->themingDefaults->getName()]);
		}

		$clientDownload = $this->config->getSystemValue('customclient_desktop', 'https://nextcloud.com/install/#install-clients');
		if ($clientDownload === '') {
			$emailTemplate->addBodyButton(
				$leftButtonText,
				$link
			);
		} else {
			$emailTemplate->addBodyButtonGroup(
				$leftButtonText,
				$link,
				$l10n->t('Install Client'),
				$clientDownload
			);
		}

		$emailTemplate->addFooter('', $lang);

		return $emailTemplate;
	}

	/**
	 * Sends a welcome mail to $user
	 *
	 * @param IUser $user
	 * @param IEmailTemplate $emailTemplate
	 * @throws \Exception If mail could not be sent
	 */
	public function sendMail(IUser $user,
							 IEMailTemplate $emailTemplate) {
		$message = $this->mailer->createMessage();
		$message->setTo([$user->getEMailAddress() => $user->getDisplayName()]);
		$message->setFrom([$this->fromAddress => $this->themingDefaults->getName()]);
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}
}
