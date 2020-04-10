<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

namespace OC\GlobalScale;

use OCP\IConfig;

class Config implements \OCP\GlobalScale\IConfig {

	/** @var IConfig */
	private $config;

	/**
	 * Config constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * check if global scale is enabled
	 *
	 * @since 12.0.1
	 * @return bool
	 */
	public function isGlobalScaleEnabled() {
		$enabled = $this->config->getSystemValue('gs.enabled', false);
		return $enabled !== false;
	}

	/**
	 * check if federation should only be used internally in a global scale setup
	 *
	 * @since 12.0.1
	 * @return bool
	 */
	public function onlyInternalFederation() {
		// if global scale is disabled federation works always globally
		$gsEnabled = $this->isGlobalScaleEnabled();
		if ($gsEnabled === false) {
			return false;
		}

		$enabled = $this->config->getSystemValue('gs.federation', 'internal');

		return $enabled === 'internal';
	}

}
