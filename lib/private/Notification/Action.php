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

namespace OC\Notification;

use OCP\Notification\IAction;

class Action implements IAction {

	/** @var string */
	protected $label;

	/** @var string */
	protected $labelParsed;

	/** @var string */
	protected $link;

	/** @var string */
	protected $requestType;

	/** @var string */
	protected $icon;

	/** @var bool */
	protected $primary;

	public function __construct() {
		$this->label = '';
		$this->labelParsed = '';
		$this->link = '';
		$this->requestType = '';
		$this->primary = false;
	}

	/**
	 * @param string $label
	 * @return $this
	 * @throws \InvalidArgumentException if the label is invalid
	 * @since 8.2.0
	 */
	public function setLabel(string $label): IAction {
		if ($label === '' || isset($label[32])) {
			throw new \InvalidArgumentException('The given label is invalid');
		}
		$this->label = $label;
		return $this;
	}

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * @param string $label
	 * @return $this
	 * @throws \InvalidArgumentException if the label is invalid
	 * @since 8.2.0
	 */
	public function setParsedLabel(string $label): IAction {
		if ($label === '') {
			throw new \InvalidArgumentException('The given parsed label is invalid');
		}
		$this->labelParsed = $label;
		return $this;
	}

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getParsedLabel(): string {
		return $this->labelParsed;
	}

	/**
	 * @param $primary bool
	 * @return $this
	 * @since 9.0.0
	 */
	public function setPrimary(bool $primary): IAction {
		$this->primary = $primary;
		return $this;
	}

	/**
	 * @return bool
	 * @since 9.0.0
	 */
	public function isPrimary(): bool {
		return $this->primary;
	}

	/**
	 * @param string $link
	 * @param string $requestType
	 * @return $this
	 * @throws \InvalidArgumentException if the link is invalid
	 * @since 8.2.0
	 */
	public function setLink(string $link, string $requestType): IAction {
		if ($link === '' || isset($link[256])) {
			throw new \InvalidArgumentException('The given link is invalid');
		}
		if (!in_array($requestType, [
			self::TYPE_GET,
			self::TYPE_POST,
			self::TYPE_PUT,
			self::TYPE_DELETE,
			self::TYPE_WEB,
		], true)) {
			throw new \InvalidArgumentException('The given request type is invalid');
		}
		$this->link = $link;
		$this->requestType = $requestType;
		return $this;
	}

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getLink(): string {
		return $this->link;
	}

	/**
	 * @return string
	 * @since 8.2.0
	 */
	public function getRequestType(): string {
		return $this->requestType;
	}

	/**
	 * @return bool
	 */
	public function isValid(): bool {
		return $this->label !== '' && $this->link !== '';
	}

	/**
	 * @return bool
	 */
	public function isValidParsed(): bool {
		return $this->labelParsed !== '' && $this->link !== '';
	}
}
