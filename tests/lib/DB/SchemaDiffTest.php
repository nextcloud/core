<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\DB;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use OC\DB\MDB2SchemaManager;
use OC\DB\MDB2SchemaReader;
use OCP\IConfig;
use Test\TestCase;

/**
 * Class MigratorTest
 *
 * @group DB
 *
 * @package Test\DB
 */
class SchemaDiffTest extends TestCase {
	/** @var \Doctrine\DBAL\Connection $connection */
	private $connection;

	/** @var MDB2SchemaManager */
	private $manager;

	/** @var IConfig */
	private $config;

	/** @var string */
	private $testPrefix;

	private $schemaFile;

	protected function setUp(): void {
		parent::setUp();

		$this->schemaFile = \OC::$server->getTempManager()->getTemporaryFile();

		$this->config = \OC::$server->getConfig();
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->manager = new MDB2SchemaManager($this->connection);
		$this->testPrefix= strtolower($this->getUniqueID($this->config->getSystemValue('dbtableprefix', 'oc_'), 3));
	}

	protected function tearDown(): void {
		$this->manager->removeDBStructure($this->schemaFile);
		parent::tearDown();
	}

	/**
	 * @dataProvider providesSchemaFiles
	 * @param string $xml
	 */
	public function testZeroChangeOnSchemaMigrations($xml) {

		$xml = str_replace('*dbprefix*', $this->testPrefix, $xml);
		$schemaFile = $this->schemaFile;
		file_put_contents($schemaFile, $xml);

		// apply schema
		$this->manager->createDbFromStructure($schemaFile);

		$schemaReader = new MDB2SchemaReader($this->config, $this->connection->getDatabasePlatform());
		$toSchema = new Schema([], [], $this->connection->getSchemaManager()->createSchemaConfig());
		$endSchema = $schemaReader->loadSchemaFromFile($schemaFile, $toSchema);

		// get the diff
		/** @var SchemaDiff $diff */
		$migrator = $this->manager->getMigrator();
		$diff = $this->invokePrivate($migrator, 'getDiff', [$endSchema, $this->connection]);

		// no sql statement is expected
		$sqls = $diff->toSql($this->connection->getDatabasePlatform());
		$this->assertEquals([], $sqls);
	}

	public function providesSchemaFiles() {
		return [
			'explicit test on autoincrement' => [file_get_contents(__DIR__ . '/schemDiffData/autoincrement.xml')],
			'explicit test on clob' => [file_get_contents(__DIR__ . '/schemDiffData/clob.xml')],
			'explicit test on unsigned' => [file_get_contents(__DIR__ . '/schemDiffData/unsigned.xml')],
			'explicit test on default -1' => [file_get_contents(__DIR__ . '/schemDiffData/default-1.xml')],
			'testing core schema' => [file_get_contents(__DIR__ . '/schemDiffData/core.xml')],
		];
	}
}
