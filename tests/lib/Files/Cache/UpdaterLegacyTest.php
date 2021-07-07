<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Cache;

use OC\Files\Filesystem as Filesystem;
use OC\Files\View;

/**
 * Class UpdaterLegacyTest
 *
 * @group DB
 *
 * @package Test\Files\Cache
 */
class UpdaterLegacyTest extends \Test\TestCase {
	/**
	 * @var \OC\Files\Storage\Storage $storage
	 */
	private $storage;

	/**
	 * @var \OC\Files\Cache\Scanner $scanner
	 */
	private $scanner;

	/**
	 * @var \OC\Files\Cache\Cache $cache
	 */
	private $cache;

	private static $user;

	protected function setUp(): void {
		parent::setUp();

		$this->storage = new \OC\Files\Storage\Temporary([]);
		$textData = "dummy file data\n";
		$imgData = file_get_contents(\OC::$SERVERROOT . '/core/img/logo/logo.png');
		$this->storage->mkdir('folder');
		$this->storage->file_put_contents('foo.txt', $textData);
		$this->storage->file_put_contents('foo.png', $imgData);
		$this->storage->file_put_contents('folder/bar.txt', $textData);
		$this->storage->file_put_contents('folder/bar2.txt', $textData);

		$this->scanner = $this->storage->getScanner();
		$this->scanner->scan('');
		$this->cache = $this->storage->getCache();

		self::$user = $this->getUniqueID();
		\OC::$server->getUserManager()->createUser(self::$user, 'password');
		$this->loginAsUser(self::$user);

		Filesystem::init(self::$user, '/' . self::$user . '/files');

		Filesystem::clearMounts();
		Filesystem::mount($this->storage, [], '/' . self::$user . '/files');

		\OC_Hook::clear('OC_Filesystem');
	}

	protected function tearDown(): void {
		if ($this->cache) {
			$this->cache->clear();
		}

		$result = false;
		$user = \OC::$server->getUserManager()->get(self::$user);
		if ($user !== null) {
			$result = $user->delete();
		}
		$this->assertTrue($result);

		$this->logout();
		parent::tearDown();
	}

