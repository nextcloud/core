<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Ostertag <bernieo.code@gmx.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Łukasz Buśko <busko.lukasz@pm.me>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sander Ruitenbeek <sander@grids.be>
 * @author tbelau666 <thomas.belau@gmx.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Core\Command\Db;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Table;
use OCP\DB\Types;
use OC\DB\Connection;
use OC\DB\ConnectionFactory;
use OC\DB\MigrationService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use function preg_match;
use function preg_quote;

class ConvertType extends Command implements CompletionAwareInterface {
	/**
	 * @var \OCP\IConfig
	 */
	protected $config;

	/**
	 * @var \OC\DB\ConnectionFactory
	 */
	protected $connectionFactory;

	/** @var array */
	protected $columnTypes;

	/**
	 * @param \OCP\IConfig $config
	 * @param \OC\DB\ConnectionFactory $connectionFactory
	 */
	public function __construct(IConfig $config, ConnectionFactory $connectionFactory) {
		$this->config = $config;
		$this->connectionFactory = $connectionFactory;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('db:convert-type')
			->setDescription('Convert the Nextcloud database to the newly configured one')
			->addArgument(
				'type',
				InputArgument::REQUIRED,
				'the type of the database to convert to'
			)
			->addArgument(
				'username',
				InputArgument::REQUIRED,
				'the username of the database to convert to'
			)
			->addArgument(
				'hostname',
				InputArgument::REQUIRED,
				'the hostname of the database to convert to'
			)
			->addArgument(
				'database',
				InputArgument::REQUIRED,
				'the name of the database to convert to'
			)
			->addOption(
				'port',
				null,
				InputOption::VALUE_REQUIRED,
				'the port of the database to convert to'
			)
			->addOption(
				'password',
				null,
				InputOption::VALUE_REQUIRED,
				'the password of the database to convert to. Will be asked when not specified. Can also be passed via stdin.'
			)
			->addOption(
				'clear-schema',
				null,
				InputOption::VALUE_NONE,
				'remove all tables from the destination database'
			)
			->addOption(
				'all-apps',
				null,
				InputOption::VALUE_NONE,
				'whether to create schema for all apps instead of only installed apps'
			)
			->addOption(
				'chunk-size',
				null,
				InputOption::VALUE_REQUIRED,
				'the maximum number of database rows to handle in a single query, bigger tables will be handled in chunks of this size. Lower this if the process runs out of memory during conversion.',
				1000
			)
		;
	}

	protected function validateInput(InputInterface $input, OutputInterface $output) {
		$type = $this->connectionFactory->normalizeType($input->getArgument('type'));
		if ($type === 'sqlite3') {
			throw new \InvalidArgumentException(
				'Converting to SQLite (sqlite3) is currently not supported.'
			);
		}
		if ($type === $this->config->getSystemValue('dbtype', '')) {
			throw new \InvalidArgumentException(sprintf(
				'Can not convert from %1$s to %1$s.',
				$type
			));
		}
		if ($type === 'oci' && $input->getOption('clear-schema')) {
			// Doctrine unconditionally tries (at least in version 2.3)
			// to drop sequence triggers when dropping a table, even though
			// such triggers may not exist. This results in errors like
			// "ORA-04080: trigger 'OC_STORAGES_AI_PK' does not exist".
			throw new \InvalidArgumentException(
				'The --clear-schema option is not supported when converting to Oracle (oci).'
			);
		}
	}

