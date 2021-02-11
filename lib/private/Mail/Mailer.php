<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arne Hamann <kontakt+github@arne.email>
 * @author Branko Kokanovic <branko@kokanovic.org>
 * @author Carsten Wiedmann <carsten_sttgt@gmx.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jared Boone <jared.boone@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author kevin147147 <kevintamool@gmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tekhnee <info@tekhnee.org>
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

namespace OC\Mail;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Mail\IAttachment;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Mail\Events\BeforeMessageSent;

/**
 * Class Mailer provides some basic functions to create a mail message that can be used in combination with
 * \OC\Mail\Message.
 *
 * Example usage:
 *
 * 	$mailer = \OC::$server->getMailer();
 * 	$message = $mailer->createMessage();
 * 	$message->setSubject('Your Subject');
 * 	$message->setFrom(array('cloud@domain.org' => 'ownCloud Notifier'));
 * 	$message->setTo(array('recipient@domain.org' => 'Recipient'));
 * 	$message->setBody('The message text', 'text/html');
 * 	$mailer->send($message);
 *
 * This message can then be passed to send() of \OC\Mail\Mailer
 *
 * @package OC\Mail
 */
class Mailer implements IMailer {
	/** @var \Swift_Mailer Cached mailer */
	private $instance = null;
	/** @var IConfig */
	private $config;
	/** @var ILogger */
	private $logger;
	/** @var Defaults */
	private $defaults;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IL10N */
	private $l10n;
	/** @var IEventDispatcher */
	private $dispatcher;
	/** @var IFactory */
	private $l10nFactory;

