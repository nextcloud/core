<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\OCS;

class Provider extends \OCP\AppFramework\Controller {
	/** @var \OCP\App\IAppManager */
	private $appManager;

	/**
	 * @param string $appName
	 * @param \OCP\IRequest $request
	 * @param \OCP\App\IAppManager $appManager
	 */
	public function __construct($appName,
								\OCP\IRequest $request,
								\OCP\App\IAppManager $appManager) {
		parent::__construct($appName, $request);
		$this->appManager = $appManager;
	}

	/**
	 * @return \OCP\AppFramework\Http\JSONResponse
	 */
	public function buildProviderList() {
		$services = [
			'PRIVATE_DATA' => [
				'version' => 1,
				'endpoints' => [
					'store' => '/ocs/v2.php/privatedata/setattribute',
					'read' => '/ocs/v2.php/privatedata/getattribute',
					'delete' => '/ocs/v2.php/privatedata/deleteattribute',
				],
			],
		];

		if($this->appManager->isEnabledForUser('files_sharing')) {
			$services['SHARING'] = [
				'version' => 1,
				'endpoints' => [
					'share' => '/ocs/v2.php/apps/files_sharing/api/v1/shares',
				],
			];
			$services['FEDERATED_SHARING'] = [
				'version' => 1,
				'endpoints' => [
					'share' => '/ocs/v2.php/cloud/shares',
					'webdav' => '/public.php/webdav/',
				],
			];
		}

		if ($this->appManager->isEnabledForUser('federation')) {
			if (isset($services['FEDERATED_SHARING'])) {
				$services['FEDERATED_SHARING']['endpoints']['shared-secret'] = '/ocs/v2.php/cloud/shared-secret';
				$services['FEDERATED_SHARING']['endpoints']['system-address-book'] = '/remote.php/dav/addressbooks/system/system/system';
				$services['FEDERATED_SHARING']['endpoints']['carddav-user'] = 'system';
			} else {
				$services['FEDERATED_SHARING'] = [
					'version' => 1,
					'endpoints' => [
						'shared-secret' => '/ocs/v2.php/cloud/shared-secret',
						'system-address-book' => '/remote.php/dav/addressbooks/system/system/system',
						'carddav-user' => 'system'
					],
				];
			}
		}

		if($this->appManager->isEnabledForUser('activity')) {
			$services['ACTIVITY'] = [
				'version' => 1,
				'endpoints' => [
					'list' => '/ocs/v2.php/cloud/activity',
				],
			];
		}

		if($this->appManager->isEnabledForUser('provisioning_api')) {
			$services['PROVISIONING'] = [
				'version' => 1,
				'endpoints' => [
					'user' => '/ocs/v2.php/cloud/users',
					'groups' => '/ocs/v2.php/cloud/groups',
					'apps' => '/ocs/v2.php/cloud/apps',
				],
			];
		}

		return new \OCP\AppFramework\Http\JSONResponse([
			'version' => 2,
			'services' => $services,
		]);
	}
}
