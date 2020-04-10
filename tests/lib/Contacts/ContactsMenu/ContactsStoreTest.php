<?php
/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 * @copyright 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author 2017 Lukas Reschke <lukas@statuscode.ch>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Tests\Contacts\ContactsMenu;

use OC\Contacts\ContactsMenu\ContactsStore;
use OCP\Contacts\IManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit_Framework_MockObject_MockObject;
use Test\TestCase;

class ContactsStoreTest extends TestCase {
	/** @var ContactsStore */
	private $contactsStore;
	/** @var IManager|PHPUnit_Framework_MockObject_MockObject */
	private $contactsManager;
	/** @var IUserManager|PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var IGroupManager|PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;
	/** @var IConfig|PHPUnit_Framework_MockObject_MockObject */
	private $config;

	protected function setUp(): void {
		parent::setUp();

		$this->contactsManager = $this->createMock(IManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->contactsStore = new ContactsStore($this->contactsManager, $this->config, $this->userManager, $this->groupManager);
	}

	public function testGetContactsWithoutFilter() {
		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo(''), $this->equalTo(['FN', 'EMAIL']))
			->willReturn([
				[
					'UID' => 123,
				],
				[
					'UID' => 567,
					'FN' => 'Darren Roner',
					'EMAIL' => [
						'darren@roner.au'
					],
				],
			]);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('user123');

		$entries = $this->contactsStore->getContacts($user, '');

		$this->assertCount(2, $entries);
		$this->assertEquals([
			'darren@roner.au'
		], $entries[1]->getEMailAddresses());
	}

	public function testGetContactsHidesOwnEntry() {
		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo(''), $this->equalTo(['FN', 'EMAIL']))
			->willReturn([
				[
					'UID' => 'user123',
				],
				[
					'UID' => 567,
					'FN' => 'Darren Roner',
					'EMAIL' => [
						'darren@roner.au'
					],
				],
			]);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('user123');

		$entries = $this->contactsStore->getContacts($user, '');

