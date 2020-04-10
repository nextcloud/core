<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

class DeleteConfig extends Base {
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
			->setName('config:system:delete')
			->setDescription('Delete a system config value')
			->addArgument(
				'name',
				InputArgument::REQUIRED | InputArgument::IS_ARRAY,
				'Name of the config to delete, specify multiple for array parameter'
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
		$configNames = $input->getArgument('name');
		$configName = $configNames[0];

		if (count($configNames) > 1) {
			if ($input->hasParameterOption('--error-if-not-exists') && !in_array($configName, $this->systemConfig->getKeys())) {
				$output->writeln('<error>System config ' . implode(' => ', $configNames) . ' could not be deleted because it did not exist</error>');
				return 1;
			}

			$value = $this->systemConfig->getValue($configName);

			try {
				$value = $this->removeSubValue(array_slice($configNames, 1), $value, $input->hasParameterOption('--error-if-not-exists'));
			}
			catch (\UnexpectedValueException $e) {
				$output->writeln('<error>System config ' . implode(' => ', $configNames) . ' could not be deleted because it did not exist</error>');
				return 1;
			}

			$this->systemConfig->setValue($configName, $value);
			$output->writeln('<info>System config value ' . implode(' => ', $configNames) . ' deleted</info>');
			return 0;
		} else {
			if ($input->hasParameterOption('--error-if-not-exists') && !in_array($configName, $this->systemConfig->getKeys())) {
				$output->writeln('<error>System config ' . $configName . ' could not be deleted because it did not exist</error>');
				return 1;
			}

			$this->systemConfig->deleteValue($configName);
			$output->writeln('<info>System config value ' . $configName . ' deleted</info>');
			return 0;
		}
	}

	protected function removeSubValue($keys, $currentValue, $throwError) {
		$nextKey = array_shift($keys);

		if (is_array($currentValue)) {
			if (isset($currentValue[$nextKey])) {
				if (empty($keys)) {
					unset($currentValue[$nextKey]);
				} else {
					$currentValue[$nextKey] = $this->removeSubValue($keys, $currentValue[$nextKey], $throwError);
				}
			} else if ($throwError) {
				throw new \UnexpectedValueException('Config parameter does not exist');
			}
		} else if ($throwError) {
			throw new \UnexpectedValueException('Config parameter does not exist');
		}

		return $currentValue;
	}
}
