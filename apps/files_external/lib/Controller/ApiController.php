<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Files_External\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends OCSController {

	/** @var IUserSession */
	private $userSession;

	public function __construct(string $appName,
								IRequest $request,
								IUserSession $userSession) {
		parent::__construct($appName, $request);

		$this->userSession = $userSession;
	}

	/**
	 * Formats the given mount config to a mount entry.
	 *
	 * @param string $mountPoint mount point name, relative to the data dir
	 * @param array $mountConfig mount config to format
	 *
	 * @return array entry
	 */
	private function formatMount(string $mountPoint, array $mountConfig): array {
		// strip "/$user/files" from mount point
		$mountPoint = explode('/', trim($mountPoint, '/'), 3);
		$mountPoint = $mountPoint[2] ?? '';

		// split path from mount point
		$path = \dirname($mountPoint);
		if ($path === '.') {
			$path = '';
		}

		$isSystemMount = !$mountConfig['personal'];

		$permissions = \OCP\Constants::PERMISSION_READ;
		// personal mounts can be deleted
		if (!$isSystemMount) {
			$permissions |= \OCP\Constants::PERMISSION_DELETE;
		}

		$entry = [
			'name' => basename($mountPoint),
			'path' => $path,
			'type' => 'dir',
			'backend' => $mountConfig['backend'],
			'scope' => $isSystemMount ? 'system' : 'personal',
			'permissions' => $permissions,
			'id' => $mountConfig['id'],
			'class' => $mountConfig['class']
		];
		return $entry;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Returns the mount points visible for this user.
	 *
	 * @return DataResponse share information
	 */
	public function getUserMounts(): DataResponse {
		$entries = [];
		$user = $this->userSession->getUser()->getUID();

		$mounts = \OCA\Files_External\MountConfig::getAbsoluteMountPoints($user);
		foreach ($mounts as $mountPoint => $mount) {
			$entries[] = $this->formatMount($mountPoint, $mount);
		}

		return new DataResponse($entries);
	}
}
