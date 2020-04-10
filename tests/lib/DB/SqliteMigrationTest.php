<?php
/**
 * Copyright (c) 2014 Thomas Müller <deepdiver@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\DB;

/**
 * Class SqliteMigration
 *
 * @group DB
 */
class SqliteMigrationTest extends \Test\TestCase {

	/** @var \Doctrine\DBAL\Connection */
	private $connection;

	/** @var string */
	private $tableName;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		if (!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
			$this->markTestSkipped("Test only relevant on Sqlite");
		}

		$dbPrefix = \OC::$server->getConfig()->getSystemValue("dbtableprefix");
		$this->tableName = $this->getUniqueID($dbPrefix . '_enum_bit_test');
		$this->connection->exec("CREATE TABLE $this->tableName(t0 tinyint unsigned, t1 tinyint)");
	}

	protected function tearDown(): void {
		$this->connection->getSchemaManager()->dropTable($this->tableName);
		parent::tearDown();
	}

	public function testNonOCTables() {
		$manager = new \OC\DB\MDB2SchemaManager($this->connection);
		$manager->updateDbFromStructure(__DIR__ . '/testschema.xml');

		$this->addToAssertionCount(1);
	}

}
