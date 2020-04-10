<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Janis Köhr <janiskoehr@icloud.com>
 *
 * @author Janis Köhr <janis.koehr@novatec-gmbh.de>
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

namespace OCA\Accessibility\Migration;

use OCA\Accessibility\AppInfo\Application;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RepairUserConfig implements IRepairStep {

	/** @var IUserManager */
	protected $userManager;

	/** @var IConfig */
	protected $config;

	/**
	 * MigrateUserConfig constructor.
	 *
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 */
	public function __construct(IConfig $config,
								IUserManager $userManager) {
		$this->config = $config;
		$this->userManager = $userManager;
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 * @since 9.1.0
	 */
	public function getName() {
		return 'Migrate old user config';
	}

	/**
	 * Run repair step.
	 * Must throw exception on error.
	 *
	 * @param IOutput $output
	 * @throws \Exception in case of failure
	 * @since 9.1.0
	 */
	public function run(IOutput $output) {
		$output->startProgress();
		$this->userManager->callForSeenUsers(function (IUser $user) use ($output) {
			$theme = $this->config->getUserValue($user->getUID(), Application::APP_NAME, 'theme', false);
			if ($theme === 'themedark') {
				$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'theme', 'dark');
			}
			if ($theme === 'themehighcontrast') {
				$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'highcontrast', 'highcontrast');
				$this->config->deleteUserValue($user->getUID(), Application::APP_NAME, 'theme');
			}
			$output->advance();
		});
		$output->finishProgress();
	}

}
