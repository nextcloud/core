<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Service;

use OCP\IUserSession;

/**
 * Read user defined mounts from the legacy mount.json
 */
class UserLegacyStoragesService extends LegacyStoragesService {
	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * @param BackendService $backendService
	 * @param IUserSession $userSession
	 */
	public function __construct(BackendService $backendService, IUserSession $userSession) {
		$this->backendService = $backendService;
		$this->userSession = $userSession;
	}

	/**
	 * Read legacy config data
	 *
	 * @return array list of storage configs
	 */
	protected function readLegacyConfig() {
		// read user config
		$user = $this->userSession->getUser()->getUID();
		return \OC_Mount_Config::readData($user);
	}
}
