<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\App\AppStore\Version;

/**
 * Class VersionParser parses the versions as sent by the Nextcloud app store
 *
 * @package OC\App\AppStore
 */
class VersionParser {
	/**
	 * @param string $versionString
	 * @return bool
	 */
	private function isValidVersionString($versionString) {
		return (bool)preg_match('/^[0-9.]+$/', $versionString);
	}

	/**
	 * Returns the version for a version string
	 *
	 * @param string $versionSpec
	 * @return Version
	 * @throws \Exception If the version cannot be parsed
	 */
	public function getVersion($versionSpec) {
		// * indicates that the version is compatible with all versions
		if($versionSpec === '*') {
			return new Version('', '');
		}

		// Count the amount of =, if it is one then it's either maximum or minimum
		// version. If it is two then it is maximum and minimum.
		$versionElements = explode(' ', $versionSpec);
		$firstVersion = isset($versionElements[0]) ? $versionElements[0] : '';
		$firstVersionNumber = substr($firstVersion, 2);
		$secondVersion = isset($versionElements[1]) ? $versionElements[1] : '';
		$secondVersionNumber = substr($secondVersion, 2);

		switch(count($versionElements)) {
			case 1:
				if(!$this->isValidVersionString($firstVersionNumber)) {
					break;
				}
				if(strpos($firstVersion, '>') === 0) {
					return new Version($firstVersionNumber, '');
				}
				return new Version('', $firstVersionNumber);
			case 2:
				if(!$this->isValidVersionString($firstVersionNumber) || !$this->isValidVersionString($secondVersionNumber)) {
					break;
				}
				return new Version($firstVersionNumber, $secondVersionNumber);
		}

		throw new \Exception(
			sprintf(
				'Version cannot be parsed: %s',
				$versionSpec
			)
		);
	}
}
