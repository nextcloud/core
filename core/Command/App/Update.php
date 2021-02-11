<?php
/**
 * @copyright Copyright (c) 2018, michag86 (michag86@arcor.de)
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author michag86 <micha_g@arcor.de>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Core\Command\App;

use OC\Installer;
use OCP\App\IAppManager;
use OCP\ILogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Update extends Command {

	/** @var IAppManager */
	protected $manager;
	/** @var Installer */
	private $installer;
	/** @var ILogger */
	private $logger;

	/**
	 * @param IAppManager $manager
	 * @param Installer $installer
	 */
	public function __construct(IAppManager $manager, Installer $installer, ILogger $logger) {
		parent::__construct();
		$this->manager = $manager;
		$this->installer = $installer;
		$this->logger = $logger;
	}

	protected function configure() {
		$this
			->setName('app:update')
			->setDescription('update an app or all apps')
			->addArgument(
				'app-id',
				InputArgument::OPTIONAL,
				'update the specified app'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'update all updatable apps'
			)
			->addOption(
				'showonly',
				null,
				InputOption::VALUE_NONE,
				'show update(s) without updating'
			)
			->addOption(
				'allow-unstable',
				null,
				InputOption::VALUE_NONE,
				'allow updating to unstable releases'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$singleAppId = $input->getArgument('app-id');

		if ($singleAppId) {
			$apps = [$singleAppId];
			try {
				$this->manager->getAppPath($singleAppId);
			} catch (\OCP\App\AppPathNotFoundException $e) {
				$output->writeln($singleAppId . ' not installed');
				return 1;
			}
		} elseif ($input->getOption('all') || $input->getOption('showonly')) {
			$apps = \OC_App::getAllApps();
		} else {
			$output->writeln("<error>Please specify an app to update or \"--all\" to update all updatable apps\"</error>");
			return 1;
		}

		$return = 0;
		foreach ($apps as $appId) {
			$newVersion = $this->installer->isUpdateAvailable($appId, $input->getOption('allow-unstable'));
			if ($newVersion) {
				$output->writeln($appId . ' new version available: ' . $newVersion);

				if (!$input->getOption('showonly')) {
					try {
						$result = $this->installer->updateAppstoreApp($appId, $input->getOption('allow-unstable'));
					} catch (\Exception $e) {
						$this->logger->logException($e, ['message' => 'Failure during update of app "' . $appId . '"','app' => 'app:update']);
						$output->writeln('Error: ' . $e->getMessage());
						$return = 1;
					}

					if ($result === false) {
						$output->writeln($appId . ' couldn\'t be updated');
						$return = 1;
					} elseif ($result === true) {
						$output->writeln($appId . ' updated');
					}
				}
			}
		}

		return $return;
	}
}
