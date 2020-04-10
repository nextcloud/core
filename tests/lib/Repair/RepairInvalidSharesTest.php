<?php
/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Repair;

use OC\Repair\RepairInvalidShares;
use OC\Share\Constants;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Test\TestCase;

/**
 * Tests for repairing invalid shares
 *
 * @group DB
 *
 * @see \OC\Repair\RepairInvalidShares
 */
class RepairInvalidSharesTest extends TestCase {

	/** @var IRepairStep */
	private $repair;

	/** @var \OCP\IDBConnection */
	private $connection;

	protected function setUp(): void {
		parent::setUp();

		$config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$config->expects($this->any())
			->method('getSystemValue')
			->with('version')
			->willReturn('12.0.0.0');

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->deleteAllShares();

		/** @var \OCP\IConfig $config */
		$this->repair = new RepairInvalidShares($config, $this->connection);
	}

	protected function tearDown(): void {
		$this->deleteAllShares();

		parent::tearDown();
	}

	protected function deleteAllShares() {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('share')->execute();
	}

	/**
	 * Test remove shares where the parent share does not exist anymore
	 */
	public function testSharesNonExistingParent() {
		$qb = $this->connection->getQueryBuilder();
		$shareValues = [
			'share_type' => $qb->expr()->literal(Constants::SHARE_TYPE_USER),
			'share_with' => $qb->expr()->literal('recipientuser1'),
			'uid_owner' => $qb->expr()->literal('user1'),
			'item_type' => $qb->expr()->literal('folder'),
			'item_source' => $qb->expr()->literal(123),
			'item_target' => $qb->expr()->literal('/123'),
			'file_source' => $qb->expr()->literal(123),
			'file_target' => $qb->expr()->literal('/test'),
			'permissions' => $qb->expr()->literal(1),
			'stime' => $qb->expr()->literal(time()),
			'expiration' => $qb->expr()->literal('2015-09-25 00:00:00')
		];

		// valid share
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('share')
			->values($shareValues)
			->execute();
		$parent = $this->getLastShareId();

		// share with existing parent
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('share')
			->values(array_merge($shareValues, [
				'parent' => $qb->expr()->literal($parent),
			]))->execute();
		$validChild = $this->getLastShareId();

		// share with non-existing parent
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('share')
			->values(array_merge($shareValues, [
				'parent' => $qb->expr()->literal($parent + 100),
			]))->execute();
		$invalidChild = $this->getLastShareId();

		$query = $this->connection->getQueryBuilder();
		$result = $query->select('id')
			->from('share')
			->orderBy('id', 'ASC')
			->execute();
		$rows = $result->fetchAll();
		$this->assertEquals([['id' => $parent], ['id' => $validChild], ['id' => $invalidChild]], $rows);
		$result->closeCursor();

		/** @var IOutput | \PHPUnit_Framework_MockObject_MockObject $outputMock */
		$outputMock = $this->getMockBuilder('\OCP\Migration\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$this->repair->run($outputMock);

		$query = $this->connection->getQueryBuilder();
		$result = $query->select('id')
			->from('share')
			->orderBy('id', 'ASC')
			->execute();
		$rows = $result->fetchAll();
		$this->assertEquals([['id' => $parent], ['id' => $validChild]], $rows);
		$result->closeCursor();
	}

	public function fileSharePermissionsProvider() {
		return [
			// unchanged for folder
			[
				'folder',
				31,
				31,
			],
			// unchanged for read-write + share
			[
				'file',
				\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE,
				\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE,
			],
			// fixed for all perms
			[
				'file',
				\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE | \OCP\Constants::PERMISSION_SHARE,
				\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE,
			],
		];
	}

	/**
	 * Test adjusting file share permissions
	 *
	 * @dataProvider fileSharePermissionsProvider
	 */
	public function testFileSharePermissions($itemType, $testPerms, $expectedPerms) {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->expr()->literal(Constants::SHARE_TYPE_LINK),
				'uid_owner' => $qb->expr()->literal('user1'),
				'item_type' => $qb->expr()->literal($itemType),
				'item_source' => $qb->expr()->literal(123),
				'item_target' => $qb->expr()->literal('/123'),
				'file_source' => $qb->expr()->literal(123),
				'file_target' => $qb->expr()->literal('/test'),
				'permissions' => $qb->expr()->literal($testPerms),
				'stime' => $qb->expr()->literal(time()),
			])
			->execute();

		$shareId = $this->getLastShareId();

		/** @var IOutput | \PHPUnit_Framework_MockObject_MockObject $outputMock */
		$outputMock = $this->getMockBuilder('\OCP\Migration\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$this->repair->run($outputMock);

		$results = $this->connection->getQueryBuilder()
			->select('*')
			->from('share')
			->orderBy('permissions', 'ASC')
			->execute()
			->fetchAll();

		$this->assertCount(1, $results);

		$updatedShare = $results[0];

		$this->assertEquals($expectedPerms, $updatedShare['permissions']);
	}

	/**
	 * @return int
	 */
	protected function getLastShareId() {
		return $this->connection->lastInsertId('*PREFIX*share');
	}
}
