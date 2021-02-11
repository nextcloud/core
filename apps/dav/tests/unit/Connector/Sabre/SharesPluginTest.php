<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Maxence Lange <maxence@nextcloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OCA\DAV\Connector\Sabre\Directory;
use OCA\DAV\Connector\Sabre\File;
use OCA\DAV\Connector\Sabre\Node;
use OCA\DAV\Upload\UploadFile;
use OCP\Files\Folder;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Sabre\DAV\Tree;

class SharesPluginTest extends \Test\TestCase {
	public const SHARETYPES_PROPERTYNAME = \OCA\DAV\Connector\Sabre\SharesPlugin::SHARETYPES_PROPERTYNAME;

	/**
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @var \Sabre\DAV\Tree
	 */
	private $tree;

	/**
	 * @var \OCP\Share\IManager
	 */
	private $shareManager;

	/**
	 * @var \OCP\Files\Folder
	 */
	private $userFolder;

	/**
	 * @var \OCA\DAV\Connector\Sabre\SharesPlugin
	 */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();
		$this->server = new \Sabre\DAV\Server();
		$this->tree = $this->createMock(Tree::class);
		$this->shareManager = $this->createMock(IManager::class);
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('user1');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->userFolder = $this->createMock(Folder::class);

		$this->plugin = new \OCA\DAV\Connector\Sabre\SharesPlugin(
			$this->tree,
			$userSession,
			$this->userFolder,
			$this->shareManager
		);
		$this->plugin->initialize($this->server);
	}

	/**
	 * @dataProvider sharesGetPropertiesDataProvider
	 */
	public function testGetProperties($shareTypes) {
		$sabreNode = $this->getMockBuilder(Node::class)
			->disableOriginalConstructor()
			->getMock();
		$sabreNode->expects($this->any())
			->method('getId')
			->willReturn(123);
		$sabreNode->expects($this->any())
			->method('getPath')
			->willReturn('/subdir');

		// node API nodes
		$node = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();

		$this->userFolder->expects($this->once())
			->method('get')
			->with('/subdir')
			->willReturn($node);

		$this->shareManager->expects($this->any())
			->method('getSharesBy')
			->with(
				$this->equalTo('user1'),
				$this->anything(),
				$this->anything(),
				$this->equalTo(false),
				$this->equalTo(-1)
			)
			->willReturnCallback(function ($userId, $requestedShareType, $node, $flag, $limit) use ($shareTypes) {
				if (in_array($requestedShareType, $shareTypes)) {
					$share = $this->createMock(IShare::class);
					$share->method('getShareType')
						->willReturn($requestedShareType);
					return [$share];
				}
				return [];
			});

		$propFind = new \Sabre\DAV\PropFind(
			'/dummyPath',
			[self::SHARETYPES_PROPERTYNAME],
			0
		);

		$this->plugin->handleGetProperties(
			$propFind,
			$sabreNode
		);

		$result = $propFind->getResultForMultiStatus();

		$this->assertEmpty($result[404]);
		unset($result[404]);
		$this->assertEquals($shareTypes, $result[200][self::SHARETYPES_PROPERTYNAME]->getShareTypes());
	}

	/**
	 * @dataProvider sharesGetPropertiesDataProvider
	 */
	public function testPreloadThenGetProperties($shareTypes) {
		$sabreNode1 = $this->createMock(File::class);
		$sabreNode1->method('getId')
			->willReturn(111);
		$sabreNode2 = $this->createMock(File::class);
		$sabreNode2->method('getId')
			->willReturn(222);
		$sabreNode2->method('getPath')
			->willReturn('/subdir/foo');

		$sabreNode = $this->createMock(Directory::class);
		$sabreNode->method('getId')
			->willReturn(123);
		// never, because we use getDirectoryListing from the Node API instead
		$sabreNode->expects($this->never())
			->method('getChildren');
		$sabreNode->expects($this->any())
			->method('getPath')
			->willReturn('/subdir');

		// node API nodes
		$node = $this->createMock(Folder::class);
		$node->method('getId')
			->willReturn(123);
		$node1 = $this->createMock(File::class);
		$node1->method('getId')
			->willReturn(111);
		$node2 = $this->createMock(File::class);
		$node2->method('getId')
			->willReturn(222);

		$this->userFolder->method('get')
			->with('/subdir')
			->willReturn($node);

		$dummyShares = array_map(function ($type) {
			$share = $this->getMockBuilder(IShare::class)->getMock();
			$share->expects($this->any())
				->method('getShareType')
				->willReturn($type);
			return $share;
		}, $shareTypes);

		$this->shareManager->expects($this->any())
			->method('getSharesBy')
			->with(
				$this->equalTo('user1'),
				$this->anything(),
				$this->anything(),
				$this->equalTo(false),
				$this->equalTo(-1)
			)
			->willReturnCallback(function ($userId, $requestedShareType, $node, $flag, $limit) use ($shareTypes, $dummyShares) {
				if ($node->getId() === 111 && in_array($requestedShareType, $shareTypes)) {
					foreach ($dummyShares as $dummyShare) {
						if ($dummyShare->getShareType() === $requestedShareType) {
							return [$dummyShare];
						}
					}
				}

				return [];
			});

		$this->shareManager->expects($this->any())
			->method('getSharesInFolder')
			->with(
				$this->equalTo('user1'),
				$this->anything(),
				$this->equalTo(true)
			)
			->willReturnCallback(function ($userId, $node, $flag) use ($shareTypes, $dummyShares) {
				return [111 => $dummyShares];
			});

		// simulate sabre recursive PROPFIND traversal
		$propFindRoot = new \Sabre\DAV\PropFind(
			'/subdir',
			[self::SHARETYPES_PROPERTYNAME],
			1
		);
		$propFind1 = new \Sabre\DAV\PropFind(
			'/subdir/test.txt',
			[self::SHARETYPES_PROPERTYNAME],
			0
		);
		$propFind2 = new \Sabre\DAV\PropFind(
			'/subdir/test2.txt',
			[self::SHARETYPES_PROPERTYNAME],
			0
		);

		$this->plugin->handleGetProperties(
			$propFindRoot,
			$sabreNode
		);
		$this->plugin->handleGetProperties(
			$propFind1,
			$sabreNode1
		);
		$this->plugin->handleGetProperties(
			$propFind2,
			$sabreNode2
		);

		$result = $propFind1->getResultForMultiStatus();

		$this->assertEmpty($result[404]);
		unset($result[404]);
		$this->assertEquals($shareTypes, $result[200][self::SHARETYPES_PROPERTYNAME]->getShareTypes());
	}

	public function sharesGetPropertiesDataProvider() {
		return [
			[[]],
			[[IShare::TYPE_USER]],
			[[IShare::TYPE_GROUP]],
			[[IShare::TYPE_LINK]],
			[[IShare::TYPE_REMOTE]],
			[[IShare::TYPE_ROOM]],
			[[IShare::TYPE_DECK]],
			[[IShare::TYPE_USER, IShare::TYPE_GROUP]],
			[[IShare::TYPE_USER, IShare::TYPE_GROUP, IShare::TYPE_LINK]],
			[[IShare::TYPE_USER, IShare::TYPE_LINK]],
			[[IShare::TYPE_GROUP, IShare::TYPE_LINK]],
			[[IShare::TYPE_USER, IShare::TYPE_REMOTE]],
		];
	}

	public function testGetPropertiesSkipChunks(): void {
		$sabreNode = $this->getMockBuilder(UploadFile::class)
			->disableOriginalConstructor()
			->getMock();

		$propFind = new \Sabre\DAV\PropFind(
			'/dummyPath',
			[self::SHARETYPES_PROPERTYNAME],
			0
		);

		$this->plugin->handleGetProperties(
			$propFind,
			$sabreNode
		);

		$result = $propFind->getResultForMultiStatus();
		$this->assertCount(1, $result[404]);
	}
}
