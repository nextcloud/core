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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Accessibility\Service;

use OCA\Accessibility\AppInfo\Application;
use OCP\AppFramework\Services\InitialStateProvider;
use OCP\IConfig;
use OCP\IUserSession;

class JSDataService extends InitialStateProvider {
	/** @var IUserSession */
	private $userSession;
	/** @var IConfig */
	private $config;

	public function __construct(
		IUserSession $userSession,
		IConfig $config
	) {
		$this->userSession = $userSession;
		$this->config = $config;
	}

	public function getKey(): string {
		return 'data';
	}

	public function getData() {
		$user = $this->userSession->getUser();

		if ($user === null) {
			$theme = false;
			$highcontrast = false;
		} else {
			$theme = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'theme', false);
			$highcontrast = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'highcontrast', false) !== false;
		}

		return [
			'theme' => $theme,
			'highcontrast' => $highcontrast,
		];
	}
}
