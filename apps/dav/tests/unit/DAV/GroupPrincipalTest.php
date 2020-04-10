<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2018, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\Tests\unit\DAV;

use OC\Group\Group;
use OCA\DAV\DAV\GroupPrincipalBackend;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IManager;
use Sabre\DAV\PropPatch;

class GroupPrincipalTest extends \Test\TestCase {

	/** @var IGroupManager | \PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;

	/** @var IUserSession | \PHPUnit_Framework_MockObject_MockObject */
	private $userSession;

	/** @var IManager | \PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	/** @var GroupPrincipalBackend */
	private $connector;

	protected function setUp(): void {
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->shareManager = $this->createMock(IManager::class);

		$this->connector = new GroupPrincipalBackend(
			$this->groupManager,
			$this->userSession,
			$this->shareManager);
		parent::setUp();
	}

	public function testGetPrincipalsByPrefixWithoutPrefix() {
		$response = $this->connector->getPrincipalsByPrefix('');
		$this->assertSame([], $response);
	}

	public function testGetPrincipalsByPrefixWithUsers() {
		$group1 = $this->mockGroup('foo');
		$group2 = $this->mockGroup('bar');
		$this->groupManager
			->expects($this->once())
			->method('search')
			->with('')
			->willReturn([$group1, $group2]);

		$expectedResponse = [
			0 => [
				'uri' => 'principals/groups/foo',
				'{DAV:}displayname' => 'Group foo',
				'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'GROUP',
			],
			1 => [
				'uri' => 'principals/groups/bar',
				'{DAV:}displayname' => 'Group bar',
				'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'GROUP',
			]
		];
		$response = $this->connector->getPrincipalsByPrefix('principals/groups');
		$this->assertSame($expectedResponse, $response);
	}

	public function testGetPrincipalsByPrefixEmpty() {
		$this->groupManager
			->expects($this->once())
			->method('search')
			->with('')
			->willReturn([]);

		$response = $this->connector->getPrincipalsByPrefix('principals/groups');
		$this->assertSame([], $response);
	}

	public function testGetPrincipalsByPathWithoutMail() {
		$group1 = $this->mockGroup('foo');
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($group1);

		$expectedResponse = [
			'uri' => 'principals/groups/foo',
			'{DAV:}displayname' => 'Group foo',
			'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'GROUP',
		];
		$response = $this->connector->getPrincipalByPath('principals/groups/foo');
		$this->assertSame($expectedResponse, $response);
	}

	public function testGetPrincipalsByPathWithMail() {
		$fooUser = $this->mockGroup('foo');
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn($fooUser);

		$expectedResponse = [
			'uri' => 'principals/groups/foo',
			'{DAV:}displayname' => 'Group foo',
			'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'GROUP',
		];
		$response = $this->connector->getPrincipalByPath('principals/groups/foo');
		$this->assertSame($expectedResponse, $response);
	}

	public function testGetPrincipalsByPathEmpty() {
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('foo')
			->willReturn(null);

		$response = $this->connector->getPrincipalByPath('principals/groups/foo');
		$this->assertSame(null, $response);
	}

	public function testGetPrincipalsByPathGroupWithSlash() {
		$group1 = $this->mockGroup('foo/bar');
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('foo/bar')
			->willReturn($group1);

		$expectedResponse = [
			'uri' => 'principals/groups/foo%2Fbar',
			'{DAV:}displayname' => 'Group foo/bar',
			'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => 'GROUP',
		];
		$response = $this->connector->getPrincipalByPath('principals/groups/foo/bar');
		$this->assertSame($expectedResponse, $response);
	}

	public function testGetGroupMemberSet() {
		$response = $this->connector->getGroupMemberSet('principals/groups/foo');
		$this->assertSame([], $response);
	}

	public function testGetGroupMembership() {
		$response = $this->connector->getGroupMembership('principals/groups/foo');
		$this->assertSame([], $response);
	}

	
	public function testSetGroupMembership() {
		$this->expectException(\Sabre\DAV\Exception::class);
		$this->expectExceptionMessage('Setting members of the group is not supported yet');

		$this->connector->setGroupMemberSet('principals/groups/foo', ['foo']);
	}

	public function testUpdatePrincipal() {
		$this->assertSame(0, $this->connector->updatePrincipal('foo', new PropPatch([])));
	}

	public function testSearchPrincipalsWithEmptySearchProperties() {
		$this->assertSame([], $this->connector->searchPrincipals('principals/groups', []));
	}

	public function testSearchPrincipalsWithWrongPrefixPath() {
		$this->assertSame([], $this->connector->searchPrincipals('principals/users',
			['{DAV:}displayname' => 'Foo']));
	}

