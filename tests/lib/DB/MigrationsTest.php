<?php

/**
 * Copyright (c) 2016 Thomas Müller <thomas.mueller@tmit.eu>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\DB;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use OC\DB\Connection;
use OC\DB\MigrationService;
use OC\DB\SchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IMigrationStep;

/**
 * Class MigrationsTest
 *
 * @package Test\DB
 */
class MigrationsTest extends \Test\TestCase {

	/** @var MigrationService | \PHPUnit_Framework_MockObject_MockObject */
	private $migrationService;
	/** @var \PHPUnit_Framework_MockObject_MockObject | IDBConnection $db */
	private $db;

	protected function setUp(): void {
		parent::setUp();

		$this->db = $this->createMock(Connection::class);
		$this->db->expects($this->any())->method('getPrefix')->willReturn('test_oc_');
		$this->migrationService = new MigrationService('testing', $this->db);
	}

	public function testGetters() {
		$this->assertEquals('testing', $this->migrationService->getApp());
		$this->assertEquals(\OC::$SERVERROOT . '/apps/testing/lib/Migration', $this->migrationService->getMigrationsDirectory());
		$this->assertEquals('OCA\Testing\Migration', $this->migrationService->getMigrationsNamespace());
		$this->assertEquals('test_oc_migrations', $this->migrationService->getMigrationsTableName());
	}

	public function testCore() {
		$this->migrationService = new MigrationService('core', $this->db);

		$this->assertEquals('core', $this->migrationService->getApp());
		$this->assertEquals(\OC::$SERVERROOT . '/core/Migrations', $this->migrationService->getMigrationsDirectory());
		$this->assertEquals('OC\Core\Migrations', $this->migrationService->getMigrationsNamespace());
		$this->assertEquals('test_oc_migrations', $this->migrationService->getMigrationsTableName());
	}


	public function testExecuteUnknownStep() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Version 20170130180000 is unknown.');

