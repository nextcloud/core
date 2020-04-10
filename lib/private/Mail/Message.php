<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arne Hamann <kontakt+github@arne.email>
 * @author Jared Boone <jared.boone@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OCP\Mail\IAttachment;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMessage;
use Swift_Message;

/**
 * Class Message provides a wrapper around SwiftMail
 *
 * @package OC\Mail
 */
class Message implements IMessage {
	/** @var Swift_Message */
	private $swiftMessage;
	/** @var bool */
	private $plainTextOnly;

	public function __construct(Swift_Message $swiftMessage, bool $plainTextOnly) {
		$this->swiftMessage = $swiftMessage;
		$this->plainTextOnly = $plainTextOnly;
	}

	/**
	 * @param IAttachment $attachment
	 * @return $this
	 * @since 13.0.0
	 */
	public function attach(IAttachment $attachment): IMessage {
		/** @var Attachment $attachment */
		$this->swiftMessage->attach($attachment->getSwiftAttachment());
		return $this;
	}

	/**
	 * SwiftMailer does currently not work with IDN domains, this function therefore converts the domains
	 * FIXME: Remove this once SwiftMailer supports IDN
	 *
	 * @param array $addresses Array of mail addresses, key will get converted
	 * @return array Converted addresses if `idn_to_ascii` exists
	 */
	protected function convertAddresses(array $addresses): array {
		if (!function_exists('idn_to_ascii') || !defined('INTL_IDNA_VARIANT_UTS46')) {
			return $addresses;
		}

		$convertedAddresses = [];

		foreach($addresses as $email => $readableName) {
			if(!is_numeric($email)) {
				list($name, $domain) = explode('@', $email, 2);
				$domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
				$convertedAddresses[$name.'@'.$domain] = $readableName;
			} else {
				list($name, $domain) = explode('@', $readableName, 2);
				$domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
				$convertedAddresses[$email] = $name.'@'.$domain;
			}
		}

		return $convertedAddresses;
	}

	/**
	 * Set the from address of this message.
	 *
	 * If no "From" address is used \OC\Mail\Mailer will use mail_from_address and mail_domain from config.php
	 *
	 * @param array $addresses Example: array('sender@domain.org', 'other@domain.org' => 'A name')
	 * @return $this
	 */
	public function setFrom(array $addresses): IMessage {
		$addresses = $this->convertAddresses($addresses);

		$this->swiftMessage->setFrom($addresses);
		return $this;
	}

	/**
	 * Get the from address of this message.
	 *
	 * @return array
	 */
	public function getFrom(): array {
		return $this->swiftMessage->getFrom() ?? [];
	}

	/**
	 * Set the Reply-To address of this message
	 *
	 * @param array $addresses
	 * @return $this
	 */
	public function setReplyTo(array $addresses): IMessage {
		$addresses = $this->convertAddresses($addresses);

		$this->swiftMessage->setReplyTo($addresses);
		return $this;
	}

	/**
	 * Returns the Reply-To address of this message
	 *
	 * @return string
	 */
	public function getReplyTo(): string {
		return $this->swiftMessage->getReplyTo();
	}

	/**
	 * Set the to addresses of this message.
	 *
	 * @param array $recipients Example: array('recipient@domain.org', 'other@domain.org' => 'A name')
	 * @return $this
	 */
	public function setTo(array $recipients): IMessage {
		$recipients = $this->convertAddresses($recipients);

		$this->swiftMessage->setTo($recipients);
		return $this;
	}

	/**
	 * Get the to address of this message.
	 *
	 * @return array
	 */
	public function getTo(): array {
		return $this->swiftMessage->getTo() ?? [];
	}

	/**
	 * Set the CC recipients of this message.
	 *
	 * @param array $recipients Example: array('recipient@domain.org', 'other@domain.org' => 'A name')
	 * @return $this
	 */
	public function setCc(array $recipients): IMessage {
		$recipients = $this->convertAddresses($recipients);

		$this->swiftMessage->setCc($recipients);
		return $this;
	}

	/**
	 * Get the cc address of this message.
	 *
	 * @return array
	 */
	public function getCc(): array {
		return $this->swiftMessage->getCc() ?? [];
	}

	/**
	 * Set the BCC recipients of this message.
	 *
	 * @param array $recipients Example: array('recipient@domain.org', 'other@domain.org' => 'A name')
	 * @return $this
	 */
	public function setBcc(array $recipients): IMessage {
		$recipients = $this->convertAddresses($recipients);

		$this->swiftMessage->setBcc($recipients);
		return $this;
	}

	/**
	 * Get the Bcc address of this message.
	 *
	 * @return array
	 */
	public function getBcc(): array {
		return $this->swiftMessage->getBcc() ?? [];
	}

	/**
	 * Set the subject of this message.
	 *
	 * @param string $subject
	 * @return IMessage
	 */
	public function setSubject(string $subject): IMessage {
		$this->swiftMessage->setSubject($subject);
		return $this;
	}

	/**
	 * Get the from subject of this message.
	 *
	 * @return string
	 */
	public function getSubject(): string {
		return $this->swiftMessage->getSubject();
	}

	/**
	 * Set the plain-text body of this message.
	 *
	 * @param string $body
	 * @return $this
	 */
	public function setPlainBody(string $body): IMessage {
		$this->swiftMessage->setBody($body);
		return $this;
	}

	/**
	 * Get the plain body of this message.
	 *
	 * @return string
	 */
	public function getPlainBody(): string {
		return $this->swiftMessage->getBody();
	}

	/**
	 * Set the HTML body of this message. Consider also sending a plain-text body instead of only an HTML one.
	 *
	 * @param string $body
	 * @return $this
	 */
	public function setHtmlBody($body) {
		if (!$this->plainTextOnly) {
			$this->swiftMessage->addPart($body, 'text/html');
		}
		return $this;
	}

	/**
	 * Get's the underlying SwiftMessage
	 * @param Swift_Message $swiftMessage
	 */
	public function setSwiftMessage(Swift_Message $swiftMessage): void {
		$this->swiftMessage = $swiftMessage;
	}

	/**
	 * Get's the underlying SwiftMessage
	 * @return Swift_Message
	 */
	public function getSwiftMessage(): Swift_Message {
		return $this->swiftMessage;
	}

	/**
	 * @param string $body
	 * @param string $contentType
	 * @return $this
	 */
	public function setBody($body, $contentType) {
		if (!$this->plainTextOnly || $contentType !== 'text/html') {
			$this->swiftMessage->setBody($body, $contentType);
		}
		return $this;
	}

	/**
	 * @param IEMailTemplate $emailTemplate
	 * @return $this
	 */
	public function useTemplate(IEMailTemplate $emailTemplate): IMessage {
		$this->setSubject($emailTemplate->renderSubject());
		$this->setPlainBody($emailTemplate->renderText());
		if (!$this->plainTextOnly) {
			$this->setHtmlBody($emailTemplate->renderHtml());
		}
		return $this;
	}
}
