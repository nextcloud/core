<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetConfig extends Base {
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
			->setName('config:system:set')
			->setDescription('Set a system config value')
			->addArgument(
				'name',
				InputArgument::REQUIRED | InputArgument::IS_ARRAY,
				'Name of the config parameter, specify multiple for array parameter'
			)
			->addOption(
				'type',
				null,
				InputOption::VALUE_REQUIRED,
				'Value type [string, integer, double, boolean]',
				'string'
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
		$configNames = $input->getArgument('name');
		$configName = $configNames[0];
		$configValue = $this->castValue($input->getOption('value'), $input->getOption('type'));
		$updateOnly = $input->getOption('update-only');

		if (count($configNames) > 1) {
			$existingValue = $this->systemConfig->getValue($configName);

			$newValue = $this->mergeArrayValue(
				array_slice($configNames, 1), $existingValue, $configValue['value'], $updateOnly
			);

			$this->systemConfig->setValue($configName, $newValue);
		} else {
			if ($updateOnly && !in_array($configName, $this->systemConfig->getKeys(), true)) {
				throw new \UnexpectedValueException('Config parameter does not exist');
			}

			$this->systemConfig->setValue($configName, $configValue['value']);
		}

		$output->writeln('<info>System config value ' . implode(' => ', $configNames) . ' set to ' . $configValue['readable-value'] . '</info>');
		return 0;
	}

	/**
	 * @param string $value
	 * @param string $type
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	protected function castValue($value, $type) {
		switch ($type) {
			case 'integer':
			case 'int':
				if (!is_numeric($value)) {
					throw new \InvalidArgumentException('Non-numeric value specified');
				}
				return [
					'value' => (int) $value,
					'readable-value' => 'integer ' . (int) $value,
				];

			case 'double':
			case 'float':
				if (!is_numeric($value)) {
					throw new \InvalidArgumentException('Non-numeric value specified');
				}
				return [
					'value' => (double) $value,
					'readable-value' => 'double ' . (double) $value,
				];

			case 'boolean':
			case 'bool':
				$value = strtolower($value);
				switch ($value) {
					case 'true':
						return [
							'value' => true,
							'readable-value' => 'boolean ' . $value,
						];

					case 'false':
						return [
							'value' => false,
							'readable-value' => 'boolean ' . $value,
						];

					default:
						throw new \InvalidArgumentException('Unable to parse value as boolean');
				}

			case 'null':
				return [
					'value' => null,
					'readable-value' => 'null',
				];

			case 'string':
				$value = (string) $value;
				return [
					'value' => $value,
					'readable-value' => ($value === '') ? 'empty string' : 'string ' . $value,
				];

			default:
				throw new \InvalidArgumentException('Invalid type');
		}
	}

	/**
	 * @param array $configNames
	 * @param mixed $existingValues
	 * @param mixed $value
	 * @param bool $updateOnly
	 * @return array merged value
	 * @throws \UnexpectedValueException
	 */
	protected function mergeArrayValue(array $configNames, $existingValues, $value, $updateOnly) {
		$configName = array_shift($configNames);
		if (!is_array($existingValues)) {
			$existingValues = [];
		}
		if (!empty($configNames)) {
			if (isset($existingValues[$configName])) {
				$existingValue = $existingValues[$configName];
			} else {
				$existingValue = [];
			}
			$existingValues[$configName] = $this->mergeArrayValue($configNames, $existingValue, $value, $updateOnly);
		} else {
			if (!isset($existingValues[$configName]) && $updateOnly) {
				throw new \UnexpectedValueException('Config parameter does not exist');
			}
			$existingValues[$configName] = $value;
		}
		return $existingValues;
	}

	/**
	 * @param string $optionName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeOptionValues($optionName, CompletionContext $context) {
		if ($optionName === 'type') {
			return ['string', 'integer', 'double', 'boolean'];
		}
		return parent::completeOptionValues($optionName, $context);
	}
}
