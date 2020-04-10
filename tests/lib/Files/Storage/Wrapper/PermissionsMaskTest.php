<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Storage\Wrapper;

use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;
use OCP\Files\Cache\IScanner;

/**
 * @group DB
 */
class PermissionsMaskTest extends \Test\Files\Storage\Storage {

	/**
	 * @var \OC\Files\Storage\Temporary
	 */
	private $sourceStorage;

	protected function setUp(): void {
		parent::setUp();
		$this->sourceStorage = new \OC\Files\Storage\Temporary([]);
		$this->instance = $this->getMaskedStorage(Constants::PERMISSION_ALL);
	}

	protected function tearDown(): void {
		$this->sourceStorage->cleanUp();
		parent::tearDown();
	}

	protected function getMaskedStorage($mask) {
		return new \OC\Files\Storage\Wrapper\PermissionsMask([
			'storage' => $this->sourceStorage,
			'mask' => $mask
		]);
	}

	public function testMkdirNoCreate() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE);
		$this->assertFalse($storage->mkdir('foo'));
		$this->assertFalse($storage->file_exists('foo'));
	}

	public function testRmdirNoDelete() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE);
		$this->assertTrue($storage->mkdir('foo'));
		$this->assertTrue($storage->file_exists('foo'));
		$this->assertFalse($storage->rmdir('foo'));
		$this->assertTrue($storage->file_exists('foo'));
	}

	public function testTouchNewFileNoCreate() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE);
		$this->assertFalse($storage->touch('foo'));
		$this->assertFalse($storage->file_exists('foo'));
	}

	public function testTouchNewFileNoUpdate() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE);
		$this->assertTrue($storage->touch('foo'));
		$this->assertTrue($storage->file_exists('foo'));
	}

	public function testTouchExistingFileNoUpdate() {
		$this->sourceStorage->touch('foo');
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE);
		$this->assertFalse($storage->touch('foo'));
	}

	public function testUnlinkNoDelete() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE);
		$this->assertTrue($storage->touch('foo'));
		$this->assertTrue($storage->file_exists('foo'));
		$this->assertFalse($storage->unlink('foo'));
		$this->assertTrue($storage->file_exists('foo'));
	}

	public function testPutContentsNewFileNoUpdate() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE);
		$this->assertEquals(3, $storage->file_put_contents('foo', 'bar'));
		$this->assertEquals('bar', $storage->file_get_contents('foo'));
	}

	public function testPutContentsNewFileNoCreate() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE);
		$this->assertFalse($storage->file_put_contents('foo', 'bar'));
	}

	public function testPutContentsExistingFileNoUpdate() {
		$this->sourceStorage->touch('foo');
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE);
		$this->assertFalse($storage->file_put_contents('foo', 'bar'));
	}

	public function testFopenExistingFileNoUpdate() {
		$this->sourceStorage->touch('foo');
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE);
		$this->assertFalse($storage->fopen('foo', 'w'));
	}

	public function testFopenNewFileNoCreate() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE);
		$this->assertFalse($storage->fopen('foo', 'w'));
	}

	public function testScanNewFiles() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_READ + Constants::PERMISSION_CREATE);
		$storage->file_put_contents('foo', 'bar');
		$storage->getScanner()->scan('');

		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE, $this->sourceStorage->getCache()->get('foo')->getPermissions());
		$this->assertEquals(Constants::PERMISSION_READ, $storage->getCache()->get('foo')->getPermissions());
	}

	public function testScanNewWrappedFiles() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_READ + Constants::PERMISSION_CREATE);
		$wrappedStorage = new Wrapper(['storage' => $storage]);
		$wrappedStorage->file_put_contents('foo', 'bar');
		$wrappedStorage->getScanner()->scan('');

		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE, $this->sourceStorage->getCache()->get('foo')->getPermissions());
		$this->assertEquals(Constants::PERMISSION_READ, $storage->getCache()->get('foo')->getPermissions());
	}

	public function testScanNewFilesNested() {
		$storage = $this->getMaskedStorage(Constants::PERMISSION_READ + Constants::PERMISSION_CREATE + Constants::PERMISSION_UPDATE);
		$nestedStorage = new \OC\Files\Storage\Wrapper\PermissionsMask([
			'storage' => $storage,
			'mask' => Constants::PERMISSION_READ + Constants::PERMISSION_CREATE
		]);
		$wrappedStorage = new Wrapper(['storage' => $nestedStorage]);
		$wrappedStorage->file_put_contents('foo', 'bar');
		$wrappedStorage->getScanner()->scan('');

		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE, $this->sourceStorage->getCache()->get('foo')->getPermissions());
		$this->assertEquals(Constants::PERMISSION_READ + Constants::PERMISSION_UPDATE, $storage->getCache()->get('foo')->getPermissions());
		$this->assertEquals(Constants::PERMISSION_READ, $wrappedStorage->getCache()->get('foo')->getPermissions());
	}

	public function testScanUnchanged() {
		$this->sourceStorage->mkdir('foo');
		$this->sourceStorage->file_put_contents('foo/bar.txt', 'bar');

		$this->sourceStorage->getScanner()->scan('foo');

		$storage = $this->getMaskedStorage(Constants::PERMISSION_READ);
		$scanner = $storage->getScanner();
		$called = false;
		$scanner->listen('\OC\Files\Cache\Scanner', 'addToCache', function () use (&$called) {
			$called = true;
		});
		$scanner->scan('foo', IScanner::SCAN_RECURSIVE, IScanner::REUSE_ETAG | IScanner::REUSE_SIZE);

		$this->assertFalse($called);
	}

	public function testScanUnchangedWrapped() {
		$this->sourceStorage->mkdir('foo');
		$this->sourceStorage->file_put_contents('foo/bar.txt', 'bar');

		$this->sourceStorage->getScanner()->scan('foo');

		$storage = $this->getMaskedStorage(Constants::PERMISSION_READ);
		$wrappedStorage = new Wrapper(['storage' => $storage]);
		$scanner = $wrappedStorage->getScanner();
		$called = false;
		$scanner->listen('\OC\Files\Cache\Scanner', 'addToCache', function () use (&$called) {
			$called = true;
		});
		$scanner->scan('foo', IScanner::SCAN_RECURSIVE, IScanner::REUSE_ETAG | IScanner::REUSE_SIZE);

		$this->assertFalse($called);
	}
}
