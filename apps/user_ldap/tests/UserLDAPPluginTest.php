<?php
/**
 * @copyright Copyright (c) 2017 EITA Cooperative (eita.org.br)
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

namespace OCA\User_LDAP\Tests;

use OC\User\Backend;
use OCA\User_LDAP\UserPluginManager;

class UserLDAPPluginTest extends \Test\TestCase {

	/**
	 * @return UserPluginManager
	 */
	private function getUserPluginManager() {
		return new UserPluginManager();
	}

	public function testImplementsActions() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::CREATE_USER);

		$plugin2 = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions'])
			->getMock();

		$plugin2->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::PROVIDE_AVATAR);

		$pluginManager->register($plugin);
		$pluginManager->register($plugin2);

		$this->assertEquals($pluginManager->getImplementedActions(), Backend::CREATE_USER | Backend::PROVIDE_AVATAR);
		$this->assertTrue($pluginManager->implementsActions(Backend::CREATE_USER));
		$this->assertTrue($pluginManager->implementsActions(Backend::PROVIDE_AVATAR));
	}

	public function testCreateUser() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions', 'createUser'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::CREATE_USER);

		$plugin->expects($this->once())
			->method('createUser')
			->with(
				$this->equalTo('user'),
				$this->equalTo('password')
			);

		$pluginManager->register($plugin);
		$pluginManager->createUser('user', 'password');
	}

	
	public function testCreateUserNotRegistered() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('No plugin implements createUser in this LDAP Backend.');

		$pluginManager = $this->getUserPluginManager();
		$pluginManager->createUser('foo','bar');
	}

	public function testSetPassword() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions', 'setPassword'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::SET_PASSWORD);

		$plugin->expects($this->once())
			->method('setPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('password')
			);

		$pluginManager->register($plugin);
		$pluginManager->setPassword('user', 'password');
	}

	
	public function testSetPasswordNotRegistered() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('No plugin implements setPassword in this LDAP Backend.');

		$pluginManager = $this->getUserPluginManager();
		$pluginManager->setPassword('foo','bar');
	}

	public function testGetHome() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions', 'getHome'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::GET_HOME);

		$plugin->expects($this->once())
			->method('getHome')
			->with(
				$this->equalTo('uid')
			);

		$pluginManager->register($plugin);
		$pluginManager->getHome('uid');
	}

	
	public function testGetHomeNotRegistered() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('No plugin implements getHome in this LDAP Backend.');

		$pluginManager = $this->getUserPluginManager();
		$pluginManager->getHome('foo');
	}

	public function testGetDisplayName() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions', 'getDisplayName'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::GET_DISPLAYNAME);

		$plugin->expects($this->once())
			->method('getDisplayName')
			->with(
				$this->equalTo('uid')
			);

		$pluginManager->register($plugin);
		$pluginManager->getDisplayName('uid');
	}

	
	public function testGetDisplayNameNotRegistered() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('No plugin implements getDisplayName in this LDAP Backend.');

		$pluginManager = $this->getUserPluginManager();
		$pluginManager->getDisplayName('foo');
	}

	public function testSetDisplayName() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions', 'setDisplayName'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::SET_DISPLAYNAME);

		$plugin->expects($this->once())
			->method('setDisplayName')
			->with(
				$this->equalTo('user'),
				$this->equalTo('password')
			);

		$pluginManager->register($plugin);
		$pluginManager->setDisplayName('user', 'password');
	}

	
	public function testSetDisplayNameNotRegistered() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('No plugin implements setDisplayName in this LDAP Backend.');

		$pluginManager = $this->getUserPluginManager();
		$pluginManager->setDisplayName('foo', 'bar');
	}

	public function testCanChangeAvatar() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions', 'canChangeAvatar'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::PROVIDE_AVATAR);

		$plugin->expects($this->once())
			->method('canChangeAvatar')
			->with(
				$this->equalTo('uid')
			);

		$pluginManager->register($plugin);
		$pluginManager->canChangeAvatar('uid');
	}

	
	public function testCanChangeAvatarNotRegistered() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('No plugin implements canChangeAvatar in this LDAP Backend.');

		$pluginManager = $this->getUserPluginManager();
		$pluginManager->canChangeAvatar('foo');
	}

	public function testCountUsers() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions', 'countUsers'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(Backend::COUNT_USERS);

		$plugin->expects($this->once())
			->method('countUsers');

		$pluginManager->register($plugin);
		$pluginManager->countUsers();
	}

	
	public function testCountUsersNotRegistered() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('No plugin implements countUsers in this LDAP Backend.');

		$pluginManager = $this->getUserPluginManager();
		$pluginManager->countUsers();
	}

	public function testDeleteUser() {
		$pluginManager = $this->getUserPluginManager();

		$plugin = $this->getMockBuilder('OCA\User_LDAP\Tests\LDAPUserPluginDummy')
			->setMethods(['respondToActions', 'canDeleteUser','deleteUser'])
			->getMock();

		$plugin->expects($this->any())
			->method('respondToActions')
			->willReturn(0);

		$plugin->expects($this->any())
			->method('canDeleteUser')
			->willReturn(true);

		$plugin->expects($this->once())
			->method('deleteUser')
			->with(
				$this->equalTo('uid')
			);

		$this->assertFalse($pluginManager->canDeleteUser());
		$pluginManager->register($plugin);
		$this->assertTrue($pluginManager->canDeleteUser());
		$pluginManager->deleteUser('uid');
	}

	
	public function testDeleteUserNotRegistered() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('No plugin implements deleteUser in this LDAP Backend.');

		$pluginManager = $this->getUserPluginManager();
		$pluginManager->deleteUser('foo');
	}
}
