<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Authentication\TwoFactorAuth;

use JsonSerializable;

class EnforcementState implements JsonSerializable {

	/** @var bool */
	private $enforced;

	/** @var array */
	private $enforcedGroups;

	/** @var array */
	private $excludedGroups;

	/**
	 * EnforcementState constructor.
	 *
	 * @param bool $enforced
	 * @param string[] $enforcedGroups
	 * @param string[] $excludedGroups
	 */
	public function __construct(bool $enforced,
								array $enforcedGroups = [],
								array $excludedGroups = []) {
		$this->enforced = $enforced;
		$this->enforcedGroups = $enforcedGroups;
		$this->excludedGroups = $excludedGroups;
	}

	/**
	 * @return bool
	 */
	public function isEnforced(): bool {
		return $this->enforced;
	}

	/**
	 * @return string[]
	 */
	public function getEnforcedGroups(): array {
		return $this->enforcedGroups;
	}

	/**
	 * @return string[]
	 */
	public function getExcludedGroups(): array {
		return $this->excludedGroups;
	}

	public function jsonSerialize(): array {
		return [
			'enforced' => $this->enforced,
			'enforcedGroups' => $this->enforcedGroups,
			'excludedGroups' => $this->excludedGroups,
		];
	}

}
