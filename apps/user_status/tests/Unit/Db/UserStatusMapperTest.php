<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\UserStatus\Tests\Db;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Db\UserStatusMapper;
use Test\TestCase;

class UserStatusMapperTest extends TestCase {

	/** @var UserStatusMapper */
	private $mapper;

	protected function setUp(): void {
		parent::setUp();

		// make sure that DB is empty
		$qb = self::$realDatabase->getQueryBuilder();
		$qb->delete('user_status')->execute();

		$this->mapper = new UserStatusMapper(self::$realDatabase);
	}

	public function testGetTableName(): void {
		$this->assertEquals('user_status', $this->mapper->getTableName());
	}

	public function testGetFindAll(): void {
		$this->insertSampleStatuses();

		$allResults = $this->mapper->findAll();
		$this->assertCount(3, $allResults);

		$limitedResults = $this->mapper->findAll(2);
		$this->assertCount(2, $limitedResults);
		$this->assertEquals('admin', $limitedResults[0]->getUserId());
		$this->assertEquals('user1', $limitedResults[1]->getUserId());

		$offsetResults = $this->mapper->findAll(null, 2);
		$this->assertCount(1, $offsetResults);
		$this->assertEquals('user2', $offsetResults[0]->getUserId());
	}

	public function testFindAllRecent(): void {
		$this->insertSampleStatuses();

		$allResults = $this->mapper->findAllRecent(2, 0);
		$this->assertCount(2, $allResults);
		$this->assertEquals('user2', $allResults[0]->getUserId());
		$this->assertEquals('user1', $allResults[1]->getUserId());
	}

	public function testGetFind(): void {
		$this->insertSampleStatuses();

		$adminStatus = $this->mapper->findByUserId('admin');
		$this->assertEquals('admin', $adminStatus->getUserId());
		$this->assertEquals('offline', $adminStatus->getStatus());
		$this->assertEquals(0, $adminStatus->getStatusTimestamp());
		$this->assertEquals(false, $adminStatus->getIsUserDefined());
		$this->assertEquals(null, $adminStatus->getCustomIcon());
		$this->assertEquals(null, $adminStatus->getCustomMessage());
		$this->assertEquals(null, $adminStatus->getClearAt());

		$user1Status = $this->mapper->findByUserId('user1');
		$this->assertEquals('user1', $user1Status->getUserId());
		$this->assertEquals('dnd', $user1Status->getStatus());
		$this->assertEquals(5000, $user1Status->getStatusTimestamp());
		$this->assertEquals(true, $user1Status->getIsUserDefined());
		$this->assertEquals('💩', $user1Status->getCustomIcon());
		$this->assertEquals('Do not disturb', $user1Status->getCustomMessage());
		$this->assertEquals(50000, $user1Status->getClearAt());

		$user2Status = $this->mapper->findByUserId('user2');
		$this->assertEquals('user2', $user2Status->getUserId());
		$this->assertEquals('away', $user2Status->getStatus());
		$this->assertEquals(6000, $user2Status->getStatusTimestamp());
		$this->assertEquals(false, $user2Status->getIsUserDefined());
		$this->assertEquals('🏝', $user2Status->getCustomIcon());
		$this->assertEquals('On vacation', $user2Status->getCustomMessage());
		$this->assertEquals(60000, $user2Status->getClearAt());
	}

	public function testFindByUserIds(): void {
		$this->insertSampleStatuses();

		$statuses = $this->mapper->findByUserIds(['admin', 'user2']);
		$this->assertCount(2, $statuses);

		$adminStatus = $statuses[0];
		$this->assertEquals('admin', $adminStatus->getUserId());
		$this->assertEquals('offline', $adminStatus->getStatus());
		$this->assertEquals(0, $adminStatus->getStatusTimestamp());
		$this->assertEquals(false, $adminStatus->getIsUserDefined());
		$this->assertEquals(null, $adminStatus->getCustomIcon());
		$this->assertEquals(null, $adminStatus->getCustomMessage());
		$this->assertEquals(null, $adminStatus->getClearAt());

		$user2Status = $statuses[1];
		$this->assertEquals('user2', $user2Status->getUserId());
		$this->assertEquals('away', $user2Status->getStatus());
		$this->assertEquals(6000, $user2Status->getStatusTimestamp());
		$this->assertEquals(false, $user2Status->getIsUserDefined());
		$this->assertEquals('🏝', $user2Status->getCustomIcon());
		$this->assertEquals('On vacation', $user2Status->getCustomMessage());
		$this->assertEquals(60000, $user2Status->getClearAt());
	}

