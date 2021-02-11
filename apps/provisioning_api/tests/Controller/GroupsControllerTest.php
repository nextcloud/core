<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tom Needham <tom@owncloud.com>
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

namespace OCA\Provisioning_API\Tests\Controller;

use OC\Accounts\AccountManager;
use OC\Group\Manager;
use OC\SubAdmin;
use OC\User\NoUserException;
use OCA\Provisioning_API\Controller\GroupsController;
use OCP\Accounts\IAccountManager;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\UserInterface;

class GroupsControllerTest extends \Test\TestCase {

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	protected $request;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $userManager;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var Manager|\PHPUnit\Framework\MockObject\MockObject */
	protected $groupManager;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	protected $userSession;
	/** @var AccountManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $accountManager;
	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	protected $logger;
	/** @var  SubAdmin|\PHPUnit\Framework\MockObject\MockObject */
	protected $subAdminManager;

	/** @var GroupsController|\PHPUnit\Framework\MockObject\MockObject */
	protected $api;


	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(Manager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->accountManager = $this->createMock(AccountManager::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->subAdminManager = $this->createMock(SubAdmin::class);

		$this->groupManager
				->method('getSubAdmin')
				->willReturn($this->subAdminManager);

		$this->api = $this->getMockBuilder(GroupsController::class)
			->setConstructorArgs([
				'provisioning_api',
				$this->request,
				$this->userManager,
				$this->config,
				$this->groupManager,
				$this->userSession,
				$this->accountManager,
				$this->l10nFactory,
				$this->logger
			])
			->setMethods(['fillStorageInfo'])
			->getMock();
	}

	/**
	 * @param string $gid
	 * @return \OCP\IGroup|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function createGroup($gid) {
		$group = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$group
			->method('getGID')
			->willReturn($gid);
		$group
			->method('getDisplayName')
			->willReturn($gid.'-name');
		$group
			->method('count')
			->willReturn(123);
		$group
			->method('countDisabled')
			->willReturn(11);
		$group
			->method('canAddUser')
			->willReturn(true);
		$group
			->method('canRemoveUser')
			->willReturn(true);

		return $group;
	}

	/**
	 * @param string $uid
	 * @return \OCP\IUser|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function createUser($uid) {
		$user = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$user
			->method('getUID')
			->willReturn($uid);
		$backendMock = $this->createMock(UserInterface::class);
		$user
			->method('getBackend')
			->willReturn($backendMock);
		return $user;
	}

	private function asUser() {
		$user = $this->createUser('user');
		$this->userSession
			->method('getUser')
			->willReturn($user);
	}

	private function asAdmin() {
		$user = $this->createUser('admin');
		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->groupManager
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
	}

	private function asSubAdminOfGroup($group) {
		$user = $this->createUser('subAdmin');
		$this->userSession
			->method('getUser')
			->willReturn($user);

		$this->subAdminManager
			->method('isSubAdminOfGroup')
			->willReturnCallback(function ($_user, $_group) use ($user, $group) {
				if ($_user === $user && $_group === $group) {
					return true;
				}
				return false;
			});
	}

	private function useAccountManager() {
		$this->accountManager->expects($this->any())
			->method('getUser')
			->willReturnCallback(function (IUser $user) {
				return [
					IAccountManager::PROPERTY_PHONE => ['value' => '0800-call-' . $user->getUID()],
					IAccountManager::PROPERTY_ADDRESS => ['value' => 'Holzweg 99, 0601 Herrera, Panama'],
					IAccountManager::PROPERTY_WEBSITE => ['value' => 'https://' . $user->getUid() . '.pa'],
					IAccountManager::PROPERTY_TWITTER => ['value' => '@' . $user->getUID()],
				];
			});
	}

	public function dataGetGroups() {
		return [
			[null, 0, 0],
			['foo', 0, 0],
			[null, 1, 0],
			[null, 0, 2],
			['foo', 1, 2],
		];
	}

	/**
	 * @dataProvider dataGetGroups
	 *
	 * @param string|null $search
	 * @param int|null $limit
	 * @param int|null $offset
	 */
	public function testGetGroups($search, $limit, $offset) {
		$groups = [$this->createGroup('group1'), $this->createGroup('group2')];

		$search = $search === null ? '' : $search;

		$this->groupManager
			->expects($this->once())
			->method('search')
			->with($search, $limit, $offset)
			->willReturn($groups);

		$result = $this->api->getGroups($search, $limit, $offset);
		$this->assertEquals(['groups' => ['group1', 'group2']], $result->getData());
	}

	/**
	 * @dataProvider dataGetGroups
	 *
	 * @param string|null $search
	 * @param int|null $limit
	 * @param int|null $offset
	 */
	public function testGetGroupsDetails($search, $limit, $offset) {
		$groups = [$this->createGroup('group1'), $this->createGroup('group2')];

		$search = $search === null ? '' : $search;

		$this->groupManager
			->expects($this->once())
			->method('search')
			->with($search, $limit, $offset)
			->willReturn($groups);

		$result = $this->api->getGroupsDetails($search, $limit, $offset);
		$this->assertEquals(['groups' => [
			[
				'id' => 'group1',
				'displayname' => 'group1-name',
				'usercount' => 123,
				'disabled' => 11,
				'canAdd' => true,
				'canRemove' => true
			],
			[
				'id' => 'group2',
				'displayname' => 'group2-name',
				'usercount' => 123,
				'disabled' => 11,
				'canAdd' => true,
				'canRemove' => true
			]
		]], $result->getData());
	}

	public function testGetGroupAsSubadmin() {
		$group = $this->createGroup('group');
		$this->asSubAdminOfGroup($group);

		$this->groupManager
			->method('get')
			->with('group')
			->willReturn($group);
		$this->groupManager
			->method('groupExists')
			->with('group')
			->willReturn(true);
		$group
			->method('getUsers')
			->willReturn([
				$this->createUser('user1'),
				$this->createUser('user2')
			]);

		$result = $this->api->getGroup('group');

		$this->assertEquals(['users' => ['user1', 'user2']], $result->getData());
	}


	public function testGetGroupAsIrrelevantSubadmin() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(403);

		$group = $this->createGroup('group');
		$otherGroup = $this->createGroup('otherGroup');
		$this->asSubAdminOfGroup($otherGroup);

		$this->groupManager
			->method('get')
			->with('group')
			->willReturn($group);
		$this->groupManager
			->method('groupExists')
			->with('group')
			->willReturn(true);

		$this->api->getGroup('group');
	}

