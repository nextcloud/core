<?php
/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Repair;

use OCP\Migration\IOutput;

/**
 * Tests for fixing the SQLite id recycling
 *
 * @group DB
 */
class RepairSqliteAutoincrementTest extends \Test\TestCase {

	/**
	 * @var \OC\Repair\SqliteAutoincrement
	 */
	private $repair;

	/**
	 * @var \Doctrine\DBAL\Connection
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

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->config = \OC::$server->getConfig();
		if (!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
			$this->markTestSkipped("Test only relevant on Sqlite");
		}

		$dbPrefix = $this->config->getSystemValue('dbtableprefix', 'oc_');
		$this->tableName = $this->getUniqueID($dbPrefix . 'autoinc_test');
		$this->connection->exec('CREATE TABLE ' . $this->tableName . '("someid" INTEGER NOT NULL, "text" VARCHAR(16), PRIMARY KEY("someid"))');

		$this->repair = new \OC\Repair\SqliteAutoincrement($this->connection);
	}

	protected function tearDown(): void {
		$this->connection->getSchemaManager()->dropTable($this->tableName);
		parent::tearDown();
	}

	/**
	 * Tests whether autoincrement works
	 *
	 * @return boolean true if autoincrement works, false otherwise
	 */
	protected function checkAutoincrement() {
		$this->connection->executeUpdate('INSERT INTO ' . $this->tableName . ' ("text") VALUES ("test")');
		$insertId = $this->connection->lastInsertId();
		$this->connection->executeUpdate('DELETE FROM ' . $this->tableName . ' WHERE "someid" = ?', [$insertId]);

		// insert again
		$this->connection->executeUpdate('INSERT INTO ' . $this->tableName . ' ("text") VALUES ("test2")');
		$newInsertId = $this->connection->lastInsertId();

		return ($insertId !== $newInsertId);
	}

	public function testConvertIdColumn() {
		$this->assertFalse($this->checkAutoincrement());

		/** @var IOutput | \PHPUnit_Framework_MockObject_MockObject $outputMock */
		$outputMock = $this->getMockBuilder('\OCP\Migration\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$this->repair->run($outputMock);

		$this->assertTrue($this->checkAutoincrement());
	}
}
