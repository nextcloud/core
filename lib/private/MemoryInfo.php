<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Michael Weimann (<mail@michael-weimann.eu>)
 *
 * @author Michael Weimann <mail@michael-weimann.eu>
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

namespace OC;

/**
 * Helper class that covers memory info.
 */
class MemoryInfo {

	const RECOMMENDED_MEMORY_LIMIT = 512 * 1024 * 1024;

	/**
	 * Tests if the memory limit is greater or equal the recommended value.
	 *
	 * @return bool
	 */
	public function isMemoryLimitSufficient(): bool {
		$memoryLimit = $this->getMemoryLimit();
		return $memoryLimit === -1 || $memoryLimit >= self::RECOMMENDED_MEMORY_LIMIT;
	}

	/**
	 * Returns the php memory limit.
	 *
	 * @return int The memory limit in bytes.
	 */
	public function getMemoryLimit(): int {
		$iniValue = trim(ini_get('memory_limit'));
		if ($iniValue === '-1') {
			return -1;
		} else if (is_numeric($iniValue) === true) {
			return (int)$iniValue;
		} else {
			return $this->memoryLimitToBytes($iniValue);
		}
	}

	/**
	 * Converts the ini memory limit to bytes.
	 *
	 * @param string $memoryLimit The "memory_limit" ini value
	 * @return int
	 */
	private function memoryLimitToBytes(string $memoryLimit): int {
		$last = strtolower(substr($memoryLimit, -1));
		$memoryLimit = (int)substr($memoryLimit, 0, -1);

		// intended fall trough
		switch($last) {
			case 'g':
				$memoryLimit *= 1024;
			case 'm':
				$memoryLimit *= 1024;
			case 'k':
				$memoryLimit *= 1024;
		}

		return $memoryLimit;
	}
}