		$this->migrationService->executeStep('20170130180000');
	}


	public function testUnknownApp() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('App not found');

		$migrationService = new MigrationService('unknown-bloody-app', $this->db);
	}


	public function testExecuteStepWithUnknownClass() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Migration step \'X\' is unknown');

		$this->migrationService = $this->getMockBuilder(MigrationService::class)
			->setMethods(['findMigrations'])
			->setConstructorArgs(['testing', $this->db])
			->getMock();
		$this->migrationService->expects($this->any())->method('findMigrations')->willReturn(
			['20170130180000' => 'X', '20170130180001' => 'Y', '20170130180002' => 'Z', '20170130180003' => 'A']
		);
		$this->migrationService->executeStep('20170130180000');
	}

	public function testExecuteStepWithSchemaChange() {

		$schema = $this->createMock(Schema::class);
		$this->db->expects($this->any())
			->method('createSchema')
			->willReturn($schema);

		$this->db->expects($this->once())
			->method('migrateToSchema');

		$wrappedSchema = $this->createMock(Schema::class);
		$wrappedSchema->expects($this->once())
			->method('getTables')
			->willReturn([]);
		$wrappedSchema->expects($this->once())
			->method('getSequences')
			->willReturn([]);

		$schemaResult = $this->createMock(SchemaWrapper::class);
		$schemaResult->expects($this->once())
			->method('getWrappedSchema')
			->willReturn($wrappedSchema);

		$step = $this->createMock(IMigrationStep::class);
		$step->expects($this->at(0))
			->method('preSchemaChange');
		$step->expects($this->at(1))
			->method('changeSchema')
			->willReturn($schemaResult);
		$step->expects($this->at(2))
			->method('postSchemaChange');

		$this->migrationService = $this->getMockBuilder(MigrationService::class)
			->setMethods(['createInstance'])
			->setConstructorArgs(['testing', $this->db])
			->getMock();

		$this->migrationService->expects($this->any())
			->method('createInstance')
			->with('20170130180000')
			->willReturn($step);
		$this->migrationService->executeStep('20170130180000');
	}

	public function testExecuteStepWithoutSchemaChange() {

		$schema = $this->createMock(Schema::class);
		$this->db->expects($this->any())
			->method('createSchema')
			->willReturn($schema);

		$this->db->expects($this->never())
			->method('migrateToSchema');

		$step = $this->createMock(IMigrationStep::class);
		$step->expects($this->at(0))
			->method('preSchemaChange');
		$step->expects($this->at(1))
			->method('changeSchema')
			->willReturn(null);
		$step->expects($this->at(2))
			->method('postSchemaChange');

		$this->migrationService = $this->getMockBuilder(MigrationService::class)
			->setMethods(['createInstance'])
			->setConstructorArgs(['testing', $this->db])
			->getMock();

		$this->migrationService->expects($this->any())
			->method('createInstance')
			->with('20170130180000')
			->willReturn($step);
		$this->migrationService->executeStep('20170130180000');
	}

	public function dataGetMigration() {
		return [
			['current', '20170130180001'],
			['prev', '20170130180000'],
			['next', '20170130180002'],
			['latest', '20170130180003'],
		];
	}

	/**
	 * @dataProvider dataGetMigration
	 * @param string $alias
	 * @param string $expected
	 */
	public function testGetMigration($alias, $expected) {
		$this->migrationService = $this->getMockBuilder(MigrationService::class)
			->setMethods(['getMigratedVersions', 'findMigrations'])
			->setConstructorArgs(['testing', $this->db])
			->getMock();
		$this->migrationService->expects($this->any())->method('getMigratedVersions')->willReturn(
			['20170130180000', '20170130180001']
		);
		$this->migrationService->expects($this->any())->method('findMigrations')->willReturn(
			['20170130180000' => 'X', '20170130180001' => 'Y', '20170130180002' => 'Z', '20170130180003' => 'A']
		);

		$this->assertEquals(
			['20170130180000', '20170130180001', '20170130180002', '20170130180003'],
			$this->migrationService->getAvailableVersions());

		$migration = $this->migrationService->getMigration($alias);
		$this->assertEquals($expected, $migration);
	}

	public function testMigrate() {
		$this->migrationService = $this->getMockBuilder(MigrationService::class)
			->setMethods(['getMigratedVersions', 'findMigrations', 'executeStep'])
			->setConstructorArgs(['testing', $this->db])
			->getMock();
		$this->migrationService->expects($this->any())->method('getMigratedVersions')->willReturn(
			['20170130180000', '20170130180001']
		);
		$this->migrationService->expects($this->any())->method('findMigrations')->willReturn(
			['20170130180000' => 'X', '20170130180001' => 'Y', '20170130180002' => 'Z', '20170130180003' => 'A']
		);

		$this->assertEquals(
			['20170130180000', '20170130180001', '20170130180002', '20170130180003'],
			$this->migrationService->getAvailableVersions());

		$this->migrationService->expects($this->exactly(2))->method('executeStep')
			->withConsecutive(['20170130180002'], ['20170130180003']);
		$this->migrationService->migrate();
	}

	public function testEnsureOracleIdentifierLengthLimitValid() {
		$column = $this->createMock(Column::class);
		$column->expects($this->once())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$index = $this->createMock(Index::class);
		$index->expects($this->once())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$foreignKey = $this->createMock(ForeignKeyConstraint::class);
		$foreignKey->expects($this->once())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$table = $this->createMock(Table::class);
		$table->expects($this->atLeastOnce())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$sequence = $this->createMock(Sequence::class);
		$sequence->expects($this->atLeastOnce())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$table->expects($this->once())
			->method('getColumns')
			->willReturn([$column]);
		$table->expects($this->once())
			->method('getIndexes')
			->willReturn([$index]);
		$table->expects($this->once())
			->method('getForeignKeys')
			->willReturn([$foreignKey]);
		$table->expects($this->once())
			->method('getPrimaryKey')
			->willReturn(null);

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);
		$schema->expects($this->once())
			->method('getSequences')
			->willReturn([$sequence]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}

	public function testEnsureOracleIdentifierLengthLimitValidWithPrimaryKey() {
		$index = $this->createMock(Index::class);
		$index->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$table = $this->createMock(Table::class);
		$table->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 26));

		$table->expects($this->once())
			->method('getColumns')
			->willReturn([]);
		$table->expects($this->once())
			->method('getIndexes')
			->willReturn([]);
		$table->expects($this->once())
			->method('getForeignKeys')
			->willReturn([]);
		$table->expects($this->once())
			->method('getPrimaryKey')
			->willReturn($index);

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);
		$schema->expects($this->once())
			->method('getSequences')
			->willReturn([]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}

	public function testEnsureOracleIdentifierLengthLimitValidWithPrimaryKeyDefault() {
		$defaultName = 'PRIMARY';
		if ($this->db->getDatabasePlatform() instanceof PostgreSqlPlatform) {
			$defaultName = \str_repeat('a', 26) . '_' . \str_repeat('b', 30) . '_seq';
		} else if ($this->db->getDatabasePlatform() instanceof OraclePlatform) {
			$defaultName = \str_repeat('a', 26) . '_seq';
		}

		$index = $this->createMock(Index::class);
		$index->expects($this->any())
			->method('getName')
			->willReturn($defaultName);
		$index->expects($this->any())
			->method('getColumns')
			->willReturn([\str_repeat('b', 30)]);

		$table = $this->createMock(Table::class);
		$table->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 25));

		$table->expects($this->once())
			->method('getColumns')
			->willReturn([]);
		$table->expects($this->once())
			->method('getIndexes')
			->willReturn([]);
		$table->expects($this->once())
			->method('getForeignKeys')
			->willReturn([]);
		$table->expects($this->once())
			->method('getPrimaryKey')
			->willReturn($index);

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);
		$schema->expects($this->once())
			->method('getSequences')
			->willReturn([]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}


	public function testEnsureOracleIdentifierLengthLimitTooLongTableName() {
		$this->expectException(\InvalidArgumentException::class);

		$table = $this->createMock(Table::class);
		$table->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 31));

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}


	public function testEnsureOracleIdentifierLengthLimitTooLongPrimaryWithDefault() {
		$this->expectException(\InvalidArgumentException::class);

		$defaultName = 'PRIMARY';
		if ($this->db->getDatabasePlatform() instanceof PostgreSqlPlatform) {
			$defaultName = \str_repeat('a', 27) . '_' . \str_repeat('b', 30) . '_seq';
		} else if ($this->db->getDatabasePlatform() instanceof OraclePlatform) {
			$defaultName = \str_repeat('a', 27) . '_seq';
		}

		$index = $this->createMock(Index::class);
		$index->expects($this->any())
			->method('getName')
			->willReturn($defaultName);
		$index->expects($this->any())
			->method('getColumns')
			->willReturn([\str_repeat('b', 30)]);

		$table = $this->createMock(Table::class);
		$table->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 27));

		$table->expects($this->once())
			->method('getColumns')
			->willReturn([]);
		$table->expects($this->once())
			->method('getIndexes')
			->willReturn([]);
		$table->expects($this->once())
			->method('getForeignKeys')
			->willReturn([]);
		$table->expects($this->once())
			->method('getPrimaryKey')
			->willReturn($index);

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}


	public function testEnsureOracleIdentifierLengthLimitTooLongPrimaryWithName() {
		$this->expectException(\InvalidArgumentException::class);

		$index = $this->createMock(Index::class);
		$index->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 31));

		$table = $this->createMock(Table::class);
		$table->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 26));

		$table->expects($this->once())
			->method('getColumns')
			->willReturn([]);
		$table->expects($this->once())
			->method('getIndexes')
			->willReturn([]);
		$table->expects($this->once())
			->method('getForeignKeys')
			->willReturn([]);
		$table->expects($this->once())
			->method('getPrimaryKey')
			->willReturn($index);

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}


	public function testEnsureOracleIdentifierLengthLimitTooLongColumnName() {
		$this->expectException(\InvalidArgumentException::class);

		$column = $this->createMock(Column::class);
		$column->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 31));

		$table = $this->createMock(Table::class);
		$table->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$table->expects($this->once())
			->method('getColumns')
			->willReturn([$column]);

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}


	public function testEnsureOracleIdentifierLengthLimitTooLongIndexName() {
		$this->expectException(\InvalidArgumentException::class);

		$index = $this->createMock(Index::class);
		$index->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 31));

		$table = $this->createMock(Table::class);
		$table->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$table->expects($this->once())
			->method('getColumns')
			->willReturn([]);
		$table->expects($this->once())
			->method('getIndexes')
			->willReturn([$index]);

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}


	public function testEnsureOracleIdentifierLengthLimitTooLongForeignKeyName() {
		$this->expectException(\InvalidArgumentException::class);

		$foreignKey = $this->createMock(ForeignKeyConstraint::class);
		$foreignKey->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 31));

		$table = $this->createMock(Table::class);
		$table->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 30));

		$table->expects($this->once())
			->method('getColumns')
			->willReturn([]);
		$table->expects($this->once())
			->method('getIndexes')
			->willReturn([]);
		$table->expects($this->once())
			->method('getForeignKeys')
			->willReturn([$foreignKey]);

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([$table]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}


	public function testEnsureOracleIdentifierLengthLimitTooLongSequenceName() {
		$this->expectException(\InvalidArgumentException::class);

		$sequence = $this->createMock(Sequence::class);
		$sequence->expects($this->any())
			->method('getName')
			->willReturn(\str_repeat('a', 31));

		$schema = $this->createMock(Schema::class);
		$schema->expects($this->once())
			->method('getTables')
			->willReturn([]);
		$schema->expects($this->once())
			->method('getSequences')
			->willReturn([$sequence]);

		$sourceSchema = $this->createMock(Schema::class);
		$sourceSchema->expects($this->any())
			->method('getTable')
			->willThrowException(new SchemaException());
		$sourceSchema->expects($this->any())
			->method('hasSequence')
			->willReturn(false);

		self::invokePrivate($this->migrationService, 'ensureOracleIdentifierLengthLimit', [$sourceSchema, $schema, 3]);
	}
}
