<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Phil Davis <phil.davis@inf.org>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Activity;

use OCP\Activity\IEvent;
use OCP\RichObjectStrings\InvalidObjectExeption;
use OCP\RichObjectStrings\IValidator;

class Event implements IEvent {

	/** @var string */
	protected $app = '';
	/** @var string */
	protected $type = '';
	/** @var string */
	protected $affectedUser = '';
	/** @var string */
	protected $author = '';
	/** @var int */
	protected $timestamp = 0;
	/** @var string */
	protected $subject = '';
	/** @var array */
	protected $subjectParameters = [];
	/** @var string */
	protected $subjectParsed = '';
	/** @var string */
	protected $subjectRich = '';
	/** @var array */
	protected $subjectRichParameters = [];
	/** @var string */
	protected $message = '';
	/** @var array */
	protected $messageParameters = [];
	/** @var string */
	protected $messageParsed = '';
	/** @var string */
	protected $messageRich = '';
	/** @var array */
	protected $messageRichParameters = [];
	/** @var string */
	protected $objectType = '';
	/** @var int */
	protected $objectId = 0;
	/** @var string */
	protected $objectName = '';
	/** @var string */
	protected $link = '';
	/** @var string */
	protected $icon = '';
	/** @var bool */
	protected $generateNotification = true;

	/** @var IEvent|null */
	protected $child;
	/** @var IValidator */
	protected $richValidator;

	/**
	 * @param IValidator $richValidator
	 */
	public function __construct(IValidator $richValidator) {
		$this->richValidator = $richValidator;
	}

