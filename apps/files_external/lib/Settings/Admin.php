<?php
/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Files_External\Settings;

use OCA\Files_External\Lib\Auth\Password\GlobalAuth;
use OCA\Files_External\Service\BackendService;
use OCA\Files_External\Service\GlobalStoragesService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Encryption\IManager;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	/** @var IManager */
	private $encryptionManager;

	/** @var GlobalStoragesService */
	private $globalStoragesService;

	/** @var BackendService */
	private $backendService;

	/** @var GlobalAuth	 */
	private $globalAuth;

	public function __construct(
		IManager $encryptionManager,
		GlobalStoragesService $globalStoragesService,
		BackendService $backendService,
		GlobalAuth $globalAuth
	) {
		$this->encryptionManager = $encryptionManager;
		$this->globalStoragesService = $globalStoragesService;
		$this->backendService = $backendService;
		$this->globalAuth = $globalAuth;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$parameters = [
			'encryptionEnabled' => $this->encryptionManager->isEnabled(),
			'visibilityType' => BackendService::VISIBILITY_ADMIN,
			'storages' => $this->globalStoragesService->getStorages(),
			'backends' => $this->backendService->getAvailableBackends(),
			'authMechanisms' => $this->backendService->getAuthMechanisms(),
			'dependencies' => \OCA\Files_External\MountConfig::dependencyMessage($this->backendService->getBackends()),
			'allowUserMounting' => $this->backendService->isUserMountingAllowed(),
			'globalCredentials' => $this->globalAuth->getAuth(''),
			'globalCredentialsUid' => '',
		];

		return new TemplateResponse('files_external', 'settings', $parameters, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'externalstorages';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 40;
	}
}
