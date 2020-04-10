<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Files_Sharing\Settings;

use OCA\Files_Sharing\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	/** @var IConfig */
	private $config;
	/** @var IInitialStateService */
	private $initialState;
	/** @var string */
	private $userId;

	public function __construct(IConfig $config, IInitialStateService $initialState, string $userId) {
		$this->config = $config;
		$this->initialState = $initialState;
		$this->userId = $userId;
	}

	public function getForm(): TemplateResponse {
		$defaultAcceptSystemConfig = $this->config->getSystemValueBool('sharing.enable_share_accept', false) ? 'no' : 'yes';
		$acceptDefault = $this->config->getUserValue($this->userId, Application::APP_ID, 'default_accept', $defaultAcceptSystemConfig) === 'yes';
		$enforceAccept = $this->config->getSystemValueBool('sharing.force_share_accept', false);
		$this->initialState->provideInitialState(Application::APP_ID, 'accept_default', $acceptDefault);
		$this->initialState->provideInitialState(Application::APP_ID, 'enforce_accept', $enforceAccept);
		return new TemplateResponse('files_sharing', 'Settings/personal');
	}

	public function getSection(): string {
		return 'sharing';
	}

	public function getPriority(): int {
		return 90;
	}

}
