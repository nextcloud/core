<?php
/**
 * @copyright Copyright (c) 2018, Tobia De Koninck (tobia@ledfan.be)
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Tobia De Koninck <LEDfan@users.noreply.github.com>
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

namespace OCA\UpdateNotification\Command;

use OC\App\AppManager;
use OC\Installer;
use OCA\UpdateNotification\UpdateChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Check extends Command {

	/**
	 * @var Installer $installer
	 */
	private $installer;

	/**
	 * @var AppManager $appManager
	 */
	private $appManager;

	/**
	 * @var UpdateChecker $updateChecker
	 */
	private $updateChecker;

	public function __construct(AppManager $appManager, UpdateChecker $updateChecker, Installer $installer) {
		parent::__construct();
		$this->installer = $installer;
		$this->appManager = $appManager;
		$this->updateChecker = $updateChecker;
	}

	protected function configure() {
		$this
			->setName('update:check')
			->setDescription('Check for server and app updates')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$updatesAvailableCount = 0;

		// Server
		$r = $this->updateChecker->getUpdateState();
		if (isset($r['updateAvailable']) && $r['updateAvailable']) {
			$output->writeln($r['updateVersionString'] . ' is available. Get more information on how to update at '. $r['updateLink'] . '.');
			$updatesAvailableCount += 1;
		}


		// Apps
		$apps = $this->appManager->getInstalledApps();
		foreach ($apps as $app) {
			$update = $this->installer->isUpdateAvailable($app);
			if ($update !== false) {
				$output->writeln('Update for ' . $app . ' to version ' . $update . ' is available.');
				$updatesAvailableCount += 1;
			}
		}

		// Report summary
		if ($updatesAvailableCount === 0) {
			$output->writeln('<info>Everything up to date</info>');
		} else if ($updatesAvailableCount === 1) {
			$output->writeln('<comment>1 update available</comment>');
		} else {
			$output->writeln('<comment>' . $updatesAvailableCount . ' updates available</comment>');
		}

		return 0;
	}
}
