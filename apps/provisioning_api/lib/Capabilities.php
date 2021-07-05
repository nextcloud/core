<?php
/**
 * @copyright Copyright (c) 2021 Vincent Petry <vincent@nextcloud.com>
 *
 * @author Vincent Petry <vincent@nextcloud.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Provisioning_API;

use OCA\FederatedFileSharing\FederatedShareProvider;
use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {

	/** @var IAppManager */
	private $appManager;

	public function __construct(IAppManager $appManager) {
		$this->appManager = $appManager;
	}

	/**
	 * Function an app uses to return the capabilities
	 *
	 * @return array Array containing the apps capabilities
	 */
	public function getCapabilities() {
		$federatedScopeEnabled = $this->appManager->isEnabledForUser('federation');

		$publishedScopeEnabled = false;

		$federatedFileSharingEnabled = $this->appManager->isEnabledForUser('federatedfilesharing');
		if ($federatedFileSharingEnabled) {
			/** @var FederatedShareProvider $shareProvider */
			$shareProvider = \OC::$server->query(FederatedShareProvider::class);
			$publishedScopeEnabled = $shareProvider->isLookupServerUploadEnabled();
		}

		return [
			'provisioning_api' => [
				'version' => $this->appManager->getAppVersion('provisioning_api'),
				'AccountPropertyScopesVersion' => 2,
				'AccountPropertyScopesFederatedEnabled' => $federatedScopeEnabled,
				'AccountPropertyScopesPublishedEnabled' => $publishedScopeEnabled,
			]
		];
	}
}