		$this->assertCount(1, $entries);
	}

	public function testGetContactsWithoutBinaryImage() {
		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo(''), $this->equalTo(['FN', 'EMAIL']))
			->willReturn([
				[
					'UID' => 123,
				],
				[
					'UID' => 567,
					'FN' => 'Darren Roner',
					'EMAIL' => [
						'darren@roner.au'
					],
					'PHOTO' => base64_encode('photophotophoto'),
				],
			]);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('user123');

		$entries = $this->contactsStore->getContacts($user, '');

		$this->assertCount(2, $entries);
		$this->assertNull($entries[1]->getAvatar());
	}

	public function testGetContactsWithoutAvatarURI() {
		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo(''), $this->equalTo(['FN', 'EMAIL']))
			->willReturn([
				[
					'UID' => 123,
				],
				[
					'UID' => 567,
					'FN' => 'Darren Roner',
					'EMAIL' => [
						'darren@roner.au'
					],
					'PHOTO' => 'VALUE=uri:https://photo',
				],
			]);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('user123');

		$entries = $this->contactsStore->getContacts($user, '');

		$this->assertCount(2, $entries);
		$this->assertEquals('https://photo', $entries[1]->getAvatar());
	}

	public function testGetContactsWhenUserIsInExcludeGroups() {
		$this->config->expects($this->at(0))->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_allow_share_dialog_user_enumeration'), $this->equalTo('yes'))
			->willReturn('yes');

		$this->config->expects($this->at(1))
			->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_restrict_user_enumeration_to_group'), $this->equalTo('no'))
			->willReturn('no');

		$this->config->expects($this->at(2))
			->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_exclude_groups'), $this->equalTo('no'))
			->willReturn('yes');

		$this->config->expects($this->at(3))
			->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_only_share_with_group_members'), $this->equalTo('no'))
			->willReturn('yes');

		$this->config->expects($this->at(4))
			->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_exclude_groups_list'), $this->equalTo(''))
			->willReturn('["group1", "group5", "group6"]');

		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $currentUser */
		$currentUser = $this->createMock(IUser::class);
		$currentUser->expects($this->once())
			->method('getUID')
			->willReturn('user001');

		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($this->equalTo($currentUser))
			->willReturn(['group1', 'group2', 'group3']);


		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo(''), $this->equalTo(['FN', 'EMAIL']))
			->willReturn([
				[
					'UID' => 'user123',
					'isLocalSystemBook' => true
				],
				[
					'UID' => 'user12345',
					'isLocalSystemBook' => true
				],
			]);


		$entries = $this->contactsStore->getContacts($currentUser, '');

		$this->assertCount(0, $entries);
	}

	public function testGetContactsOnlyShareIfInTheSameGroup() {
		$this->config->expects($this->at(0))->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_allow_share_dialog_user_enumeration'), $this->equalTo('yes'))
			->willReturn('yes');

		$this->config->expects($this->at(1)) ->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_restrict_user_enumeration_to_group'), $this->equalTo('no'))
			->willReturn('no');

		$this->config->expects($this->at(2)) ->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_exclude_groups'), $this->equalTo('no'))
			->willReturn('no');

		$this->config->expects($this->at(3))
			->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_only_share_with_group_members'), $this->equalTo('no'))
			->willReturn('yes');

		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $currentUser */
		$currentUser = $this->createMock(IUser::class);
		$currentUser->expects($this->once())
			->method('getUID')
			->willReturn('user001');

		$this->groupManager->expects($this->at(0))
			->method('getUserGroupIds')
			->with($this->equalTo($currentUser))
			->willReturn(['group1', 'group2', 'group3']);

		$user1 = $this->createMock(IUser::class);
		$this->userManager->expects($this->at(0))
			->method('get')
			->with('user1')
			->willReturn($user1);
		$this->groupManager->expects($this->at(1))
			->method('getUserGroupIds')
			->with($this->equalTo($user1))
			->willReturn(['group1']);
		$user2 = $this->createMock(IUser::class);
		$this->userManager->expects($this->at(1))
			->method('get')
			->with('user2')
			->willReturn($user2);
		$this->groupManager->expects($this->at(2))
			->method('getUserGroupIds')
			->with($this->equalTo($user2))
			->willReturn(['group2', 'group3']);
		$user3 = $this->createMock(IUser::class);
		$this->userManager->expects($this->at(2))
			->method('get')
			->with('user3')
			->willReturn($user3);
		$this->groupManager->expects($this->at(3))
			->method('getUserGroupIds')
			->with($this->equalTo($user3))
			->willReturn(['group8', 'group9']);

		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo(''), $this->equalTo(['FN', 'EMAIL']))
			->willReturn([
				[
					'UID' => 'user1',
					'isLocalSystemBook' => true
				],
				[
					'UID' => 'user2',
					'isLocalSystemBook' => true
				],
				[
					'UID' => 'user3',
					'isLocalSystemBook' => true
				],
				[
					'UID' => 'contact',
				],
			]);

		$entries = $this->contactsStore->getContacts($currentUser, '');

		$this->assertCount(3, $entries);
		$this->assertEquals('user1', $entries[0]->getProperty('UID'));
		$this->assertEquals('user2', $entries[1]->getProperty('UID'));
		$this->assertEquals('contact', $entries[2]->getProperty('UID'));
	}

	public function testGetContactsOnlyEnumerateIfInTheSameGroup() {
		$this->config->expects($this->at(0))->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_allow_share_dialog_user_enumeration'), $this->equalTo('yes'))
			->willReturn('yes');

		$this->config->expects($this->at(1)) ->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_restrict_user_enumeration_to_group'), $this->equalTo('no'))
			->willReturn('yes');

		$this->config->expects($this->at(2)) ->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_exclude_groups'), $this->equalTo('no'))
			->willReturn('no');

		$this->config->expects($this->at(3))
			->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_only_share_with_group_members'), $this->equalTo('no'))
			->willReturn('no');

		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $currentUser */
		$currentUser = $this->createMock(IUser::class);
		$currentUser->expects($this->once())
			->method('getUID')
			->willReturn('user001');

		$this->groupManager->expects($this->at(0))
			->method('getUserGroupIds')
			->with($this->equalTo($currentUser))
			->willReturn(['group1', 'group2', 'group3']);

		$user1 = $this->createMock(IUser::class);
		$this->userManager->expects($this->at(0))
			->method('get')
			->with('user1')
			->willReturn($user1);
		$this->groupManager->expects($this->at(1))
			->method('getUserGroupIds')
			->with($this->equalTo($user1))
			->willReturn(['group1']);
		$user2 = $this->createMock(IUser::class);
		$this->userManager->expects($this->at(1))
			->method('get')
			->with('user2')
			->willReturn($user2);
		$this->groupManager->expects($this->at(2))
			->method('getUserGroupIds')
			->with($this->equalTo($user2))
			->willReturn(['group2', 'group3']);
		$user3 = $this->createMock(IUser::class);
		$this->userManager->expects($this->at(2))
			->method('get')
			->with('user3')
			->willReturn($user3);
		$this->groupManager->expects($this->at(3))
			->method('getUserGroupIds')
			->with($this->equalTo($user3))
			->willReturn(['group8', 'group9']);

		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo(''), $this->equalTo(['FN', 'EMAIL']))
			->willReturn([
				[
					'UID' => 'user1',
					'isLocalSystemBook' => true
				],
				[
					'UID' => 'user2',
					'isLocalSystemBook' => true
				],
				[
					'UID' => 'user3',
					'isLocalSystemBook' => true
				],
				[
					'UID' => 'contact',
				],
			]);

		$entries = $this->contactsStore->getContacts($currentUser, '');

		$this->assertCount(3, $entries);
		$this->assertEquals('user1', $entries[0]->getProperty('UID'));
		$this->assertEquals('user2', $entries[1]->getProperty('UID'));
		$this->assertEquals('contact', $entries[2]->getProperty('UID'));
	}

	public function testGetContactsWithFilter() {
		$this->config->expects($this->at(0))->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_allow_share_dialog_user_enumeration'), $this->equalTo('yes'))
			->willReturn('no');

		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$this->contactsManager->expects($this->any())
			->method('search')
			->willReturn([
				[
					'UID' => 'a567',
					'FN' => 'Darren Roner',
					'EMAIL' => [
						'darren@roner.au',
					],
					'isLocalSystemBook' => true,
				],
				[
					'UID' => 'john',
					'FN' => 'John Doe',
					'EMAIL' => [
						'john@example.com',
					],
					'isLocalSystemBook' => true,
				],
				[
					'FN' => 'Anne D',
					'EMAIL' => [
						'anne@example.com',
					],
					'isLocalSystemBook' => false,
				],
			]);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('user123');

		// Complete match on UID should match
		$entry = $this->contactsStore->getContacts($user, 'a567');
		$this->assertSame(2, count($entry));
		$this->assertEquals([
			'darren@roner.au'
		], $entry[0]->getEMailAddresses());

		// Partial match on UID should not match
		$entry = $this->contactsStore->getContacts($user, 'a56');
		$this->assertSame(1, count($entry));
		$this->assertEquals([
			'anne@example.com'
		], $entry[0]->getEMailAddresses());

		// Complete match on email should match
		$entry = $this->contactsStore->getContacts($user, 'john@example.com');
		$this->assertSame(2, count($entry));
		$this->assertEquals([
			'john@example.com'
		], $entry[0]->getEMailAddresses());
		$this->assertEquals([
			'anne@example.com'
		], $entry[1]->getEMailAddresses());

		// Partial match on email should not match
		$entry = $this->contactsStore->getContacts($user, 'john@example.co');
		$this->assertSame(1, count($entry));
		$this->assertEquals([
			'anne@example.com'
		], $entry[0]->getEMailAddresses());

		// Match on FN should not match
		$entry = $this->contactsStore->getContacts($user, 'Darren Roner');
		$this->assertSame(1, count($entry));
		$this->assertEquals([
			'anne@example.com'
		], $entry[0]->getEMailAddresses());

		// Don't filter users in local addressbook
		$entry = $this->contactsStore->getContacts($user, 'Anne D');
		$this->assertSame(1, count($entry));
		$this->assertEquals([
			'anne@example.com'
		], $entry[0]->getEMailAddresses());
	}

	public function testFindOneUser() {
		$this->config->expects($this->at(0))->method('getAppValue')
			->with($this->equalTo('core'), $this->equalTo('shareapi_allow_share_dialog_user_enumeration'), $this->equalTo('yes'))
			->willReturn('yes');

		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo('a567'), $this->equalTo(['UID']))
			->willReturn([
				[
					'UID' => 123,
					'isLocalSystemBook' => false
				],
				[
					'UID' => 'a567',
					'FN' => 'Darren Roner',
					'EMAIL' => [
						'darren@roner.au'
					],
					'isLocalSystemBook' => true
				],
			]);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('user123');

		$entry = $this->contactsStore->findOne($user, 0, 'a567');

		$this->assertEquals([
			'darren@roner.au'
		], $entry->getEMailAddresses());
	}

	public function testFindOneEMail() {
		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo('darren@roner.au'), $this->equalTo(['EMAIL']))
			->willReturn([
				[
					'UID' => 123,
					'isLocalSystemBook' => false
				],
				[
					'UID' => 'a567',
					'FN' => 'Darren Roner',
					'EMAIL' => [
						'darren@roner.au'
					],
					'isLocalSystemBook' => false
				],
			]);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('user123');

		$entry = $this->contactsStore->findOne($user, 4, 'darren@roner.au');

		$this->assertEquals([
			'darren@roner.au'
		], $entry->getEMailAddresses());
	}

	public function testFindOneNotSupportedType() {
		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);

		$entry = $this->contactsStore->findOne($user, 42, 'darren@roner.au');

		$this->assertEquals(null, $entry);
	}

	public function testFindOneNoMatches() {
		/** @var IUser|PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$this->contactsManager->expects($this->once())
			->method('search')
			->with($this->equalTo('a567'), $this->equalTo(['UID']))
			->willReturn([
				[
					'UID' => 123,
					'isLocalSystemBook' => false
				],
				[
					'UID' => 'a567',
					'FN' => 'Darren Roner',
					'EMAIL' => [
						'darren@roner.au123'
					],
					'isLocalSystemBook' => false
				],
			]);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('user123');

		$entry = $this->contactsStore->findOne($user, 0, 'a567');

		$this->assertEquals(null, $entry);
	}
}
