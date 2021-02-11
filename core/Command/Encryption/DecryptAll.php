<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author davitol <dtoledo@solidgear.es>
 * @author Evgeny Golyshev <eugulixes@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Marius Blüm <marius@lineone.io>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Ruben Homs <ruben@homs.codes>
 * @author Sergio Bertolín <sbertolin@solidgear.es>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use OCP\App\IAppManager;
use OCP\Encryption\IManager;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DecryptAll extends Command {

	/** @var IManager */
	protected $encryptionManager;

	/** @var  IAppManager */
	protected $appManager;

	/** @var IConfig */
	protected $config;

	/** @var  QuestionHelper */
	protected $questionHelper;

	/** @var bool */
	protected $wasTrashbinEnabled;

	/** @var  bool */
	protected $wasMaintenanceModeEnabled;

	/** @var \OC\Encryption\DecryptAll */
	protected $decryptAll;

	/**
	 * @param IManager $encryptionManager
	 * @param IAppManager $appManager
	 * @param IConfig $config
	 * @param \OC\Encryption\DecryptAll $decryptAll
	 * @param QuestionHelper $questionHelper
	 */
	public function __construct(
		IManager $encryptionManager,
		IAppManager $appManager,
		IConfig $config,
		\OC\Encryption\DecryptAll $decryptAll,
		QuestionHelper $questionHelper
	) {
		parent::__construct();

		$this->appManager = $appManager;
		$this->encryptionManager = $encryptionManager;
		$this->config = $config;
		$this->decryptAll = $decryptAll;
		$this->questionHelper = $questionHelper;
	}

	/**
	 * Set maintenance mode and disable the trashbin app
	 */
	protected function forceMaintenanceAndTrashbin() {
		$this->wasTrashbinEnabled = $this->appManager->isEnabledForUser('files_trashbin');
		$this->wasMaintenanceModeEnabled = $this->config->getSystemValueBool('maintenance');
		$this->config->setSystemValue('maintenance', true);
		$this->appManager->disableApp('files_trashbin');
	}

	/**
	 * Reset the maintenance mode and re-enable the trashbin app
	 */
	protected function resetMaintenanceAndTrashbin() {
		$this->config->setSystemValue('maintenance', $this->wasMaintenanceModeEnabled);
		if ($this->wasTrashbinEnabled) {
			$this->appManager->enableApp('files_trashbin');
		}
	}

	protected function configure() {
		parent::configure();

		$this->setName('encryption:decrypt-all');
		$this->setDescription('Disable server-side encryption and decrypt all files');
		$this->setHelp(
			'This will disable server-side encryption and decrypt all files for '
			. 'all users if it is supported by your encryption module. '
			. 'Please make sure that no user access his files during this process!'
		);
		$this->addArgument(
			'user',
			InputArgument::OPTIONAL,
			'user for which you want to decrypt all files (optional)',
			''
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$input->isInteractive()) {
			$output->writeln('Invalid TTY.');
			$output->writeln('If you are trying to execute the command in a Docker ');
			$output->writeln("container, do not forget to execute 'docker exec' with");
			$output->writeln("the '-i' and '-t' options.");
			$output->writeln('');
			return 1;
		}

		$isMaintenanceModeEnabled = $this->config->getSystemValue('maintenance', false);
		if ($isMaintenanceModeEnabled) {
			$output->writeln("Maintenance mode must be disabled when starting decryption,");
			$output->writeln("in order to load the relevant encryption modules correctly.");
			$output->writeln("Your instance will automatically be put to maintenance mode");
			$output->writeln("during the actual decryption of the files.");
			return 1;
		}

		try {
			if ($this->encryptionManager->isEnabled() === true) {
				$output->write('Disable server side encryption... ');
				$this->config->setAppValue('core', 'encryption_enabled', 'no');
				$output->writeln('done.');
			} else {
				$output->writeln('Server side encryption not enabled. Nothing to do.');
				return 0;
			}

			$uid = $input->getArgument('user');
			if ($uid === '') {
				$message = 'your Nextcloud';
			} else {
				$message = "$uid's account";
			}

			$output->writeln("\n");
			$output->writeln("You are about to start to decrypt all files stored in $message.");
			$output->writeln('It will depend on the encryption module and your setup if this is possible.');
			$output->writeln('Depending on the number and size of your files this can take some time');
			$output->writeln('Please make sure that no user access his files during this process!');
			$output->writeln('');
			$question = new ConfirmationQuestion('Do you really want to continue? (y/n) ', false);
			if ($this->questionHelper->ask($input, $output, $question)) {
				$this->forceMaintenanceAndTrashbin();
				$user = $input->getArgument('user');
				$result = $this->decryptAll->decryptAll($input, $output, $user);
				if ($result === false) {
					$output->writeln(' aborted.');
					$output->writeln('Server side encryption remains enabled');
					$this->config->setAppValue('core', 'encryption_enabled', 'yes');
				} elseif ($uid !== '') {
					$output->writeln('Server side encryption remains enabled');
					$this->config->setAppValue('core', 'encryption_enabled', 'yes');
				}
				$this->resetMaintenanceAndTrashbin();
				return 0;
			} else {
				$output->write('Enable server side encryption... ');
				$this->config->setAppValue('core', 'encryption_enabled', 'yes');
				$output->writeln('done.');
				$output->writeln('aborted');
				return 1;
			}
		} catch (\Exception $e) {
			// enable server side encryption again if something went wrong
			$this->config->setAppValue('core', 'encryption_enabled', 'yes');
			$this->resetMaintenanceAndTrashbin();
			throw $e;
		}
	}
}