	/**
	 * @param IConfig $config
	 * @param ILogger $logger
	 * @param Defaults $defaults
	 * @param IURLGenerator $urlGenerator
	 * @param IL10N $l10n
	 * @param IEventDispatcher $dispatcher
	 */
	public function __construct(IConfig $config,
						 ILogger $logger,
						 Defaults $defaults,
						 IURLGenerator $urlGenerator,
						 IL10N $l10n,
						 IEventDispatcher $dispatcher,
						 IFactory $l10nFactory) {
		$this->config = $config;
		$this->logger = $logger;
		$this->defaults = $defaults;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->dispatcher = $dispatcher;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * Creates a new message object that can be passed to send()
	 *
	 * @return IMessage
	 */
	public function createMessage(): IMessage {
		$plainTextOnly = $this->config->getSystemValue('mail_send_plaintext_only', false);
		return new Message(new \Swift_Message(), $plainTextOnly);
	}

	/**
	 * @param string|null $data
	 * @param string|null $filename
	 * @param string|null $contentType
	 * @return IAttachment
	 * @since 13.0.0
	 */
	public function createAttachment($data = null, $filename = null, $contentType = null): IAttachment {
		return new Attachment(new \Swift_Attachment($data, $filename, $contentType));
	}

	/**
	 * @param string $path
	 * @param string|null $contentType
	 * @return IAttachment
	 * @since 13.0.0
	 */
	public function createAttachmentFromPath(string $path, $contentType = null): IAttachment {
		return new Attachment(\Swift_Attachment::fromPath($path, $contentType));
	}

	/**
	 * Creates a new email template object
	 *
	 * @param string $emailId
	 * @param array $data
	 * @return IEMailTemplate
	 * @since 12.0.0
	 */
	public function createEMailTemplate(string $emailId, array $data = []): IEMailTemplate {
		$class = $this->config->getSystemValue('mail_template_class', '');

		if ($class !== '' && class_exists($class) && is_a($class, EMailTemplate::class, true)) {
			return new $class(
				$this->defaults,
				$this->urlGenerator,
				$this->l10nFactory,
				$emailId,
				$data
			);
		}

		return new EMailTemplate(
			$this->defaults,
			$this->urlGenerator,
			$this->l10nFactory,
			$emailId,
			$data
		);
	}

	/**
	 * Send the specified message. Also sets the from address to the value defined in config.php
	 * if no-one has been passed.
	 *
	 * @param IMessage|Message $message Message to send
	 * @return string[] Array with failed recipients. Be aware that this depends on the used mail backend and
	 * therefore should be considered
	 * @throws \Exception In case it was not possible to send the message. (for example if an invalid mail address
	 * has been supplied.)
	 */
	public function send(IMessage $message): array {
		$debugMode = $this->config->getSystemValue('mail_smtpdebug', false);

		if (empty($message->getFrom())) {
			$message->setFrom([\OCP\Util::getDefaultEmailAddress('no-reply') => $this->defaults->getName()]);
		}

		$failedRecipients = [];

		$mailer = $this->getInstance();

		// Enable logger if debug mode is enabled
		if ($debugMode) {
			$mailLogger = new \Swift_Plugins_Loggers_ArrayLogger();
			$mailer->registerPlugin(new \Swift_Plugins_LoggerPlugin($mailLogger));
		}


		$this->dispatcher->dispatchTyped(new BeforeMessageSent($message));

		$mailer->send($message->getSwiftMessage(), $failedRecipients);

		// Debugging logging
		$logMessage = sprintf('Sent mail to "%s" with subject "%s"', print_r($message->getTo(), true), $message->getSubject());
		$this->logger->debug($logMessage, ['app' => 'core']);
		if ($debugMode && isset($mailLogger)) {
			$this->logger->debug($mailLogger->dump(), ['app' => 'core']);
		}

		return $failedRecipients;
	}

	/**
	 * Checks if an e-mail address is valid
	 *
	 * @param string $email Email address to be validated
	 * @return bool True if the mail address is valid, false otherwise
	 */
	public function validateMailAddress(string $email): bool {
		$validator = new EmailValidator();
		$validation = new RFCValidation();

		return $validator->isValid($this->convertEmail($email), $validation);
	}

	/**
	 * SwiftMailer does currently not work with IDN domains, this function therefore converts the domains
	 *
	 * FIXME: Remove this once SwiftMailer supports IDN
	 *
	 * @param string $email
	 * @return string Converted mail address if `idn_to_ascii` exists
	 */
	protected function convertEmail(string $email): string {
		if (!function_exists('idn_to_ascii') || !defined('INTL_IDNA_VARIANT_UTS46') || strpos($email, '@') === false) {
			return $email;
		}

		list($name, $domain) = explode('@', $email, 2);
		$domain = idn_to_ascii($domain, 0,INTL_IDNA_VARIANT_UTS46);
		return $name.'@'.$domain;
	}

	protected function getInstance(): \Swift_Mailer {
		if (!is_null($this->instance)) {
			return $this->instance;
		}

		$transport = null;

		switch ($this->config->getSystemValue('mail_smtpmode', 'smtp')) {
			case 'sendmail':
				$transport = $this->getSendMailInstance();
				break;
			case 'smtp':
			default:
				$transport = $this->getSmtpInstance();
				break;
		}

		return new \Swift_Mailer($transport);
	}

	/**
	 * Returns the SMTP transport
	 *
	 * @return \Swift_SmtpTransport
	 */
	protected function getSmtpInstance(): \Swift_SmtpTransport {
		$transport = new \Swift_SmtpTransport();
		$transport->setTimeout($this->config->getSystemValue('mail_smtptimeout', 10));
		$transport->setHost($this->config->getSystemValue('mail_smtphost', '127.0.0.1'));
		$transport->setPort($this->config->getSystemValue('mail_smtpport', 25));
		if ($this->config->getSystemValue('mail_smtpauth', false)) {
			$transport->setUsername($this->config->getSystemValue('mail_smtpname', ''));
			$transport->setPassword($this->config->getSystemValue('mail_smtppassword', ''));
			$transport->setAuthMode($this->config->getSystemValue('mail_smtpauthtype', 'LOGIN'));
		}
		$smtpSecurity = $this->config->getSystemValue('mail_smtpsecure', '');
		if (!empty($smtpSecurity)) {
			$transport->setEncryption($smtpSecurity);
		}
		$streamingOptions = $this->config->getSystemValue('mail_smtpstreamoptions', []);
		if (is_array($streamingOptions) && !empty($streamingOptions)) {
			$transport->setStreamOptions($streamingOptions);
		}

		return $transport;
	}

	/**
	 * Returns the sendmail transport
	 *
	 * @return \Swift_SendmailTransport
	 */
	protected function getSendMailInstance(): \Swift_SendmailTransport {
		switch ($this->config->getSystemValue('mail_smtpmode', 'smtp')) {
			case 'qmail':
				$binaryPath = '/var/qmail/bin/sendmail';
				break;
			default:
				$sendmail = \OC_Helper::findBinaryPath('sendmail');
				if ($sendmail === null) {
					$sendmail = '/usr/sbin/sendmail';
				}
				$binaryPath = $sendmail;
				break;
		}

		switch ($this->config->getSystemValue('mail_sendmailmode', 'smtp')) {
			case 'pipe':
				$binaryParam = ' -t';
				break;
			default:
				$binaryParam = ' -bs';
				break;
		}

		return new \Swift_SendmailTransport($binaryPath . $binaryParam);
	}
}
