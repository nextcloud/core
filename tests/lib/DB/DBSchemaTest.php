<?php
/**
 * Copyright (c) 2012 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\DB;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use OC_DB;
use OCP\ITempManager;
use OCP\Security\ISecureRandom;
use Test\TestCase;

/**
 * Class DBSchemaTest
 *
 * @group DB
 */
class DBSchemaTest extends TestCase {
	protected $schema_file;
	protected $schema_file2;
	protected $table1;
	protected $table2;
	/** @var ITempManager */
	protected $tempManager;

	protected function setUp(): void {
		parent::setUp();

		$this->tempManager = \OC::$server->getTempManager();
		$this->schema_file = $this->tempManager->getTemporaryFile();
		$this->schema_file2 = $this->tempManager->getTemporaryFile();

		$dbfile = \OC::$SERVERROOT.'/tests/data/db_structure.xml';
		$dbfile2 = \OC::$SERVERROOT.'/tests/data/db_structure2.xml';

		$r = '_' . \OC::$server->getSecureRandom()->
			generate(4, ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS) . '_';
		$content = file_get_contents($dbfile);
		$content = str_replace('*dbprefix*', '*dbprefix*'.$r, $content);
		file_put_contents($this->schema_file, $content);
		$content = file_get_contents($dbfile2);
		$content = str_replace('*dbprefix*', '*dbprefix*'.$r, $content);
		file_put_contents($this->schema_file2, $content);

		$this->table1 = $r.'cntcts_addrsbks';
		$this->table2 = $r.'cntcts_cards';
	}

	protected function tearDown(): void {
		unlink($this->schema_file);
		unlink($this->schema_file2);

		parent::tearDown();
	}

	// everything in one test, they depend on each other
	/**
	 * @medium
	 */
	public function testSchema() {
		$this->doTestSchemaCreating();
		$this->doTestSchemaChanging();
		$this->doTestSchemaDumping();
		$this->doTestSchemaRemoving();
	}

	public function doTestSchemaCreating() {
		OC_DB::createDbFromStructure($this->schema_file);
		$this->assertTableExist($this->table1);
		$this->assertTableExist($this->table2);
	}

	public function doTestSchemaChanging() {
		OC_DB::updateDbFromStructure($this->schema_file2);
		$this->assertTableExist($this->table2);
	}

	public function doTestSchemaDumping() {
		$outfile = $this->tempManager->getTemporaryFile();
		OC_DB::getDbStructure($outfile);
		$content = file_get_contents($outfile);
		$this->assertContains($this->table1, $content);
		$this->assertContains($this->table2, $content);
	}

	public function doTestSchemaRemoving() {
		OC_DB::removeDBStructure($this->schema_file);
		$this->assertTableNotExist($this->table1);
		$this->assertTableNotExist($this->table2);
	}

	/**
	 * @param string $table
	 */
	public function assertTableExist($table) {
		$this->assertTrue(OC_DB::tableExists($table), 'Table ' . $table . ' does not exist');
	}

	/**
	 * @param string $table
	 */
	public function assertTableNotExist($table) {
		$platform = \OC::$server->getDatabaseConnection()->getDatabasePlatform();
		if ($platform instanceof SqlitePlatform) {
			// sqlite removes the tables after closing the DB
			$this->addToAssertionCount(1);
		} else {
			$this->assertFalse(OC_DB::tableExists($table), 'Table ' . $table . ' exists.');
		}
	}
}
