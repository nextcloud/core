<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christian Kampka <christian@kampka.net>
 * @author Daniel Hansson <daniel@techandme.se>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Pulzer <t.pulzer@kniel.de>
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

namespace OC\Core\Command\Maintenance;

use InvalidArgumentException;
use OC\Installer;
use OC\Setup;
use OC\SystemConfig;
use OCP\Defaults;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Install extends Command {

	/**
	 * @var SystemConfig
	 */
	private $config;

	public function __construct(SystemConfig $config) {
		parent::__construct();
		$this->config = $config;
	}

	protected function configure() {
		$this
			->setName('maintenance:install')
			->setDescription('install Nextcloud')
			->addOption('database', null, InputOption::VALUE_REQUIRED, 'Supported database type', 'sqlite')
			->addOption('database-name', null, InputOption::VALUE_REQUIRED, 'Name of the database')
			->addOption('database-host', null, InputOption::VALUE_REQUIRED, 'Hostname of the database', 'localhost')
			->addOption('database-port', null, InputOption::VALUE_REQUIRED, 'Port the database is listening on')
			->addOption('database-user', null, InputOption::VALUE_REQUIRED, 'User name to connect to the database')
			->addOption('database-pass', null, InputOption::VALUE_OPTIONAL, 'Password of the database user', null)
			->addOption('database-table-prefix', null, InputOption::VALUE_OPTIONAL, 'Prefix for all tables (default: oc_)', null)
			->addOption('database-table-space', null, InputOption::VALUE_OPTIONAL, 'Table space of the database (oci only)', null)
			->addOption('admin-user', null, InputOption::VALUE_REQUIRED, 'User name of the admin account', 'admin')
			->addOption('admin-pass', null, InputOption::VALUE_REQUIRED, 'Password of the admin account')
			->addOption('admin-email', null, InputOption::VALUE_OPTIONAL, 'E-Mail of the admin account')
			->addOption('data-dir', null, InputOption::VALUE_REQUIRED, 'Path to data directory', \OC::$SERVERROOT."/data");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		// validate the environment
		$server = \OC::$server;
		$setupHelper = new Setup(
			$this->config,
			$server->getIniWrapper(),
			$server->getL10N('lib'),
			$server->query(Defaults::class),
			$server->getLogger(),
			$server->getSecureRandom(),
			\OC::$server->query(Installer::class)
		);
		$sysInfo = $setupHelper->getSystemInfo(true);
		$errors = $sysInfo['errors'];
		if (count($errors) > 0) {
			$this->printErrors($output, $errors);

			// ignore the OS X setup warning
			if(count($errors) !== 1 ||
				(string)$errors[0]['error'] !== 'Mac OS X is not supported and Nextcloud will not work properly on this platform. Use it at your own risk! ') {
				return 1;
			}
		}

		// validate user input
		$options = $this->validateInput($input, $output, array_keys($sysInfo['databases']));

		// perform installation
		$errors = $setupHelper->install($options);
		if (count($errors) > 0) {
			$this->printErrors($output, $errors);
			return 1;
		}
		$output->writeln("Nextcloud was successfully installed");
		return 0;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param string[] $supportedDatabases
	 * @return array
	 */
	protected function validateInput(InputInterface $input, OutputInterface $output, $supportedDatabases) {
		$db = strtolower($input->getOption('database'));

		if (!in_array($db, $supportedDatabases)) {
			throw new InvalidArgumentException("Database <$db> is not supported.");
		}

		$dbUser = $input->getOption('database-user');
		$dbPass = $input->getOption('database-pass');
		$dbName = $input->getOption('database-name');
		$dbPort = $input->getOption('database-port');
		if ($db === 'oci') {
			// an empty hostname needs to be read from the raw parameters
			$dbHost = $input->getParameterOption('--database-host', '');
		} else {
			$dbHost = $input->getOption('database-host');
		}
		if ($dbPort) {
			// Append the port to the host so it is the same as in the config (there is no dbport config)
			$dbHost .= ':' . $dbPort;
		}
		$dbTablePrefix = 'oc_';
		if ($input->hasParameterOption('--database-table-prefix')) {
			$dbTablePrefix = (string) $input->getOption('database-table-prefix');
			$dbTablePrefix = trim($dbTablePrefix);
		}
		if ($input->hasParameterOption('--database-pass')) {
			$dbPass = (string) $input->getOption('database-pass');
		}
		$adminLogin = $input->getOption('admin-user');
		$adminPassword = $input->getOption('admin-pass');
		$adminEmail = $input->getOption('admin-email');
		$dataDir = $input->getOption('data-dir');

		if ($db !== 'sqlite') {
			if (is_null($dbUser)) {
				throw new InvalidArgumentException("Database user not provided.");
			}
			if (is_null($dbName)) {
				throw new InvalidArgumentException("Database name not provided.");
			}
			if (is_null($dbPass)) {
				/** @var QuestionHelper $helper */
				$helper = $this->getHelper('question');
				$question = new Question('What is the password to access the database with user <'.$dbUser.'>?');
				$question->setHidden(true);
				$question->setHiddenFallback(false);
				$dbPass = $helper->ask($input, $output, $question);
			}
		}

		if (is_null($adminPassword)) {
			/** @var QuestionHelper $helper */
			$helper = $this->getHelper('question');
			$question = new Question('What is the password you like to use for the admin account <'.$adminLogin.'>?');
			$question->setHidden(true);
			$question->setHiddenFallback(false);
			$adminPassword = $helper->ask($input, $output, $question);
		}

		if ($adminEmail !== null && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
			throw new InvalidArgumentException('Invalid e-mail-address <' . $adminEmail . '> for <' . $adminLogin . '>.');
		}

		$options = [
			'dbtype' => $db,
			'dbuser' => $dbUser,
			'dbpass' => $dbPass,
			'dbname' => $dbName,
			'dbhost' => $dbHost,
			'dbtableprefix' => $dbTablePrefix,
			'adminlogin' => $adminLogin,
			'adminpass' => $adminPassword,
			'adminemail' => $adminEmail,
			'directory' => $dataDir
		];
		if ($db === 'oci') {
			$options['dbtablespace'] = $input->getParameterOption('--database-table-space', '');
		}
		return $options;
	}

	/**
	 * @param OutputInterface $output
	 * @param $errors
	 */
	protected function printErrors(OutputInterface $output, $errors) {
		foreach ($errors as $error) {
			if (is_array($error)) {
				$output->writeln('<error>' . (string)$error['error'] . '</error>');
				$output->writeln('<info> -> ' . (string)$error['hint'] . '</info>');
			} else {
				$output->writeln('<error>' . (string)$error . '</error>');
			}
		}
	}
}
