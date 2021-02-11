<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2017, ownCloud GmbH
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\DB;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use OC\App\InfoParser;
use OC\IntegrityCheck\Helpers\AppLocator;
use OC\Migration\SimpleOutput;
use OCP\AppFramework\App;
use OCP\AppFramework\QueryException;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class MigrationService {

	/** @var boolean */
	private $migrationTableCreated;
	/** @var array */
	private $migrations;
	/** @var IOutput */
	private $output;
	/** @var Connection */
	private $connection;
	/** @var string */
	private $appName;
	/** @var bool */
	private $checkOracle;

	/**
	 * MigrationService constructor.
	 *
	 * @param $appName
	 * @param Connection $connection
	 * @param AppLocator $appLocator
	 * @param IOutput|null $output
	 * @throws \Exception
	 */
	public function __construct($appName, Connection $connection, IOutput $output = null, AppLocator $appLocator = null) {
		$this->appName = $appName;
		$this->connection = $connection;
		$this->output = $output;
		if (null === $this->output) {
			$this->output = new SimpleOutput(\OC::$server->getLogger(), $appName);
		}

		if ($appName === 'core') {
			$this->migrationsPath = \OC::$SERVERROOT . '/core/Migrations';
			$this->migrationsNamespace = 'OC\\Core\\Migrations';
			$this->checkOracle = true;
		} else {
			if (null === $appLocator) {
				$appLocator = new AppLocator();
			}
			$appPath = $appLocator->getAppPath($appName);
			$namespace = App::buildAppNamespace($appName);
			$this->migrationsPath = "$appPath/lib/Migration";
			$this->migrationsNamespace = $namespace . '\\Migration';

			$infoParser = new InfoParser();
			$info = $infoParser->parse($appPath . '/appinfo/info.xml');
			if (!isset($info['dependencies']['database'])) {
				$this->checkOracle = true;
			} else {
				$this->checkOracle = false;
				foreach ($info['dependencies']['database'] as $database) {
					if (\is_string($database) && $database === 'oci') {
						$this->checkOracle = true;
					} elseif (\is_array($database) && isset($database['@value']) && $database['@value'] === 'oci') {
						$this->checkOracle = true;
					}
				}
			}
		}
	}

	/**
	 * Returns the name of the app for which this migration is executed
	 *
	 * @return string
	 */
	public function getApp() {
		return $this->appName;
	}

	/**
	 * @return bool
	 * @codeCoverageIgnore - this will implicitly tested on installation
	 */
	private function createMigrationTable() {
		if ($this->migrationTableCreated) {
			return false;
		}

		if ($this->connection->tableExists('migrations') && \OC::$server->getConfig()->getAppValue('core', 'vendor', '') !== 'owncloud') {
			$this->migrationTableCreated = true;
			return false;
		}

		$schema = new SchemaWrapper($this->connection);

		/**
		 * We drop the table when it has different columns or the definition does not
		 * match. E.g. ownCloud uses a length of 177 for app and 14 for version.
		 */
		try {
			$table = $schema->getTable('migrations');
			$columns = $table->getColumns();

			if (count($columns) === 2) {
				try {
					$column = $table->getColumn('app');
					$schemaMismatch = $column->getLength() !== 255;

					if (!$schemaMismatch) {
						$column = $table->getColumn('version');
						$schemaMismatch = $column->getLength() !== 255;
					}
				} catch (SchemaException $e) {
					// One of the columns is missing
					$schemaMismatch = true;
				}

				if (!$schemaMismatch) {
					// Table exists and schema matches: return back!
					$this->migrationTableCreated = true;
					return false;
				}
			}

			// Drop the table, when it didn't match our expectations.
			$this->connection->dropTable('migrations');

			// Recreate the schema after the table was dropped.
			$schema = new SchemaWrapper($this->connection);
		} catch (SchemaException $e) {
			// Table not found, no need to panic, we will create it.
		}

		$table = $schema->createTable('migrations');
		$table->addColumn('app', Types::STRING, ['length' => 255]);
		$table->addColumn('version', Types::STRING, ['length' => 255]);
		$table->setPrimaryKey(['app', 'version']);

		$this->connection->migrateToSchema($schema->getWrappedSchema());

		$this->migrationTableCreated = true;

		return true;
	}

	/**
	 * Returns all versions which have already been applied
	 *
	 * @return string[]
	 * @codeCoverageIgnore - no need to test this
	 */
	public function getMigratedVersions() {
		$this->createMigrationTable();
		$qb = $this->connection->getQueryBuilder();

		$qb->select('version')
			->from('migrations')
			->where($qb->expr()->eq('app', $qb->createNamedParameter($this->getApp())))
			->orderBy('version');

		$result = $qb->execute();
		$rows = $result->fetchAll(\PDO::FETCH_COLUMN);
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Returns all versions which are available in the migration folder
	 *
	 * @return array
	 */
	public function getAvailableVersions() {
		$this->ensureMigrationsAreLoaded();
		return array_map('strval', array_keys($this->migrations));
	}

	protected function findMigrations() {
		$directory = realpath($this->migrationsPath);
		if ($directory === false || !file_exists($directory) || !is_dir($directory)) {
			return [];
		}

		$iterator = new \RegexIterator(
			new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			),
			'#^.+\\/Version[^\\/]{1,255}\\.php$#i',
			\RegexIterator::GET_MATCH);

		$files = array_keys(iterator_to_array($iterator));
		uasort($files, function ($a, $b) {
			preg_match('/^Version(\d+)Date(\d+)\\.php$/', basename($a), $matchA);
			preg_match('/^Version(\d+)Date(\d+)\\.php$/', basename($b), $matchB);
			if (!empty($matchA) && !empty($matchB)) {
				if ($matchA[1] !== $matchB[1]) {
					return ($matchA[1] < $matchB[1]) ? -1 : 1;
				}
				return ($matchA[2] < $matchB[2]) ? -1 : 1;
			}
			return (basename($a) < basename($b)) ? -1 : 1;
		});

		$migrations = [];

		foreach ($files as $file) {
			$className = basename($file, '.php');
			$version = (string) substr($className, 7);
			if ($version === '0') {
				throw new \InvalidArgumentException(
					"Cannot load a migrations with the name '$version' because it is a reserved number"
				);
			}
			$migrations[$version] = sprintf('%s\\%s', $this->migrationsNamespace, $className);
		}

		return $migrations;
	}

	/**
	 * @param string $to
	 * @return string[]
	 */
	private function getMigrationsToExecute($to) {
		$knownMigrations = $this->getMigratedVersions();
		$availableMigrations = $this->getAvailableVersions();

		$toBeExecuted = [];
		foreach ($availableMigrations as $v) {
			if ($to !== 'latest' && $v > $to) {
				continue;
			}
			if ($this->shallBeExecuted($v, $knownMigrations)) {
				$toBeExecuted[] = $v;
			}
		}

		return $toBeExecuted;
	}

	/**
	 * @param string $m
	 * @param string[] $knownMigrations
	 * @return bool
	 */
	private function shallBeExecuted($m, $knownMigrations) {
		if (in_array($m, $knownMigrations)) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $version
	 */
	private function markAsExecuted($version) {
		$this->connection->insertIfNotExist('*PREFIX*migrations', [
			'app' => $this->appName,
			'version' => $version
		]);
	}

	/**
	 * Returns the name of the table which holds the already applied versions
	 *
	 * @return string
	 */
	public function getMigrationsTableName() {
		return $this->connection->getPrefix() . 'migrations';
	}

	/**
	 * Returns the namespace of the version classes
	 *
	 * @return string
	 */
	public function getMigrationsNamespace() {
		return $this->migrationsNamespace;
	}

	/**
	 * Returns the directory which holds the versions
	 *
	 * @return string
	 */
	public function getMigrationsDirectory() {
		return $this->migrationsPath;
	}

	/**
	 * Return the explicit version for the aliases; current, next, prev, latest
	 *
	 * @param string $alias
	 * @return mixed|null|string
	 */
	public function getMigration($alias) {
		switch ($alias) {
			case 'current':
				return $this->getCurrentVersion();
			case 'next':
				return $this->getRelativeVersion($this->getCurrentVersion(), 1);
			case 'prev':
				return $this->getRelativeVersion($this->getCurrentVersion(), -1);
			case 'latest':
				$this->ensureMigrationsAreLoaded();

				$migrations = $this->getAvailableVersions();
				return @end($migrations);
		}
		return '0';
	}

	/**
	 * @param string $version
	 * @param int $delta
	 * @return null|string
	 */
	private function getRelativeVersion($version, $delta) {
		$this->ensureMigrationsAreLoaded();

		$versions = $this->getAvailableVersions();
		array_unshift($versions, 0);
		$offset = array_search($version, $versions, true);
		if ($offset === false || !isset($versions[$offset + $delta])) {
			// Unknown version or delta out of bounds.
			return null;
		}

		return (string) $versions[$offset + $delta];
	}

	/**
	 * @return string
	 */
	private function getCurrentVersion() {
		$m = $this->getMigratedVersions();
		if (count($m) === 0) {
			return '0';
		}
		$migrations = array_values($m);
		return @end($migrations);
	}

	/**
	 * @param string $version
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	private function getClass($version) {
		$this->ensureMigrationsAreLoaded();

		if (isset($this->migrations[$version])) {
			return $this->migrations[$version];
		}

		throw new \InvalidArgumentException("Version $version is unknown.");
	}

	/**
	 * Allows to set an IOutput implementation which is used for logging progress and messages
	 *
	 * @param IOutput $output
	 */
	public function setOutput(IOutput $output) {
		$this->output = $output;
	}

	/**
	 * Applies all not yet applied versions up to $to
	 *
	 * @param string $to
	 * @param bool $schemaOnly
	 * @throws \InvalidArgumentException
	 */
	public function migrate($to = 'latest', $schemaOnly = false) {
		if ($schemaOnly) {
			$this->migrateSchemaOnly($to);
			return;
		}

		// read known migrations
		$toBeExecuted = $this->getMigrationsToExecute($to);
		foreach ($toBeExecuted as $version) {
			try {
				$this->executeStep($version, $schemaOnly);
			} catch (DriverException $e) {
				// The exception itself does not contain the name of the migration,
				// so we wrap it here, to make debugging easier.
				throw new \Exception('Database error when running migration ' . $to . ' for app ' . $this->getApp(), 0, $e);
			}
		}
	}

	/**
	 * Applies all not yet applied versions up to $to
	 *
	 * @param string $to
	 * @throws \InvalidArgumentException
	 */
	public function migrateSchemaOnly($to = 'latest') {
		// read known migrations
		$toBeExecuted = $this->getMigrationsToExecute($to);

		if (empty($toBeExecuted)) {
			return;
		}

		$toSchema = null;
		foreach ($toBeExecuted as $version) {
			$instance = $this->createInstance($version);

			$toSchema = $instance->changeSchema($this->output, function () use ($toSchema) {
				return $toSchema ?: new SchemaWrapper($this->connection);
			}, ['tablePrefix' => $this->connection->getPrefix()]) ?: $toSchema;

			$this->markAsExecuted($version);
		}

		if ($toSchema instanceof SchemaWrapper) {
			$targetSchema = $toSchema->getWrappedSchema();
			if ($this->checkOracle) {
				$beforeSchema = $this->connection->createSchema();
				$this->ensureOracleIdentifierLengthLimit($beforeSchema, $targetSchema, strlen($this->connection->getPrefix()));
			}
			$this->connection->migrateToSchema($targetSchema);
			$toSchema->performDropTableCalls();
		}
	}

	/**
	 * Get the human readable descriptions for the migration steps to run
	 *
	 * @param string $to
	 * @return string[] [$name => $description]
	 */
	public function describeMigrationStep($to = 'latest') {
		$toBeExecuted = $this->getMigrationsToExecute($to);
		$description = [];
		foreach ($toBeExecuted as $version) {
			$migration = $this->createInstance($version);
			if ($migration->name()) {
				$description[$migration->name()] = $migration->description();
			}
		}
		return $description;
	}

	/**
	 * @param string $version
	 * @return IMigrationStep
	 * @throws \InvalidArgumentException
	 */
	protected function createInstance($version) {
		$class = $this->getClass($version);
		try {
			$s = \OC::$server->query($class);

			if (!$s instanceof IMigrationStep) {
				throw new \InvalidArgumentException('Not a valid migration');
			}
		} catch (QueryException $e) {
			if (class_exists($class)) {
				$s = new $class();
			} else {
				throw new \InvalidArgumentException("Migration step '$class' is unknown");
			}
		}

		return $s;
	}

	/**
	 * Executes one explicit version
	 *
	 * @param string $version
	 * @param bool $schemaOnly
	 * @throws \InvalidArgumentException
	 */
	public function executeStep($version, $schemaOnly = false) {
		$instance = $this->createInstance($version);

		if (!$schemaOnly) {
			$instance->preSchemaChange($this->output, function () {
				return new SchemaWrapper($this->connection);
			}, ['tablePrefix' => $this->connection->getPrefix()]);
		}

		$toSchema = $instance->changeSchema($this->output, function () {
			return new SchemaWrapper($this->connection);
		}, ['tablePrefix' => $this->connection->getPrefix()]);

		if ($toSchema instanceof SchemaWrapper) {
			$targetSchema = $toSchema->getWrappedSchema();
			if ($this->checkOracle) {
				$sourceSchema = $this->connection->createSchema();
				$this->ensureOracleIdentifierLengthLimit($sourceSchema, $targetSchema, strlen($this->connection->getPrefix()));
			}
			$this->connection->migrateToSchema($targetSchema);
			$toSchema->performDropTableCalls();
		}

		if (!$schemaOnly) {
			$instance->postSchemaChange($this->output, function () {
				return new SchemaWrapper($this->connection);
			}, ['tablePrefix' => $this->connection->getPrefix()]);
		}

		$this->markAsExecuted($version);
	}

	public function ensureOracleIdentifierLengthLimit(Schema $sourceSchema, Schema $targetSchema, int $prefixLength) {
		$sequences = $targetSchema->getSequences();

		foreach ($targetSchema->getTables() as $table) {
			try {
				$sourceTable = $sourceSchema->getTable($table->getName());
			} catch (SchemaException $e) {
				if (\strlen($table->getName()) - $prefixLength > 27) {
					throw new \InvalidArgumentException('Table name "'  . $table->getName() . '" is too long.');
				}
				$sourceTable = null;
			}

			foreach ($table->getColumns() as $thing) {
				if ((!$sourceTable instanceof Table || !$sourceTable->hasColumn($thing->getName())) && \strlen($thing->getName()) > 30) {
					throw new \InvalidArgumentException('Column name "'  . $table->getName() . '"."' . $thing->getName() . '" is too long.');
				}

				if ($thing->getNotnull() && $thing->getDefault() === ''
					&& $sourceTable instanceof Table && !$sourceTable->hasColumn($thing->getName())) {
					throw new \InvalidArgumentException('Column name "'  . $table->getName() . '"."' . $thing->getName() . '" is NotNull, but has empty string or null as default.');
				}
			}

			foreach ($table->getIndexes() as $thing) {
				if ((!$sourceTable instanceof Table || !$sourceTable->hasIndex($thing->getName())) && \strlen($thing->getName()) > 30) {
					throw new \InvalidArgumentException('Index name "'  . $table->getName() . '"."' . $thing->getName() . '" is too long.');
				}
			}

			foreach ($table->getForeignKeys() as $thing) {
				if ((!$sourceTable instanceof Table || !$sourceTable->hasForeignKey($thing->getName())) && \strlen($thing->getName()) > 30) {
					throw new \InvalidArgumentException('Foreign key name "'  . $table->getName() . '"."' . $thing->getName() . '" is too long.');
				}
			}

			$primaryKey = $table->getPrimaryKey();
			if ($primaryKey instanceof Index && (!$sourceTable instanceof Table || !$sourceTable->hasPrimaryKey())) {
				$indexName = strtolower($primaryKey->getName());
				$isUsingDefaultName = $indexName === 'primary';

				if ($this->connection->getDatabasePlatform() instanceof PostgreSQL94Platform) {
					$defaultName = $table->getName() . '_pkey';
					$isUsingDefaultName = strtolower($defaultName) === $indexName;

					if ($isUsingDefaultName) {
						$sequenceName = $table->getName() . '_' . implode('_', $primaryKey->getColumns()) . '_seq';
						$sequences = array_filter($sequences, function (Sequence $sequence) use ($sequenceName) {
							return $sequence->getName() !== $sequenceName;
						});
					}
				} elseif ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
					$defaultName = $table->getName() . '_seq';
					$isUsingDefaultName = strtolower($defaultName) === $indexName;
				}

				if (!$isUsingDefaultName && \strlen($indexName) > 30) {
					throw new \InvalidArgumentException('Primary index name  on "'  . $table->getName() . '" is too long.');
				}
				if ($isUsingDefaultName && \strlen($table->getName()) - $prefixLength >= 23) {
					throw new \InvalidArgumentException('Primary index name  on "'  . $table->getName() . '" is too long.');
				}
			}
		}

		foreach ($sequences as $sequence) {
			if (!$sourceSchema->hasSequence($sequence->getName()) && \strlen($sequence->getName()) > 30) {
				throw new \InvalidArgumentException('Sequence name "'  . $sequence->getName() . '" is too long.');
			}
		}
	}

	private function ensureMigrationsAreLoaded() {
		if (empty($this->migrations)) {
			$this->migrations = $this->findMigrations();
		}
	}
}
