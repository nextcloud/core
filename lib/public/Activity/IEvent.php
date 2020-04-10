<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

/**
 * Public interface of ownCloud for apps to use.
 * Activity/IEvent interface
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP\Activity;

/**
 * Interface IEvent
 *
 * @package OCP\Activity
 * @since 8.2.0
 */
interface IEvent {
	/**
	 * Set the app of the activity
	 *
	 * @param string $app
	 * @return IEvent
	 * @throws \InvalidArgumentException if the app id is invalid
	 * @since 8.2.0
	 */
	public function setApp(string $app): self;

	/**
	 * Set the type of the activity
	 *
	 * @param string $type
	 * @return IEvent
	 * @throws \InvalidArgumentException if the type is invalid
	 * @since 8.2.0
	 */
	public function setType(string $type): self;

	/**
	 * Set the affected user of the activity
	 *
	 * @param string $user
	 * @return IEvent
	 * @throws \InvalidArgumentException if the affected user is invalid
	 * @since 8.2.0
	 */
	public function setAffectedUser(string $user): self;

	/**
	 * Set the author of the activity
	 *
	 * @param string $author
	 * @return IEvent
	 * @throws \InvalidArgumentException if the author is invalid
	 * @since 8.2.0
	 */
	public function setAuthor(string $author): self;

	/**
	 * Set the author of the activity
	 *
	 * @param int $timestamp
	 * @return IEvent
	 * @throws \InvalidArgumentException if the timestamp is invalid
	 * @since 8.2.0
	 */
	public function setTimestamp(int $timestamp): self;

	/**
	 * Set the subject of the activity
	 *
	 * @param string $subject
	 * @param array $parameters
	 * @return IEvent
	 * @throws \InvalidArgumentException if the subject or parameters are invalid
	 * @since 8.2.0
	 */
	public function setSubject(string $subject, array $parameters = []): self;

	/**
	 * Set a parsed subject
	 *
	 * HTML is not allowed in the parsed subject and will be escaped
	 * automatically by the clients. You can use the RichObjectString system
	 * provided by the Nextcloud server to highlight important parameters via
	 * the setRichSubject method, but make sure, that a plain text message is
	 * always set via setParsedSubject, to support clients which can not handle
	 * rich strings.
	 *
	 * See https://github.com/nextcloud/server/issues/1706 for more information.
	 *
	 * @param string $subject
	 * @return $this
	 * @throws \InvalidArgumentException if the subject is invalid
	 * @since 11.0.0
	 */
	public function setParsedSubject(string $subject): self;

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getParsedSubject(): string;

	/**
	 * Set a RichObjectString subject
	 *
	 * HTML is not allowed in the rich subject and will be escaped automatically
	 * by the clients, but you can use the RichObjectString system provided by
	 * the Nextcloud server to highlight important parameters.
	 * Also make sure, that a plain text subject is always set via
	 * setParsedSubject, to support clients which can not handle rich strings.
	 *
	 * See https://github.com/nextcloud/server/issues/1706 for more information.
	 *
	 * @param string $subject
	 * @param array $parameters
	 * @return $this
	 * @throws \InvalidArgumentException if the subject or parameters are invalid
	 * @since 11.0.0
	 */
	public function setRichSubject(string $subject, array $parameters = []): self;

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getRichSubject(): string;

	/**
	 * @return array[]
	 * @since 11.0.0
	 */
	public function getRichSubjectParameters(): array;

	/**
	 * Set the message of the activity
	 *
	 * @param string $message
	 * @param array $parameters
	 * @return IEvent
	 * @throws \InvalidArgumentException if the message or parameters are invalid
	 * @since 8.2.0
	 */
	public function setMessage(string $message, array $parameters = []): self;

	/**
	 * Set a parsed message
	 *
	 * HTML is not allowed in the parsed message and will be escaped
	 * automatically by the clients. You can use the RichObjectString system
	 * provided by the Nextcloud server to highlight important parameters via
	 * the setRichMessage method, but make sure, that a plain text message is
	 * always set via setParsedMessage, to support clients which can not handle
	 * rich strings.
	 *
	 * See https://github.com/nextcloud/server/issues/1706 for more information.
	 *
	 * @param string $message
	 * @return $this
	 * @throws \InvalidArgumentException if the message is invalid
	 * @since 11.0.0
	 */
	public function setParsedMessage(string $message): self;

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getParsedMessage(): string;

	/**
	 * Set a RichObjectString message
	 *
	 * HTML is not allowed in the rich message and will be escaped automatically
	 * by the clients, but you can use the RichObjectString system provided by
	 * the Nextcloud server to highlight important parameters.
	 * Also make sure, that a plain text message is always set via
	 * setParsedMessage, to support clients which can not handle rich strings.
	 *
	 * See https://github.com/nextcloud/server/issues/1706 for more information.
	 *
	 * @param string $message
	 * @param array $parameters
	 * @return $this
	 * @throws \InvalidArgumentException if the message or parameters are invalid
	 * @since 11.0.0
	 */
	public function setRichMessage(string $message, array $parameters = []): self;

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getRichMessage(): string;

	/**
	 * @return array[]
	 * @since 11.0.0
	 */
	public function getRichMessageParameters(): array;

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
	public function setObject(string $objectType, int $objectId, string $objectName = ''): self;

	/**
	 * Set the link of the activity
	 *
	 * @param string $link
	 * @return IEvent
	 * @throws \InvalidArgumentException if the link is invalid
	 * @since 8.2.0
	 */
	public function setLink(string $link): self;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getApp(): string;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getType(): string;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getAffectedUser(): string;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getAuthor(): string;

	/**
	 * @return int
	 * @since 8.2.0
	 */
	public function getTimestamp(): int;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getSubject(): string;

	/**
	 * @return array
	 * @since 8.2.0
	 */
	public function getSubjectParameters(): array;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getMessage(): string;

	/**
	 * @return array
	 * @since 8.2.0
	 */
	public function getMessageParameters(): array;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getObjectType(): string;

	/**
	 * @return int
	 * @since 8.2.0
	 */
	public function getObjectId(): int;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getObjectName(): string;

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getLink(): string;

	/**
	 * @param string $icon
	 * @return $this
	 * @throws \InvalidArgumentException if the icon is invalid
	 * @since 11.0.0
	 */
	public function setIcon(string $icon): self;

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getIcon(): string;

	/**
	 * @param IEvent $child
	 * @return $this
	 * @since 11.0.0 - Since 15.0.0 returns $this
	 */
	public function setChildEvent(IEvent $child): self;

	/**
	 * @return IEvent|null
	 * @since 11.0.0
	 */
	public function getChildEvent();

	/**
	 * @return bool
	 * @since 11.0.0
	 */
	public function isValid(): bool;

	/**
	 * @return bool
	 * @since 11.0.0
	 */
	public function isValidParsed(): bool;
}
