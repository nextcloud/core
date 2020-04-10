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

namespace OC\Core\Command\Config\System;

use OC\SystemConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetConfig extends Base {
	/** * @var SystemConfig */
	protected $systemConfig;

	/**
	 * @param SystemConfig $systemConfig
	 */
	public function __construct(SystemConfig $systemConfig) {
		parent::__construct();
		$this->systemConfig = $systemConfig;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('config:system:get')
			->setDescription('Get a system config value')
			->addArgument(
				'name',
				InputArgument::REQUIRED | InputArgument::IS_ARRAY,
				'Name of the config to get, specify multiple for array parameter'
			)
			->addOption(
				'default-value',
				null,
				InputOption::VALUE_OPTIONAL,
				'If no default value is set and the config does not exist, the command will exit with 1'
			)
		;
	}

	/**
	 * Executes the current command.
	 *
	 * @param InputInterface  $input  An InputInterface instance
	 * @param OutputInterface $output An OutputInterface instance
	 * @return null|int null or 0 if everything went fine, or an error code
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$configNames = $input->getArgument('name');
		$configName = array_shift($configNames);
		$defaultValue = $input->getOption('default-value');

		if (!in_array($configName, $this->systemConfig->getKeys()) && !$input->hasParameterOption('--default-value')) {
			return 1;
		}

		if (!in_array($configName, $this->systemConfig->getKeys())) {
			$configValue = $defaultValue;
		} else {
			$configValue = $this->systemConfig->getValue($configName);
			if (!empty($configNames)) {
				foreach ($configNames as $configName) {
					if (isset($configValue[$configName])) {
						$configValue = $configValue[$configName];
					} else if (!$input->hasParameterOption('--default-value')) {
						return 1;
					} else {
						$configValue = $defaultValue;
						break;
					}
				}
			}
		}

		$this->writeMixedInOutputFormat($input, $output, $configValue);
		return 0;
	}
}