	protected function readPassword(InputInterface $input, OutputInterface $output) {
		// Explicitly specified password
		if ($input->getOption('password')) {
			return;
		}

		// Read from stdin. stream_set_blocking is used to prevent blocking
		// when nothing is passed via stdin.
		stream_set_blocking(STDIN, 0);
		$password = file_get_contents('php://stdin');
		stream_set_blocking(STDIN, 1);
		if (trim($password) !== '') {
			$input->setOption('password', $password);
			return;
		}

		// Read password by interacting
		if ($input->isInteractive()) {
			/** @var QuestionHelper $helper */
			$helper = $this->getHelper('question');
			$question = new Question('What is the database password?');
			$question->setHidden(true);
			$question->setHiddenFallback(false);
			$password = $helper->ask($input, $output, $question);
			$input->setOption('password', $password);
			return;
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->validateInput($input, $output);
		$this->readPassword($input, $output);

		/** @var Connection $fromDB */
		$fromDB = \OC::$server->get(Connection::class);
		$toDB = $this->getToDBConnection($input, $output);

		if ($input->getOption('clear-schema')) {
			$this->clearSchema($toDB, $input, $output);
		}

		$this->createSchema($fromDB, $toDB, $input, $output);

		$toTables = $this->getTables($toDB);
		$fromTables = $this->getTables($fromDB);

		// warn/fail if there are more tables in 'from' database
		$extraFromTables = array_diff($fromTables, $toTables);
		if (!empty($extraFromTables)) {
			$output->writeln('<comment>The following tables will not be converted:</comment>');
			$output->writeln($extraFromTables);
			if (!$input->getOption('all-apps')) {
				$output->writeln('<comment>Please note that tables belonging to available but currently not installed apps</comment>');
				$output->writeln('<comment>can be included by specifying the --all-apps option.</comment>');
			}

			$continueConversion = !$input->isInteractive(); // assume yes for --no-interaction and no otherwise.
			$question = new ConfirmationQuestion('Continue with the conversion (y/n)? [n] ', $continueConversion);

			/** @var QuestionHelper $helper */
			$helper = $this->getHelper('question');

			if (!$helper->ask($input, $output, $question)) {
				return 1;
			}
		}
		$intersectingTables = array_intersect($toTables, $fromTables);
		$this->convertDB($fromDB, $toDB, $intersectingTables, $input, $output);
		return 0;
	}

	protected function createSchema(Connection $fromDB, Connection $toDB, InputInterface $input, OutputInterface $output) {
		$output->writeln('<info>Creating schema in new database</info>');

		$fromMS = new MigrationService('core', $fromDB);
		$currentMigration = $fromMS->getMigration('current');
		if ($currentMigration !== '0') {
			$toMS = new MigrationService('core', $toDB);
			$toMS->migrate($currentMigration);
		}

		$schemaManager = new \OC\DB\MDB2SchemaManager($toDB);
		$apps = $input->getOption('all-apps') ? \OC_App::getAllApps() : \OC_App::getEnabledApps();
		foreach ($apps as $app) {
			if (file_exists(\OC_App::getAppPath($app).'/appinfo/database.xml')) {
				$schemaManager->createDbFromStructure(\OC_App::getAppPath($app).'/appinfo/database.xml');
			} else {
				// Make sure autoloading works...
				\OC_App::loadApp($app);
				$fromMS = new MigrationService($app, $fromDB);
				$currentMigration = $fromMS->getMigration('current');
				if ($currentMigration !== '0') {
					$toMS = new MigrationService($app, $toDB);
					$toMS->migrate($currentMigration, true);
				}
			}
		}
	}

	protected function getToDBConnection(InputInterface $input, OutputInterface $output) {
		$type = $input->getArgument('type');
		$connectionParams = $this->connectionFactory->createConnectionParams();
		$connectionParams = array_merge($connectionParams, [
			'host' => $input->getArgument('hostname'),
			'user' => $input->getArgument('username'),
			'password' => $input->getOption('password'),
			'dbname' => $input->getArgument('database'),
		]);
		if ($input->getOption('port')) {
			$connectionParams['port'] = $input->getOption('port');
		}
		return $this->connectionFactory->getConnection($type, $connectionParams);
	}

	protected function clearSchema(Connection $db, InputInterface $input, OutputInterface $output) {
		$toTables = $this->getTables($db);
		if (!empty($toTables)) {
			$output->writeln('<info>Clearing schema in new database</info>');
		}
		foreach ($toTables as $table) {
			$db->getSchemaManager()->dropTable($table);
		}
	}

	protected function getTables(Connection $db) {
		$db->getConfiguration()->setSchemaAssetsFilter(function ($asset) {
			/** @var string|AbstractAsset $asset */
			$filterExpression = '/^' . preg_quote($this->config->getSystemValue('dbtableprefix', 'oc_')) . '/';
			if ($asset instanceof AbstractAsset) {
				return preg_match($filterExpression, $asset->getName()) !== false;
			}
			return preg_match($filterExpression, $asset) !== false;
		});
		return $db->getSchemaManager()->listTableNames();
	}

	/**
	 * @param Connection $fromDB
	 * @param Connection $toDB
	 * @param Table $table
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function copyTable(Connection $fromDB, Connection $toDB, Table $table, InputInterface $input, OutputInterface $output) {
		if ($table->getName() === $toDB->getPrefix() . 'migrations') {
			$output->writeln('<comment>Skipping migrations table because it was already filled by running the migrations</comment>');
			return;
		}

		$chunkSize = $input->getOption('chunk-size');

		$query = $fromDB->getQueryBuilder();
		$query->automaticTablePrefix(false);
		$query->select($query->func()->count('*', 'num_entries'))
			->from($table->getName());
		$result = $query->execute();
		$count = $result->fetchOne();
		$result->closeCursor();

		$numChunks = ceil($count / $chunkSize);
		if ($numChunks > 1) {
			$output->writeln('chunked query, ' . $numChunks . ' chunks');
		}

		$progress = new ProgressBar($output, $count);
		$progress->start();
		$redraw = $count > $chunkSize ? 100 : ($count > 100 ? 5 : 1);
		$progress->setRedrawFrequency($redraw);

		$query = $fromDB->getQueryBuilder();
		$query->automaticTablePrefix(false);
		$query->select('*')
			->from($table->getName())
			->setMaxResults($chunkSize);

		try {
			$orderColumns = $table->getPrimaryKeyColumns();
		} catch (Exception $e) {
			$orderColumns = [];
			foreach ($table->getColumns() as $column) {
				$orderColumns[] = $column->getName();
			}
		}

		foreach ($orderColumns as $column) {
			$query->addOrderBy($column);
		}

		$insertQuery = $toDB->getQueryBuilder();
		$insertQuery->automaticTablePrefix(false);
		$insertQuery->insert($table->getName());
		$parametersCreated = false;

		for ($chunk = 0; $chunk < $numChunks; $chunk++) {
			$query->setFirstResult($chunk * $chunkSize);

			$result = $query->execute();

			while ($row = $result->fetch()) {
				$progress->advance();
				if (!$parametersCreated) {
					foreach ($row as $key => $value) {
						$insertQuery->setValue($key, $insertQuery->createParameter($key));
					}
					$parametersCreated = true;
				}

				foreach ($row as $key => $value) {
					$type = $this->getColumnType($table, $key);
					if ($type !== false) {
						$insertQuery->setParameter($key, $value, $type);
					} else {
						$insertQuery->setParameter($key, $value);
					}
				}
				$insertQuery->execute();
			}
			$result->closeCursor();
		}
		$progress->finish();
	}

	protected function getColumnType(Table $table, $columnName) {
		$tableName = $table->getName();
		if (isset($this->columnTypes[$tableName][$columnName])) {
			return $this->columnTypes[$tableName][$columnName];
		}

		$type = $table->getColumn($columnName)->getType()->getName();

		switch ($type) {
			case Types::BLOB:
			case Types::TEXT:
				$this->columnTypes[$tableName][$columnName] = IQueryBuilder::PARAM_LOB;
				break;
			case Types::BOOLEAN:
				$this->columnTypes[$tableName][$columnName] = IQueryBuilder::PARAM_BOOL;
				break;
			default:
				$this->columnTypes[$tableName][$columnName] = false;
		}

		return $this->columnTypes[$tableName][$columnName];
	}

	protected function convertDB(Connection $fromDB, Connection $toDB, array $tables, InputInterface $input, OutputInterface $output) {
		$this->config->setSystemValue('maintenance', true);
		$schema = $fromDB->createSchema();

		try {
			// copy table rows
			foreach ($tables as $table) {
				$output->writeln($table);
				$this->copyTable($fromDB, $toDB, $schema->getTable($table), $input, $output);
			}
			if ($input->getArgument('type') === 'pgsql') {
				$tools = new \OC\DB\PgSqlTools($this->config);
				$tools->resynchronizeDatabaseSequences($toDB);
			}
			// save new database config
			$this->saveDBInfo($input);
		} catch (\Exception $e) {
			$this->config->setSystemValue('maintenance', false);
			throw $e;
		}
		$this->config->setSystemValue('maintenance', false);
	}

	protected function saveDBInfo(InputInterface $input) {
		$type = $input->getArgument('type');
		$username = $input->getArgument('username');
		$dbHost = $input->getArgument('hostname');
		$dbName = $input->getArgument('database');
		$password = $input->getOption('password');
		if ($input->getOption('port')) {
			$dbHost .= ':'.$input->getOption('port');
		}

		$this->config->setSystemValues([
			'dbtype' => $type,
			'dbname' => $dbName,
			'dbhost' => $dbHost,
			'dbuser' => $username,
			'dbpassword' => $password,
		]);
	}

	/**
	 * Return possible values for the named option
	 *
	 * @param string $optionName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeOptionValues($optionName, CompletionContext $context) {
		return [];
	}

	/**
	 * Return possible values for the named argument
	 *
	 * @param string $argumentName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context) {
		if ($argumentName === 'type') {
			return ['mysql', 'oci', 'pgsql'];
		}
		return [];
	}
}