	public function testUserIdUnique(): void {
		// Test that inserting a second status for a user is throwing an exception

		$userStatus1 = new UserStatus();
		$userStatus1->setUserId('admin');
		$userStatus1->setStatus('dnd');
		$userStatus1->setStatusTimestamp(5000);
		$userStatus1->setIsUserDefined(true);

		$this->mapper->insert($userStatus1);

		$userStatus2 = new UserStatus();
		$userStatus2->setUserId('admin');
		$userStatus2->setStatus('away');
		$userStatus2->setStatusTimestamp(6000);
		$userStatus2->setIsUserDefined(false);

		$this->expectException(UniqueConstraintViolationException::class);

		$this->mapper->insert($userStatus2);
	}

	/**
	 * @param string $status
	 * @param bool $isUserDefined
	 * @param int $timestamp
	 * @param bool $expectsClean
	 *
	 * @dataProvider clearStatusesOlderThanDataProvider
	 */
	public function testClearStatusesOlderThan(string $status, bool $isUserDefined, int $timestamp, bool $expectsClean): void {
		$oldStatus = UserStatus::fromParams([
			'userId' => 'john.doe',
			'status' => $status,
			'isUserDefined' => $isUserDefined,
			'statusTimestamp' => $timestamp,
		]);

		$this->mapper->insert($oldStatus);

		$this->mapper->clearStatusesOlderThan(5000, 8000);

		$updatedStatus = $this->mapper->findAll()[0];

		if ($expectsClean) {
			$this->assertEquals('offline', $updatedStatus->getStatus());
			$this->assertFalse($updatedStatus->getIsUserDefined());
			$this->assertEquals(8000, $updatedStatus->getStatusTimestamp());
		} else {
			$this->assertEquals($status, $updatedStatus->getStatus());
			$this->assertEquals($isUserDefined, $updatedStatus->getIsUserDefined());
			$this->assertEquals($timestamp, $updatedStatus->getStatusTimestamp());
		}
	}

	public function clearStatusesOlderThanDataProvider(): array {
		return [
			['online', true, 6000, false],
			['online', true, 4000, true],
			['online', false, 6000, false],
			['online', false, 4000, true],
			['away', true, 6000, false],
			['away', true, 4000, false],
			['away', false, 6000, false],
			['away', false, 4000, true],
			['dnd', true, 6000, false],
			['dnd', true, 4000, false],
			['invisible', true, 6000, false],
			['invisible', true, 4000, false],
		];
	}

	public function testClearMessagesOlderThan(): void {
		$this->insertSampleStatuses();

		$this->mapper->clearMessagesOlderThan(55000);

		$allStatuses = $this->mapper->findAll();
		$this->assertCount(3, $allStatuses);

		$user1Status = $this->mapper->findByUserId('user1');
		$this->assertEquals('user1', $user1Status->getUserId());
		$this->assertEquals('dnd', $user1Status->getStatus());
		$this->assertEquals(5000, $user1Status->getStatusTimestamp());
		$this->assertEquals(true, $user1Status->getIsUserDefined());
		$this->assertEquals(null, $user1Status->getCustomIcon());
		$this->assertEquals(null, $user1Status->getCustomMessage());
		$this->assertEquals(null, $user1Status->getClearAt());
	}

	private function insertSampleStatuses(): void {
		$userStatus1 = new UserStatus();
		$userStatus1->setUserId('admin');
		$userStatus1->setStatus('offline');
		$userStatus1->setStatusTimestamp(0);
		$userStatus1->setIsUserDefined(false);

		$userStatus2 = new UserStatus();
		$userStatus2->setUserId('user1');
		$userStatus2->setStatus('dnd');
		$userStatus2->setStatusTimestamp(5000);
		$userStatus2->setIsUserDefined(true);
		$userStatus2->setCustomIcon('💩');
		$userStatus2->setCustomMessage('Do not disturb');
		$userStatus2->setClearAt(50000);

		$userStatus3 = new UserStatus();
		$userStatus3->setUserId('user2');
		$userStatus3->setStatus('away');
		$userStatus3->setStatusTimestamp(6000);
		$userStatus3->setIsUserDefined(false);
		$userStatus3->setCustomIcon('🏝');
		$userStatus3->setCustomMessage('On vacation');
		$userStatus3->setClearAt(60000);

		$this->mapper->insert($userStatus1);
		$this->mapper->insert($userStatus2);
		$this->mapper->insert($userStatus3);
	}
}
