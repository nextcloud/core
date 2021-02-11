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

namespace OC\App;

use InvalidArgumentException;
use function explode;

class CompareVersion {
	private const REGEX_MAJOR = '/^\d+$/';
	private const REGEX_MAJOR_MINOR = '/^\d+\.\d+$/';
	private const REGEX_MAJOR_MINOR_PATCH = '/^\d+\.\d+\.\d+(?!\.\d+)/';
	private const REGEX_ACTUAL = '/^\d+(\.\d+){1,2}/';

	/**
	 * Checks if the given server version fulfills the given (app) version requirements.
	 *
	 * Version requirements can be 'major.minor.patch', 'major.minor' or just 'major',
	 * so '13.0.1', '13.0' and '13' are valid.
	 *
	 * @param string $actual version as major.minor.patch notation
	 * @param string $required version where major is requried and minor and patch are optional
	 * @param string $comparator passed to `version_compare`
	 * @return bool whether the requirement is fulfilled
	 * @throws InvalidArgumentException if versions specified in an invalid format
	 */
	public function isCompatible(string $actual, string $required,
		string $comparator = '>='): bool {
		if (!preg_match(self::REGEX_ACTUAL, $actual, $matches)) {
			throw new InvalidArgumentException("version specification $actual is invalid");
		}
		$cleanActual = $matches[0];

		if (preg_match(self::REGEX_MAJOR, $required) === 1) {
			return $this->compareMajor($cleanActual, $required, $comparator);
		} elseif (preg_match(self::REGEX_MAJOR_MINOR, $required) === 1) {
			return $this->compareMajorMinor($cleanActual, $required, $comparator);
		} elseif (preg_match(self::REGEX_MAJOR_MINOR_PATCH, $required) === 1) {
			return $this->compareMajorMinorPatch($cleanActual, $required, $comparator);
		} else {
			throw new InvalidArgumentException("required version $required is invalid");
		}
	}

	private function compareMajor(string $actual, string $required,
		string $comparator) {
		$actualMajor = explode('.', $actual)[0];
		$requiredMajor = explode('.', $required)[0];

		return version_compare($actualMajor, $requiredMajor, $comparator);
	}

	private function compareMajorMinor(string $actual, string $required,
		string $comparator) {
		$actualMajor = explode('.', $actual)[0];
		$actualMinor = explode('.', $actual)[1];
		$requiredMajor = explode('.', $required)[0];
		$requiredMinor = explode('.', $required)[1];

		return version_compare("$actualMajor.$actualMinor",
			"$requiredMajor.$requiredMinor", $comparator);
	}

	private function compareMajorMinorPatch($actual, $required, $comparator) {
		$actualMajor = explode('.', $actual)[0];
		$actualMinor = explode('.', $actual)[1];
		$actualPatch = explode('.', $actual)[2];
		$requiredMajor = explode('.', $required)[0];
		$requiredMinor = explode('.', $required)[1];
		$requiredPatch = explode('.', $required)[2];

		return version_compare("$actualMajor.$actualMinor.$actualPatch",
			"$requiredMajor.$requiredMinor.$requiredPatch", $comparator);
	}
}