	public function testWrite() {
		$textSize = strlen("dummy file data\n");
		$imageSize = filesize(\OC::$SERVERROOT . '/core/img/logo/logo.png');
		$this->cache->put('foo.txt', ['mtime' => 100, 'storage_mtime' => 150]);
		$rootCachedData = $this->cache->get('');
		$this->assertEquals(3 * $textSize + $imageSize, $rootCachedData['size']);

		$fooCachedData = $this->cache->get('foo.txt');
		Filesystem::file_put_contents('foo.txt', 'asd');
		$cachedData = $this->cache->get('foo.txt');
		$this->assertEquals(3, $cachedData['size']);
		$this->assertIsString($fooCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($fooCachedData['etag'], $cachedData['etag']);
		$cachedData = $this->cache->get('');
		$this->assertEquals(2 * $textSize + $imageSize + 3, $cachedData['size']);
		$this->assertIsString($rootCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($rootCachedData['etag'], $cachedData['etag']);
		$rootCachedData = $cachedData;

		$this->assertFalse($this->cache->inCache('bar.txt'));
		Filesystem::file_put_contents('bar.txt', 'asd');
		$this->assertTrue($this->cache->inCache('bar.txt'));
		$cachedData = $this->cache->get('bar.txt');
		$this->assertEquals(3, $cachedData['size']);
		$mtime = $cachedData['mtime'];
		$cachedData = $this->cache->get('');
		$this->assertEquals(2 * $textSize + $imageSize + 2 * 3, $cachedData['size']);
		$this->assertIsString($rootCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($rootCachedData['etag'], $cachedData['etag']);
		$this->assertGreaterThanOrEqual($rootCachedData['mtime'], $mtime);
	}

	public function testWriteWithMountPoints() {
		$storage2 = new \OC\Files\Storage\Temporary([]);
		$storage2->getScanner()->scan(''); //initialize etags
		$cache2 = $storage2->getCache();
		Filesystem::mount($storage2, [], '/' . self::$user . '/files/folder/substorage');
		$view = new View('/' . self::$user . '/files');
		$folderCachedData = $view->getFileInfo('folder');
		$substorageCachedData = $cache2->get('');
		Filesystem::file_put_contents('folder/substorage/foo.txt', 'asd');
		$this->assertTrue($cache2->inCache('foo.txt'));
		$cachedData = $cache2->get('foo.txt');
		$oldEtag = $substorageCachedData['etag'];
		$this->assertEquals(3, $cachedData['size']);
		$mtime = $cachedData['mtime'];

		$cachedData = $cache2->get('');
		$this->assertIsString($substorageCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($oldEtag, $cachedData['etag']);

		$cachedData = $view->getFileInfo('folder');
		$this->assertIsString($folderCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($oldEtag, $cachedData['etag']);
	}

	public function testDelete() {
		$textSize = strlen("dummy file data\n");
		$imageSize = filesize(\OC::$SERVERROOT . '/core/img/logo/logo.png');
		$rootCachedData = $this->cache->get('');
		$oldEtag = $rootCachedData['etag'];
		$this->assertEquals(3 * $textSize + $imageSize, $rootCachedData['size']);

		$this->assertTrue($this->cache->inCache('foo.txt'));
		Filesystem::unlink('foo.txt');
		$this->assertFalse($this->cache->inCache('foo.txt'));
		$cachedData = $this->cache->get('');
		$this->assertEquals(2 * $textSize + $imageSize, $cachedData['size']);
		$this->assertIsString($rootCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($oldEtag, $cachedData['etag']);
		$this->assertGreaterThanOrEqual($rootCachedData['mtime'], $cachedData['mtime']);
		$rootCachedData = $cachedData;

		Filesystem::mkdir('bar_folder');
		$this->assertTrue($this->cache->inCache('bar_folder'));
		$cachedData = $this->cache->get('');
		$this->assertIsString($rootCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($oldEtag, $cachedData['etag']);
		$rootCachedData = $cachedData;
		$oldEtag = $rootCachedData['etag'];
		Filesystem::rmdir('bar_folder');
		$this->assertFalse($this->cache->inCache('bar_folder'));
		$cachedData = $this->cache->get('');
		$this->assertIsString($rootCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($oldEtag, $cachedData['etag']);
		$this->assertGreaterThanOrEqual($rootCachedData['mtime'], $cachedData['mtime']);
	}

	public function testDeleteWithMountPoints() {
		$storage2 = new \OC\Files\Storage\Temporary([]);
		$cache2 = $storage2->getCache();
		Filesystem::mount($storage2, [], '/' . self::$user . '/files/folder/substorage');
		Filesystem::file_put_contents('folder/substorage/foo.txt', 'asd');
		$view = new View('/' . self::$user . '/files');
		$this->assertTrue($cache2->inCache('foo.txt'));
		$folderCachedData = $view->getFileInfo('folder');
		$substorageCachedData = $cache2->get('');
		$oldEtag = $folderCachedData['etag'];
		Filesystem::unlink('folder/substorage/foo.txt');
		$this->assertFalse($cache2->inCache('foo.txt'));

		$cachedData = $cache2->get('');
		$this->assertIsString($substorageCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($substorageCachedData, $cachedData['etag']);
		$this->assertGreaterThanOrEqual($substorageCachedData['mtime'], $cachedData['mtime']);

		$cachedData = $view->getFileInfo('folder');
		$this->assertIsString($folderCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($oldEtag, $cachedData['etag']);
		$this->assertGreaterThanOrEqual($folderCachedData['mtime'], $cachedData['mtime']);
	}

	public function testRename() {
		$textSize = strlen("dummy file data\n");
		$imageSize = filesize(\OC::$SERVERROOT . '/core/img/logo/logo.png');
		$rootCachedData = $this->cache->get('');
		$this->assertEquals(3 * $textSize + $imageSize, $rootCachedData['size']);

		$this->assertTrue($this->cache->inCache('foo.txt'));
		$fooCachedData = $this->cache->get('foo.txt');
		$this->assertFalse($this->cache->inCache('bar.txt'));
		Filesystem::rename('foo.txt', 'bar.txt');
		$this->assertFalse($this->cache->inCache('foo.txt'));
		$this->assertTrue($this->cache->inCache('bar.txt'));
		$cachedData = $this->cache->get('bar.txt');
		$this->assertEquals($fooCachedData['fileid'], $cachedData['fileid']);
		$mtime = $cachedData['mtime'];
		$cachedData = $this->cache->get('');
		$this->assertEquals(3 * $textSize + $imageSize, $cachedData['size']);
		$this->assertIsString($rootCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($rootCachedData['etag'], $cachedData['etag']);
	}

	public function testRenameExtension() {
		$fooCachedData = $this->cache->get('foo.txt');
		$this->assertEquals('text/plain', $fooCachedData['mimetype']);
		Filesystem::rename('foo.txt', 'foo.abcd');
		$fooCachedData = $this->cache->get('foo.abcd');
		$this->assertEquals('application/octet-stream', $fooCachedData['mimetype']);
	}

	public function testRenameWithMountPoints() {
		$storage2 = new \OC\Files\Storage\Temporary([]);
		$cache2 = $storage2->getCache();
		Filesystem::mount($storage2, [], '/' . self::$user . '/files/folder/substorage');
		Filesystem::file_put_contents('folder/substorage/foo.txt', 'asd');
		$view = new View('/' . self::$user . '/files');
		$this->assertTrue($cache2->inCache('foo.txt'));
		$folderCachedData = $view->getFileInfo('folder');
		$oldEtag = $folderCachedData['etag'];
		$substorageCachedData = $cache2->get('');
		$fooCachedData = $cache2->get('foo.txt');
		Filesystem::rename('folder/substorage/foo.txt', 'folder/substorage/bar.txt');
		$this->assertFalse($cache2->inCache('foo.txt'));
		$this->assertTrue($cache2->inCache('bar.txt'));
		$cachedData = $cache2->get('bar.txt');
		$this->assertEquals($fooCachedData['fileid'], $cachedData['fileid']);
		$mtime = $cachedData['mtime'];

		$cachedData = $cache2->get('');
		$this->assertIsString($substorageCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($oldEtag, $cachedData['etag']);
		// rename can cause mtime change - invalid assert
//		$this->assertEquals($mtime, $cachedData['mtime']);

		$cachedData = $view->getFileInfo('folder');
		$this->assertIsString($folderCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($oldEtag, $cachedData['etag']);
		// rename can cause mtime change - invalid assert
//		$this->assertEquals($mtime, $cachedData['mtime']);
	}

	public function testTouch() {
		$rootCachedData = $this->cache->get('');
		$fooCachedData = $this->cache->get('foo.txt');
		Filesystem::touch('foo.txt');
		$cachedData = $this->cache->get('foo.txt');
		$this->assertIsString($fooCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertGreaterThanOrEqual($fooCachedData['mtime'], $cachedData['mtime']);

		$cachedData = $this->cache->get('');
		$this->assertIsString($rootCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($rootCachedData['etag'], $cachedData['etag']);
		$this->assertGreaterThanOrEqual($rootCachedData['mtime'], $cachedData['mtime']);
		$rootCachedData = $cachedData;

		$time = 1371006070;
		$barCachedData = $this->cache->get('folder/bar.txt');
		$folderCachedData = $this->cache->get('folder');
		$this->cache->put('', ['mtime' => $time - 100]);
		Filesystem::touch('folder/bar.txt', $time);
		$cachedData = $this->cache->get('folder/bar.txt');
		$this->assertIsString($barCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($barCachedData['etag'], $cachedData['etag']);
		$this->assertEquals($time, $cachedData['mtime']);

		$cachedData = $this->cache->get('folder');
		$this->assertIsString($folderCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($folderCachedData['etag'], $cachedData['etag']);

		$cachedData = $this->cache->get('');
		$this->assertIsString($rootCachedData['etag']);
		$this->assertIsString($cachedData['etag']);
		$this->assertNotSame($rootCachedData['etag'], $cachedData['etag']);
		$this->assertEquals($time, $cachedData['mtime']);
	}
}
