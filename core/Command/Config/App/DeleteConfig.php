<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Core\Command\Config\App;

use OCP\IConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteConfig extends Base {
	/** * @var IConfig */
	protected $config;

	/**
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		parent::__construct();
		$this->config = $config;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('config:app:delete')
			->setDescription('Delete an app config value')
			->addArgument(
				'app',
				InputArgument::REQUIRED,
				'Name of the app'
			)
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'Name of the config to delete'
			)
			->addOption(
				'error-if-not-exists',
				null,
				InputOption::VALUE_NONE,
				'Checks whether the config exists before deleting it'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$appName = $input->getArgument('app');
		$configName = $input->getArgument('name');

		if ($input->hasParameterOption('--error-if-not-exists') && !in_array($configName, $this->config->getAppKeys($appName))) {
			$output->writeln('<error>Config ' . $configName . ' of app ' . $appName . ' could not be deleted because it did not exist</error>');
			return 1;
		}

		$this->config->deleteAppValue($appName, $configName);
		$output->writeln('<info>Config value ' . $configName . ' of app ' . $appName . ' deleted</info>');
		return 0;
	}
}
