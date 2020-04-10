<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Node;

/**
 * Class FileTest
 *
 * @group DB
 *
 * @package Test\Files\Node
 */
class FileTest extends NodeTest {
	protected function createTestNode($root, $view, $path) {
		return new \OC\Files\Node\File($root, $view, $path);
	}

	protected function getNodeClass() {
		return '\OC\Files\Node\File';
	}

	protected function getNonExistingNodeClass() {
		return '\OC\Files\Node\NonExistingFile';
	}

	protected function getViewDeleteMethod() {
		return 'unlink';
	}

	public function testGetContent() {
		/** @var \OC\Files\Node\Root|\PHPUnit_Framework_MockObject_MockObject $root */
		$root = $this->getMockBuilder('\OC\Files\Node\Root')
			->setConstructorArgs([$this->manager, $this->view, $this->user, $this->userMountCache, $this->logger, $this->userManager])
			->getMock();

		$hook = function ($file) {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		$this->view->expects($this->once())
			->method('file_get_contents')
			->with('/bar/foo')
			->willReturn('bar');

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_READ]));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$this->assertEquals('bar', $node->getContent());
	}

	
	public function testGetContentNotPermitted() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		/** @var \OC\Files\Node\Root|\PHPUnit_Framework_MockObject_MockObject $root */
		$root = $this->getMockBuilder('\OC\Files\Node\Root')
			->setConstructorArgs([$this->manager, $this->view, $this->user, $this->userMountCache, $this->logger, $this->userManager])
			->getMock();

		$root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => 0]));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$node->getContent();
	}

	public function testPutContent() {
		/** @var \OC\Files\Node\Root|\PHPUnit_Framework_MockObject_MockObject $root */
		$root = $this->getMockBuilder('\OC\Files\Node\Root')
			->setConstructorArgs([$this->manager, $this->view, $this->user, $this->userMountCache, $this->logger, $this->userManager])
			->getMock();

		$root->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL]));

		$this->view->expects($this->once())
			->method('file_put_contents')
			->with('/bar/foo', 'bar')
			->willReturn(true);

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$node->putContent('bar');
	}

	
	public function testPutContentNotPermitted() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		/** @var \OC\Files\Node\Root|\PHPUnit_Framework_MockObject_MockObject $root */
		$root = $this->getMockBuilder('\OC\Files\Node\Root')
			->setConstructorArgs([$this->manager, $this->view, $this->user, $this->userMountCache, $this->logger, $this->userManager])
			->getMock();

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_READ]));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$node->putContent('bar');
	}

	public function testGetMimeType() {
		/** @var \OC\Files\Node\Root|\PHPUnit_Framework_MockObject_MockObject $root */
		$root = $this->getMockBuilder('\OC\Files\Node\Root')
			->setConstructorArgs([$this->manager, $this->view, $this->user, $this->userMountCache, $this->logger, $this->userManager])
			->getMock();

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['mimetype' => 'text/plain']));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$this->assertEquals('text/plain', $node->getMimeType());
	}

	public function testFOpenRead() {
		$stream = fopen('php://memory', 'w+');
		fwrite($stream, 'bar');
		rewind($stream);

		$root = new \OC\Files\Node\Root(
			$this->manager,
			$this->view,
			$this->user,
			$this->userMountCache,
			$this->logger,
			$this->userManager
		);

		$hook = function ($file) {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		$this->view->expects($this->once())
			->method('fopen')
			->with('/bar/foo', 'r')
			->willReturn($stream);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL]));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$fh = $node->fopen('r');
		$this->assertEquals($stream, $fh);
		$this->assertEquals('bar', fread($fh, 3));
	}

	public function testFOpenWrite() {
		$stream = fopen('php://memory', 'w+');

		$root = new \OC\Files\Node\Root(
			$this->manager,
			new $this->view,
			$this->user,
			$this->userMountCache,
			$this->logger,
			$this->userManager
		);
		$hooksCalled = 0;
		$hook = function ($file) use (&$hooksCalled) {
			$hooksCalled++;
		};

		$root->listen('\OC\Files', 'preWrite', $hook);
		$root->listen('\OC\Files', 'postWrite', $hook);

		$this->view->expects($this->once())
			->method('fopen')
			->with('/bar/foo', 'w')
			->willReturn($stream);

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_ALL]));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$fh = $node->fopen('w');
		$this->assertEquals($stream, $fh);
		fwrite($fh, 'bar');
		rewind($fh);
		$this->assertEquals('bar', fread($stream, 3));
		$this->assertEquals(2, $hooksCalled);
	}

	
	public function testFOpenReadNotPermitted() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$root = new \OC\Files\Node\Root(
			$this->manager,
			$this->view,
			$this->user,
			$this->userMountCache,
			$this->logger,
			$this->userManager
		);
		$hook = function ($file) {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => 0]));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$node->fopen('r');
	}

	
	public function testFOpenReadWriteNoReadPermissions() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$root = new \OC\Files\Node\Root(
			$this->manager,
			$this->view,
			$this->user,
			$this->userMountCache,
			$this->logger,
			$this->userManager
		);
		$hook = function () {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_UPDATE]));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$node->fopen('w');
	}

	
	public function testFOpenReadWriteNoWritePermissions() {
		$this->expectException(\OCP\Files\NotPermittedException::class);

		$root = new \OC\Files\Node\Root(
			$this->manager,
			new $this->view,
			$this->user,
			$this->userMountCache,
			$this->logger,
			$this->userManager
		);
		$hook = function () {
			throw new \Exception('Hooks are not supposed to be called');
		};

		$this->view->expects($this->once())
			->method('getFileInfo')
			->with('/bar/foo')
			->willReturn($this->getFileInfo(['permissions' => \OCP\Constants::PERMISSION_READ]));

		$node = new \OC\Files\Node\File($root, $this->view, '/bar/foo');
		$node->fopen('w');
	}


}
