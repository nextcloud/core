<?php
/**
 * @author Arthur Schiwon <blizzz@owncloud.com>
 * @author Lukas Reschke <lukas@owncloud.com>
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

namespace Test\Group;

use OCP\IUserSession;

class MetaDataTest extends \Test\TestCase {
	/** @var \OC\Group\Manager */
	private $groupManager;
	/** @var \OCP\IUserSession */
	private $userSession;
	/** @var \OC\Group\MetaData */
	private $groupMetadata;
	/** @var bool */
	private $isAdmin = true;

	protected function setUp(): void {
		parent::setUp();
		$this->groupManager = $this->getMockBuilder('\OC\Group\Manager')
			->disableOriginalConstructor()
			->getMock();
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupMetadata = new \OC\Group\MetaData(
			'foo',
			$this->isAdmin,
			$this->groupManager,
			$this->userSession
		);
	}

	private function getGroupMock($countCallCount = 0) {
		$group = $this->getMockBuilder('\OC\Group\Group')
			->disableOriginalConstructor()
			->getMock();

		$group->expects($this->exactly(6))
			->method('getGID')
			->will($this->onConsecutiveCalls(
				'admin', 'admin',
				'g2', 'g2',
				'g3', 'g3'));

		$group->expects($this->exactly(3))
			->method('getDisplayName')
			->will($this->onConsecutiveCalls(
				'admin',
				'g2',
				'g3'));

		$group->expects($this->exactly($countCallCount))
			->method('count')
			->with('')
			->will($this->onConsecutiveCalls(2, 3, 5));

		return $group;
	}


	public function testGet() {
		$group = $this->getGroupMock();
		$groups = array_fill(0, 3, $group);

		$this->groupManager->expects($this->once())
			->method('search')
			->with('')
			->willReturn($groups);

		list($adminGroups, $ordinaryGroups) = $this->groupMetadata->get();

		$this->assertSame(1, count($adminGroups));
		$this->assertSame(2, count($ordinaryGroups));

		$this->assertSame('g2', $ordinaryGroups[0]['name']);
		// user count is not loaded
		$this->assertSame(0, $ordinaryGroups[0]['usercount']);
	}

	public function testGetWithSorting() {
		$this->groupMetadata->setSorting(1);
		$group = $this->getGroupMock(3);
		$groups = array_fill(0, 3, $group);

		$this->groupManager->expects($this->once())
			->method('search')
			->with('')
			->willReturn($groups);

		list($adminGroups, $ordinaryGroups) = $this->groupMetadata->get();

		$this->assertSame(1, count($adminGroups));
		$this->assertSame(2, count($ordinaryGroups));

		$this->assertSame('g3', $ordinaryGroups[0]['name']);
		$this->assertSame(5, $ordinaryGroups[0]['usercount']);
	}

	public function testGetWithCache() {
		$group = $this->getGroupMock();
		$groups = array_fill(0, 3, $group);

		$this->groupManager->expects($this->once())
			->method('search')
			->with('')
			->willReturn($groups);

		//two calls, if caching fails call counts for group and groupmanager
		//are exceeded
		$this->groupMetadata->get();
		$this->groupMetadata->get();
	}

	//get() does not need to be tested with search parameters, because they are
	//solely and only passed to GroupManager and Group.

	public function testGetGroupsAsAdmin() {
		$this->groupManager
			->expects($this->once())
			->method('search')
			->with('Foo')
			->willReturn(['DummyValue']);

		$expected = ['DummyValue'];
		$this->assertSame($expected, $this->invokePrivate($this->groupMetadata, 'getGroups', ['Foo']));
	}
}
