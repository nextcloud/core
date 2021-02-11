<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OC\Console;

use OC\MemoryInfo;
use OC\NeedsUpdateException;
use OC_App;
use OCP\AppFramework\QueryException;
use OCP\Console\ConsoleEvent;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Application {
	/** @var IConfig */
	private $config;
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var IRequest */
	private $request;
	/** @var ILogger  */
	private $logger;
	/** @var MemoryInfo */
	private $memoryInfo;

	/**
	 * @param IConfig $config
	 * @param EventDispatcherInterface $dispatcher
	 * @param IRequest $request
	 * @param ILogger $logger
	 * @param MemoryInfo $memoryInfo
	 */
	public function __construct(IConfig $config,
								EventDispatcherInterface $dispatcher,
								IRequest $request,
								ILogger $logger,
								MemoryInfo $memoryInfo) {
		$defaults = \OC::$server->getThemingDefaults();
		$this->config = $config;
		$this->application = new SymfonyApplication($defaults->getName(), \OC_Util::getVersionString());
		$this->dispatcher = $dispatcher;
		$this->request = $request;
		$this->logger = $logger;
		$this->memoryInfo = $memoryInfo;
	}

	/**
	 * @param InputInterface $input
	 * @param ConsoleOutputInterface $output
	 * @throws \Exception
	 */
	public function loadCommands(
		InputInterface $input,
		ConsoleOutputInterface $output
	) {
		// $application is required to be defined in the register_command scripts
		$application = $this->application;
		$inputDefinition = $application->getDefinition();
		$inputDefinition->addOption(
			new InputOption(
				'no-warnings',
				null,
				InputOption::VALUE_NONE,
				'Skip global warnings, show command output only',
				null
			)
		);
		try {
			$input->bind($inputDefinition);
		} catch (\RuntimeException $e) {
			//expected if there are extra options
		}
		if ($input->getOption('no-warnings')) {
			$output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
		}

		if ($this->memoryInfo->isMemoryLimitSufficient() === false) {
			$output->getErrorOutput()->writeln(
				'<comment>The current PHP memory limit ' .
				'is below the recommended value of 512MB.</comment>'
			);
		}

		try {
			require_once __DIR__ . '/../../../core/register_command.php';
			if ($this->config->getSystemValue('installed', false)) {
				if (\OCP\Util::needUpgrade()) {
					throw new NeedsUpdateException();
				} elseif ($this->config->getSystemValueBool('maintenance')) {
					$this->writeMaintenanceModeInfo($input, $output);
				} else {
					OC_App::loadApps();
					foreach (\OC::$server->getAppManager()->getInstalledApps() as $app) {
						$appPath = \OC_App::getAppPath($app);
						if ($appPath === false) {
							continue;
						}
						// load commands using info.xml
						$info = \OC_App::getAppInfo($app);
						if (isset($info['commands'])) {
							$this->loadCommandsFromInfoXml($info['commands']);
						}
						// load from register_command.php
						\OC_App::registerAutoloading($app, $appPath);
						$file = $appPath . '/appinfo/register_command.php';
						if (file_exists($file)) {
							try {
								require $file;
							} catch (\Exception $e) {
								$this->logger->logException($e);
							}
						}
					}
				}
			} elseif ($input->getArgument('command') !== '_completion' && $input->getArgument('command') !== 'maintenance:install') {
				$output->writeln("Nextcloud is not installed - only a limited number of commands are available");
			}
		} catch (NeedsUpdateException $e) {
			if ($input->getArgument('command') !== '_completion') {
				$output->writeln("Nextcloud or one of the apps require upgrade - only a limited number of commands are available");
				$output->writeln("You may use your browser or the occ upgrade command to do the upgrade");
			}
		}

		if ($input->getFirstArgument() !== 'check') {
			$errors = \OC_Util::checkServer(\OC::$server->getSystemConfig());
			if (!empty($errors)) {
				foreach ($errors as $error) {
					$output->writeln((string)$error['error']);
					$output->writeln((string)$error['hint']);
					$output->writeln('');
				}
				throw new \Exception("Environment not properly prepared.");
			}
		}
	}

	/**
	 * Write a maintenance mode info.
	 * The commands "_completion" and "maintenance:mode" are excluded.
	 *
	 * @param InputInterface $input The input implementation for reading inputs.
	 * @param ConsoleOutputInterface $output The output implementation
	 * for writing outputs.
	 * @return void
	 */
	private function writeMaintenanceModeInfo(
		InputInterface $input, ConsoleOutputInterface $output
	) {
		if ($input->getArgument('command') !== '_completion'
			&& $input->getArgument('command') !== 'maintenance:mode') {
			$errOutput = $output->getErrorOutput();
			$errOutput->writeln(
				'<comment>Nextcloud is in maintenance mode - ' .
				'no apps have been loaded</comment>' . PHP_EOL
			);
		}
	}

	/**
	 * Sets whether to automatically exit after a command execution or not.
	 *
	 * @param bool $boolean Whether to automatically exit after a command execution or not
	 */
	public function setAutoExit($boolean) {
		$this->application->setAutoExit($boolean);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws \Exception
	 */
	public function run(InputInterface $input = null, OutputInterface $output = null) {
		$this->dispatcher->dispatch(ConsoleEvent::EVENT_RUN, new ConsoleEvent(
			ConsoleEvent::EVENT_RUN,
			$this->request->server['argv']
		));
		return $this->application->run($input, $output);
	}

	private function loadCommandsFromInfoXml($commands) {
		foreach ($commands as $command) {
			try {
				$c = \OC::$server->query($command);
			} catch (QueryException $e) {
				if (class_exists($command)) {
					try {
						$c = new $command();
					} catch (\ArgumentCountError $e2) {
						throw new \Exception("Failed to construct console command '$command': " . $e->getMessage(), 0, $e);
					}
				} else {
					throw new \Exception("Console command '$command' is unknown and could not be loaded");
				}
			}

			$this->application->add($c);
		}
	}
}
