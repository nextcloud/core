<?php
/**
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

namespace Test;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\SubAdminAddedEvent;
use OCP\Group\Events\SubAdminRemovedEvent;

/**
 * @group DB
 */
class SubAdminTest extends \Test\TestCase {

	/** @var \OCP\IUserManager */
	private $userManager;

	/** @var \OCP\IGroupManager */
	private $groupManager;

	/** @var \OCP\IDBConnection */
	private $dbConn;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var \OCP\IUser[] */
	private $users;

	/** @var \OCP\IGroup[] */
	private $groups;

	protected function setUp(): void {
		$this->users = [];
		$this->groups = [];

		$this->userManager = \OC::$server->getUserManager();
		$this->groupManager = \OC::$server->getGroupManager();
		$this->dbConn = \OC::$server->getDatabaseConnection();
		$this->eventDispatcher = \OC::$server->get(IEventDispatcher::class);

		// Create 3 users and 3 groups
		for ($i = 0; $i < 3; $i++) {
			$this->users[] = $this->userManager->createUser('user'.$i, 'user');
			$this->groups[] = $this->groupManager->createGroup('group'.$i);
		}

		// Create admin group
		if (!$this->groupManager->groupExists('admin')) {
			$this->groupManager->createGroup('admin');
		}

		// Create "orphaned" users and groups (scenario: temporarily disabled
		// backend)
		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('group_admin')
			->values([
				'gid' => $qb->createNamedParameter($this->groups[0]->getGID()),
				'uid' => $qb->createNamedParameter('orphanedUser')
			])
			->execute();
		$qb->insert('group_admin')
			->values([
				'gid' => $qb->createNamedParameter('orphanedGroup'),
				'uid' => $qb->createNamedParameter('orphanedUser')
			])
			->execute();
		$qb->insert('group_admin')
			->values([
				'gid' => $qb->createNamedParameter('orphanedGroup'),
				'uid' => $qb->createNamedParameter($this->users[0]->getUID())
			])
			->execute();
	}

