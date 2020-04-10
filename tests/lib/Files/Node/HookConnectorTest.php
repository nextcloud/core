<?php
/**
 * Copyright (c) 2015 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Node;

use OC\Files\Filesystem;
use OC\Files\Node\HookConnector;
use OC\Files\Node\Root;
use OC\Files\Storage\Temporary;
use OC\Files\View;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent as APIGenericEvent;
use OCP\Files\Node;
use OCP\ILogger;
use OCP\IUserManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Test\TestCase;
use Test\Traits\MountProviderTrait;
use Test\Traits\UserTrait;

/**
 * Class HookConnectorTest
 *
 * @group DB
 *
 * @package Test\Files\Node
 */
class HookConnectorTest extends TestCase {
	use UserTrait;
	use MountProviderTrait;
	/** @var \PHPUnit\Framework\MockObject\MockObject */
	/** @var EventDispatcherInterface  */
	protected $eventDispatcher;

	/**
	 * @var View
	 */
	private $view;

	/**
	 * @var Root
	 */
	private $root;

	/**
	 * @var string
	 */
	private $userId;

	protected function setUp(): void {
		parent::setUp();
		$this->userId = $this->getUniqueID();
		$this->createUser($this->userId, 'pass');
		$this->registerMount($this->userId, new Temporary(), '/' . $this->userId . '/files/');
		\OC_Util::setupFS($this->userId);
		$this->view = new View();
		$this->root = new Root(
			Filesystem::getMountManager(),
			$this->view,
			\OC::$server->getUserManager()->get($this->userId),
			\OC::$server->getUserMountCache(),
			$this->createMock(ILogger::class),
			$this->createMock(IUserManager::class)
		);
		$this->eventDispatcher = \OC::$server->getEventDispatcher();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\OC_Hook::clear('OC_Filesystem');
		\OC_Util::tearDownFS();
	}

	public function viewToNodeProvider() {
		return [
			[function () {
				Filesystem::file_put_contents('test.txt', 'asd');
			}, 'preWrite', '\OCP\Files::preWrite'],
			[function () {
				Filesystem::file_put_contents('test.txt', 'asd');
			}, 'postWrite', '\OCP\Files::postWrite'],
			[function () {
				Filesystem::file_put_contents('test.txt', 'asd');
			}, 'preCreate', '\OCP\Files::preCreate'],
			[function () {
				Filesystem::file_put_contents('test.txt', 'asd');
			}, 'postCreate', '\OCP\Files::postCreate'],
			[function () {
				Filesystem::mkdir('test.txt');
			}, 'preCreate', '\OCP\Files::preCreate'],
			[function () {
				Filesystem::mkdir('test.txt');
			}, 'postCreate', '\OCP\Files::postCreate'],
			[function () {
				Filesystem::touch('test.txt');
			}, 'preTouch', '\OCP\Files::preTouch'],
			[function () {
				Filesystem::touch('test.txt');
			}, 'postTouch', '\OCP\Files::postTouch'],
			[function () {
				Filesystem::touch('test.txt');
			}, 'preCreate', '\OCP\Files::preCreate'],
			[function () {
				Filesystem::touch('test.txt');
			}, 'postCreate', '\OCP\Files::postCreate'],
			[function () {
				Filesystem::file_put_contents('test.txt', 'asd');
				Filesystem::unlink('test.txt');
			}, 'preDelete', '\OCP\Files::preDelete'],
			[function () {
				Filesystem::file_put_contents('test.txt', 'asd');
				Filesystem::unlink('test.txt');
			}, 'postDelete', '\OCP\Files::postDelete'],
			[function () {
				Filesystem::mkdir('test.txt');
				Filesystem::rmdir('test.txt');
			}, 'preDelete', '\OCP\Files::preDelete'],
			[function () {
				Filesystem::mkdir('test.txt');
				Filesystem::rmdir('test.txt');
			}, 'postDelete', '\OCP\Files::postDelete'],
		];
	}

	/**
	 * @param callable $operation
	 * @param string $expectedHook
	 * @dataProvider viewToNodeProvider
	 */
	public function testViewToNode(callable $operation, $expectedHook, $expectedEvent) {
		$connector = new HookConnector($this->root, $this->view, $this->eventDispatcher);
		$connector->viewToNode();
		$hookCalled = false;
		/** @var Node $hookNode */
		$hookNode = null;

		$this->root->listen('\OC\Files', $expectedHook, function ($node) use (&$hookNode, &$hookCalled) {
			$hookCalled = true;
			$hookNode = $node;
		});

		$dispatcherCalled = false;
		/** @var Node $dispatcherNode */
		$dispatcherNode = null;
		$this->eventDispatcher->addListener($expectedEvent, function ($event) use (&$dispatcherCalled, &$dispatcherNode) {
			/** @var GenericEvent|APIGenericEvent $event */
			$dispatcherCalled = true;
			$dispatcherNode = $event->getSubject();
		});

		$operation();

		$this->assertTrue($hookCalled);
		$this->assertEquals('/' . $this->userId . '/files/test.txt', $hookNode->getPath());

		$this->assertTrue($dispatcherCalled);
		$this->assertEquals('/' . $this->userId . '/files/test.txt', $dispatcherNode->getPath());
	}

