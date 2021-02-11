<?php

/**
 * Copyright (c) 2014 Thomas Müller <deepdiver@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\DB;

use Doctrine\DBAL\Platforms\OraclePlatform;

/**
 * Class MDB2SchemaManager
 *
 * @group DB
 *
 * @package Test\DB
 */
class MDB2SchemaManagerTest extends \Test\TestCase {
	protected function tearDown(): void {
		// do not drop the table for Oracle as it will create a bogus transaction
		// that will break the following test suites requiring transactions
		if (\OC::$server->getConfig()->getSystemValue('dbtype', 'sqlite') !== 'oci') {
			\OC::$server->getDatabaseConnection()->dropTable('table');
		}

		parent::tearDown();
	}

	public function testAutoIncrement() {
		$connection = \OC::$server->get(\OC\DB\Connection::class);
		if ($connection->getDatabasePlatform() instanceof OraclePlatform) {
			$this->markTestSkipped('Adding auto increment columns in Oracle is not supported.');
		}

		$manager = new \OC\DB\MDB2SchemaManager($connection);

		$manager->createDbFromStructure(__DIR__ . '/ts-autoincrement-before.xml');
		$connection->executeUpdate('insert into `*PREFIX*table` values (?)', ['abc']);
		$connection->executeUpdate('insert into `*PREFIX*table` values (?)', ['abc']);
		$connection->executeUpdate('insert into `*PREFIX*table` values (?)', ['123']);
		$connection->executeUpdate('insert into `*PREFIX*table` values (?)', ['123']);
		$manager->updateDbFromStructure(__DIR__ . '/ts-autoincrement-after.xml');

		$this->addToAssertionCount(1);
	}
}
