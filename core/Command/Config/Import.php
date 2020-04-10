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

namespace OC\Core\Command\Config;

use OCP\IConfig;
use Stecman\Component\Symfony\Console\BashCompletion\Completion;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\ShellPathCompletion;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Command implements CompletionAwareInterface  {
	protected $validRootKeys = ['system', 'apps'];

	/** @var IConfig */
	protected $config;

	/**
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		parent::__construct();
		$this->config = $config;
	}

	protected function configure() {
		$this
			->setName('config:import')
			->setDescription('Import a list of configs')
			->addArgument(
				'file',
				InputArgument::OPTIONAL,
				'File with the json array to import'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$importFile = $input->getArgument('file');
		if ($importFile !== null) {
			$content = $this->getArrayFromFile($importFile);
		} else {
			$content = $this->getArrayFromStdin();
		}

		try {
			$configs = $this->validateFileContent($content);
		} catch (\UnexpectedValueException $e) {
			$output->writeln('<error>' . $e->getMessage(). '</error>');
			return;
		}

		if (!empty($configs['system'])) {
			$this->config->setSystemValues($configs['system']);
		}

		if (!empty($configs['apps'])) {
			foreach ($configs['apps'] as $app => $appConfigs) {
				foreach ($appConfigs as $key => $value) {
					if ($value === null) {
						$this->config->deleteAppValue($app, $key);
					} else {
						$this->config->setAppValue($app, $key, $value);
					}
				}
			}
		}

		$output->writeln('<info>Config successfully imported from: ' . $importFile . '</info>');
	}

	/**
	 * Get the content from stdin ("config:import < file.json")
	 *
	 * @return string
	 */
	protected function getArrayFromStdin() {
		// Read from stdin. stream_set_blocking is used to prevent blocking
		// when nothing is passed via stdin.
		stream_set_blocking(STDIN, 0);
		$content = file_get_contents('php://stdin');
		stream_set_blocking(STDIN, 1);
		return $content;
	}

	/**
	 * Get the content of the specified file ("config:import file.json")
	 *
	 * @param string $importFile
	 * @return string
	 */
	protected function getArrayFromFile($importFile) {
		return file_get_contents($importFile);
	}

	/**
	 * @param string $content
	 * @return array
	 * @throws \UnexpectedValueException when the array is invalid
	 */
	protected function validateFileContent($content) {
		$decodedContent = json_decode($content, true);
		if (!is_array($decodedContent) || empty($decodedContent)) {
			throw new \UnexpectedValueException('The file must contain a valid json array');
		}

		$this->validateArray($decodedContent);

		return $decodedContent;
	}

	/**
	 * Validates that the array only contains `system` and `apps`
	 *
	 * @param array $array
	 */
	protected function validateArray($array) {
		$arrayKeys = array_keys($array);
		$additionalKeys = array_diff($arrayKeys, $this->validRootKeys);
		$commonKeys = array_intersect($arrayKeys, $this->validRootKeys);
		if (!empty($additionalKeys)) {
			throw new \UnexpectedValueException('Found invalid entries in root: ' . implode(', ', $additionalKeys));
		}
		if (empty($commonKeys)) {
			throw new \UnexpectedValueException('At least one key of the following is expected: ' . implode(', ', $this->validRootKeys));
		}

		if (isset($array['system'])) {
			if (is_array($array['system'])) {
				foreach ($array['system'] as $name => $value) {
					$this->checkTypeRecursively($value, $name);
				}
			} else {
				throw new \UnexpectedValueException('The system config array is not an array');
			}
		}

		if (isset($array['apps'])) {
			if (is_array($array['apps'])) {
				$this->validateAppsArray($array['apps']);
			} else {
				throw new \UnexpectedValueException('The apps config array is not an array');
			}
		}
	}

	/**
	 * @param mixed $configValue
	 * @param string $configName
	 */
	protected function checkTypeRecursively($configValue, $configName) {
		if (!is_array($configValue) && !is_bool($configValue) && !is_int($configValue) && !is_string($configValue) && !is_null($configValue)) {
			throw new \UnexpectedValueException('Invalid system config value for "' . $configName . '". Only arrays, bools, integers, strings and null (delete) are allowed.');
		}
		if (is_array($configValue)) {
			foreach ($configValue as $key => $value) {
				$this->checkTypeRecursively($value, $configName);
			}
		}
	}

	/**
	 * Validates that app configs are only integers and strings
	 *
	 * @param array $array
	 */
	protected function validateAppsArray($array) {
		foreach ($array as $app => $configs) {
			foreach ($configs as $name => $value) {
				if (!is_int($value) && !is_string($value) && !is_null($value)) {
					throw new \UnexpectedValueException('Invalid app config value for "' . $app . '":"' . $name . '". Only integers, strings and null (delete) are allowed.');
				}
			}
		}
	}

	/**
	 * @param string $optionName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeOptionValues($optionName, CompletionContext $context) {
		return [];
	}

	/**
	 * @param string $argumentName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context) {
		if ($argumentName === 'file') {
			$helper = new ShellPathCompletion(
				$this->getName(),
				'file',
				Completion::TYPE_ARGUMENT
			);
			return $helper->run();
		}
		return [];
	}
}
