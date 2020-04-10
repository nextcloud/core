<?php
/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Settings\Settings\Admin;

use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Encryption\IManager;
use OCP\IInitialStateService;
use OCP\IUserManager;
use OCP\Settings\ISettings;

class Security implements ISettings {

	/** @var IManager */
	private $manager;

	/** @var IUserManager */
	private $userManager;

	/** @var MandatoryTwoFactor */
	private $mandatoryTwoFactor;

	/** @var IInitialStateService */
	private $initialState;

	public function __construct(IManager $manager,
								IUserManager $userManager,
								MandatoryTwoFactor $mandatoryTwoFactor,
								IInitialStateService $initialState) {
		$this->manager = $manager;
		$this->userManager = $userManager;
		$this->mandatoryTwoFactor = $mandatoryTwoFactor;
		$this->initialState = $initialState;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$encryptionModules = $this->manager->getEncryptionModules();
		$defaultEncryptionModuleId = $this->manager->getDefaultEncryptionModuleId();
		$encryptionModuleList = [];
		foreach ($encryptionModules as $module) {
			$encryptionModuleList[$module['id']]['displayName'] = $module['displayName'];
			$encryptionModuleList[$module['id']]['default'] = false;
			if ($module['id'] === $defaultEncryptionModuleId) {
				$encryptionModuleList[$module['id']]['default'] = true;
			}
		}

		$this->initialState->provideInitialState(
			'settings',
			'mandatory2FAState',
			$this->mandatoryTwoFactor->getState()
		);

		$parameters = [
			// Encryption API
			'encryptionEnabled'       => $this->manager->isEnabled(),
			'encryptionReady'         => $this->manager->isReady(),
			'externalBackendsEnabled' => count($this->userManager->getBackends()) > 1,
			// Modules
			'encryptionModules'       => $encryptionModuleList,
		];

		return new TemplateResponse('settings', 'settings/admin/security', $parameters, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'security';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 10;
	}
}
