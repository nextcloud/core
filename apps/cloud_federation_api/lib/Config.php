<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
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

namespace OCA\CloudFederationAPI;

use OCP\Federation\ICloudFederationProviderManager;

/**
 * Class config
 *
 * handles all the config parameters
 *
 * @package OCA\CloudFederationAPI
 */
class Config {

	/** @var ICloudFederationProviderManager */
	private $cloudFederationProviderManager;

	public function __construct(ICloudFederationProviderManager $cloudFederationProviderManager) {
		$this->cloudFederationProviderManager = $cloudFederationProviderManager;
	}

	/**
	 * get a list of supported share types
	 *
	 * @param string $resourceType
	 * @return array
	 */
	public function getSupportedShareTypes($resourceType) {
		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider($resourceType);
			return $provider->getSupportedShareTypes();
		} catch (\Exception $e) {
			return [];
		}
	}

}
