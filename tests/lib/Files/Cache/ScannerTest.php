<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Cache;

use OC\Files\Cache\CacheEntry;

/**
 * Class ScannerTest
 *
 * @group DB
 *
 * @package Test\Files\Cache
 */
class ScannerTest extends \Test\TestCase {
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

	protected function setUp(): void {
		parent::setUp();

		$this->storage = new \OC\Files\Storage\Temporary([]);
		$this->scanner = new \OC\Files\Cache\Scanner($this->storage);
		$this->cache = new \OC\Files\Cache\Cache($this->storage);
	}

	protected function tearDown(): void {
		if ($this->cache) {
			$this->cache->clear();
		}

		parent::tearDown();
	}

	public function testFile() {
		$data = "dummy file data\n";
		$this->storage->file_put_contents('foo.txt', $data);
		$this->scanner->scanFile('foo.txt');

		$this->assertEquals($this->cache->inCache('foo.txt'), true);
		$cachedData = $this->cache->get('foo.txt');
		$this->assertEquals($cachedData['size'], strlen($data));
		$this->assertEquals($cachedData['mimetype'], 'text/plain');
		$this->assertNotEquals($cachedData['parent'], -1); //parent folders should be scanned automatically

		$data = file_get_contents(\OC::$SERVERROOT . '/core/img/logo/logo.png');
		$this->storage->file_put_contents('foo.png', $data);
		$this->scanner->scanFile('foo.png');

		$this->assertEquals($this->cache->inCache('foo.png'), true);
		$cachedData = $this->cache->get('foo.png');
		$this->assertEquals($cachedData['size'], strlen($data));
		$this->assertEquals($cachedData['mimetype'], 'image/png');
	}

	public function testFile4Byte() {
		$data = "dummy file data\n";
		$this->storage->file_put_contents('foo🙈.txt', $data);

		if (\OC::$server->getDatabaseConnection()->supports4ByteText()) {
			$this->assertNotNull($this->scanner->scanFile('foo🙈.txt'));
			$this->assertTrue($this->cache->inCache('foo🙈.txt'), true);

			$cachedData = $this->cache->get('foo🙈.txt');
			$this->assertEquals(strlen($data), $cachedData['size']);
			$this->assertEquals('text/plain', $cachedData['mimetype']);
			$this->assertNotEquals(-1, $cachedData['parent']); //parent folders should be scanned automatically
		} else {
			$this->assertNull($this->scanner->scanFile('foo🙈.txt'));
			$this->assertFalse($this->cache->inCache('foo🙈.txt'), true);
		}
	}

	public function testFileInvalidChars() {
		$data = "dummy file data\n";
		$this->storage->file_put_contents("foo\nbar.txt", $data);

		$this->assertNull($this->scanner->scanFile("foo\nbar.txt"));
		$this->assertFalse($this->cache->inCache("foo\nbar.txt"), true);
	}

	private function fillTestFolders() {
		$textData = "dummy file data\n";
		$imgData = file_get_contents(\OC::$SERVERROOT . '/core/img/logo/logo.png');
		$this->storage->mkdir('folder');
		$this->storage->file_put_contents('foo.txt', $textData);
		$this->storage->file_put_contents('foo.png', $imgData);
		$this->storage->file_put_contents('folder/bar.txt', $textData);
	}

	public function testFolder() {
		$this->fillTestFolders();

		$this->scanner->scan('');
		$this->assertEquals($this->cache->inCache(''), true);
		$this->assertEquals($this->cache->inCache('foo.txt'), true);
		$this->assertEquals($this->cache->inCache('foo.png'), true);
		$this->assertEquals($this->cache->inCache('folder'), true);
		$this->assertEquals($this->cache->inCache('folder/bar.txt'), true);

		$cachedDataText = $this->cache->get('foo.txt');
		$cachedDataText2 = $this->cache->get('foo.txt');
		$cachedDataImage = $this->cache->get('foo.png');
		$cachedDataFolder = $this->cache->get('');
		$cachedDataFolder2 = $this->cache->get('folder');

		$this->assertEquals($cachedDataImage['parent'], $cachedDataText['parent']);
		$this->assertEquals($cachedDataFolder['fileid'], $cachedDataImage['parent']);
		$this->assertEquals($cachedDataFolder['size'], $cachedDataImage['size'] + $cachedDataText['size'] + $cachedDataText2['size']);
		$this->assertEquals($cachedDataFolder2['size'], $cachedDataText2['size']);
	}