	public function viewToNodeProviderCopyRename() {
		return [
			[function () {
				Filesystem::file_put_contents('source', 'asd');
				Filesystem::rename('source', 'target');
			}, 'preRename', '\OCP\Files::preRename'],
			[function () {
				Filesystem::file_put_contents('source', 'asd');
				Filesystem::rename('source', 'target');
			}, 'postRename', '\OCP\Files::postRename'],
			[function () {
				Filesystem::file_put_contents('source', 'asd');
				Filesystem::copy('source', 'target');
			}, 'preCopy', '\OCP\Files::preCopy'],
			[function () {
				Filesystem::file_put_contents('source', 'asd');
				Filesystem::copy('source', 'target');
			}, 'postCopy', '\OCP\Files::postCopy'],
		];
	}

	/**
	 * @param callable $operation
	 * @param string $expectedHook
	 * @dataProvider viewToNodeProviderCopyRename
	 */
	public function testViewToNodeCopyRename(callable $operation, $expectedHook, $expectedEvent) {
		$connector = new HookConnector($this->root, $this->view, $this->eventDispatcher);
		$connector->viewToNode();
		$hookCalled = false;
		/** @var Node $hookSourceNode */
		$hookSourceNode = null;
		/** @var Node $hookTargetNode */
		$hookTargetNode = null;

		$this->root->listen('\OC\Files', $expectedHook, function ($sourceNode, $targetNode) use (&$hookCalled, &$hookSourceNode, &$hookTargetNode) {
			$hookCalled = true;
			$hookSourceNode = $sourceNode;
			$hookTargetNode = $targetNode;
		});

		$dispatcherCalled = false;
		/** @var Node $dispatcherSourceNode */
		$dispatcherSourceNode = null;
		/** @var Node $dispatcherTargetNode */
		$dispatcherTargetNode = null;
		$this->eventDispatcher->addListener($expectedEvent, function ($event) use (&$dispatcherSourceNode, &$dispatcherTargetNode, &$dispatcherCalled) {
			/** @var GenericEvent|APIGenericEvent $event */
			$dispatcherCalled = true;
			list($dispatcherSourceNode, $dispatcherTargetNode) = $event->getSubject();
		});

		$operation();

		$this->assertTrue($hookCalled);
		$this->assertEquals('/' . $this->userId . '/files/source', $hookSourceNode->getPath());
		$this->assertEquals('/' . $this->userId . '/files/target', $hookTargetNode->getPath());

		$this->assertTrue($dispatcherCalled);
		$this->assertEquals('/' . $this->userId . '/files/source', $dispatcherSourceNode->getPath());
		$this->assertEquals('/' . $this->userId . '/files/target', $dispatcherTargetNode->getPath());
	}

	public function testPostDeleteMeta() {
		$connector = new HookConnector($this->root, $this->view, $this->eventDispatcher);
		$connector->viewToNode();
		$hookCalled = false;
		/** @var Node $hookNode */
		$hookNode = null;

		$this->root->listen('\OC\Files', 'postDelete', function ($node) use (&$hookNode, &$hookCalled) {
			$hookCalled = true;
			$hookNode = $node;
		});

		$dispatcherCalled = false;
		/** @var Node $dispatcherNode */
		$dispatcherNode = null;
		$this->eventDispatcher->addListener('\OCP\Files::postDelete', function ($event) use (&$dispatcherCalled, &$dispatcherNode) {
			/** @var GenericEvent|APIGenericEvent $event */
			$dispatcherCalled = true;
			$dispatcherNode = $event->getSubject();
		});

		Filesystem::file_put_contents('test.txt', 'asd');
		$info = Filesystem::getFileInfo('test.txt');
		Filesystem::unlink('test.txt');

		$this->assertTrue($hookCalled);
		$this->assertEquals($hookNode->getId(), $info->getId());

		$this->assertTrue($dispatcherCalled);
		$this->assertEquals($dispatcherNode->getId(), $info->getId());
	}
}
