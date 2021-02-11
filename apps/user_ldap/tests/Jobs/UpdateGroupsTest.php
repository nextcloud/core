<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\user_ldap\tests\Jobs;

use Doctrine\DBAL\Driver\Statement;
use OCA\User_LDAP\Group_Proxy;
use OCA\User_LDAP\Jobs\UpdateGroups;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class UpdateGroupsTest extends TestCase {

	/** @var Group_Proxy|\PHPUnit\Framework\MockObject\MockObject */
	protected $groupBackend;
	/** @var IEventDispatcher|\PHPUnit\Framework\MockObject\MockObject */
	protected $dispatcher;
	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $groupManager;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $userManager;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	protected $logger;
	/** @var IDBConnection|\PHPUnit\Framework\MockObject\MockObject */
	protected $dbc;

	/** @var UpdateGroups */
	protected $updateGroupsJob;

	public function setUp(): void {
		$this->groupBackend = $this->createMock(Group_Proxy::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->dbc = $this->createMock(IDBConnection::class);

		$this->updateGroupsJob = new UpdateGroups(
			$this->groupBackend,
			$this->dispatcher,
			$this->groupManager,
			$this->userManager,
			$this->logger,
			$this->dbc
		);
	}

	public function testHandleKnownGroups() {
		$knownGroups = [
			'emptyGroup' => \serialize([]),
			'stableGroup' => \serialize(['userA', 'userC', 'userE']),
			'groupWithAdditions' => \serialize(['userA', 'userC', 'userE']),
			'groupWithRemovals' => \serialize(['userA', 'userC', 'userDeleted', 'userE']),
			'groupWithAdditionsAndRemovals' => \serialize(['userA', 'userC', 'userE']),
			'vanishedGroup' => \serialize(['userB', 'userDeleted'])
		];
		$knownGroupsDB = [];
		foreach ($knownGroups as $gid => $members) {
			$knownGroupsDB[] = [
				'owncloudname' => $gid,
				'owncloudusers' => $members
			];
		}
		$actualGroups = [
			'emptyGroup' => [],
			'stableGroup' => ['userA', 'userC', 'userE'],
			'groupWithAdditions' => ['userA', 'userC', 'userE', 'userF'],
			'groupWithRemovals' => ['userA', 'userE'],
			'groupWithAdditionsAndRemovals' => ['userC', 'userE', 'userF'],
			'newGroup' => ['userB', 'userF'],
		];
		$groups = array_intersect(array_keys($knownGroups), array_keys($actualGroups));

		/** @var IQueryBuilder|\PHPUnit\Framework\MockObject\MockObject $updateQb */
		$updateQb = $this->createMock(IQueryBuilder::class);
		$updateQb->expects($this->once())
			->method('update')
			->willReturn($updateQb);
		$updateQb->expects($this->once())
			->method('set')
			->willReturn($updateQb);
		$updateQb->expects($this->once())
			->method('where')
			->willReturn($updateQb);
		// three groups need to be updated
		$updateQb->expects($this->exactly(3))
			->method('setParameters');
		$updateQb->expects($this->exactly(3))
			->method('execute');
		$updateQb->expects($this->any())
			->method('expr')
			->willReturn($this->createMock(IExpressionBuilder::class));

		$stmt = $this->createMock(Statement::class);
		$stmt->expects($this->once())
			->method('fetchAll')
			->willReturn($knownGroupsDB);

		$selectQb = $this->createMock(IQueryBuilder::class);
		$selectQb->expects($this->once())
			->method('select')
			->willReturn($selectQb);
		$selectQb->expects($this->once())
			->method('from')
			->willReturn($selectQb);
		$selectQb->expects($this->once())
			->method('execute')
			->willReturn($stmt);

		$this->dbc->expects($this->any())
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($updateQb, $selectQb);

		$this->groupBackend->expects($this->any())
			->method('usersInGroup')
			->willReturnCallback(function ($groupID) use ($actualGroups) {
				return isset($actualGroups[$groupID]) ? $actualGroups[$groupID] : [];
			});

		$this->groupManager->expects($this->any())
			->method('get')
			->willReturnCallback(function (string $groupId): ?IGroup {
				if ($groupId === 'vanishedGroup') {
					return null;
				}
				return $this->createMock(IGroup::class);
			});

		$this->userManager->expects($this->exactly(5))
			->method('get')
			->willReturnCallback(function (string $userId) {
				if ($userId === 'userDeleted') {
					// user already deleted
					return null;
				}
				return $this->createMock(IUser::class);
			});

		$addedEvents = 0;
		$removedEvents = 0;
		$this->dispatcher->expects($this->exactly(4))
			->method('dispatchTyped')
			->willReturnCallback(function ($event) use (&$addedEvents, &$removedEvents) {
				if ($event instanceof UserRemovedEvent) {
					$removedEvents++;
				} elseif ($event instanceof UserAddedEvent) {
					$addedEvents++;
				}
			});

		$this->invokePrivate($this->updateGroupsJob, 'handleKnownGroups', [$groups]);

		$this->assertSame(2, $removedEvents);
		$this->assertSame(2, $addedEvents);
		// and no event for the user that is already deleted, the DB is nevertheless updated, hence 5
	}
}
