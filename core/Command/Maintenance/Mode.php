<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author scolebrook <scolebrook@mac.com>
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

namespace OC\Core\Command\Maintenance;

use OCP\IConfig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Mode extends Command {

	/** @var IConfig */
	protected $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('maintenance:mode')
			->setDescription('set maintenance mode')
			->addOption(
				'on',
				null,
				InputOption::VALUE_NONE,
				'enable maintenance mode'
			)
			->addOption(
				'off',
				null,
				InputOption::VALUE_NONE,
				'disable maintenance mode'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$maintenanceMode = $this->config->getSystemValueBool('maintenance');
		if ($input->getOption('on')) {
			if ($maintenanceMode === false) {
				$this->config->setSystemValue('maintenance', true);
				$output->writeln('Maintenance mode enabled');
			} else {
				$output->writeln('Maintenance mode already enabled');
			}
		} elseif ($input->getOption('off')) {
			if ($maintenanceMode === true) {
				$this->config->setSystemValue('maintenance', false);
				$output->writeln('Maintenance mode disabled');
			} else {
				$output->writeln('Maintenance mode already disabled');
			}
		} else {
			if ($maintenanceMode) {
				$output->writeln('Maintenance mode is currently enabled');
			} else {
				$output->writeln('Maintenance mode is currently disabled');
			}
		}
	}
}
