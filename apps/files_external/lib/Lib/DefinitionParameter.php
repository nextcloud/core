<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Lib;

/**
 * Parameter for an external storage definition
 */
class DefinitionParameter implements \JsonSerializable {
	// placeholder value for password fields, when the client updates a storage configuration
	// placeholder values are ignored and the field is left unmodified
	const UNMODIFIED_PLACEHOLDER = '__unmodified__';

	/** Value constants */
	const VALUE_TEXT = 0;
	const VALUE_BOOLEAN = 1;
	const VALUE_PASSWORD = 2;
	const VALUE_HIDDEN = 3;

	/** Flag constants */
	const FLAG_NONE = 0;
	const FLAG_OPTIONAL = 1;
	const FLAG_USER_PROVIDED = 2;

	/** @var string name of parameter */
	private $name;

	/** @var string human-readable parameter text */
	private $text;

	/** @var string human-readable parameter tooltip */
	private $tooltip = '';

	/** @var int value type, see self::VALUE_* constants */
	private $type = self::VALUE_TEXT;

	/** @var int flags, see self::FLAG_* constants */
	private $flags = self::FLAG_NONE;

	/**
	 * @param string $name
	 * @param string $text
	 */
	public function __construct($name, $text) {
		$this->name = $name;
		$this->text = $text;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getText() {
		return $this->text;
	}

	/**
	 * Get value type
	 *
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Set value type
	 *
	 * @param int $type
	 * @return self
	 */
	public function setType($type) {
		$this->type = $type;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTypeName() {
		switch ($this->type) {
			case self::VALUE_BOOLEAN:
				return 'boolean';
			case self::VALUE_TEXT:
				return 'text';
			case self::VALUE_PASSWORD:
				return 'password';
			default:
				return 'unknown';
		}
	}

	/**
	 * @return int
	 */
	public function getFlags() {
		return $this->flags;
	}

	/**
	 * @param int $flags
	 * @return self
	 */
	public function setFlags($flags) {
		$this->flags = $flags;
		return $this;
	}

	/**
	 * @param int $flag
	 * @return self
	 */
	public function setFlag($flag) {
		$this->flags |= $flag;
		return $this;
	}

	/**
	 * @param int $flag
	 * @return bool
	 */
	public function isFlagSet($flag) {
		return (bool)($this->flags & $flag);
	}

	/**
	 * @return string
	 */
	public function getTooltip(): string {
		return $this->tooltip;
	}

	/**
	 * @param string $tooltip
	 * @return self
	 */
	public function setTooltip(string $tooltip) {
		$this->tooltip = $tooltip;
		return $this;
	}

	/**
	 * Serialize into JSON for client-side JS
	 *
	 * @return string
	 */
	public function jsonSerialize() {
		return [
			'value' => $this->getText(),
			'flags' => $this->getFlags(),
			'type' => $this->getType(),
			'tooltip' => $this->getTooltip(),
		];
	}

	public function isOptional() {
		return $this->isFlagSet(self::FLAG_OPTIONAL) || $this->isFlagSet(self::FLAG_USER_PROVIDED);
	}

	/**
	 * Validate a parameter value against this
	 * Convert type as necessary
	 *
	 * @param mixed $value Value to check
	 * @return bool success
	 */
	public function validateValue(&$value) {
		switch ($this->getType()) {
			case self::VALUE_BOOLEAN:
				if (!is_bool($value)) {
					switch ($value) {
						case 'true':
							$value = true;
							break;
						case 'false':
							$value = false;
							break;
						default:
							return false;
					}
				}
				break;
			default:
				if (!$value && !$this->isOptional()) {
					return false;
				}
				break;
		}
		return true;
	}
}
