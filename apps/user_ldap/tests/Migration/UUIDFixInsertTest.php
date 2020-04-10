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

use OCA\User_LDAP\Mapping\GroupMapping;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Migration\UUIDFixInsert;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\Migration\IOutput;
use Test\TestCase;

class UUIDFixInsertTest extends TestCase {
	/** @var  IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var  UserMapping|\PHPUnit_Framework_MockObject_MockObject */
	protected $userMapper;

	/** @var  GroupMapping|\PHPUnit_Framework_MockObject_MockObject */
	protected $groupMapper;

	/** @var  IJobList|\PHPUnit_Framework_MockObject_MockObject */
	protected $jobList;

	/** @var  UUIDFixInsert */
	protected $job;

	protected function setUp(): void {
		parent::setUp();

		$this->jobList = $this->createMock(IJobList::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userMapper = $this->createMock(UserMapping::class);
		$this->groupMapper = $this->createMock(GroupMapping::class);
		$this->job = new UUIDFixInsert(
			$this->config,
			$this->userMapper,
			$this->groupMapper,
			$this->jobList
		);
	}

	public function testGetName() {
		$this->assertSame('Insert UUIDFix background job for user and group in batches', $this->job->getName());
	}

	public function recordProvider() {
		$record = [
			'dn' => 'cn=somerecord,dc=somewhere',
			'name' => 'Something',
			'uuid' => 'AB12-3456-CDEF7-8GH9'
		];
		array_fill(0, 50, $record);

		$userBatches = [
			0 => array_fill(0, 50, $record),
			1 => array_fill(0, 50, $record),
			2 => array_fill(0,  13, $record),
		];

		$groupBatches = [
			0 => array_fill(0, 7, $record),
		];

		return [
			['userBatches' => $userBatches, 'groupBatches' => $groupBatches]
		];
	}

	public function recordProviderTooLongAndNone() {
		$record = [
			'dn' => 'cn=somerecord,dc=somewhere',
			'name' => 'Something',
			'uuid' => 'AB12-3456-CDEF7-8GH9'
		];
		array_fill(0, 50, $record);

		$userBatches = [
			0 => array_fill(0, 50, $record),
			1 => array_fill(0, 40, $record),
			2 => array_fill(0, 32, $record),
			3 => array_fill(0, 32, $record),
			4 => array_fill(0, 23, $record),
		];

		$groupBatches = [0 => []];

		return [
			['userBatches' => $userBatches, 'groupBatches' => $groupBatches]
		];
	}

	/**
	 * @dataProvider recordProvider
	 */
	public function testRun($userBatches, $groupBatches) {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('user_ldap', 'installed_version', '1.2.1')
			->willReturn('1.2.0');

		$this->userMapper->expects($this->exactly(3))
			->method('getList')
			->withConsecutive([0, 50], [50, 50], [100, 50])
			->willReturnOnConsecutiveCalls($userBatches[0], $userBatches[1], $userBatches[2]);

		$this->groupMapper->expects($this->exactly(1))
			->method('getList')
			->with(0, 50)
			->willReturn($groupBatches[0]);

		$this->jobList->expects($this->exactly(4))
			->method('add');

		/** @var IOutput $out */
		$out = $this->createMock(IOutput::class);
		$this->job->run($out);
	}

	/**
	 * @dataProvider recordProviderTooLongAndNone
	 */
	public function testRunWithManyAndNone($userBatches, $groupBatches) {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('user_ldap', 'installed_version', '1.2.1')
			->willReturn('1.2.0');

		$this->userMapper->expects($this->exactly(5))
			->method('getList')
			->withConsecutive([0, 50], [0, 40], [0, 32], [32, 32], [64, 32])
			->willReturnOnConsecutiveCalls($userBatches[0], $userBatches[1], $userBatches[2],  $userBatches[3],  $userBatches[4]);

		$this->groupMapper->expects($this->once())
			->method('getList')
			->with(0, 50)
			->willReturn($groupBatches[0]);

		$this->jobList->expects($this->at(0))
			->method('add')
			->willThrowException(new \InvalidArgumentException('Background job arguments can\'t exceed 4000 etc'));
		$this->jobList->expects($this->at(1))
			->method('add')
			->willThrowException(new \InvalidArgumentException('Background job arguments can\'t exceed 4000 etc'));
		$this->jobList->expects($this->at(2))
			->method('add');
		$this->jobList->expects($this->at(3))
			->method('add');
		$this->jobList->expects($this->at(4))
			->method('add');

		/** @var IOutput $out */
		$out = $this->createMock(IOutput::class);
		$this->job->run($out);
	}

	public function testDonNotRun() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('user_ldap', 'installed_version', '1.2.1')
			->willReturn('1.2.1');
		$this->userMapper->expects($this->never())
			->method('getList');
		$this->groupMapper->expects($this->never())
			->method('getList');
		$this->jobList->expects($this->never())
			->method('add');

		/** @var IOutput $out */
		$out = $this->createMock(IOutput::class);
		$this->job->run($out);
	}
}
