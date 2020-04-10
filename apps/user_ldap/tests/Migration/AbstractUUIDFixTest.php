<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\User_LDAP\Tests\Migration;

use OCA\User_LDAP\Access;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\Mapping\GroupMapping;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Migration\UUIDFixUser;
use OCA\User_LDAP\User_Proxy;
use OCP\IConfig;
use Test\TestCase;

abstract class AbstractUUIDFixTest extends TestCase {
	/** @var  Helper|\PHPUnit_Framework_MockObject_MockObject */
	protected $helper;

	/** @var  IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var  LDAP|\PHPUnit_Framework_MockObject_MockObject */
	protected $ldap;

	/** @var  UserMapping|GroupMapping|\PHPUnit_Framework_MockObject_MockObject */
	protected $mapper;

	/** @var  UUIDFixUser */
	protected $job;

	/** @var  User_Proxy|\PHPUnit_Framework_MockObject_MockObject */
	protected $proxy;

	/** @var  Access|\PHPUnit_Framework_MockObject_MockObject */
	protected $access;

	/** @var bool */
	protected $isUser = true;

	protected function setUp(): void {
		parent::setUp();

		$this->ldap = $this->createMock(LDAP::class);
		$this->config = $this->createMock(IConfig::class);
		$this->access = $this->createMock(Access::class);

		$this->helper = $this->createMock(Helper::class);
		$this->helper->expects($this->any())
			->method('getServerConfigurationPrefixes')
			->with(true)
			->willReturn(['s01', 's03']);
	}

	protected function mockProxy($className) {
		$this->proxy = $this->createMock($className);
		$this->proxy->expects($this->any())
			->method('getLDAPAccess')
			->willReturn($this->access);
	}

	protected function instantiateJob($className) {
		$this->job = new $className($this->mapper, $this->ldap, $this->config, $this->helper);
		$this->job->overrideProxy($this->proxy);
	}

	public function testRunSingleRecord() {
		$args = [
			'records' => [
				0 => [
					'name' => 'Someone',
					'dn' => 'uid=Someone,dc=Somewhere',
					'uuid' => 'kaput'
				]
			]
		];
		$correctUUID = '4355-AED3-9D73-03AD';

		$this->access->expects($this->once())
			->method('getUUID')
			->with($args['records'][0]['dn'], $this->isUser)
			->willReturn($correctUUID);

		$this->mapper->expects($this->once())
			->method('setUUIDbyDN')
			->with($correctUUID, $args['records'][0]['dn']);

		$this->job->run($args);
	}

	public function testRunValidRecord() {
		$correctUUID = '4355-AED3-9D73-03AD';
		$args = [
			'records' => [
				0 => [
					'name' => 'Someone',
					'dn' => 'uid=Someone,dc=Somewhere',
					'uuid' => $correctUUID
				]
			]
		];

		$this->access->expects($this->once())
			->method('getUUID')
			->with($args['records'][0]['dn'], $this->isUser)
			->willReturn($correctUUID);

		$this->mapper->expects($this->never())
			->method('setUUIDbyDN');

		$this->job->run($args);
	}

	public function testRunRemovedRecord() {
		$args = [
			'records' => [
				0 => [
					'name' => 'Someone',
					'dn' => 'uid=Someone,dc=Somewhere',
					'uuid' => 'kaput'
				]
			]
		];

		$this->access->expects($this->once())
			->method('getUUID')
			->with($args['records'][0]['dn'], $this->isUser)
			->willReturn(false);

		$this->mapper->expects($this->never())
			->method('setUUIDbyDN');

		$this->job->run($args);
	}

	public function testRunManyRecords() {
		$args = [
			'records' => [
				0 => [
					'name' => 'Someone',
					'dn' => 'uid=Someone,dc=Somewhere',
					'uuid' => 'kaput'
				],
				1 => [
					'name' => 'kdslkdsaIdsal',
					'dn' => 'uid=kdslkdsaIdsal,dc=Somewhere',
					'uuid' => 'AED3-4355-03AD-9D73'
				],
				2 => [
					'name' => 'Paperboy',
					'dn' => 'uid=Paperboy,dc=Somewhere',
					'uuid' => 'kaput'
				]
			]
		];
		$correctUUIDs = ['4355-AED3-9D73-03AD', 'AED3-4355-03AD-9D73', 'AED3-9D73-4355-03AD'];

		$this->access->expects($this->exactly(3))
			->method('getUUID')
			->withConsecutive(
				[$args['records'][0]['dn'], $this->isUser],
				[$args['records'][1]['dn'], $this->isUser],
				[$args['records'][2]['dn'], $this->isUser]
			)
			->willReturnOnConsecutiveCalls($correctUUIDs[0], $correctUUIDs[1], $correctUUIDs[2]);

		$this->mapper->expects($this->exactly(2))
			->method('setUUIDbyDN')
			->withConsecutive(
				[$correctUUIDs[0], $args['records'][0]['dn']],
				[$correctUUIDs[2], $args['records'][2]['dn']]
			);

		$this->job->run($args);
	}

}
