<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OC\DB;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;

class MDB2SchemaManager {
	/** @var Connection $conn */
	protected $conn;

	/**
	 * @param Connection $conn
	 */
	public function __construct($conn) {
		$this->conn = $conn;
	}

	/**
	 * Creates tables from XML file
	 * @param string $file file to read structure from
	 * @return bool
	 *
	 * TODO: write more documentation
	 */
	public function createDbFromStructure($file) {
		$schemaReader = new MDB2SchemaReader(\OC::$server->getConfig(), $this->conn->getDatabasePlatform());
		$toSchema = new Schema([], [], $this->conn->getSchemaManager()->createSchemaConfig());
		$toSchema = $schemaReader->loadSchemaFromFile($file, $toSchema);
		return $this->executeSchemaChange($toSchema);
	}

	/**
	 * @return \OC\DB\Migrator
	 */
	public function getMigrator() {
		$random = \OC::$server->getSecureRandom();
		$platform = $this->conn->getDatabasePlatform();
		$config = \OC::$server->getConfig();
		$dispatcher = \OC::$server->getEventDispatcher();
		if ($platform instanceof SqlitePlatform) {
			return new SQLiteMigrator($this->conn, $random, $config, $dispatcher);
		} elseif ($platform instanceof OraclePlatform) {
			return new OracleMigrator($this->conn, $random, $config, $dispatcher);
		} elseif ($platform instanceof MySQLPlatform) {
			return new MySQLMigrator($this->conn, $random, $config, $dispatcher);
		} elseif ($platform instanceof PostgreSQL94Platform) {
			return new PostgreSqlMigrator($this->conn, $random, $config, $dispatcher);
		} else {
			return new Migrator($this->conn, $random, $config, $dispatcher);
		}
	}

	/**
	 * Reads database schema from file
	 *
	 * @param string $file file to read from
	 * @return \Doctrine\DBAL\Schema\Schema
	 */
	private function readSchemaFromFile($file) {
		$platform = $this->conn->getDatabasePlatform();
		$schemaReader = new MDB2SchemaReader(\OC::$server->getConfig(), $platform);
		$toSchema = new Schema([], [], $this->conn->getSchemaManager()->createSchemaConfig());
		return $schemaReader->loadSchemaFromFile($file, $toSchema);
	}

	/**
	 * update the database scheme
	 * @param string $file file to read structure from
	 * @param bool $generateSql only return the sql needed for the upgrade
	 * @return string|boolean
	 */
	public function updateDbFromStructure($file, $generateSql = false) {
		$toSchema = $this->readSchemaFromFile($file);
		$migrator = $this->getMigrator();

		if ($generateSql) {
			return $migrator->generateChangeScript($toSchema);
		} else {
			$migrator->migrate($toSchema);
			return true;
		}
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Schema $schema
	 * @return string
	 */
	public function generateChangeScript($schema) {
		$migrator = $this->getMigrator();
		return $migrator->generateChangeScript($schema);
	}

	/**
	 * remove all tables defined in a database structure xml file
	 *
	 * @param string $file the xml file describing the tables
	 */
	public function removeDBStructure($file) {
		$schemaReader = new MDB2SchemaReader(\OC::$server->getConfig(), $this->conn->getDatabasePlatform());
		$toSchema = new Schema([], [], $this->conn->getSchemaManager()->createSchemaConfig());
		$fromSchema = $schemaReader->loadSchemaFromFile($file, $toSchema);
		$toSchema = clone $fromSchema;
		foreach ($toSchema->getTables() as $table) {
			$toSchema->dropTable($table->getName());
		}
		$comparator = new \Doctrine\DBAL\Schema\Comparator();
		$schemaDiff = $comparator->compare($fromSchema, $toSchema);
		$this->executeSchemaChange($schemaDiff);
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Schema|\Doctrine\DBAL\Schema\SchemaDiff $schema
	 * @return bool
	 */
	private function executeSchemaChange($schema) {
		$this->conn->beginTransaction();
		foreach ($schema->toSql($this->conn->getDatabasePlatform()) as $sql) {
			$this->conn->query($sql);
		}
		$this->conn->commit();

		if ($this->conn->getDatabasePlatform() instanceof SqlitePlatform) {
			$this->conn->close();
			$this->conn->connect();
		}
		return true;
	}
}
