<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Node;

use OC\Files\FileInfo;
use OC\Files\Mount\Manager;
use OC\Files\View;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Class NodeTest
 *
 * @package Test\Files\Node
 */
abstract class NodeTest extends \Test\TestCase {
	/** @var \OC\User\User */
	protected $user;
	/** @var \OC\Files\Mount\Manager */
	protected $manager;
	/** @var \OC\Files\View|\PHPUnit_Framework_MockObject_MockObject */
	protected $view;
	/** @var \OC\Files\Node\Root|\PHPUnit_Framework_MockObject_MockObject */
	protected $root;
	/** @var \OCP\Files\Config\IUserMountCache|\PHPUnit_Framework_MockObject_MockObject */
	protected $userMountCache;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	protected $logger;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->manager = $this->getMockBuilder(Manager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userMountCache = $this->getMockBuilder('\OCP\Files\Config\IUserMountCache')
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->createMock(ILogger::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->root = $this->getMockBuilder('\OC\Files\Node\Root')
			->setConstructorArgs([$this->manager, $this->view, $this->user, $this->userMountCache, $this->logger, $this->userManager])
			->getMock();
	}

	/**
	 * @param IRootFolder $root
	 * @param View $view
	 * @param string $path
	 * @return Node
	 */
	protected abstract function createTestNode($root, $view, $path);

	/**
	 * @return string
	 */
	protected abstract function getNodeClass();

	/**
	 * @return string
	 */
	protected abstract function getNonExistingNodeClass();

	/**
	 * @return string
	 */
	protected abstract function getViewDeleteMethod();

	protected function getMockStorage() {
		$storage = $this->getMockBuilder(Storage::class)
			->disableOriginalConstructor()
			->getMock();
		$storage->expects($this->any())
			->method('getId')
			->willReturn('home::someuser');
		return $storage;
	}

	protected function getFileInfo($data) {
		return new FileInfo('', $this->getMockStorage(), '', $data, null);
	}

	public function testDelete() {
		$this->root->expects($this->exactly(2))
			->method('emit')
			->willReturn(true);
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL]));

		$this->view->expects($this->once())
			->method($this->getViewDeleteMethod())
			->with('/bar/foo')
			->willReturn(true);

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$node->delete();
	}

	public function testDeleteHooks() {
		$test = $this;
		$hooksRun = 0;
		/**
		 * @param \OC\Files\Node\File $node
		 */
		$preListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertInstanceOf($this->getNodeClass(), $node);
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$test->assertEquals(1, $node->getId());
			$hooksRun++;
		};

		/**
		 * @param \OC\Files\Node\File $node
		 */
		$postListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertInstanceOf($this->getNonExistingNodeClass(), $node);
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$test->assertEquals(1, $node->getId());
			$test->assertEquals('text/plain', $node->getMimeType());
			$hooksRun++;
		};

		$root = new \OC\Files\Node\Root(
			$this->manager,
			$this->view,
			$this->user,
			$this->userMountCache,
			$this->logger,
			$this->userManager
		);

		$root->listen('\OC\Files', 'preDelete', $preListener);
		$root->listen('\OC\Files', 'postDelete', $postListener);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL, 'fileid' => 1, 'mimetype' => 'text/plain']));

		$this->view->expects($this->once())
			->method($this->getViewDeleteMethod())
			->with('/bar/foo')
			->willReturn(true);

		$this->view->expects($this->any())
			->method('resolvePath')
			->with('/bar/foo')
			->willReturn([null, 'foo']);

		$node = $this->createTestNode($root, $this->view, '/bar/foo');
		$node->delete();
		$this->assertEquals(2, $hooksRun);
	}

	
	public function testDeleteNotPermitted() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_READ]));

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$node->delete();
	}


	public function testStat() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$stat = [
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50,
			'permissions' => 0
		];

		$this->view->expects($this->once())
			->method('stat')
			->with('/bar/foo')
			->willReturn($stat);

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals($stat, $node->stat());
	}

	public function testGetId() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$stat = $this->getFileInfo([
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50
		]);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($stat);

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals(1, $node->getId());
	}

	public function testGetSize() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);


		$stat = $this->getFileInfo([
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50
		]);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($stat);

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals(100, $node->getSize());
	}

	public function testGetEtag() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$stat = $this->getFileInfo([
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50
		]);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($stat);

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals('qwerty', $node->getEtag());
	}

	public function testGetMTime() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$stat = $this->getFileInfo([
			'fileid' => 1,
			'size' => 100,
			'etag' => 'qwerty',
			'mtime' => 50
		]);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($stat);

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals(50, $node->getMTime());
	}

	public function testGetStorage() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMockBuilder('\OC\Files\Storage\Storage')
			->disableOriginalConstructor()
			->getMock();

		$this->view->expects($this->once())
			->method('resolvePath')
			->with('/bar/foo')
			->willReturn([$storage, 'foo']);


		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals($storage, $node->getStorage());
	}

	public function testGetPath() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals('/bar/foo', $node->getPath());
	}

	public function testGetInternalPath() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);
		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMockBuilder('\OC\Files\Storage\Storage')
			->disableOriginalConstructor()
			->getMock();

		$this->view->expects($this->once())
			->method('resolvePath')
			->with('/bar/foo')
			->willReturn([$storage, 'foo']);


		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals('foo', $node->getInternalPath());
	}

	public function testGetName() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$this->assertEquals('foo', $node->getName());
	}

	public function testTouchSetMTime() {
		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$this->view->expects($this->once())
			->method('touch')
			->with('/bar/foo', 100)
			->willReturn(true);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL]));

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$node->touch(100);
		$this->assertEquals(100, $node->getMTime());
	}

	public function testTouchHooks() {
		$test = $this;
		$hooksRun = 0;
		/**
		 * @param \OC\Files\Node\File $node
		 */
		$preListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$hooksRun++;
		};

		/**
		 * @param \OC\Files\Node\File $node
		 */
		$postListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$hooksRun++;
		};

		$root = new \OC\Files\Node\Root(
			$this->manager,
			$this->view,
			$this->user,
			$this->userMountCache,
			$this->logger,
			$this->userManager
		);
		$root->listen('\OC\Files', 'preTouch', $preListener);
		$root->listen('\OC\Files', 'postTouch', $postListener);

		$this->view->expects($this->once())
			->method('touch')
			->with('/bar/foo', 100)
			->willReturn(true);

		$this->view->expects($this->any())
			->method('resolvePath')
			->with('/bar/foo')
			->willReturn([null, 'foo']);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL]));

		$node = $this->createTestNode($root, $this->view, '/bar/foo');
		$node->touch(100);
		$this->assertEquals(2, $hooksRun);
	}

	
	public function testTouchNotPermitted() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$this->root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_READ]));

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$node->touch(100);
	}

	
	public function testInvalidPath() {
		$this->expectException(\OCP\Files\InvalidPathException::class);

		$node = $this->createTestNode($this->root, $this->view, '/../foo');
		$node->getFileInfo();
	}

	public function testCopySameStorage() {
		$this->view->expects($this->any())
			->method('copy')
			->with('/bar/foo', '/bar/asd')
			->willReturn(true);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL, 'fileid' => 3]));

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\Folder($this->root, $this->view, '/bar');
		$newNode = $this->createTestNode($this->root, $this->view, '/bar/asd');

		$this->root->expects($this->exactly(2))
			->method('get')
			->willReturnMap([
				['/bar/asd', $newNode],
				['/bar', $parentNode]
			]);

		$target = $node->copy('/bar/asd');
		$this->assertInstanceOf($this->getNodeClass(), $target);
		$this->assertEquals(3, $target->getId());
	}

	
	public function testCopyNotPermitted() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->createMock('\OC\Files\Storage\Storage');

		$this->root->expects($this->never())
			->method('getMount');

		$storage->expects($this->never())
			->method('copy');

		$this->view->expects($this->any())
			->method('getFileInfo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_READ, 'fileid' => 3]));

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\Folder($this->root, $this->view, '/bar');

		$this->root->expects($this->once())
			->method('get')
			->willReturnMap([
				['/bar', $parentNode]
			]);

		$node->copy('/bar/asd');
	}

	
	public function testCopyNoParent() {
		$this->expectException(\OCP\Files\NotFoundException::class);

		$this->view->expects($this->never())
			->method('copy');

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');

		$this->root->expects($this->once())
			->method('get')
			->with('/bar/asd')
			->will($this->throwException(new NotFoundException()));

		$node->copy('/bar/asd/foo');
	}

	
	public function testCopyParentIsFile() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$this->view->expects($this->never())
			->method('copy');

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\File($this->root, $this->view, '/bar');

		$this->root->expects($this->once())
			->method('get')
			->willReturnMap([
				['/bar', $parentNode]
			]);

		$node->copy('/bar/asd');
	}

	public function testMoveSameStorage() {
		$this->view->expects($this->any())
			->method('rename')
			->with('/bar/foo', '/bar/asd')
			->willReturn(true);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL, 'fileid' => 1]));

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\Folder($this->root, $this->view, '/bar');

		$this->root->expects($this->any())
			->method('get')
			->willReturnMap([['/bar', $parentNode], ['/bar/asd', $node]]);

		$target = $node->move('/bar/asd');
		$this->assertInstanceOf($this->getNodeClass(), $target);
		$this->assertEquals(1, $target->getId());
		$this->assertEquals('/bar/asd', $node->getPath());
	}

	public function moveOrCopyProvider() {
		return [
			['move', 'rename', 'preRename', 'postRename'],
			['copy', 'copy', 'preCopy', 'postCopy'],
		];
	}

	/**
	 * @dataProvider moveOrCopyProvider
	 * @param string $operationMethod
	 * @param string $viewMethod
	 * @param string $preHookName
	 * @param string $postHookName
	 */
	public function testMoveCopyHooks($operationMethod, $viewMethod, $preHookName, $postHookName) {
		/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject $root */
		$root = $this->getMockBuilder('\OC\Files\Node\Root')
			->setConstructorArgs([$this->manager, $this->view, $this->user, $this->userMountCache, $this->logger, $this->userManager])
			->setMethods(['get'])
			->getMock();

		$this->view->expects($this->any())
			->method($viewMethod)
			->with('/bar/foo', '/bar/asd')
			->willReturn(true);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL, 'fileid' => 1]));

		/**
		 * @var \OC\Files\Node\File|\PHPUnit_Framework_MockObject_MockObject $node
		 */
		$node = $this->createTestNode($root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\Folder($root, $this->view, '/bar');
		$targetTestNode = $this->createTestNode($root, $this->view, '/bar/asd');

		$root->expects($this->any())
			->method('get')
			->willReturnMap([['/bar', $parentNode], ['/bar/asd', $targetTestNode]]);

		$hooksRun = 0;

		$preListener = function (Node $sourceNode, Node $targetNode) use (&$hooksRun, $node) {
			$this->assertSame($node, $sourceNode);
			$this->assertInstanceOf($this->getNodeClass(), $sourceNode);
			$this->assertInstanceOf($this->getNonExistingNodeClass(), $targetNode);
			$this->assertEquals('/bar/asd', $targetNode->getPath());
			$hooksRun++;
		};

		$postListener = function (Node $sourceNode, Node $targetNode) use (&$hooksRun, $node, $targetTestNode) {
			$this->assertSame($node, $sourceNode);
			$this->assertNotSame($node, $targetNode);
			$this->assertSame($targetTestNode, $targetNode);
			$this->assertInstanceOf($this->getNodeClass(), $sourceNode);
			$this->assertInstanceOf($this->getNodeClass(), $targetNode);
			$hooksRun++;
		};

		$preWriteListener = function (Node $targetNode) use (&$hooksRun) {
			$this->assertInstanceOf($this->getNonExistingNodeClass(), $targetNode);
			$this->assertEquals('/bar/asd', $targetNode->getPath());
			$hooksRun++;
		};

		$postWriteListener = function (Node $targetNode) use (&$hooksRun, $targetTestNode) {
			$this->assertSame($targetTestNode, $targetNode);
			$hooksRun++;
		};

		$root->listen('\OC\Files', $preHookName, $preListener);
		$root->listen('\OC\Files', 'preWrite', $preWriteListener);
		$root->listen('\OC\Files', $postHookName, $postListener);
		$root->listen('\OC\Files', 'postWrite', $postWriteListener);

		$node->$operationMethod('/bar/asd');

		$this->assertEquals(4, $hooksRun);
	}

	
	public function testMoveNotPermitted() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_READ]));

		$this->view->expects($this->never())
			->method('rename');

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\Folder($this->root, $this->view, '/bar');

		$this->root->expects($this->once())
			->method('get')
			->with('/bar')
			->willReturn($parentNode);

		$node->move('/bar/asd');
	}

	
	public function testMoveNoParent() {
		$this->expectException(\OCP\Files\NotFoundException::class);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->createMock('\OC\Files\Storage\Storage');

		$storage->expects($this->never())
			->method('rename');

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');

		$this->root->expects($this->once())
			->method('get')
			->with('/bar')
			->will($this->throwException(new NotFoundException()));

		$node->move('/bar/asd');
	}

	
	public function testMoveParentIsFile() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$this->view->expects($this->never())
			->method('rename');

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\File($this->root, $this->view, '/bar');

		$this->root->expects($this->once())
			->method('get')
			->with('/bar')
			->willReturn($parentNode);

		$node->move('/bar/asd');
	}

	
	public function testMoveFailed() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$this->view->expects($this->any())
			->method('rename')
			->with('/bar/foo', '/bar/asd')
			->willReturn(false);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL, 'fileid' => 1]));

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\Folder($this->root, $this->view, '/bar');

		$this->root->expects($this->any())
			->method('get')
			->willReturnMap([['/bar', $parentNode], ['/bar/asd', $node]]);

		$node->move('/bar/asd');
	}

	
	public function testCopyFailed() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$this->view->expects($this->any())
			->method('copy')
			->with('/bar/foo', '/bar/asd')
			->willReturn(false);

		$this->view->expects($this->any())
			->method('getFileInfo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL, 'fileid' => 1]));

		$node = $this->createTestNode($this->root, $this->view, '/bar/foo');
		$parentNode = new \OC\Files\Node\Folder($this->root, $this->view, '/bar');

		$this->root->expects($this->any())
			->method('get')
			->willReturnMap([['/bar', $parentNode], ['/bar/asd', $node]]);

		$node->copy('/bar/asd');
	}
}
