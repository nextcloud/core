<?php
/**
 * @copyright Copyright (c) 2017, ownCloud GmbH
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Core\Command\Db\Migrations;

use OC\DB\MigrationService;
use OC\Migration\ConsoleOutput;
use OCP\IDBConnection;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command implements CompletionAwareInterface {

	/** @var IDBConnection */
	private $connection;

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('migrations:status')
			->setDescription('View the status of a set of migrations.')
			->addArgument('app', InputArgument::REQUIRED, 'Name of the app this migration command shall work on');
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$appName = $input->getArgument('app');
		$ms = new MigrationService($appName, $this->connection, new ConsoleOutput($output));

		$infos = $this->getMigrationsInfos($ms);
		foreach ($infos as $key => $value) {
			if (is_array($value)) {
				$output->writeln("    <comment>>></comment> $key:");
				foreach ($value as $subKey => $subValue) {
					$output->writeln("        <comment>>></comment> $subKey: " . str_repeat(' ', 46 - strlen($subKey)) . $subValue);
				}
			} else {
				$output->writeln("    <comment>>></comment> $key: " . str_repeat(' ', 50 - strlen($key)) . $value);
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
		if ($argumentName === 'app') {
			$allApps = \OC_App::getAllApps();
			return array_diff($allApps, \OC_App::getEnabledApps(true, true));
		}
		return [];
	}

	/**
	 * @param MigrationService $ms
	 * @return array associative array of human readable info name as key and the actual information as value
	 */
	public function getMigrationsInfos(MigrationService $ms) {

		$executedMigrations = $ms->getMigratedVersions();
		$availableMigrations = $ms->getAvailableVersions();
		$executedUnavailableMigrations = array_diff($executedMigrations, array_keys($availableMigrations));

		$numExecutedUnavailableMigrations = count($executedUnavailableMigrations);
		$numNewMigrations = count(array_diff(array_keys($availableMigrations), $executedMigrations));
		$pending = $ms->describeMigrationStep('lastest');

		$infos = [
			'App'								=> $ms->getApp(),
			'Version Table Name'				=> $ms->getMigrationsTableName(),
			'Migrations Namespace'				=> $ms->getMigrationsNamespace(),
			'Migrations Directory'				=> $ms->getMigrationsDirectory(),
			'Previous Version'					=> $this->getFormattedVersionAlias($ms, 'prev'),
			'Current Version'					=> $this->getFormattedVersionAlias($ms, 'current'),
			'Next Version'						=> $this->getFormattedVersionAlias($ms, 'next'),
			'Latest Version'					=> $this->getFormattedVersionAlias($ms, 'latest'),
			'Executed Migrations'				=> count($executedMigrations),
			'Executed Unavailable Migrations'	=> $numExecutedUnavailableMigrations,
			'Available Migrations'				=> count($availableMigrations),
			'New Migrations'					=> $numNewMigrations,
			'Pending Migrations'				=> count($pending) ? $pending : 'None'
		];

		return $infos;
	}

	/**
	 * @param MigrationService $migrationService
	 * @param string $alias
	 * @return mixed|null|string
	 */
	private function getFormattedVersionAlias(MigrationService $migrationService, $alias) {
		$migration = $migrationService->getMigration($alias);
		//No version found
		if ($migration === null) {
			if ($alias === 'next') {
				return 'Already at latest migration step';
			}

			if ($alias === 'prev') {
				return 'Already at first migration step';
			}
		}

		return $migration;
	}


}
