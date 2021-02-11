<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author sualko <klaus@jsxc.org>
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

namespace OC\Core\Command\App;

use OC\Installer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command {
	protected function configure() {
		$this
			->setName('app:install')
			->setDescription('install an app')
			->addArgument(
				'app-id',
				InputArgument::REQUIRED,
				'install the specified app'
			)
			->addOption(
				'keep-disabled',
				null,
				InputOption::VALUE_NONE,
				'don\'t enable the app afterwards'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$appId = $input->getArgument('app-id');

		if (\OC_App::getAppPath($appId)) {
			$output->writeln($appId . ' already installed');
			return 1;
		}

		try {
			/** @var Installer $installer */
			$installer = \OC::$server->query(Installer::class);
			$installer->downloadApp($appId);
			$result = $installer->installApp($appId);
		} catch (\Exception $e) {
			$output->writeln('Error: ' . $e->getMessage());
			return 1;
		}

		if ($result === false) {
			$output->writeln($appId . ' couldn\'t be installed');
			return 1;
		}

		$appVersion = \OC_App::getAppVersion($appId);
		$output->writeln($appId . ' ' . $appVersion . ' installed');

		if (!$input->getOption('keep-disabled')) {
			$appClass = new \OC_App();
			$appClass->enable($appId);
			$output->writeln($appId . ' enabled');
		}

		return 0;
	}
}
