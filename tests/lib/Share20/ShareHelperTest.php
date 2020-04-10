<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Share20;

use OC\Share20\ShareHelper;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Share\IManager;
use Test\TestCase;

class ShareHelperTest extends TestCase {

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $manager;

	/** @var ShareHelper */
	private $helper;

	protected function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(IManager::class);

		$this->helper = new ShareHelper($this->manager);
	}

	public function dataGetPathsForAccessList() {
		return [
			[[], [], false, [], [], false, [
				'users' => [],
				'remotes' => [],
			]],
			[['user1', 'user2'], ['user1' => 'foo', 'user2' => 'bar'], true, [], [], false, [
				'users' => ['user1' => 'foo', 'user2' => 'bar'],
				'remotes' => [],
			]],
			[[], [], false, ['remote1', 'remote2'], ['remote1' => 'qwe', 'remote2' => 'rtz'], true, [
				'users' => [],
				'remotes' => ['remote1' => 'qwe', 'remote2' => 'rtz'],
			]],
			[['user1', 'user2'], ['user1' => 'foo', 'user2' => 'bar'], true, ['remote1', 'remote2'], ['remote1' => 'qwe', 'remote2' => 'rtz'], true, [
				'users' => ['user1' => 'foo', 'user2' => 'bar'],
				'remotes' => ['remote1' => 'qwe', 'remote2' => 'rtz'],
			]],
		];
	}

	/**
	 * @dataProvider dataGetPathsForAccessList
	 */
	public function testGetPathsForAccessList(array $userList, array $userMap, $resolveUsers, array $remoteList, array $remoteMap, $resolveRemotes, array $expected) {
		$this->manager->expects($this->once())
			->method('getAccessList')
			->willReturn([
				'users' => $userList,
				'remote' => $remoteList,
			]);

		/** @var Node|\PHPUnit_Framework_MockObject_MockObject $node */
		$node = $this->createMock(Node::class);
		/** @var ShareHelper|\PHPUnit_Framework_MockObject_MockObject $helper */
		$helper = $this->getMockBuilder(ShareHelper::class)
			->setConstructorArgs([$this->manager])
			->setMethods(['getPathsForUsers', 'getPathsForRemotes'])
			->getMock();

		$helper->expects($resolveUsers ? $this->once() : $this->never())
			->method('getPathsForUsers')
			->with($node, $userList)
			->willReturn($userMap);

		$helper->expects($resolveRemotes ? $this->once() : $this->never())
			->method('getPathsForRemotes')
			->with($node, $remoteList)
			->willReturn($remoteMap);

		$this->assertSame($expected, $helper->getPathsForAccessList($node));
	}

	public function dataGetPathsForUsers() {
		return [
			[[], [23 => 'TwentyThree', 42 => 'FortyTwo'], []],
			[
				[
					'test1' => ['node_id' => 16, 'node_path' => '/foo'],
					'test2' => ['node_id' => 23, 'node_path' => '/bar'],
					'test3' => ['node_id' => 42, 'node_path' => '/cat'],
					'test4' => ['node_id' => 48, 'node_path' => '/dog'],
				],
				[16 => 'SixTeen', 23 => 'TwentyThree', 42 => 'FortyTwo'],
				[
					'test1' => '/foo/TwentyThree/FortyTwo',
					'test2' => '/bar/FortyTwo',
					'test3' => '/cat',
				],
			],
		];
	}

	/**
	 * @dataProvider dataGetPathsForUsers
	 *
	 * @param array $users
	 * @param array $nodes
	 * @param array $expected
	 */
	public function testGetPathsForUsers(array $users, array $nodes, array $expected) {
		$lastNode = null;
		foreach ($nodes as $nodeId => $nodeName) {
			/** @var Node|\PHPUnit_Framework_MockObject_MockObject $node */
			$node = $this->createMock(Node::class);
			$node->expects($this->any())
				->method('getId')
				->willReturn($nodeId);
			$node->expects($this->any())
				->method('getName')
				->willReturn($nodeName);
			if ($lastNode === null) {
				$node->expects($this->any())
					->method('getParent')
					->willThrowException(new NotFoundException());
			} else {
				$node->expects($this->any())
					->method('getParent')
					->willReturn($lastNode);
			}
			$lastNode = $node;
		}

		$this->assertEquals($expected, self::invokePrivate($this->helper, 'getPathsForUsers', [$lastNode, $users]));
	}

	public function dataGetPathsForRemotes() {
		return [
			[[], [23 => 'TwentyThree', 42 => 'FortyTwo'], []],
			[
				[
					'test1' => ['node_id' => 16, 'token' => 't1'],
					'test2' => ['node_id' => 23, 'token' => 't2'],
					'test3' => ['node_id' => 42, 'token' => 't3'],
					'test4' => ['node_id' => 48, 'token' => 't4'],
				],
				[
					16 => '/admin/files/SixTeen',
					23 => '/admin/files/SixTeen/TwentyThree',
					42 => '/admin/files/SixTeen/TwentyThree/FortyTwo',
				],
				[
					'test1' => ['token' => 't1', 'node_path' => '/SixTeen'],
					'test2' => ['token' => 't2', 'node_path' => '/SixTeen/TwentyThree'],
					'test3' => ['token' => 't3', 'node_path' => '/SixTeen/TwentyThree/FortyTwo'],
				],
			],
		];
	}

	/**
	 * @dataProvider dataGetPathsForRemotes
	 *
	 * @param array $remotes
	 * @param array $nodes
	 * @param array $expected
	 */
	public function testGetPathsForRemotes(array $remotes, array $nodes, array $expected) {
		$lastNode = null;
		foreach ($nodes as $nodeId => $nodePath) {
			/** @var Node|\PHPUnit_Framework_MockObject_MockObject $node */
			$node = $this->createMock(Node::class);
			$node->expects($this->any())
				->method('getId')
				->willReturn($nodeId);
			$node->expects($this->any())
				->method('getPath')
				->willReturn($nodePath);
			if ($lastNode === null) {
				$node->expects($this->any())
					->method('getParent')
					->willThrowException(new NotFoundException());
			} else {
				$node->expects($this->any())
					->method('getParent')
					->willReturn($lastNode);
			}
			$lastNode = $node;
		}

		$this->assertEquals($expected, self::invokePrivate($this->helper, 'getPathsForRemotes', [$lastNode, $remotes]));
	}

	public function dataGetMountedPath() {
		return [
			['/admin/files/foobar', '/foobar'],
			['/admin/files/foo/bar', '/foo/bar'],
		];
	}

	/**
	 * @dataProvider dataGetMountedPath
	 * @param string $path
	 * @param string $expected
	 */
	public function testGetMountedPath($path, $expected) {
		/** @var Node|\PHPUnit_Framework_MockObject_MockObject $node */
		$node = $this->createMock(Node::class);
		$node->expects($this->once())
			->method('getPath')
			->willReturn($path);

		$this->assertSame($expected, self::invokePrivate($this->helper, 'getMountedPath', [$node]));
	}
}