	/**
	 * Set the app of the activity
	 *
	 * @param string $app
	 * @return IEvent
	 * @throws \InvalidArgumentException if the app id is invalid
	 * @since 8.2.0
	 */
	public function setApp(string $app): IEvent {
		if ($app === '' || isset($app[32])) {
			throw new \InvalidArgumentException('The given app is invalid');
		}
		$this->app = $app;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getApp(): string {
		return $this->app;
	}

	/**
	 * Set the type of the activity
	 *
	 * @param string $type
	 * @return IEvent
	 * @throws \InvalidArgumentException if the type is invalid
	 * @since 8.2.0
	 */
	public function setType(string $type): IEvent {
		if ($type === '' || isset($type[255])) {
			throw new \InvalidArgumentException('The given type is invalid');
		}
		$this->type = $type;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * Set the affected user of the activity
	 *
	 * @param string $affectedUser
	 * @return IEvent
	 * @throws \InvalidArgumentException if the affected user is invalid
	 * @since 8.2.0
	 */
	public function setAffectedUser(string $affectedUser): IEvent {
		if ($affectedUser === '' || isset($affectedUser[64])) {
			throw new \InvalidArgumentException('The given affected user is invalid');
		}
		$this->affectedUser = $affectedUser;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAffectedUser(): string {
		return $this->affectedUser;
	}

	/**
	 * Set the author of the activity
	 *
	 * @param string $author
	 * @return IEvent
	 * @throws \InvalidArgumentException if the author is invalid
	 * @since 8.2.0
	 */
	public function setAuthor(string $author): IEvent {
		if (isset($author[64])) {
			throw new \InvalidArgumentException('The given author user is invalid');
		}
		$this->author = $author;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAuthor(): string {
		return $this->author;
	}

	/**
	 * Set the timestamp of the activity
	 *
	 * @param int $timestamp
	 * @return IEvent
	 * @throws \InvalidArgumentException if the timestamp is invalid
	 * @since 8.2.0
	 */
	public function setTimestamp(int $timestamp): IEvent {
		$this->timestamp = $timestamp;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTimestamp(): int {
		return $this->timestamp;
	}

	/**
	 * Set the subject of the activity
	 *
	 * @param string $subject
	 * @param array $parameters
	 * @return IEvent
	 * @throws \InvalidArgumentException if the subject or parameters are invalid
	 * @since 8.2.0
	 */
	public function setSubject(string $subject, array $parameters = []): IEvent {
		if (isset($subject[255])) {
			throw new \InvalidArgumentException('The given subject is invalid');
		}
		$this->subject = $subject;
		$this->subjectParameters = $parameters;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getSubject(): string {
		return $this->subject;
	}

	/**
	 * @return array
	 */
	public function getSubjectParameters(): array {
		return $this->subjectParameters;
	}

	/**
	 * @param string $subject
	 * @return $this
	 * @throws \InvalidArgumentException if the subject is invalid
	 * @since 11.0.0
	 */
	public function setParsedSubject(string $subject): IEvent {
		if ($subject === '') {
			throw new \InvalidArgumentException('The given parsed subject is invalid');
		}
		$this->subjectParsed = $subject;
		return $this;
	}

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getParsedSubject(): string {
		return $this->subjectParsed;
	}

	/**
	 * @param string $subject
	 * @param array $parameters
	 * @return $this
	 * @throws \InvalidArgumentException if the subject or parameters are invalid
	 * @since 11.0.0
	 */
	public function setRichSubject(string $subject, array $parameters = []): IEvent {
		if ($subject === '') {
			throw new \InvalidArgumentException('The given parsed subject is invalid');
		}
		$this->subjectRich = $subject;
		$this->subjectRichParameters = $parameters;

		return $this;
	}

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getRichSubject(): string {
		return $this->subjectRich;
	}

	/**
	 * @return array[]
	 * @since 11.0.0
	 */
	public function getRichSubjectParameters(): array {
		return $this->subjectRichParameters;
	}

	/**
	 * Set the message of the activity
	 *
	 * @param string $message
	 * @param array $parameters
	 * @return IEvent
	 * @throws \InvalidArgumentException if the message or parameters are invalid
	 * @since 8.2.0
	 */
	public function setMessage(string $message, array $parameters = []): IEvent {
		if (isset($message[255])) {
			throw new \InvalidArgumentException('The given message is invalid');
		}
		$this->message = $message;
		$this->messageParameters = $parameters;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMessage(): string {
		return $this->message;
	}

	/**
	 * @return array
	 */
	public function getMessageParameters(): array {
		return $this->messageParameters;
	}

	/**
	 * @param string $message
	 * @return $this
	 * @throws \InvalidArgumentException if the message is invalid
	 * @since 11.0.0
	 */
	public function setParsedMessage(string $message): IEvent {
		$this->messageParsed = $message;
		return $this;
	}

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getParsedMessage(): string {
		return $this->messageParsed;
	}

	/**
	 * @param string $message
	 * @param array $parameters
	 * @return $this
	 * @throws \InvalidArgumentException if the subject or parameters are invalid
	 * @since 11.0.0
	 */
	public function setRichMessage(string $message, array $parameters = []): IEvent {
		$this->messageRich = $message;
		$this->messageRichParameters = $parameters;

		return $this;
	}

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getRichMessage(): string {
		return $this->messageRich;
	}

	/**
	 * @return array[]
	 * @since 11.0.0
	 */
	public function getRichMessageParameters(): array {
		return $this->messageRichParameters;
	}

	/**
	 * Set the object of the activity
	 *
	 * @param string $objectType
	 * @param int $objectId
	 * @param string $objectName
	 * @return IEvent
	 * @throws \InvalidArgumentException if the object is invalid
	 * @since 8.2.0
	 */
	public function setObject(string $objectType, int $objectId, string $objectName = ''): IEvent {
		if (isset($objectType[255])) {
			throw new \InvalidArgumentException('The given object type is invalid');
		}
		if (isset($objectName[4000])) {
			throw new \InvalidArgumentException('The given object name is invalid');
		}
		$this->objectType = $objectType;
		$this->objectId = $objectId;
		$this->objectName = $objectName;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getObjectType(): string {
		return $this->objectType;
	}

	/**
	 * @return int
	 */
	public function getObjectId(): int {
		return $this->objectId;
	}

	/**
	 * @return string
	 */
	public function getObjectName(): string {
		return $this->objectName;
	}

	/**
	 * Set the link of the activity
	 *
	 * @param string $link
	 * @return IEvent
	 * @throws \InvalidArgumentException if the link is invalid
	 * @since 8.2.0
	 */
	public function setLink(string $link): IEvent {
		if (isset($link[4000])) {
			throw new \InvalidArgumentException('The given link is invalid');
		}
		$this->link = $link;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getLink(): string {
		return $this->link;
	}

	/**
	 * @param string $icon
	 * @return $this
	 * @throws \InvalidArgumentException if the icon is invalid
	 * @since 11.0.0
	 */
	public function setIcon(string $icon): IEvent {
		if (isset($icon[4000])) {
			throw new \InvalidArgumentException('The given icon is invalid');
		}
		$this->icon = $icon;
		return $this;
	}

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getIcon(): string {
		return $this->icon;
	}

	/**
	 * @param IEvent $child
	 * @return $this
	 * @since 11.0.0 - Since 15.0.0 returns $this
	 */
	public function setChildEvent(IEvent $child): IEvent {
		$this->child = $child;
		return $this;
	}

	/**
	 * @return IEvent|null
	 * @since 11.0.0
	 */
	public function getChildEvent() {
		return $this->child;
	}

	/**
	 * @return bool
	 * @since 8.2.0
	 */
	public function isValid(): bool {
		return
			$this->isValidCommon()
			&&
			$this->getSubject() !== ''
		;
	}

	/**
	 * @return bool
	 * @since 8.2.0
	 */
	public function isValidParsed(): bool {
		if ($this->getRichSubject() !== '' || !empty($this->getRichSubjectParameters())) {
			try {
				$this->richValidator->validate($this->getRichSubject(), $this->getRichSubjectParameters());
			} catch (InvalidObjectExeption $e) {
				return false;
			}
		}

		if ($this->getRichMessage() !== '' || !empty($this->getRichMessageParameters())) {
			try {
				$this->richValidator->validate($this->getRichMessage(), $this->getRichMessageParameters());
			} catch (InvalidObjectExeption $e) {
				return false;
			}
		}

		return
			$this->isValidCommon()
			&&
			$this->getParsedSubject() !== ''
		;
	}

	protected function isValidCommon(): bool {
		return
			$this->getApp() !== ''
			&&
			$this->getType() !== ''
			&&
			$this->getAffectedUser() !== ''
			&&
			$this->getTimestamp() !== 0
			/**
			 * Disabled for BC with old activities
			 * &&
			 * $this->getObjectType() !== ''
			 * &&
			 * $this->getObjectId() !== 0
			 */
		;
	}

	public function setGenerateNotification(bool $generate): IEvent {
		$this->generateNotification = $generate;
		return $this;
	}

	public function getGenerateNotification(): bool {
		return $this->generateNotification;
	}
}