	public function testGetGroupAsAdmin() {
		$group = $this->createGroup('group');
		$this->asAdmin();

		$this->groupManager
			->method('get')
			->with('group')
			->willReturn($group);
		$this->groupManager
			->method('groupExists')
			->with('group')
			->willReturn(true);
		$group
			->method('getUsers')
			->willReturn([
				$this->createUser('user1'),
				$this->createUser('user2')
			]);

		$result = $this->api->getGroup('group');

		$this->assertEquals(['users' => ['user1', 'user2']], $result->getData());
	}


	public function testGetGroupNonExisting() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('The requested group could not be found');
		$this->expectExceptionCode(404);

		$this->asUser();

		$this->api->getGroup($this->getUniqueID());
	}


	public function testGetSubAdminsOfGroupsNotExists() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Group does not exist');
		$this->expectExceptionCode(101);

		$this->api->getSubAdminsOfGroup('NonExistingGroup');
	}

	public function testGetSubAdminsOfGroup() {
		$group = $this->createGroup('GroupWithSubAdmins');
		$this->groupManager
			->method('get')
			->with('GroupWithSubAdmins')
			->willReturn($group);

		$this->subAdminManager
			->expects($this->once())
			->method('getGroupsSubAdmins')
			->with($group)
			->willReturn([
				$this->createUser('SubAdmin1'),
				$this->createUser('SubAdmin2'),
			]);

		$result = $this->api->getSubAdminsOfGroup('GroupWithSubAdmins');
		$this->assertEquals(['SubAdmin1', 'SubAdmin2'], $result->getData());
	}

	public function testGetSubAdminsOfGroupEmptyList() {
		$group = $this->createGroup('GroupWithOutSubAdmins');
		$this->groupManager
			->method('get')
			->with('GroupWithOutSubAdmins')
			->willReturn($group);

		$this->subAdminManager
			->expects($this->once())
			->method('getGroupsSubAdmins')
			->with($group)
			->willReturn([
			]);

		$result = $this->api->getSubAdminsOfGroup('GroupWithOutSubAdmins');
		$this->assertEquals([], $result->getData());
	}


	public function testAddGroupEmptyGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Invalid group name');
		$this->expectExceptionCode(101);

		$this->api->addGroup('');
	}


	public function testAddGroupExistingGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(102);

		$this->groupManager
			->method('groupExists')
			->with('ExistingGroup')
			->willReturn(true);

		$this->api->addGroup('ExistingGroup');
	}

	public function testAddGroup() {
		$this->groupManager
			->method('groupExists')
			->with('NewGroup')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('createGroup')
			->with('NewGroup');

		$this->api->addGroup('NewGroup');
	}

	public function testAddGroupWithSpecialChar() {
		$this->groupManager
			->method('groupExists')
			->with('Iñtërnâtiônàlizætiøn')
			->willReturn(false);

		$this->groupManager
			->expects($this->once())
			->method('createGroup')
			->with('Iñtërnâtiônàlizætiøn');

		$this->api->addGroup('Iñtërnâtiônàlizætiøn');
	}


	public function testDeleteGroupNonExisting() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(101);

		$this->api->deleteGroup('NonExistingGroup');
	}


	public function testDeleteAdminGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(102);

		$this->groupManager
			->method('groupExists')
			->with('admin')
			->willReturn('true');

		$this->api->deleteGroup('admin');
	}

	public function testDeleteGroup() {
		$this->groupManager
			->method('groupExists')
			->with('ExistingGroup')
			->willReturn('true');

		$group = $this->createGroup('ExistingGroup');
		$this->groupManager
			->method('get')
			->with('ExistingGroup')
			->willReturn($group);
		$group
			->expects($this->once())
			->method('delete')
			->willReturn(true);

		$this->api->deleteGroup('ExistingGroup');
	}

	public function testDeleteGroupEncoding() {
		$this->groupManager
			->method('groupExists')
			->with('ExistingGroup A/B')
			->willReturn('true');

		$group = $this->createGroup('ExistingGroup');
		$this->groupManager
			->method('get')
			->with('ExistingGroup A/B')
			->willReturn($group);
		$group
			->expects($this->once())
			->method('delete')
			->willReturn(true);

		$this->api->deleteGroup(urlencode('ExistingGroup A/B'));
	}

	public function testGetGroupUsersDetails() {
		$gid = 'ncg1';

		$this->asAdmin();
		$this->useAccountManager();

		$users = [
			'ncu1' => $this->createUser('ncu1'), # regular
			'ncu2' => $this->createUser('ncu2'), # the zombie
		];
		$users['ncu2']->expects($this->atLeastOnce())
			->method('getHome')
			->willThrowException(new NoUserException());

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function (string $uid) use ($users) {
				return isset($users[$uid]) ? $users[$uid] : null;
			});

		$group = $this->createGroup($gid);
		$group->expects($this->once())
			->method('searchUsers')
			->with('', null, 0)
			->willReturn(array_values($users));

		$this->groupManager
			->method('get')
			->with($gid)
			->willReturn($group);
		$this->groupManager->expects($this->any())
			->method('getUserGroups')
			->willReturn([$group]);

		/** @var \PHPUnit\Framework\MockObject\MockObject */
		$this->subAdminManager->expects($this->any())
			->method('isSubAdminOfGroup')
			->willReturn(false);
		$this->subAdminManager->expects($this->any())
			->method('getSubAdminsGroups')
			->willReturn([]);


		$this->api->getGroupUsersDetails($gid);
	}

	public function testGetGroupUsersDetailsEncoded() {
		$gid = 'Department A/B C/D';

		$this->asAdmin();
		$this->useAccountManager();

		$users = [
			'ncu1' => $this->createUser('ncu1'), # regular
			'ncu2' => $this->createUser('ncu2'), # the zombie
		];
		$users['ncu2']->expects($this->atLeastOnce())
			->method('getHome')
			->willThrowException(new NoUserException());

		$this->userManager->expects($this->any())
			->method('get')
			->willReturnCallback(function (string $uid) use ($users) {
				return isset($users[$uid]) ? $users[$uid] : null;
			});

		$group = $this->createGroup($gid);
		$group->expects($this->once())
			->method('searchUsers')
			->with('', null, 0)
			->willReturn(array_values($users));

		$this->groupManager
			->method('get')
			->with($gid)
			->willReturn($group);
		$this->groupManager->expects($this->any())
			->method('getUserGroups')
			->willReturn([$group]);

		/** @var \PHPUnit\Framework\MockObject\MockObject */
		$this->subAdminManager->expects($this->any())
			->method('isSubAdminOfGroup')
			->willReturn(false);
		$this->subAdminManager->expects($this->any())
			->method('getSubAdminsGroups')
			->willReturn([]);


		$this->api->getGroupUsersDetails(urlencode($gid));
	}
}
