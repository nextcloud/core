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

class SetConfig extends Base {
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
			->setName('config:app:set')
			->setDescription('Set an app config value')
			->addArgument(
				'app',
				InputArgument::REQUIRED,
				'Name of the app'
			)
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'Name of the config to set'
			)
			->addOption(
				'value',
				null,
				InputOption::VALUE_REQUIRED,
				'The new value of the config'
			)
			->addOption(
				'update-only',
				null,
				InputOption::VALUE_NONE,
				'Only updates the value, if it is not set before, it is not being added'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$appName = $input->getArgument('app');
		$configName = $input->getArgument('name');

		if (!in_array($configName, $this->config->getAppKeys($appName)) && $input->hasParameterOption('--update-only')) {
			$output->writeln('<comment>Config value ' . $configName . ' for app ' . $appName . ' not updated, as it has not been set before.</comment>');
			return 1;
		}

		$configValue = $input->getOption('value');
		$this->config->setAppValue($appName, $configName, $configValue);

		$output->writeln('<info>Config value ' . $configName . ' for app ' . $appName . ' set to ' . $configValue . '</info>');
		return 0;
	}
}
