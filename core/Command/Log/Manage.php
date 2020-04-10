<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Johannes Ernst <jernst@indiecomputing.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Pulzer <t.pulzer@kniel.de>
 * @author Tim Terhorst <mynamewastaken+gitlab@gmail.com>
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

namespace OC\Core\Command\Log;

use OCP\IConfig;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Manage extends Command implements CompletionAwareInterface {

	const DEFAULT_BACKEND = 'file';
	const DEFAULT_LOG_LEVEL = 2;
	const DEFAULT_TIMEZONE = 'UTC';

	/** @var IConfig */
	protected $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('log:manage')
			->setDescription('manage logging configuration')
			->addOption(
				'backend',
				null,
				InputOption::VALUE_REQUIRED,
				'set the logging backend [file, syslog, errorlog, systemd]'
			)
			->addOption(
				'level',
				null,
				InputOption::VALUE_REQUIRED,
				'set the log level [debug, info, warning, error, fatal]'
			)
			->addOption(
				'timezone',
				null,
				InputOption::VALUE_REQUIRED,
				'set the logging timezone'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// collate config setting to the end, to avoid partial configuration
		$toBeSet = [];

		if ($backend = $input->getOption('backend')) {
			$this->validateBackend($backend);
			$toBeSet['log_type'] = $backend;
		}

		$level = $input->getOption('level');
		if ($level !== null) {
			if (is_numeric($level)) {
				$levelNum = $level;
				// sanity check
				$this->convertLevelNumber($levelNum);
			} else {
				$levelNum = $this->convertLevelString($level);
			}
			$toBeSet['loglevel'] = $levelNum;
		}

		if ($timezone = $input->getOption('timezone')) {
			$this->validateTimezone($timezone);
			$toBeSet['logtimezone'] = $timezone;
		}

		// set config
		foreach ($toBeSet as $option => $value) {
			$this->config->setSystemValue($option, $value);
		}

		// display configuration
		$backend = $this->config->getSystemValue('log_type', self::DEFAULT_BACKEND);
		$output->writeln('Enabled logging backend: '.$backend);

		$levelNum = $this->config->getSystemValue('loglevel', self::DEFAULT_LOG_LEVEL);
		$level = $this->convertLevelNumber($levelNum);
		$output->writeln('Log level: '.$level.' ('.$levelNum.')');

		$timezone = $this->config->getSystemValue('logtimezone', self::DEFAULT_TIMEZONE);
		$output->writeln('Log timezone: '.$timezone);
	}

	/**
	 * @param string $backend
	 * @throws \InvalidArgumentException
	 */
	protected function validateBackend($backend) {
		if (!class_exists('OC\\Log\\'.ucfirst($backend))) {
			throw new \InvalidArgumentException('Invalid backend');
		}
	}

	/**
	 * @param string $timezone
	 * @throws \Exception
	 */
	protected function validateTimezone($timezone) {
		new \DateTimeZone($timezone);
	}

	/**
	 * @param string $level
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	protected function convertLevelString($level) {
		$level = strtolower($level);
		switch ($level) {
		case 'debug':
			return 0;
		case 'info':
			return 1;
		case 'warning':
		case 'warn':
			return 2;
		case 'error':
		case 'err':
			return 3;
		case 'fatal':
			return 4;
		}
		throw new \InvalidArgumentException('Invalid log level string');
	}

	/**
	 * @param int $levelNum
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function convertLevelNumber($levelNum) {
		switch ($levelNum) {
		case 0:
			return 'Debug';
		case 1:
			return 'Info';
		case 2:
			return 'Warning';
		case 3:
			return 'Error';
		case 4:
			return 'Fatal';
		}
		throw new \InvalidArgumentException('Invalid log level number');
	}

	/**
	 * @param string $optionName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeOptionValues($optionName, CompletionContext $context) {
		if ($optionName === 'backend') {
			return ['file', 'syslog', 'errorlog', 'systemd'];
		} else if ($optionName === 'level') {
			return ['debug', 'info', 'warning', 'error', 'fatal'];
		} else if ($optionName === 'timezone') {
			return \DateTimeZone::listIdentifiers();
		}
		return [];
	}

	/**
	 * @param string $argumentName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context) {
		return [];
	}
}
