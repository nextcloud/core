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

namespace OCA\CloudFederationAPI;

use OCP\Capabilities\ICapability;
use OCP\IURLGenerator;

class Capabilities implements ICapability {

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(IURLGenerator $urlGenerator) {
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Function an app uses to return the capabilities
	 *
	 * @return array Array containing the apps capabilities
	 * @since 8.2.0
	 */
	public function getCapabilities() {
		$url = $this->urlGenerator->linkToRouteAbsolute('cloud_federation_api.requesthandlercontroller.addShare');
		$capabilities = ['ocm' =>
			[
				'enabled' => true,
				'apiVersion' => '1.0-proposal1',
				'endPoint' => substr($url, 0, strrpos($url, '/')),
				'resourceTypes' => [
					[
						'name' => 'file',
						'shareTypes' => ['user', 'group'],
						'protocols' => [
							'webdav' => '/public.php/webdav/',
						]
					],
				]
			]
		];

		return $capabilities;
	}
}
