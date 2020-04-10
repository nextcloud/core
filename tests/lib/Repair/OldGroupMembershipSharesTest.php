<?php
/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Repair;

use OC\Repair\OldGroupMembershipShares;
use OC\Share\Constants;
use OCP\Migration\IOutput;

/**
 * Class OldGroupMembershipSharesTest
 *
 * @group DB
 *
 * @package Test\Repair
 */
class OldGroupMembershipSharesTest extends \Test\TestCase {

	/** @var OldGroupMembershipShares */
	protected $repair;

	/** @var \OCP\IDBConnection */
	protected $connection;

	/** @var \OCP\IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $groupManager;

	protected function setUp(): void {
		parent::setUp();

		/** \OCP\IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
		$this->groupManager = $this->getMockBuilder('OCP\IGroupManager')
			->disableOriginalConstructor()
			->getMock();
		$this->connection = \OC::$server->getDatabaseConnection();

		$this->deleteAllShares();
	}

	protected function tearDown(): void {
		$this->deleteAllShares();

		parent::tearDown();
	}

	protected function deleteAllShares() {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('share')->execute();
	}

	public function testRun() {
		$repair = new OldGroupMembershipShares(
			$this->connection,
			$this->groupManager
		);

		$this->groupManager->expects($this->exactly(2))
			->method('isInGroup')
			->willReturnMap([
				['member', 'group', true],
				['not-a-member', 'group', false],
			]);

		$parent = $this->createShare(Constants::SHARE_TYPE_GROUP, 'group', null);
		$group2 = $this->createShare(Constants::SHARE_TYPE_GROUP, 'group2', $parent);
		$user1 = $this->createShare(Constants::SHARE_TYPE_USER, 'user1', $parent);

		// \OC\Share\Constant::$shareTypeGroupUserUnique === 2
		$member = $this->createShare(2, 'member', $parent);
		$notAMember = $this->createShare(2, 'not-a-member', $parent);

		$query = $this->connection->getQueryBuilder();
		$result = $query->select('id')
			->from('share')
			->orderBy('id', 'ASC')
			->execute();
		$rows = $result->fetchAll();
		$this->assertEquals([['id' => $parent], ['id' => $group2], ['id' => $user1], ['id' => $member], ['id' => $notAMember]], $rows);
		$result->closeCursor();

		/** @var IOutput | \PHPUnit_Framework_MockObject_MockObject $outputMock */
		$outputMock = $this->getMockBuilder('\OCP\Migration\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$repair->run($outputMock);

		$query = $this->connection->getQueryBuilder();
		$result = $query->select('id')
			->from('share')
			->orderBy('id', 'ASC')
			->execute();
		$rows = $result->fetchAll();
		$this->assertEquals([['id' => $parent], ['id' => $group2], ['id' => $user1], ['id' => $member]], $rows);
		$result->closeCursor();
	}

	/**
	 * @param string $shareType
	 * @param string $shareWith
	 * @param null|int $parent
	 * @return int
	 */
	protected function createShare($shareType, $shareWith, $parent) {
		$qb = $this->connection->getQueryBuilder();
		$shareValues = [
			'share_type' => $qb->expr()->literal($shareType),
			'share_with' => $qb->expr()->literal($shareWith),
			'uid_owner' => $qb->expr()->literal('user1'),
			'item_type' => $qb->expr()->literal('folder'),
			'item_source' => $qb->expr()->literal(123),
			'item_target' => $qb->expr()->literal('/123'),
			'file_source' => $qb->expr()->literal(123),
			'file_target' => $qb->expr()->literal('/test'),
			'permissions' => $qb->expr()->literal(1),
			'stime' => $qb->expr()->literal(time()),
			'expiration' => $qb->expr()->literal('2015-09-25 00:00:00'),
		];

		if ($parent) {
			$shareValues['parent'] = $qb->expr()->literal($parent);
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('share')
			->values($shareValues)
			->execute();

		return $this->connection->lastInsertId('*PREFIX*share');
	}
}
