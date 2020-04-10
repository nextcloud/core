<?php
/**
 * Copyright (c) 2014 Thomas Müller <deepdiver@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Repair;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use OC\Repair\Collation;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\Migration\IOutput;
use Test\TestCase;

class TestCollationRepair extends Collation {
	/**
	 * @param IDBConnection $connection
	 * @return string[]
	 */
	public function getAllNonUTF8BinTables(IDBConnection $connection) {
		return parent::getAllNonUTF8BinTables($connection);
	}
}

/**
 * Tests for the converting of MySQL tables to InnoDB engine
 *
 * @group DB
 *
 * @see \OC\Repair\RepairMimeTypes
 */
class RepairCollationTest extends TestCase {

	/**
	 * @var TestCollationRepair
	 */
	private $repair;

	/**
	 * @var Connection|IDBConnection
	 */
	private $connection;

	/**
	 * @var string
	 */
	private $tableName;

	/**
	 * @var \OCP\IConfig
	 */
	private $config;

	/** @var ILogger */
	private $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->logger = $this->createMock(ILogger::class);
		$this->config = \OC::$server->getConfig();
		if (!$this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
			$this->markTestSkipped("Test only relevant on MySql");
		}

		$dbPrefix = $this->config->getSystemValue("dbtableprefix");
		$this->tableName = $this->getUniqueID($dbPrefix . "_collation_test");
		$this->connection->exec("CREATE TABLE $this->tableName(text VARCHAR(16)) COLLATE utf8_unicode_ci");

		$this->repair = new TestCollationRepair($this->config, $this->logger, $this->connection, false);
	}

	protected function tearDown(): void {
		$this->connection->getSchemaManager()->dropTable($this->tableName);
		parent::tearDown();
	}

	public function testCollationConvert() {
		$tables = $this->repair->getAllNonUTF8BinTables($this->connection);
		$this->assertGreaterThanOrEqual(1, count($tables));

		/** @var IOutput | \PHPUnit_Framework_MockObject_MockObject $outputMock */
		$outputMock = $this->getMockBuilder('\OCP\Migration\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$this->repair->run($outputMock);

		$tables = $this->repair->getAllNonUTF8BinTables($this->connection);
		$this->assertCount(0, $tables);
	}
}
