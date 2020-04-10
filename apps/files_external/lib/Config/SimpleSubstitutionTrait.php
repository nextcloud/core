<?php
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Files_External\Config;

/**
 * Trait SimpleSubstitutionTrait
 *
 * @package OCA\Files_External\Config
 * @since 16.0.0
 */
trait SimpleSubstitutionTrait {
	/**
	 * @var string the placeholder without $ prefix
	 * @since 16.0.0
	 */
	protected $placeholder;

	/** @var string */
	protected $sanitizedPlaceholder;

	/**
	 * @param mixed $optionValue
	 * @param string $replacement
	 * @return mixed
	 * @since 16.0.0
	 */
	private function processInput($optionValue, string $replacement) {
		$this->checkPlaceholder();
		if (is_array($optionValue)) {
			foreach ($optionValue as &$value) {
				$value = $this->substituteIfString($value, $replacement);
			}
		} else {
			$optionValue = $this->substituteIfString($optionValue, $replacement);
		}
		return $optionValue;
	}

	/**
	 * @throws \RuntimeException
	 */
	protected function checkPlaceholder(): void {
		$this->sanitizedPlaceholder = trim(strtolower($this->placeholder));
		if(!(bool)\preg_match('/^[a-z0-9]*$/', $this->sanitizedPlaceholder)) {
			throw new \RuntimeException(sprintf(
				'Invalid placeholder %s, only [a-z0-9] are allowed', $this->sanitizedPlaceholder
			));
		}
		if($this->sanitizedPlaceholder === '') {
			throw new \RuntimeException('Invalid empty placeholder');
		}
	}

	/**
	 * @param mixed $value
	 * @param string $replacement
	 * @return mixed
	 */
	protected function substituteIfString($value, string $replacement) {
		if(is_string($value)) {
			return str_ireplace('$' . $this->sanitizedPlaceholder, $replacement, $value);
		}
		return $value;
	}
}
