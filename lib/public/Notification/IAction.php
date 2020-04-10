<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * Interface IAction
 *
 * @package OCP\Notification
 * @since 9.0.0
 */
interface IAction {

	/**
	 * @since 17.0.0
	 */
	public const TYPE_GET = 'GET';
	/**
	 * @since 17.0.0
	 */
	public const TYPE_POST = 'POST';
	/**
	 * @since 17.0.0
	 */
	public const TYPE_PUT = 'PUT';
	/**
	 * @since 17.0.0
	 */
	public const TYPE_DELETE = 'DELETE';
	/**
	 * @since 17.0.0
	 */
	public const TYPE_WEB = 'WEB';

	/**
	 * @param string $label
	 * @return $this
	 * @throws \InvalidArgumentException if the label is invalid
	 * @since 9.0.0
	 */
	public function setLabel(string $label): IAction;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getLabel(): string;

	/**
	 * @param string $label
	 * @return $this
	 * @throws \InvalidArgumentException if the label is invalid
	 * @since 9.0.0
	 */
	public function setParsedLabel(string $label): IAction;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getParsedLabel(): string;

	/**
	 * @param bool $primary
	 * @return $this
	 * @throws \InvalidArgumentException if $primary is invalid
	 * @since 9.0.0
	 */
	public function setPrimary(bool $primary): IAction;

	/**
	 * @return bool
	 * @since 9.0.0
	 */
	public function isPrimary(): bool;

	/**
	 * @param string $link
	 * @param string $requestType
	 * @return $this
	 * @throws \InvalidArgumentException if the link is invalid
	 * @since 9.0.0
	 */
	public function setLink(string $link, string $requestType): IAction;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getLink(): string;

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getRequestType(): string;

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
