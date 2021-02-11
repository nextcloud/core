<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\DB;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use OC\DB\MDB2SchemaManager;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Class Connection
 *
 * @group DB
 *
 * @package Test\DB
 */
class ConnectionTest extends \Test\TestCase {
	/**
	 * @var \OCP\IDBConnection
	 */
	private $connection;

	public static function setUpBeforeClass(): void {
		self::dropTestTable();
		parent::setUpBeforeClass();
	}

	public static function tearDownAfterClass(): void {
		self::dropTestTable();
		parent::tearDownAfterClass();
	}

	protected static function dropTestTable() {
		if (\OC::$server->getConfig()->getSystemValue('dbtype', 'sqlite') !== 'oci') {
			\OC::$server->getDatabaseConnection()->dropTable('table');
		}
	}

	protected function setUp(): void {
		parent::setUp();
		$this->connection = \OC::$server->get(\OC\DB\Connection::class);
	}

	protected function tearDown(): void {
		parent::tearDown();
		$this->connection->dropTable('table');
	}

	/**
	 * @param string $table
	 */
	public function assertTableExist($table) {
		if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
			// sqlite removes the tables after closing the DB
			$this->addToAssertionCount(1);
		} else {
			$this->assertTrue($this->connection->tableExists($table), 'Table ' . $table . ' exists.');
		}
	}

	/**
	 * @param string $table
	 */
	public function assertTableNotExist($table) {
		if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
			// sqlite removes the tables after closing the DB
			$this->addToAssertionCount(1);
		} else {
			$this->assertFalse($this->connection->tableExists($table), 'Table ' . $table . " doesn't exist.");
		}
	}

	private function makeTestTable() {
		$schemaManager = new MDB2SchemaManager($this->connection);
		$schemaManager->createDbFromStructure(__DIR__ . '/testschema.xml');
	}

	public function testTableExists() {
		$this->assertTableNotExist('table');
		$this->makeTestTable();
		$this->assertTableExist('table');
	}

	/**
	 * @depends testTableExists
	 */
	public function testDropTable() {
		$this->makeTestTable();
		$this->assertTableExist('table');
		$this->connection->dropTable('table');
		$this->assertTableNotExist('table');
	}

	private function getTextValueByIntegerField($integerField) {
		$builder = $this->connection->getQueryBuilder();
		$query = $builder->select('*')
			->from('table')
			->where($builder->expr()->eq('integerfield', $builder->createNamedParameter($integerField, IQueryBuilder::PARAM_INT)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		return $row['textfield'] ?? null;
	}

	public function testSetValues() {
		$this->makeTestTable();
		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'foo',
			'clobfield' => 'not_null'
		]);

		$this->assertEquals('foo', $this->getTextValueByIntegerField(1));
	}

	public function testSetValuesOverWrite() {
		$this->makeTestTable();
		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'foo'
		]);

		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'bar'
		]);

		$this->assertEquals('bar', $this->getTextValueByIntegerField(1));
	}

	public function testSetValuesOverWritePrecondition() {
		$this->makeTestTable();
		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'foo',
			'booleanfield' => true,
			'clobfield' => 'not_null'
		]);

		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'bar'
		], [
			'booleanfield' => true
		]);

		$this->assertEquals('bar', $this->getTextValueByIntegerField(1));
	}


	public function testSetValuesOverWritePreconditionFailed() {
		$this->expectException(\OCP\PreConditionNotMetException::class);

		$this->makeTestTable();
		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'foo',
			'booleanfield' => true,
			'clobfield' => 'not_null'
		]);

		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'bar'
		], [
			'booleanfield' => false
		]);
	}

	public function testSetValuesSameNoError() {
		$this->makeTestTable();
		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'foo',
			'clobfield' => 'not_null'
		]);

		// this will result in 'no affected rows' on certain optimizing DBs
		// ensure the PreConditionNotMetException isn't thrown
		$this->connection->setValues('table', [
			'integerfield' => 1
		], [
			'textfield' => 'foo'
		]);

		$this->addToAssertionCount(1);
	}

	public function testInsertIfNotExist() {
		if (\OC::$server->getConfig()->getSystemValue('dbtype', 'sqlite') === 'oci') {
			self::markTestSkipped('Insert if not exist does not work with clob on oracle');
		}

		$this->makeTestTable();
		$categoryEntries = [
			['user' => 'test', 'category' => 'Family',    'expectedResult' => 1],
			['user' => 'test', 'category' => 'Friends',   'expectedResult' => 1],
			['user' => 'test', 'category' => 'Coworkers', 'expectedResult' => 1],
			['user' => 'test', 'category' => 'Coworkers', 'expectedResult' => 0],
			['user' => 'test', 'category' => 'School',    'expectedResult' => 1],
			['user' => 'test2', 'category' => 'Coworkers2', 'expectedResult' => 1],
			['user' => 'test2', 'category' => 'Coworkers2', 'expectedResult' => 0],
			['user' => 'test2', 'category' => 'School2',    'expectedResult' => 1],
			['user' => 'test2', 'category' => 'Coworkers', 'expectedResult' => 1],
		];

		$row = 0;
		foreach ($categoryEntries as $entry) {
			$result = $this->connection->insertIfNotExist('*PREFIX*table',
				[
					'textfield' => $entry['user'],
					'clobfield' => $entry['category'],
					'integerfield' => $row++,
				], ['textfield', 'clobfield']);
			$this->assertEquals($entry['expectedResult'], $result, json_encode($entry));
		}

		$query = $this->connection->prepare('SELECT * FROM `*PREFIX*table`');
		$result = $query->execute();
		$this->assertTrue((bool)$result);
		$this->assertEquals(7, count($result->fetchAll()));
	}

	public function testInsertIfNotExistNull() {
		if (\OC::$server->getConfig()->getSystemValue('dbtype', 'sqlite') === 'oci') {
			self::markTestSkipped('Insert if not exist does not work with clob on oracle');
		}

		$this->makeTestTable();
		$categoryEntries = [
			['addressbookid' => 123, 'fullname' => null, 'expectedResult' => 1],
			['addressbookid' => 123, 'fullname' => null, 'expectedResult' => 0],
			['addressbookid' => 123, 'fullname' => 'test', 'expectedResult' => 1],
		];

		$row = 0;
		foreach ($categoryEntries as $entry) {
			$result = $this->connection->insertIfNotExist('*PREFIX*table',
				[
					'integerfield_default' => $entry['addressbookid'],
					'clobfield' => $entry['fullname'],
					'integerfield' => $row++,
				], ['integerfield_default', 'clobfield']);
			$this->assertEquals($entry['expectedResult'], $result, json_encode($entry));
		}

		$query = $this->connection->prepare('SELECT * FROM `*PREFIX*table`');
		$result = $query->execute();
		$this->assertTrue((bool)$result);
		$this->assertEquals(2, count($result->fetchAll()));
	}

	public function testInsertIfNotExistDonTOverwrite() {
		if (\OC::$server->getConfig()->getSystemValue('dbtype', 'sqlite') === 'oci') {
			self::markTestSkipped('Insert if not exist does not work with clob on oracle');
		}

		$this->makeTestTable();
		$fullName = 'fullname test';
		$uri = 'uri_1';

		// Normal test to have same known data inserted.
		$query = $this->connection->prepare('INSERT INTO `*PREFIX*table` (`textfield`, `clobfield`) VALUES (?, ?)');
		$result = $query->execute([$fullName, $uri]);
		$this->assertEquals(1, $result->rowCount());
		$query = $this->connection->prepare('SELECT `textfield`, `clobfield` FROM `*PREFIX*table` WHERE `clobfield` = ?');
		$result = $query->execute([$uri]);
		$rowset = $result->fetchAll();
		$this->assertEquals(1, count($rowset));
		$this->assertArrayHasKey('textfield', $rowset[0]);
		$this->assertEquals($fullName, $rowset[0]['textfield']);

		// Try to insert a new row
		$result = $this->connection->insertIfNotExist('*PREFIX*table',
			[
				'textfield' => $fullName,
				'clobfield' => $uri,
			]);
		$this->assertEquals(0, $result);

		$query = $this->connection->prepare('SELECT `textfield`, `clobfield` FROM `*PREFIX*table` WHERE `clobfield` = ?');
		$result = $query->execute([$uri]);
		// Test that previously inserted data isn't overwritten
		// And that a new row hasn't been inserted.
		$rowset = $result->fetchAll();
		$this->assertEquals(1, count($rowset));
		$this->assertArrayHasKey('textfield', $rowset[0]);
		$this->assertEquals($fullName, $rowset[0]['textfield']);
	}

	public function testInsertIfNotExistsViolating() {
		if (\OC::$server->getConfig()->getSystemValue('dbtype', 'sqlite') === 'oci') {
			self::markTestSkipped('Insert if not exist does not work with clob on oracle');
		}

		$this->makeTestTable();
		$result = $this->connection->insertIfNotExist('*PREFIX*table',
			[
				'textfield' => md5('welcome.txt'),
				'clobfield' => $this->getUniqueID()
			]);
		$this->assertEquals(1, $result);

		$result = $this->connection->insertIfNotExist('*PREFIX*table',
			[
				'textfield' => md5('welcome.txt'),
				'clobfield' => $this->getUniqueID()
			],['textfield']);

		$this->assertEquals(0, $result);
	}

	public function insertIfNotExistsViolatingThrows() {
		return [
			[null],
			[['clobfield']],
		];
	}

	/**
	 * @dataProvider insertIfNotExistsViolatingThrows
	 *
	 * @param array $compareKeys
	 */
	public function testInsertIfNotExistsViolatingUnique($compareKeys) {
		if (\OC::$server->getConfig()->getSystemValue('dbtype', 'sqlite') === 'oci') {
			self::markTestSkipped('Insert if not exist does not work with clob on oracle');
		}

		$this->makeTestTable();
		$result = $this->connection->insertIfNotExist('*PREFIX*table',
			[
				'integerfield' => 1,
				'clobfield' => $this->getUniqueID()
			]);
		$this->assertEquals(1, $result);

		$result = $this->connection->insertIfNotExist('*PREFIX*table',
			[
				'integerfield' => 1,
				'clobfield' => $this->getUniqueID()
			], $compareKeys);

		$this->assertEquals(0, $result);
	}


	public function testUniqueConstraintViolating() {
		$this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

		$this->makeTestTable();

		$testQuery = 'INSERT INTO `*PREFIX*table` (`integerfield`, `textfield`) VALUES(?, ?)';
		$testParams = [1, 'hello'];

		$this->connection->executeUpdate($testQuery, $testParams);
		$this->connection->executeUpdate($testQuery, $testParams);
	}
}
