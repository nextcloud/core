<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Maxence Lange <maxence@nextcloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Files_Sharing\Tests;

use OCA\Files_Sharing\MountProvider;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorageFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * @group DB
 */
class MountProviderTest extends \Test\TestCase {

	/** @var MountProvider */
	private $provider;

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var IUser|\PHPUnit_Framework_MockObject_MockObject */
	private $user;

	/** @var IStorageFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $loader;

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	/** @var ILogger | \PHPUnit_Framework_MockObject_MockObject */
	private $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->user = $this->getMockBuilder(IUser::class)->getMock();
		$this->loader = $this->getMockBuilder('OCP\Files\Storage\IStorageFactory')->getMock();
		$this->shareManager = $this->getMockBuilder(IManager::class)->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();

		$this->provider = new MountProvider($this->config, $this->shareManager, $this->logger);
	}

	private function makeMockShare($id, $nodeId, $owner = 'user2', $target = null, $permissions = 31) {
		$share = $this->createMock(IShare::class);
		$share->expects($this->any())
			->method('getPermissions')
			->willReturn($permissions);
		$share->expects($this->any())
			->method('getShareOwner')
			->willReturn($owner);
		$share->expects($this->any())
			->method('getTarget')
			->willReturn($target);
		$share->expects($this->any())
			->method('getId')
			->willReturn($id);
		$share->expects($this->any())
			->method('getNodeId')
			->willReturn($nodeId);
		$share->expects($this->any())
			->method('getShareTime')
			->willReturn(
				// compute share time based on id, simulating share order
				new \DateTime('@' . (1469193980 + 1000 * $id))
			);
		return $share;
	}

	/**
	 * Tests excluding shares from the current view. This includes:
	 * - shares that were opted out of (permissions === 0)
	 * - shares with a group in which the owner is already in
	 */
	public function testExcludeShares() {
		$rootFolder = $this->createMock(IRootFolder::class);
		$userManager = $this->createMock(IUserManager::class);
		$userShares = [
			$this->makeMockShare(1, 100, 'user2', '/share2', 0),
			$this->makeMockShare(2, 100, 'user2', '/share2', 31),
		];
		$groupShares = [
			$this->makeMockShare(3, 100, 'user2', '/share2', 0),
			$this->makeMockShare(4, 101, 'user2', '/share4', 31),
			$this->makeMockShare(5, 100, 'user1', '/share4', 31),
		];
		$roomShares = [
			$this->makeMockShare(6, 102, 'user2', '/share6', 0),
			$this->makeMockShare(7, 102, 'user1', '/share6', 31),
			$this->makeMockShare(8, 102, 'user2', '/share6', 31),
			$this->makeMockShare(9, 102, 'user2', '/share6', 31),
		];
		// tests regarding circles are made in the app itself.
		$circleShares = [];
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('user1');
		$this->shareManager->expects($this->at(0))
			->method('getSharedWith')
			->with('user1', \OCP\Share::SHARE_TYPE_USER)
			->willReturn($userShares);
		$this->shareManager->expects($this->at(1))
			->method('getSharedWith')
			->with('user1', \OCP\Share::SHARE_TYPE_GROUP, null, -1)
			->willReturn($groupShares);
		$this->shareManager->expects($this->at(2))
			->method('getSharedWith')
			->with('user1', \OCP\Share::SHARE_TYPE_CIRCLE, null, -1)
			->willReturn($circleShares);
		$this->shareManager->expects($this->at(3))
			->method('getSharedWith')
			->with('user1', \OCP\Share::SHARE_TYPE_ROOM, null, -1)
			->willReturn($roomShares);
		$this->shareManager->expects($this->any())
			->method('newShare')
			->willReturnCallback(function () use ($rootFolder, $userManager) {
				return new \OC\Share20\Share($rootFolder, $userManager);
			});
		$mounts = $this->provider->getMountsForUser($this->user, $this->loader);
		$this->assertCount(3, $mounts);
		$this->assertInstanceOf('OCA\Files_Sharing\SharedMount', $mounts[0]);
		$this->assertInstanceOf('OCA\Files_Sharing\SharedMount', $mounts[1]);
		$this->assertInstanceOf('OCA\Files_Sharing\SharedMount', $mounts[2]);
		$mountedShare1 = $mounts[0]->getShare();
		$this->assertEquals('2', $mountedShare1->getId());
		$this->assertEquals('user2', $mountedShare1->getShareOwner());
		$this->assertEquals(100, $mountedShare1->getNodeId());
		$this->assertEquals('/share2', $mountedShare1->getTarget());
		$this->assertEquals(31, $mountedShare1->getPermissions());
		$mountedShare2 = $mounts[1]->getShare();
		$this->assertEquals('4', $mountedShare2->getId());
		$this->assertEquals('user2', $mountedShare2->getShareOwner());
		$this->assertEquals(101, $mountedShare2->getNodeId());
		$this->assertEquals('/share4', $mountedShare2->getTarget());
		$this->assertEquals(31, $mountedShare2->getPermissions());
		$mountedShare3 = $mounts[2]->getShare();
		$this->assertEquals('8', $mountedShare3->getId());
		$this->assertEquals('user2', $mountedShare3->getShareOwner());
		$this->assertEquals(102, $mountedShare3->getNodeId());
		$this->assertEquals('/share6', $mountedShare3->getTarget());
		$this->assertEquals(31, $mountedShare3->getPermissions());
	}

	public function mergeSharesDataProvider() {
		// note: the user in the specs here is the shareOwner not recipient
		// the recipient is always "user1"
		return [
			// #0: share as outsider with "group1" and "user1" with same permissions
			[
				[
					[1, 100, 'user2', '/share2', 31],
				],
				[
					[2, 100, 'user2', '/share2', 31],
				],
				[
					// combined, user share has higher priority
					['1', 100, 'user2', '/share2', 31],
				],
			],
			// #1: share as outsider with "group1" and "user1" with different permissions
			[
				[
					[1, 100, 'user2', '/share', 31],
				],
				[
					[2, 100, 'user2', '/share', 15],
				],
				[
					// use highest permissions
					['1', 100, 'user2', '/share', 31],
				],
			],
			// #2: share as outsider with "group1" and "group2" with same permissions
			[
				[
				],
				[
					[1, 100, 'user2', '/share', 31],
					[2, 100, 'user2', '/share', 31],
				],
				[
					// combined, first group share has higher priority
					['1', 100, 'user2', '/share', 31],
				],
			],
			// #3: share as outsider with "group1" and "group2" with different permissions
			[
				[
				],
				[
					[1, 100, 'user2', '/share', 31],
					[2, 100, 'user2', '/share', 15],
				],
				[
					// use higher permissions
					['1', 100, 'user2', '/share', 31],
				],
			],
			// #4: share as insider with "group1"
			[
				[
				],
				[
					[1, 100, 'user1', '/share', 31],
				],
				[
					// no received share since "user1" is the sharer/owner
				],
			],
			// #5: share as insider with "group1" and "group2" with different permissions
			[
				[
				],
				[
					[1, 100, 'user1', '/share', 31],
					[2, 100, 'user1', '/share', 15],
				],
				[
					// no received share since "user1" is the sharer/owner
				],
			],
			// #6: share as outside with "group1", recipient opted out
			[
				[
				],
				[
					[1, 100, 'user2', '/share', 0],
				],
				[
					// no received share since "user1" opted out
				],
			],
			// #7: share as outsider with "group1" and "user1" where recipient renamed in between
			[
				[
					[1, 100, 'user2', '/share2-renamed', 31],
				],
				[
					[2, 100, 'user2', '/share2', 31],
				],
				[
					// use target of least recent share
					['1', 100, 'user2', '/share2-renamed', 31],
				],
			],
			// #8: share as outsider with "group1" and "user1" where recipient renamed in between
			[
				[
					[2, 100, 'user2', '/share2', 31],
				],
				[
					[1, 100, 'user2', '/share2-renamed', 31],
				],
				[
					// use target of least recent share
					['1', 100, 'user2', '/share2-renamed', 31],
				],
			],
			// #9: share as outsider with "nullgroup" and "user1" where recipient renamed in between
			[
				[
					[2, 100, 'user2', '/share2', 31],
				],
				[
					[1, 100, 'nullgroup', '/share2-renamed', 31],
				],
				[
					// use target of least recent share
					['1', 100, 'nullgroup', '/share2-renamed', 31],
				],
				true
			],
		];
	}

	/**
	 * Tests merging shares.
	 *
	 * Happens when sharing the same entry to a user through multiple ways,
	 * like several groups and also direct shares at the same time.
	 *
	 * @dataProvider mergeSharesDataProvider
	 *
	 * @param array $userShares array of user share specs
	 * @param array $groupShares array of group share specs
	 * @param array $expectedShares array of expected supershare specs
	 */
	public function testMergeShares($userShares, $groupShares, $expectedShares, $moveFails = false) {
		$rootFolder = $this->createMock(IRootFolder::class);
		$userManager = $this->createMock(IUserManager::class);

		$userShares = array_map(function ($shareSpec) {
			return $this->makeMockShare($shareSpec[0], $shareSpec[1], $shareSpec[2], $shareSpec[3], $shareSpec[4]);
		}, $userShares);
		$groupShares = array_map(function ($shareSpec) {
			return $this->makeMockShare($shareSpec[0], $shareSpec[1], $shareSpec[2], $shareSpec[3], $shareSpec[4]);
		}, $groupShares);

		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('user1');

		// tests regarding circles are made in the app itself.
		$circleShares = [];
		$roomShares = [];
		$this->shareManager->expects($this->at(0))
			->method('getSharedWith')
			->with('user1', \OCP\Share::SHARE_TYPE_USER)
			->willReturn($userShares);
		$this->shareManager->expects($this->at(1))
			->method('getSharedWith')
			->with('user1', \OCP\Share::SHARE_TYPE_GROUP, null, -1)
			->willReturn($groupShares);
		$this->shareManager->expects($this->at(2))
			->method('getSharedWith')
			->with('user1', \OCP\Share::SHARE_TYPE_CIRCLE, null, -1)
			->willReturn($circleShares);
		$this->shareManager->expects($this->at(3))
			->method('getSharedWith')
			->with('user1', \OCP\Share::SHARE_TYPE_ROOM, null, -1)
			->willReturn($roomShares);
		$this->shareManager->expects($this->any())
			->method('newShare')
			->willReturnCallback(function () use ($rootFolder, $userManager) {
				return new \OC\Share20\Share($rootFolder, $userManager);
			});

		if ($moveFails) {
			$this->shareManager->expects($this->any())
				->method('moveShare')
				->will($this->throwException(new \InvalidArgumentException()));
		}

		$mounts = $this->provider->getMountsForUser($this->user, $this->loader);

		$this->assertCount(count($expectedShares), $mounts);

		foreach ($mounts as $index => $mount) {
			$expectedShare = $expectedShares[$index];
			$this->assertInstanceOf('OCA\Files_Sharing\SharedMount', $mount);

			// supershare
			$share = $mount->getShare();

			$this->assertEquals($expectedShare[0], $share->getId());
			$this->assertEquals($expectedShare[1], $share->getNodeId());
			$this->assertEquals($expectedShare[2], $share->getShareOwner());
			$this->assertEquals($expectedShare[3], $share->getTarget());
			$this->assertEquals($expectedShare[4], $share->getPermissions());
		}
	}
}
