<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Ruben Homs <ruben@homs.codes>
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

namespace OC\Core\Command\Encryption;

use OCP\Encryption\IManager;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetDefaultModule extends Command {
	/** @var IManager */
	protected $encryptionManager;

	/** @var IConfig */
	protected $config;

	/**
	 * @param IManager $encryptionManager
	 * @param IConfig $config
	 */
	public function __construct(
		IManager $encryptionManager,
		IConfig $config
	) {
		parent::__construct();
		$this->encryptionManager = $encryptionManager;
		$this->config = $config;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('encryption:set-default-module')
			->setDescription('Set the encryption default module')
			->addArgument(
				'module',
				InputArgument::REQUIRED,
				'ID of the encryption module that should be used'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$isMaintenanceModeEnabled = $this->config->getSystemValue('maintenance', false);
		if ($isMaintenanceModeEnabled) {
			$output->writeln("Maintenance mode must be disabled when setting default module,");
			$output->writeln("in order to load the relevant encryption modules correctly.");
			return;
		}

		$moduleId = $input->getArgument('module');

		if ($moduleId === $this->encryptionManager->getDefaultEncryptionModuleId()) {
			$output->writeln('"' . $moduleId . '"" is already the default module');
		} else if ($this->encryptionManager->setDefaultEncryptionModule($moduleId)) {
			$output->writeln('<info>Set default module to "' . $moduleId . '"</info>');
		} else {
			$output->writeln('<error>The specified module "' . $moduleId . '" does not exist</error>');
		}
	}
}
