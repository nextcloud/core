<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace OCP\Notification;

/**
 * Interface INotification
 *
 * @since 9.0.0
 */
interface INotification {
	/**
	 * @param string $app
	 * @return $this
	 * @throws \InvalidArgumentException if the app id is invalid
	 * @since 9.0.0
	 */
	public function setApp(string $app): INotification;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getApp(): string;

	/**
	 * @param string $user
	 * @return $this
	 * @throws \InvalidArgumentException if the user id is invalid
	 * @since 9.0.0
	 */
	public function setUser(string $user): INotification;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getUser(): string;

	/**
	 * @param \DateTime $dateTime
	 * @return $this
	 * @throws \InvalidArgumentException if the $dateTime is invalid
	 * @since 9.0.0
	 */
	public function setDateTime(\DateTime $dateTime): INotification;

	/**
	 * @return \DateTime
	 * @since 9.0.0
	 */
	public function getDateTime(): \DateTime;

	/**
	 * @param string $type
	 * @param string $id
	 * @return $this
	 * @throws \InvalidArgumentException if the object type or id is invalid
	 * @since 9.0.0
	 */
	public function setObject(string $type, string $id): INotification;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectType(): string;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectId(): string;

	/**
	 * @param string $subject
	 * @param array $parameters
	 * @return $this
	 * @throws \InvalidArgumentException if the subject or parameters are invalid
	 * @since 9.0.0
	 */
	public function setSubject(string $subject, array $parameters = []): INotification;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getSubject(): string;

	/**
	 * @return array
	 * @since 9.0.0
	 */
	public function getSubjectParameters(): array;

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
	 * @since 9.0.0
	 */
	public function setParsedSubject(string $subject): INotification;

	/**
	 * @return string
	 * @since 9.0.0
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
	public function setRichSubject(string $subject, array $parameters = []): INotification;

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
	 * @param string $message
	 * @param array $parameters
	 * @return $this
	 * @throws \InvalidArgumentException if the message or parameters are invalid
	 * @since 9.0.0
	 */
	public function setMessage(string $message, array $parameters = []): INotification;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getMessage(): string;

	/**
	 * @return array
	 * @since 9.0.0
	 */
	public function getMessageParameters(): array;

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
	 * @since 9.0.0
	 */
	public function setParsedMessage(string $message): INotification;

	/**
	 * @return string
	 * @since 9.0.0
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
	public function setRichMessage(string $message, array $parameters = []): INotification;

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
	 * @param string $link
	 * @return $this
	 * @throws \InvalidArgumentException if the link is invalid
	 * @since 9.0.0
	 */
	public function setLink(string $link): INotification;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getLink(): string;

	/**
	 * @param string $icon
	 * @return $this
	 * @throws \InvalidArgumentException if the icon is invalid
	 * @since 11.0.0
	 */
	public function setIcon(string $icon): INotification;

	/**
	 * @return string
	 * @since 11.0.0
	 */
	public function getIcon(): string;

	/**
	 * @return IAction
	 * @since 9.0.0
	 */
	public function createAction(): IAction;

	/**
	 * @param IAction $action
	 * @return $this
	 * @throws \InvalidArgumentException if the action is invalid
	 * @since 9.0.0
	 */
	public function addAction(IAction $action): INotification;

	/**
	 * @return IAction[]
	 * @since 9.0.0
	 */
	public function getActions(): array;

	/**
	 * @param IAction $action
	 * @return $this
	 * @throws \InvalidArgumentException if the action is invalid
	 * @since 9.0.0
	 */
	public function addParsedAction(IAction $action): INotification;

	/**
	 * @return IAction[]
	 * @since 9.0.0
	 */
	public function getParsedActions(): array;

	/**
	 * @return bool
	 * @since 9.0.0
	 */
	public function isValid(): bool;

	/**
	 * @return bool
	 * @since 9.0.0
	 */
	public function isValidParsed(): bool;
}
