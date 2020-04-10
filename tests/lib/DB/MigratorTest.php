<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\DB;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use OCP\IConfig;

/**
 * Class MigratorTest
 *
 * @group DB
 *
 * @package Test\DB
 */
class MigratorTest extends \Test\TestCase {
	/**
	 * @var \Doctrine\DBAL\Connection $connection
	 */
	private $connection;

	/**
	 * @var \OC\DB\MDB2SchemaManager
	 */
	private $manager;

	/**
	 * @var IConfig
	 **/
	private $config;

	/** @var string */
	private $tableName;

	/** @var string */
	private $tableNameTmp;

	protected function setUp(): void {
		parent::setUp();

		$this->config = \OC::$server->getConfig();
		$this->connection = \OC::$server->getDatabaseConnection();
		if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
			$this->markTestSkipped('DB migration tests are not supported on OCI');
		}
		$this->manager = new \OC\DB\MDB2SchemaManager($this->connection);
		$this->tableName = $this->getUniqueTableName();
		$this->tableNameTmp = $this->getUniqueTableName();
	}

	private function getUniqueTableName() {
		return strtolower($this->getUniqueID($this->config->getSystemValue('dbtableprefix', 'oc_') . 'test_'));
	}

	protected function tearDown(): void {
		// Try to delete if exists (IF EXISTS NOT SUPPORTED IN ORACLE)
		try {
			$this->connection->exec('DROP TABLE ' . $this->connection->quoteIdentifier($this->tableNameTmp));
		} catch (\Doctrine\DBAL\DBALException $e) {}

		try {
			$this->connection->exec('DROP TABLE ' . $this->connection->quoteIdentifier($this->tableName));
		} catch (\Doctrine\DBAL\DBALException $e) {}
		parent::tearDown();
	}

	/**
	 * @return \Doctrine\DBAL\Schema\Schema[]
	 */
	private function getDuplicateKeySchemas() {
		$startSchema = new Schema([], [], $this->getSchemaConfig());
		$table = $startSchema->createTable($this->tableName);
		$table->addColumn('id', 'integer');
		$table->addColumn('name', 'string');
		$table->addIndex(['id'], $this->tableName . '_id');

		$endSchema = new Schema([], [], $this->getSchemaConfig());
		$table = $endSchema->createTable($this->tableName);
		$table->addColumn('id', 'integer');
		$table->addColumn('name', 'string');
		$table->addUniqueIndex(['id'], $this->tableName . '_id');

		return [$startSchema, $endSchema];
	}

	private function getSchemaConfig() {
		$config = new SchemaConfig();
		$config->setName($this->connection->getDatabase());
		return $config;
	}

	private function isSQLite() {
		return $this->connection->getDriver() instanceof \Doctrine\DBAL\Driver\PDOSqlite\Driver;
	}

	
	public function testDuplicateKeyUpgrade() {
		$this->expectException(\OC\DB\MigrationException::class);

		if ($this->isSQLite()) {
			$this->markTestSkipped('sqlite does not throw errors when creating a new key on existing data');
		}
		list($startSchema, $endSchema) = $this->getDuplicateKeySchemas();
		$migrator = $this->manager->getMigrator();
		$migrator->migrate($startSchema);

		$this->connection->insert($this->tableName, ['id' => 1, 'name' => 'foo']);
		$this->connection->insert($this->tableName, ['id' => 2, 'name' => 'bar']);
		$this->connection->insert($this->tableName, ['id' => 2, 'name' => 'qwerty']);

		$migrator->checkMigrate($endSchema);
		$this->fail('checkMigrate should have failed');
	}

	public function testUpgrade() {
		list($startSchema, $endSchema) = $this->getDuplicateKeySchemas();
		$migrator = $this->manager->getMigrator();
		$migrator->migrate($startSchema);

		$this->connection->insert($this->tableName, ['id' => 1, 'name' => 'foo']);
		$this->connection->insert($this->tableName, ['id' => 2, 'name' => 'bar']);
		$this->connection->insert($this->tableName, ['id' => 3, 'name' => 'qwerty']);

		$migrator->checkMigrate($endSchema);
		$migrator->migrate($endSchema);
		$this->addToAssertionCount(1);
	}

	public function testUpgradeDifferentPrefix() {
		$oldTablePrefix = $this->config->getSystemValue('dbtableprefix', 'oc_');

		$this->config->setSystemValue('dbtableprefix', 'ownc_');
		$this->tableName = strtolower($this->getUniqueID($this->config->getSystemValue('dbtableprefix') . 'test_'));

		list($startSchema, $endSchema) = $this->getDuplicateKeySchemas();
		$migrator = $this->manager->getMigrator();
		$migrator->migrate($startSchema);

		$this->connection->insert($this->tableName, ['id' => 1, 'name' => 'foo']);
		$this->connection->insert($this->tableName, ['id' => 2, 'name' => 'bar']);
		$this->connection->insert($this->tableName, ['id' => 3, 'name' => 'qwerty']);

		$migrator->checkMigrate($endSchema);
		$migrator->migrate($endSchema);
		$this->addToAssertionCount(1);

		$this->config->setSystemValue('dbtableprefix', $oldTablePrefix);
	}

	public function testInsertAfterUpgrade() {
		list($startSchema, $endSchema) = $this->getDuplicateKeySchemas();
		$migrator = $this->manager->getMigrator();
		$migrator->migrate($startSchema);

		$migrator->migrate($endSchema);

		$this->connection->insert($this->tableName, ['id' => 1, 'name' => 'foo']);
		$this->connection->insert($this->tableName, ['id' => 2, 'name' => 'bar']);
		try {
			$this->connection->insert($this->tableName, ['id' => 2, 'name' => 'qwerty']);
			$this->fail('Expected duplicate key insert to fail');
		} catch (DBALException $e) {
			$this->addToAssertionCount(1);
		}
	}

	public function testAddingPrimaryKeyWithAutoIncrement() {
		$startSchema = new Schema([], [], $this->getSchemaConfig());
		$table = $startSchema->createTable($this->tableName);
		$table->addColumn('id', 'integer');
		$table->addColumn('name', 'string');

		$endSchema = new Schema([], [], $this->getSchemaConfig());
		$table = $endSchema->createTable($this->tableName);
		$table->addColumn('id', 'integer', ['autoincrement' => true]);
		$table->addColumn('name', 'string');
		$table->setPrimaryKey(['id']);

		$migrator = $this->manager->getMigrator();
		$migrator->migrate($startSchema);

		$migrator->checkMigrate($endSchema);
		$migrator->migrate($endSchema);

		$this->addToAssertionCount(1);
	}

	public function testReservedKeywords() {
		$startSchema = new Schema([], [], $this->getSchemaConfig());
		$table = $startSchema->createTable($this->tableName);
		$table->addColumn('id', 'integer', ['autoincrement' => true]);
		$table->addColumn('user', 'string', ['length' => 255]);
		$table->setPrimaryKey(['id']);

		$endSchema = new Schema([], [], $this->getSchemaConfig());
		$table = $endSchema->createTable($this->tableName);
		$table->addColumn('id', 'integer', ['autoincrement' => true]);
		$table->addColumn('user', 'string', ['length' => 64]);
		$table->setPrimaryKey(['id']);

		$migrator = $this->manager->getMigrator();
		$migrator->migrate($startSchema);

		$migrator->checkMigrate($endSchema);
		$migrator->migrate($endSchema);

		$this->addToAssertionCount(1);
	}

	public function testAddingForeignKey() {
		$startSchema = new Schema([], [], $this->getSchemaConfig());
		$table = $startSchema->createTable($this->tableName);
		$table->addColumn('id', 'integer', ['autoincrement' => true]);
		$table->addColumn('name', 'string');
		$table->setPrimaryKey(['id']);

		$fkName = "fkc";
		$tableFk = $startSchema->createTable($this->tableNameTmp);
		$tableFk->addColumn('fk_id', 'integer');
		$tableFk->addColumn('name', 'string');
		$tableFk->addForeignKeyConstraint($this->tableName, ['fk_id'], ['id'], [], $fkName);

		$migrator = $this->manager->getMigrator();
		$migrator->migrate($startSchema);


		$this->assertTrue($startSchema->getTable($this->tableNameTmp)->hasForeignKey($fkName));
	}
}
