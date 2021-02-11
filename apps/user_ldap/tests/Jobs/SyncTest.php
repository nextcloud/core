<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\User_LDAP\Tests\Jobs;

use OCA\User_LDAP\Access;
use OCA\User_LDAP\AccessFactory;
use OCA\User_LDAP\Connection;
use OCA\User_LDAP\ConnectionFactory;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\Jobs\Sync;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\Manager;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Notification\IManager;
use Test\TestCase;

class SyncTest extends TestCase {

	/** @var  array */
	protected $arguments;
	/** @var  Helper|\PHPUnit\Framework\MockObject\MockObject */
	protected $helper;
	/** @var  LDAP|\PHPUnit\Framework\MockObject\MockObject */
	protected $ldapWrapper;
	/** @var  Manager|\PHPUnit\Framework\MockObject\MockObject */
	protected $userManager;
	/** @var  UserMapping|\PHPUnit\Framework\MockObject\MockObject */
	protected $mapper;
	/** @var  Sync */
	protected $sync;
	/** @var  IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var  IAvatarManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $avatarManager;
	/** @var  IDBConnection|\PHPUnit\Framework\MockObject\MockObject */
	protected $dbc;
	/** @var  IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $ncUserManager;
	/** @var  IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $notificationManager;
	/** @var ConnectionFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $connectionFactory;
	/** @var AccessFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $accessFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->helper = $this->createMock(Helper::class);
		$this->ldapWrapper = $this->createMock(LDAP::class);
		$this->userManager = $this->createMock(Manager::class);
		$this->mapper = $this->createMock(UserMapping::class);
		$this->config = $this->createMock(IConfig::class);
		$this->avatarManager = $this->createMock(IAvatarManager::class);
		$this->dbc = $this->createMock(IDBConnection::class);
		$this->ncUserManager = $this->createMock(IUserManager::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->connectionFactory = $this->createMock(ConnectionFactory::class);
		$this->accessFactory = $this->createMock(AccessFactory::class);

		$this->arguments = [
			'helper' => $this->helper,
			'ldapWrapper' => $this->ldapWrapper,
			'mapper' => $this->mapper,
			'config' => $this->config,
			'avatarManager' => $this->avatarManager,
			'dbc' => $this->dbc,
			'ncUserManager' => $this->ncUserManager,
			'notificationManager' => $this->notificationManager,
			'connectionFactory' => $this->connectionFactory,
			'accessFactory' => $this->accessFactory,
		];

		$this->sync = new Sync($this->userManager);
	}

	public function intervalDataProvider() {
		return [
			[
				0, 1000, 750
			],
			[
				22,	0, 50
			],
			[
				500, 500, 500
			],
			[
				1357, 0, 0
			],
			[
				421337, 2000, 3000
			]
		];
	}

	/**
	 * @dataProvider intervalDataProvider
	 */
	public function testUpdateInterval($userCount, $pagingSize1, $pagingSize2) {
		$this->config->expects($this->once())
			->method('setAppValue')
			->with('user_ldap', 'background_sync_interval', $this->anything())
			->willReturnCallback(function ($a, $k, $interval) {
				$this->assertTrue($interval >= SYNC::MIN_INTERVAL);
				$this->assertTrue($interval <= SYNC::MAX_INTERVAL);
				return true;
			});
		$this->config->expects($this->atLeastOnce())
			->method('getAppKeys')
			->willReturn([
				'blabla',
				'ldap_paging_size',
				's07blabla',
				'installed',
				's07ldap_paging_size'
			]);
		$this->config->expects($this->exactly(2))
			->method('getAppValue')
			->willReturnOnConsecutiveCalls($pagingSize1, $pagingSize2);

		$this->mapper->expects($this->atLeastOnce())
			->method('count')
			->willReturn($userCount);

		$this->sync->setArgument($this->arguments);
		$this->sync->updateInterval();
	}