	public function testShallow() {
		$this->fillTestFolders();

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW);
		$this->assertEquals($this->cache->inCache(''), true);
		$this->assertEquals($this->cache->inCache('foo.txt'), true);
		$this->assertEquals($this->cache->inCache('foo.png'), true);
		$this->assertEquals($this->cache->inCache('folder'), true);
		$this->assertEquals($this->cache->inCache('folder/bar.txt'), false);

		$cachedDataFolder = $this->cache->get('');
		$cachedDataFolder2 = $this->cache->get('folder');

		$this->assertEquals(-1, $cachedDataFolder['size']);
		$this->assertEquals(-1, $cachedDataFolder2['size']);

		$this->scanner->scan('folder', \OC\Files\Cache\Scanner::SCAN_SHALLOW);

		$cachedDataFolder2 = $this->cache->get('folder');

		$this->assertNotEquals($cachedDataFolder2['size'], -1);

		$this->cache->correctFolderSize('folder');

		$cachedDataFolder = $this->cache->get('');
		$this->assertNotEquals($cachedDataFolder['size'], -1);
	}

	public function testBackgroundScan() {
		$this->fillTestFolders();
		$this->storage->mkdir('folder2');
		$this->storage->file_put_contents('folder2/bar.txt', 'foobar');

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW);
		$this->assertFalse($this->cache->inCache('folder/bar.txt'));
		$this->assertFalse($this->cache->inCache('folder/2bar.txt'));
		$cachedData = $this->cache->get('');
		$this->assertEquals(-1, $cachedData['size']);

		$this->scanner->backgroundScan();

		$this->assertTrue($this->cache->inCache('folder/bar.txt'));
		$this->assertTrue($this->cache->inCache('folder/bar.txt'));

		$cachedData = $this->cache->get('');
		$this->assertnotEquals(-1, $cachedData['size']);

		$this->assertFalse($this->cache->getIncomplete());
	}

	public function testBackgroundScanOnlyRecurseIncomplete() {
		$this->fillTestFolders();
		$this->storage->mkdir('folder2');
		$this->storage->file_put_contents('folder2/bar.txt', 'foobar');

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW);
		$this->assertFalse($this->cache->inCache('folder/bar.txt'));
		$this->assertFalse($this->cache->inCache('folder/2bar.txt'));
		$this->assertFalse($this->cache->inCache('folder2/bar.txt'));
		$this->cache->put('folder2', ['size' => 1]); // mark as complete

		$cachedData = $this->cache->get('');
		$this->assertEquals(-1, $cachedData['size']);

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_RECURSIVE_INCOMPLETE, \OC\Files\Cache\Scanner::REUSE_ETAG | \OC\Files\Cache\Scanner::REUSE_SIZE);

		$this->assertTrue($this->cache->inCache('folder/bar.txt'));
		$this->assertTrue($this->cache->inCache('folder/bar.txt'));
		$this->assertFalse($this->cache->inCache('folder2/bar.txt'));

		$cachedData = $this->cache->get('');
		$this->assertNotEquals(-1, $cachedData['size']);

		$this->assertFalse($this->cache->getIncomplete());
	}

	public function testBackgroundScanNestedIncompleteFolders() {
		$this->storage->mkdir('folder');
		$this->scanner->backgroundScan();

		$this->storage->mkdir('folder/subfolder1');
		$this->storage->mkdir('folder/subfolder2');

		$this->storage->mkdir('folder/subfolder1/subfolder3');
		$this->cache->put('folder', ['size' => -1]);
		$this->cache->put('folder/subfolder1', ['size' => -1]);

		// do a scan to get the folders into the cache.
		$this->scanner->backgroundScan();

		$this->assertTrue($this->cache->inCache('folder/subfolder1/subfolder3'));

		$this->storage->file_put_contents('folder/subfolder1/bar1.txt', 'foobar');
		$this->storage->file_put_contents('folder/subfolder1/subfolder3/bar3.txt', 'foobar');
		$this->storage->file_put_contents('folder/subfolder2/bar2.txt', 'foobar');

		//mark folders as incomplete.
		$this->cache->put('folder/subfolder1', ['size' => -1]);
		$this->cache->put('folder/subfolder2', ['size' => -1]);
		$this->cache->put('folder/subfolder1/subfolder3', ['size' => -1]);

		$this->scanner->backgroundScan();

		$this->assertTrue($this->cache->inCache('folder/subfolder1/bar1.txt'));
		$this->assertTrue($this->cache->inCache('folder/subfolder2/bar2.txt'));
		$this->assertTrue($this->cache->inCache('folder/subfolder1/subfolder3/bar3.txt'));

		//check if folder sizes are correct.
		$this->assertEquals(18, $this->cache->get('folder')['size']);
		$this->assertEquals(12, $this->cache->get('folder/subfolder1')['size']);
		$this->assertEquals(6, $this->cache->get('folder/subfolder1/subfolder3')['size']);
		$this->assertEquals(6, $this->cache->get('folder/subfolder2')['size']);
	}

	public function testReuseExisting() {
		$this->fillTestFolders();

		$this->scanner->scan('');
		$oldData = $this->cache->get('');
		$this->storage->unlink('folder/bar.txt');
		$this->cache->put('folder', ['mtime' => $this->storage->filemtime('folder'), 'storage_mtime' => $this->storage->filemtime('folder')]);
		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW, \OC\Files\Cache\Scanner::REUSE_SIZE);
		$newData = $this->cache->get('');
		$this->assertIsString($oldData['etag']);
		$this->assertIsString($newData['etag']);
		$this->assertNotSame($oldData['etag'], $newData['etag']);
		$this->assertEquals($oldData['size'], $newData['size']);

		$oldData = $newData;
		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW, \OC\Files\Cache\Scanner::REUSE_ETAG);
		$newData = $this->cache->get('');
		$this->assertSame($oldData['etag'], $newData['etag']);
		$this->assertEquals(-1, $newData['size']);

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_RECURSIVE);
		$oldData = $this->cache->get('');
		$this->assertNotEquals(-1, $oldData['size']);
		$this->scanner->scanFile('', \OC\Files\Cache\Scanner::REUSE_ETAG + \OC\Files\Cache\Scanner::REUSE_SIZE);
		$newData = $this->cache->get('');
		$this->assertSame($oldData['etag'], $newData['etag']);
		$this->assertEquals($oldData['size'], $newData['size']);

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_RECURSIVE, \OC\Files\Cache\Scanner::REUSE_ETAG + \OC\Files\Cache\Scanner::REUSE_SIZE);
		$newData = $this->cache->get('');
		$this->assertSame($oldData['etag'], $newData['etag']);
		$this->assertEquals($oldData['size'], $newData['size']);

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW, \OC\Files\Cache\Scanner::REUSE_ETAG + \OC\Files\Cache\Scanner::REUSE_SIZE);
		$newData = $this->cache->get('');
		$this->assertSame($oldData['etag'], $newData['etag']);
		$this->assertEquals($oldData['size'], $newData['size']);
	}

	public function testRemovedFile() {
		$this->fillTestFolders();

		$this->scanner->scan('');
		$this->assertTrue($this->cache->inCache('foo.txt'));
		$this->storage->unlink('foo.txt');
		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW);
		$this->assertFalse($this->cache->inCache('foo.txt'));
	}

	public function testRemovedFolder() {
		$this->fillTestFolders();

		$this->scanner->scan('');
		$this->assertTrue($this->cache->inCache('folder/bar.txt'));
		$this->storage->rmdir('/folder');
		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW);
		$this->assertFalse($this->cache->inCache('folder'));
		$this->assertFalse($this->cache->inCache('folder/bar.txt'));
	}

	public function testScanRemovedFile() {
		$this->fillTestFolders();

		$this->scanner->scan('');
		$this->assertTrue($this->cache->inCache('folder/bar.txt'));
		$this->storage->unlink('folder/bar.txt');
		$this->scanner->scanFile('folder/bar.txt');
		$this->assertFalse($this->cache->inCache('folder/bar.txt'));
	}

	public function testETagRecreation() {
		$this->fillTestFolders();

		$this->scanner->scan('folder/bar.txt');

		// manipulate etag to simulate an empty etag
		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW, \OC\Files\Cache\Scanner::REUSE_ETAG);
		/** @var CacheEntry $data0 */
		$data0 = $this->cache->get('folder/bar.txt');
		$this->assertIsString($data0['etag']);
		$data1 = $this->cache->get('folder');
		$this->assertIsString($data1['etag']);
		$data2 = $this->cache->get('');
		$this->assertIsString($data2['etag']);
		$data0['etag'] = '';
		$this->cache->put('folder/bar.txt', $data0->getData());

		// rescan
		$this->scanner->scan('folder/bar.txt', \OC\Files\Cache\Scanner::SCAN_SHALLOW, \OC\Files\Cache\Scanner::REUSE_ETAG);

		// verify cache content
		$newData0 = $this->cache->get('folder/bar.txt');
		$this->assertIsString($newData0['etag']);
		$this->assertNotEmpty($newData0['etag']);
	}

	public function testRepairParent() {
		$this->fillTestFolders();
		$this->scanner->scan('');
		$this->assertTrue($this->cache->inCache('folder/bar.txt'));
		$oldFolderId = $this->cache->getId('folder');

		// delete the folder without removing the children
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('filecache')
			->where($query->expr()->eq('fileid', $query->createNamedParameter($oldFolderId)));
		$query->execute();

		$cachedData = $this->cache->get('folder/bar.txt');
		$this->assertEquals($oldFolderId, $cachedData['parent']);
		$this->assertFalse($this->cache->inCache('folder'));

		$this->scanner->scan('');

		$this->assertTrue($this->cache->inCache('folder'));
		$newFolderId = $this->cache->getId('folder');
		$this->assertNotEquals($oldFolderId, $newFolderId);

		$cachedData = $this->cache->get('folder/bar.txt');
		$this->assertEquals($newFolderId, $cachedData['parent']);
	}

	public function testRepairParentShallow() {
		$this->fillTestFolders();
		$this->scanner->scan('');
		$this->assertTrue($this->cache->inCache('folder/bar.txt'));
		$oldFolderId = $this->cache->getId('folder');

		// delete the folder without removing the children
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('filecache')
			->where($query->expr()->eq('fileid', $query->createNamedParameter($oldFolderId)));
		$query->execute();

		$cachedData = $this->cache->get('folder/bar.txt');
		$this->assertEquals($oldFolderId, $cachedData['parent']);
		$this->assertFalse($this->cache->inCache('folder'));

		$this->scanner->scan('folder', \OC\Files\Cache\Scanner::SCAN_SHALLOW);

		$this->assertTrue($this->cache->inCache('folder'));
		$newFolderId = $this->cache->getId('folder');
		$this->assertNotEquals($oldFolderId, $newFolderId);

		$cachedData = $this->cache->get('folder/bar.txt');
		$this->assertEquals($newFolderId, $cachedData['parent']);
	}

	/**
	 * @dataProvider dataTestIsPartialFile
	 *
	 * @param string $path
	 * @param bool $expected
	 */
	public function testIsPartialFile($path, $expected) {
		$this->assertSame($expected,
			$this->scanner->isPartialFile($path)
		);
	}

	public function dataTestIsPartialFile() {
		return [
			['foo.txt.part', true],
			['/sub/folder/foo.txt.part', true],
			['/sub/folder.part/foo.txt', true],
			['foo.txt', false],
			['/sub/folder/foo.txt', false],
		];
	}
}