	/**
	 * @dataProvider searchPrincipalsDataProvider
	 */
	public function testSearchPrincipals($sharingEnabled, $groupsOnly, $test, $result) {
		$this->shareManager->expects($this->once())
			->method('shareAPIEnabled')
			->willReturn($sharingEnabled);

		if ($sharingEnabled) {
			$this->shareManager->expects($this->once())
				->method('shareWithGroupMembersOnly')
				->willReturn($groupsOnly);

			if ($groupsOnly) {
				$user = $this->createMock(IUser::class);
				$this->userSession->expects($this->once())
					->method('getUser')
					->willReturn($user);

				$this->groupManager->expects($this->once())
					->method('getUserGroupIds')
					->with($user)
					->willReturn(['group1', 'group2', 'group5']);
			}
		} else {
			$this->shareManager->expects($this->never())
				->method('shareWithGroupMembersOnly');
			$this->groupManager->expects($this->never())
				->method($this->anything());
		}

		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')->willReturn('group1');
		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')->willReturn('group2');
		$group3 = $this->createMock(IGroup::class);
		$group3->method('getGID')->willReturn('group3');
		$group4 = $this->createMock(IGroup::class);
		$group4->method('getGID')->willReturn('group4');
		$group5 = $this->createMock(IGroup::class);
		$group5->method('getGID')->willReturn('group5');

		if ($sharingEnabled) {
			$this->groupManager->expects($this->once())
				->method('search')
				->with('Foo')
				->willReturn([$group1, $group2, $group3, $group4, $group5]);
		} else {
			$this->groupManager->expects($this->never())
				->method('search');
		}

		$this->assertSame($result, $this->connector->searchPrincipals('principals/groups',
			['{DAV:}displayname' => 'Foo'], $test));
	}

	public function searchPrincipalsDataProvider() {
		return [
			[true, false, 'allof', ['principals/groups/group1', 'principals/groups/group2', 'principals/groups/group3', 'principals/groups/group4', 'principals/groups/group5']],
			[true, false, 'anyof', ['principals/groups/group1', 'principals/groups/group2', 'principals/groups/group3', 'principals/groups/group4', 'principals/groups/group5']],
			[true, true, 'allof', ['principals/groups/group1', 'principals/groups/group2', 'principals/groups/group5']],
			[true, true, 'anyof', ['principals/groups/group1', 'principals/groups/group2', 'principals/groups/group5']],
			[false, false, 'allof', []],
			[false, false, 'anyof', []],
		];
	}

	/**
	 * @dataProvider findByUriDataProvider
	 */
	public function testFindByUri($sharingEnabled, $groupsOnly, $findUri, $result) {
		$this->shareManager->expects($this->once())
			->method('shareAPIEnabled')
			->willReturn($sharingEnabled);

		if ($sharingEnabled) {
			$this->shareManager->expects($this->once())
				->method('shareWithGroupMembersOnly')
				->willReturn($groupsOnly);

			if ($groupsOnly) {
				$user = $this->createMock(IUser::class);
				$this->userSession->expects($this->once())
					->method('getUser')
					->willReturn($user);

				$this->groupManager->expects($this->at(0))
					->method('getUserGroupIds')
					->with($user)
					->willReturn(['group1', 'group2', 'group5']);
			}
		} else {
			$this->shareManager->expects($this->never())
				->method('shareWithGroupMembersOnly');
			$this->groupManager->expects($this->never())
				->method($this->anything());
		}

		$this->assertEquals($result, $this->connector->findByUri($findUri, 'principals/groups'));
	}

	public function findByUriDataProvider() {
		return [
			[false, false, 'principal:principals/groups/group1', null],
			[false, false, 'principal:principals/groups/group3', null],
			[false, true, 'principal:principals/groups/group1', null],
			[false, true, 'principal:principals/groups/group3', null],
			[true, true, 'principal:principals/groups/group1', 'principals/groups/group1'],
			[true, true, 'principal:principals/groups/group3', null],
			[true, false, 'principal:principals/groups/group1', 'principals/groups/group1'],
			[true, false, 'principal:principals/groups/group3', 'principals/groups/group3'],
		];
	}

	/**
	 * @return Group|\PHPUnit_Framework_MockObject_MockObject
	 */
	private function mockGroup($gid) {
		$fooGroup = $this->createMock(Group::class);
		$fooGroup
			->expects($this->exactly(1))
			->method('getGID')
			->willReturn($gid);
		$fooGroup
			->expects($this->exactly(1))
			->method('getDisplayName')
			->willReturn('Group '.$gid);
		return $fooGroup;
	}
}