	public function moreResultsProvider() {
		return [
			[ 3, 3, true ],
			[ 3, 5, true ],
			[ 3, 2, false],
			[ 0, 4, false],
			[ null, 4, false]
		];
	}

	/**
	 * @dataProvider moreResultsProvider
	 */
	public function testMoreResults($pagingSize, $results, $expected) {
		$connection = $this->createMock(Connection::class);
		$this->connectionFactory->expects($this->any())
			->method('get')
			->willReturn($connection);
		$connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($key) use ($pagingSize) {
				if ($key === 'ldapPagingSize') {
					return $pagingSize;
				}
				return null;
			});

		/** @var Access|\PHPUnit\Framework\MockObject\MockObject $access */
		$access = $this->createMock(Access::class);
		$this->accessFactory->expects($this->any())
			->method('get')
			->with($connection)
			->willReturn($access);

		$this->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['dn', 'uid', 'mail', 'displayname']);

		$access->expects($this->once())
			->method('fetchListOfUsers')
			->willReturn(array_pad([], $results, 'someUser'));
		$access->expects($this->any())
			->method('combineFilterWithAnd')
			->willReturn('pseudo=filter');
		$access->connection = $connection;
		$access->userManager = $this->userManager;

		$this->sync->setArgument($this->arguments);
		$hasMoreResults = $this->sync->runCycle(['prefix' => 's01', 'offset' => 100]);
		$this->assertSame($expected, $hasMoreResults);
	}

	public function cycleDataProvider() {
		$lastCycle = ['prefix' => 's01', 'offset' => 1000];
		$lastCycle2 = ['prefix' => '', 'offset' => 1000];
		return [
			[ null, ['s01'], ['prefix' => 's01', 'offset' => 0] ],
			[ null, [''], ['prefix' => '', 'offset' => 0] ],
			[ $lastCycle, ['s01', 's02'], ['prefix' => 's02', 'offset' => 0] ],
			[ $lastCycle, [''], ['prefix' => '', 'offset' => 0] ],
			[ $lastCycle2, ['', 's01'], ['prefix' => 's01', 'offset' => 0] ],
			[ $lastCycle, [], null ],
		];
	}

	/**
	 * @dataProvider cycleDataProvider
	 */
	public function testDetermineNextCycle($cycleData, $prefixes, $expectedCycle) {
		$this->helper->expects($this->any())
			->method('getServerConfigurationPrefixes')
			->with(true)
			->willReturn($prefixes);

		if (is_array($expectedCycle)) {
			$this->config->expects($this->exactly(2))
				->method('setAppValue')
				->withConsecutive(
					['user_ldap', 'background_sync_prefix', $expectedCycle['prefix']],
					['user_ldap', 'background_sync_offset', $expectedCycle['offset']]
				);
		} else {
			$this->config->expects($this->never())
				->method('setAppValue');
		}

		$this->sync->setArgument($this->arguments);
		$nextCycle = $this->sync->determineNextCycle($cycleData);

		if ($expectedCycle === null) {
			$this->assertNull($nextCycle);
		} else {
			$this->assertSame($expectedCycle['prefix'], $nextCycle['prefix']);
			$this->assertSame($expectedCycle['offset'], $nextCycle['offset']);
		}
	}

	public function testQualifiesToRun() {
		$cycleData = ['prefix' => 's01'];

		$this->config->expects($this->exactly(2))
			->method('getAppValue')
			->willReturnOnConsecutiveCalls(time() - 60 * 40, time() - 60 * 20);

		$this->sync->setArgument($this->arguments);
		$this->assertTrue($this->sync->qualifiesToRun($cycleData));
		$this->assertFalse($this->sync->qualifiesToRun($cycleData));
	}

	public function runDataProvider() {
		return [
			#0 - one LDAP server, reset
			[[
				'prefixes' => [''],
				'scheduledCycle' => ['prefix' => '', 'offset' => '4500'],
				'pagingSize' => 500,
				'usersThisCycle' => 0,
				'expectedNextCycle' => ['prefix' => '', 'offset' => '0'],
				'mappedUsers' => 123,
			]],
			#1 - 2 LDAP servers, next prefix
			[[
				'prefixes' => ['', 's01'],
				'scheduledCycle' => ['prefix' => '', 'offset' => '4500'],
				'pagingSize' => 500,
				'usersThisCycle' => 0,
				'expectedNextCycle' => ['prefix' => 's01', 'offset' => '0'],
				'mappedUsers' => 123,
			]],
			#2 - 2 LDAP servers, rotate prefix
			[[
				'prefixes' => ['', 's01'],
				'scheduledCycle' => ['prefix' => 's01', 'offset' => '4500'],
				'pagingSize' => 500,
				'usersThisCycle' => 0,
				'expectedNextCycle' => ['prefix' => '', 'offset' => '0'],
				'mappedUsers' => 123,
			]],
		];
	}

	/**
	 * @dataProvider runDataProvider
	 */
	public function testRun($runData) {
		$this->config->expects($this->any())
			->method('getAppValue')
			->willReturnCallback(function ($app, $key, $default) use ($runData) {
				if ($app === 'core' && $key === 'backgroundjobs_mode') {
					return 'cron';
				}
				if ($app = 'user_ldap') {
					// for getCycle()
					if ($key === 'background_sync_prefix') {
						return $runData['scheduledCycle']['prefix'];
					}
					if ($key === 'background_sync_offset') {
						return $runData['scheduledCycle']['offset'];
					}
					// for qualifiesToRun()
					if ($key === $runData['scheduledCycle']['prefix'] . '_lastChange') {
						return time() - 60 * 40;
					}
					// for getMinPagingSize
					if ($key === $runData['scheduledCycle']['prefix'] . 'ldap_paging_size') {
						return $runData['pagingSize'];
					}
				}

				return $default;
			});
		$this->config->expects($this->exactly(3))
			->method('setAppValue')
			->withConsecutive(
				['user_ldap', 'background_sync_prefix', $runData['expectedNextCycle']['prefix']],
				['user_ldap', 'background_sync_offset', $runData['expectedNextCycle']['offset']],
				['user_ldap', 'background_sync_interval', $this->anything()]
			);
		$this->config->expects($this->any())
			->method('getAppKeys')
			->with('user_ldap')
			->willReturn([$runData['scheduledCycle']['prefix'] . 'ldap_paging_size']);

		$this->helper->expects($this->any())
			->method('getServerConfigurationPrefixes')
			->with(true)
			->willReturn($runData['prefixes']);

		$connection = $this->createMock(Connection::class);
		$this->connectionFactory->expects($this->any())
			->method('get')
			->willReturn($connection);
		$connection->expects($this->any())
			->method('__get')
			->willReturnCallback(function ($key) use ($runData) {
				if ($key === 'ldapPagingSize') {
					return $runData['pagingSize'];
				}
				return null;
			});

		/** @var Access|\PHPUnit\Framework\MockObject\MockObject $access */
		$access = $this->createMock(Access::class);
		$this->accessFactory->expects($this->any())
			->method('get')
			->with($connection)
			->willReturn($access);

		$this->userManager->expects($this->any())
			->method('getAttributes')
			->willReturn(['dn', 'uid', 'mail', 'displayname']);

		$access->expects($this->once())
			->method('fetchListOfUsers')
			->willReturn(array_pad([], $runData['usersThisCycle'], 'someUser'));
		$access->expects($this->any())
			->method('combineFilterWithAnd')
			->willReturn('pseudo=filter');
		$access->connection = $connection;
		$access->userManager = $this->userManager;

		$this->mapper->expects($this->any())
			->method('count')
			->willReturn($runData['mappedUsers']);

		$this->sync->run($this->arguments);
	}
}
