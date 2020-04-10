<?php

/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\DB;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use OC\DB\MDB2SchemaReader;
use OCP\IConfig;
use Test\TestCase;

/**
 * Class MDB2SchemaReaderTest
 *
 * @group DB
 *
 * @package Test\DB
 */
class MDB2SchemaReaderTest extends TestCase {
	/**
	 * @var MDB2SchemaReader $reader
	 */
	protected $reader;

	/**
	 * @return IConfig
	 */
	protected function getConfig() {
		/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject $config */
		$config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$config->expects($this->any())
			->method('getSystemValue')
			->willReturnMap([
				['dbname', 'owncloud', 'testDB'],
				['dbtableprefix', 'oc_', 'test_']
			]);
		return $config;
	}

	public function testRead() {
		$reader = new MDB2SchemaReader($this->getConfig(), new MySqlPlatform());
		$schema = $reader->loadSchemaFromFile(__DIR__ . '/testschema.xml', new Schema());
		$this->assertCount(1, $schema->getTables());

		$table = $schema->getTable('test_table');
		$this->assertCount(8, $table->getColumns());

		$this->assertEquals(4, $table->getColumn('integerfield')->getLength());
		$this->assertTrue($table->getColumn('integerfield')->getAutoincrement());
		$this->assertEquals(0, $table->getColumn('integerfield')->getDefault());
		$this->assertTrue($table->getColumn('integerfield')->getNotnull());
		$this->assertInstanceOf('Doctrine\DBAL\Types\IntegerType', $table->getColumn('integerfield')->getType());

		$this->assertSame(10, $table->getColumn('integerfield_default')->getDefault());

		$this->assertEquals(32, $table->getColumn('textfield')->getLength());
		$this->assertFalse($table->getColumn('textfield')->getAutoincrement());
		$this->assertSame('foo', $table->getColumn('textfield')->getDefault());
		$this->assertTrue($table->getColumn('textfield')->getNotnull());
		$this->assertInstanceOf('Doctrine\DBAL\Types\StringType', $table->getColumn('textfield')->getType());

		$this->assertNull($table->getColumn('clobfield')->getLength());
		$this->assertFalse($table->getColumn('clobfield')->getAutoincrement());
		$this->assertNull($table->getColumn('clobfield')->getDefault());
		$this->assertFalse($table->getColumn('clobfield')->getNotnull());
		$this->assertInstanceOf('Doctrine\DBAL\Types\TextType', $table->getColumn('clobfield')->getType());

		$this->assertNull($table->getColumn('booleanfield')->getLength());
		$this->assertFalse($table->getColumn('booleanfield')->getAutoincrement());
		$this->assertNull($table->getColumn('booleanfield')->getDefault());
		$this->assertInstanceOf('Doctrine\DBAL\Types\BooleanType', $table->getColumn('booleanfield')->getType());

		$this->assertTrue($table->getColumn('booleanfield_true')->getDefault());
		$this->assertFalse($table->getColumn('booleanfield_false')->getDefault());

		$this->assertEquals(12, $table->getColumn('decimalfield_precision_scale')->getPrecision());
		$this->assertEquals(2, $table->getColumn('decimalfield_precision_scale')->getScale());

		$this->assertCount(2, $table->getIndexes());
		$this->assertEquals(['integerfield'], $table->getIndex('primary')->getUnquotedColumns());
		$this->assertTrue($table->getIndex('primary')->isPrimary());
		$this->assertTrue($table->getIndex('primary')->isUnique());
		$this->assertEquals(['booleanfield'], $table->getIndex('index_boolean')->getUnquotedColumns());
		$this->assertFalse($table->getIndex('index_boolean')->isPrimary());
		$this->assertFalse($table->getIndex('index_boolean')->isUnique());
	}
}