	protected function tearDown(): void {
		foreach ($this->users as $user) {
			$user->delete();
		}

		foreach ($this->groups as $group) {
			$group->delete();
		}

		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('group_admin')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter('orphanedUser')))
			->orWhere($qb->expr()->eq('gid', $qb->createNamedParameter('orphanedGroup')))
			->execute();
	}

	public function testCreateSubAdmin() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);

		// Look for subadmin in the database
		$qb = $this->dbConn->getQueryBuilder();
		$result = $qb->select(['gid', 'uid'])
			->from('group_admin')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($this->groups[0]->getGID())))
			->andWHere($qb->expr()->eq('uid', $qb->createNamedParameter($this->users[0]->getUID())))
			->execute()
			->fetch();
		$this->assertEquals(
			[
				'gid' => $this->groups[0]->getGID(),
				'uid' => $this->users[0]->getUID()
			], $result);

		// Delete subadmin
		$result = $qb->delete('*PREFIX*group_admin')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($this->groups[0]->getGID())))
			->andWHere($qb->expr()->eq('uid', $qb->createNamedParameter($this->users[0]->getUID())))
			->execute();
	}

	public function testDeleteSubAdmin() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);
		$subAdmin->deleteSubAdmin($this->users[0], $this->groups[0]);

		// DB query should be empty
		$qb = $this->dbConn->getQueryBuilder();
		$result = $qb->select(['gid', 'uid'])
			->from('group_admin')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($this->groups[0]->getGID())))
			->andWHere($qb->expr()->eq('uid', $qb->createNamedParameter($this->users[0]->getUID())))
			->execute()
			->fetch();
		$this->assertEmpty($result);
	}

	public function testGetSubAdminsGroups() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[1]);

		$result = $subAdmin->getSubAdminsGroups($this->users[0]);

		$this->assertContains($this->groups[0], $result);
		$this->assertContains($this->groups[1], $result);
		$this->assertNotContains($this->groups[2], $result);
		$this->assertNotContains(null, $result);

		$subAdmin->deleteSubAdmin($this->users[0], $this->groups[0]);
		$subAdmin->deleteSubAdmin($this->users[0], $this->groups[1]);
	}

	public function testGetGroupsSubAdmins() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);
		$subAdmin->createSubAdmin($this->users[1], $this->groups[0]);

		$result = $subAdmin->getGroupsSubAdmins($this->groups[0]);

		$this->assertContains($this->users[0], $result);
		$this->assertContains($this->users[1], $result);
		$this->assertNotContains($this->users[2], $result);
		$this->assertNotContains(null, $result);

		$subAdmin->deleteSubAdmin($this->users[0], $this->groups[0]);
		$subAdmin->deleteSubAdmin($this->users[1], $this->groups[0]);
	}

	public function testGetAllSubAdmin() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);

		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);
		$subAdmin->createSubAdmin($this->users[1], $this->groups[1]);
		$subAdmin->createSubAdmin($this->users[2], $this->groups[1]);

		$result = $subAdmin->getAllSubAdmins();

		$this->assertContains(['user' => $this->users[0], 'group' => $this->groups[0]], $result);
		$this->assertContains(['user' => $this->users[1], 'group' => $this->groups[1]], $result);
		$this->assertContains(['user' => $this->users[2], 'group' => $this->groups[1]], $result);
		$this->assertNotContains(['user' => null, 'group' => null], $result);
	}

	public function testIsSubAdminofGroup() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);

		$this->assertTrue($subAdmin->isSubAdminOfGroup($this->users[0], $this->groups[0]));
		$this->assertFalse($subAdmin->isSubAdminOfGroup($this->users[0], $this->groups[1]));
		$this->assertFalse($subAdmin->isSubAdminOfGroup($this->users[1], $this->groups[0]));

		$subAdmin->deleteSubAdmin($this->users[0], $this->groups[0]);
	}

	public function testIsSubAdmin() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);

		$this->assertTrue($subAdmin->isSubAdmin($this->users[0]));
		$this->assertFalse($subAdmin->isSubAdmin($this->users[1]));

		$subAdmin->deleteSubAdmin($this->users[0], $this->groups[0]);
	}

	public function testIsSubAdminAsAdmin() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$this->groupManager->get('admin')->addUser($this->users[0]);

		$this->assertTrue($subAdmin->isSubAdmin($this->users[0]));
	}

	public function testIsUserAccessible() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$this->groups[0]->addUser($this->users[1]);
		$this->groups[1]->addUser($this->users[1]);
		$this->groups[1]->addUser($this->users[2]);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);
		$subAdmin->createSubAdmin($this->users[2], $this->groups[2]);

		$this->assertTrue($subAdmin->isUserAccessible($this->users[0], $this->users[1]));
		$this->assertFalse($subAdmin->isUserAccessible($this->users[0], $this->users[2]));
		$this->assertFalse($subAdmin->isUserAccessible($this->users[2], $this->users[0]));

		$subAdmin->deleteSubAdmin($this->users[0], $this->groups[0]);
		$subAdmin->deleteSubAdmin($this->users[2], $this->groups[2]);
	}

	public function testIsUserAccessibleAsUser() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$this->assertFalse($subAdmin->isUserAccessible($this->users[0], $this->users[1]));
	}

	public function testIsUserAccessibleAdmin() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);
		$subAdmin->createSubAdmin($this->users[0], $this->groups[0]);
		$this->groupManager->get('admin')->addUser($this->users[1]);

		$this->assertFalse($subAdmin->isUserAccessible($this->users[0], $this->users[1]));
	}

	public function testPostDeleteUser() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);

		$user = array_shift($this->users);
		foreach ($this->groups as $group) {
			$subAdmin->createSubAdmin($user, $group);
		}

		$user->delete();
		$this->assertEmpty($subAdmin->getAllSubAdmins());
	}

	public function testPostDeleteGroup() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);

		$group = array_shift($this->groups);
		foreach ($this->users as $user) {
			$subAdmin->createSubAdmin($user, $group);
		}

		$group->delete();
		$this->assertEmpty($subAdmin->getAllSubAdmins());
	}

	public function testHooks() {
		$subAdmin = new \OC\SubAdmin($this->userManager, $this->groupManager, $this->dbConn, $this->eventDispatcher);

		$test = $this;
		$u = $this->users[0];
		$g = $this->groups[0];
		$count = 0;

		$this->eventDispatcher->addListener(SubAdminAddedEvent::class, function (SubAdminAddedEvent $event) use ($test, $u, $g, &$count) {
			$test->assertEquals($u->getUID(), $event->getUser()->getUID());
			$test->assertEquals($g->getGID(), $event->getGroup()->getGID());
			$count++;
		});

		$this->eventDispatcher->addListener(SubAdminRemovedEvent::class, function ($event) use ($test, $u, $g, &$count) {
			$test->assertEquals($u->getUID(), $event->getUser()->getUID());
			$test->assertEquals($g->getGID(), $event->getGroup()->getGID());
			$count++;
		});

		$subAdmin->createSubAdmin($u, $g);
		$this->assertEquals(1, $count);

		$subAdmin->deleteSubAdmin($u, $g);
		$this->assertEquals(2, $count);
	}
}
