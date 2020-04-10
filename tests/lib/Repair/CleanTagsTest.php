<?php
/**
 * Copyright (c) 2015 Joas Schilling <nickvergessen@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Repair;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IUserManager;
use OCP\Migration\IOutput;

/**
 * Tests for the cleaning the tags tables
 *
 * @group DB
 *
 * @see \OC\Repair\CleanTags
 */
class CleanTagsTest extends \Test\TestCase {

	/** @var \OC\Repair\CleanTags */
	protected $repair;

	/** @var \OCP\IDBConnection */
	protected $connection;

	/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var int */
	protected $createdFile;

	/** @var IOutput */
	private $outputMock;

	protected function setUp(): void {
		parent::setUp();

		$this->outputMock = $this->getMockBuilder('\OCP\Migration\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->repair = new \OC\Repair\CleanTags($this->connection, $this->userManager);
		$this->cleanUpTables();
	}

	protected function tearDown(): void {
		$this->cleanUpTables();

		parent::tearDown();
	}

	protected function cleanUpTables() {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('vcategory')
			->execute();

		$qb->delete('vcategory_to_object')
			->execute();

		$qb->delete('filecache')
			->execute();
	}

	public function testRun() {
		$cat1 = $this->addTagCategory('TestRepairCleanTags', 'files'); // Retained
		$cat2 = $this->addTagCategory('TestRepairCleanTags2', 'files'); // Deleted: Category will be empty
		$this->addTagCategory('TestRepairCleanTags3', 'files'); // Deleted: Category is empty
		$cat3 = $this->addTagCategory('TestRepairCleanTags', 'contacts'); // Retained

		$this->addTagEntry($this->getFileID(), $cat2, 'files'); // Retained
		$this->addTagEntry($this->getFileID() + 1, $cat1, 'files'); // Deleted: File is NULL
		$this->addTagEntry(9999999, $cat3, 'contacts'); // Retained
		$this->addTagEntry($this->getFileID(), $cat3 + 1, 'files'); // Deleted: Category is NULL

		$this->assertEntryCount('vcategory_to_object', 4, 'Assert tag entries count before repair step');
		$this->assertEntryCount('vcategory', 4, 'Assert tag categories count before repair step');

		self::invokePrivate($this->repair, 'deleteOrphanFileEntries', [$this->outputMock]);
		$this->assertEntryCount('vcategory_to_object', 3, 'Assert tag entries count after cleaning file entries');
		$this->assertEntryCount('vcategory', 4, 'Assert tag categories count after cleaning file entries');

		self::invokePrivate($this->repair, 'deleteOrphanTagEntries', [$this->outputMock]);
		$this->assertEntryCount('vcategory_to_object', 2, 'Assert tag entries count after cleaning tag entries');
		$this->assertEntryCount('vcategory', 4, 'Assert tag categories count after cleaning tag entries');

		self::invokePrivate($this->repair, 'deleteOrphanCategoryEntries', [$this->outputMock]);
		$this->assertEntryCount('vcategory_to_object', 2, 'Assert tag entries count after cleaning category entries');
		$this->assertEntryCount('vcategory', 2, 'Assert tag categories count after cleaning category entries');


		$this->addTagCategory('TestRepairCleanTags', 'contacts', 'userExists'); // Retained
		$this->assertEntryCount('vcategory', 3, 'Assert tag categories count before cleaning categories by users');

		$this->userManager->expects($this->exactly(2))
			->method('userExists')
			->willReturnMap([
				['userExists', true],
				['TestRepairCleanTags', false],
			]);

		self::invokePrivate($this->repair, 'deleteOrphanTags', [$this->outputMock]);
		$this->assertEntryCount('vcategory', 1, 'Assert tag categories count after cleaning categories by users');
	}

	/**
	 * @param string $tableName
	 * @param int $expected
	 * @param string $message
	 */
	protected function assertEntryCount($tableName, $expected, $message = '') {
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select($qb->func()->count('*'))
			->from($tableName)
			->execute();

		$this->assertEquals($expected, $result->fetchColumn(), $message);
	}

	/**
	 * Adds a new tag category to the database
	 *
	 * @param string $category
	 * @param string $type
	 * @param string $user
	 * @return int
	 */
	protected function addTagCategory($category, $type, $user = 'TestRepairCleanTags') {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('vcategory')
			->values([
				'uid'		=> $qb->createNamedParameter($user),
				'category'	=> $qb->createNamedParameter($category),
				'type'		=> $qb->createNamedParameter($type),
			])
			->execute();

		return (int) $this->getLastInsertID('vcategory', 'id');
	}

	/**
	 * Adds a new tag entry to the database
	 * @param int $objectId
	 * @param int $category
	 * @param string $type
	 */
	protected function addTagEntry($objectId, $category, $type) {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('vcategory_to_object')
			->values([
				'objid'			=> $qb->createNamedParameter($objectId, IQueryBuilder::PARAM_INT),
				'categoryid'	=> $qb->createNamedParameter($category, IQueryBuilder::PARAM_INT),
				'type'			=> $qb->createNamedParameter($type),
			])
			->execute();
	}

	/**
	 * Gets the last fileid from the file cache
	 * @return int
	 */
	protected function getFileID() {
		if ($this->createdFile) {
			return $this->createdFile;
		}

		$qb = $this->connection->getQueryBuilder();

		// We create a new file entry and delete it after the test again
		$fileName = $this->getUniqueID('TestRepairCleanTags', 12);
		$qb->insert('filecache')
			->values([
				'path'			=> $qb->createNamedParameter($fileName),
				'path_hash'		=> $qb->createNamedParameter(md5($fileName)),
			])
			->execute();
		$fileName = $this->getUniqueID('TestRepairCleanTags', 12);
		$qb->insert('filecache')
			->values([
				'path'			=> $qb->createNamedParameter($fileName),
				'path_hash'		=> $qb->createNamedParameter(md5($fileName)),
			])
			->execute();

		$this->createdFile = (int) $this->getLastInsertID('filecache', 'fileid');
		return $this->createdFile;
	}

	/**
	 * @param $tableName
	 * @param $idName
	 * @return int
	 */
	protected function getLastInsertID($tableName, $idName) {
		return $this->connection->lastInsertId("*PREFIX*$tableName");
	}
}
