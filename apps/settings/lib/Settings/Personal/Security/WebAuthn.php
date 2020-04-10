<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Settings\Settings\Personal\Security;

use OC\Authentication\WebAuthn\Db\PublicKeyCredentialMapper;
use OC\Authentication\WebAuthn\Manager;
use OCA\Settings\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IInitialStateService;
use OCP\Settings\ISettings;

class WebAuthn implements ISettings {

	/** @var PublicKeyCredentialMapper */
	private $mapper;

	/** @var string */
	private $uid;

	/** @var IInitialStateService */
	private $initialStateService;

	/** @var Manager */
	private $manager;

	public function __construct(PublicKeyCredentialMapper $mapper,
								string $UserId,
								IInitialStateService $initialStateService,
								Manager $manager) {
		$this->mapper = $mapper;
		$this->uid = $UserId;
		$this->initialStateService = $initialStateService;
		$this->manager = $manager;
	}

	public function getForm() {
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'webauthn-devices',
			$this->mapper->findAllForUid($this->uid)
		);

		return new TemplateResponse('settings', 'settings/personal/security/webauthn', [
		]);
	}

	public function getSection(): ?string {
		if (!$this->manager->isWebAuthnAvailable()) {
			return null;
		}

		return 'security';
	}

	public function getPriority(): int {
		return 20;
	}
}
