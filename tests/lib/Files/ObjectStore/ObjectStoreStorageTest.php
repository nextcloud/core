<?php
/**
 * @author Jörn Friedrich Dreyer
 * @copyright (c) 2014 Jörn Friedrich Dreyer <jfd@owncloud.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Files\ObjectStore;

use OC\Files\ObjectStore\StorageObjectStore;
use OC\Files\Storage\Temporary;
use OCP\Files\ObjectStore\IObjectStore;
use Test\Files\Storage\Storage;

/**
 * @group DB
 */
class ObjectStoreStorageTest extends Storage {
	/** @var ObjectStoreStorageOverwrite */
	protected $instance;

	/**
	 * @var IObjectStore
	 */
	private $objectStorage;

	protected function setUp(): void {
		parent::setUp();

		$baseStorage = new Temporary();
		$this->objectStorage = new StorageObjectStore($baseStorage);
		$config['objectstore'] = $this->objectStorage;
		$this->instance = new ObjectStoreStorageOverwrite($config);
	}

	protected function tearDown(): void {
		if (is_null($this->instance)) {
			return;
		}
		$this->instance->getCache()->clear();

		parent::tearDown();
	}

	public function testStat() {

		$textFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';
		$ctimeStart = time();
		$this->instance->file_put_contents('/lorem.txt', file_get_contents($textFile));
		$this->assertTrue($this->instance->isReadable('/lorem.txt'));
		$ctimeEnd = time();
		$mTime = $this->instance->filemtime('/lorem.txt');

		// check that ($ctimeStart - 5) <= $mTime <= ($ctimeEnd + 1)
		$this->assertGreaterThanOrEqual(($ctimeStart - 5), $mTime);
		$this->assertLessThanOrEqual(($ctimeEnd + 1), $mTime);
		$this->assertEquals(filesize($textFile), $this->instance->filesize('/lorem.txt'));

		$stat = $this->instance->stat('/lorem.txt');
		//only size and mtime are required in the result
		$this->assertEquals($stat['size'], $this->instance->filesize('/lorem.txt'));
		$this->assertEquals($stat['mtime'], $mTime);

		if ($this->instance->touch('/lorem.txt', 100) !== false) {
			$mTime = $this->instance->filemtime('/lorem.txt');
			$this->assertEquals($mTime, 100);
		}
	}

	public function testCheckUpdate() {
		$this->markTestSkipped('Detecting external changes is not supported on object storages');
	}

	/**
	 * @dataProvider copyAndMoveProvider
	 */
	public function testMove($source, $target) {
		$this->initSourceAndTarget($source);
		$sourceId = $this->instance->getCache()->getId(ltrim('/',$source));
		$this->assertNotEquals(-1, $sourceId);

		$this->instance->rename($source, $target);

		$this->assertTrue($this->instance->file_exists($target), $target.' was not created');
		$this->assertFalse($this->instance->file_exists($source), $source.' still exists');
		$this->assertSameAsLorem($target);

		$targetId = $this->instance->getCache()->getId(ltrim('/',$target));
		$this->assertSame($sourceId, $targetId, 'fileid must be stable on move or shares will break');
	}

	public function testRenameDirectory() {
		$this->instance->mkdir('source');
		$this->instance->file_put_contents('source/test1.txt', 'foo');
		$this->instance->file_put_contents('source/test2.txt', 'qwerty');
		$this->instance->mkdir('source/subfolder');
		$this->instance->file_put_contents('source/subfolder/test.txt', 'bar');
		$sourceId = $this->instance->getCache()->getId('source');
		$this->assertNotEquals(-1, $sourceId);
		$this->instance->rename('source', 'target');

		$this->assertFalse($this->instance->file_exists('source'));
		$this->assertFalse($this->instance->file_exists('source/test1.txt'));
		$this->assertFalse($this->instance->file_exists('source/test2.txt'));
		$this->assertFalse($this->instance->file_exists('source/subfolder'));
		$this->assertFalse($this->instance->file_exists('source/subfolder/test.txt'));

		$this->assertTrue($this->instance->file_exists('target'));
		$this->assertTrue($this->instance->file_exists('target/test1.txt'));
		$this->assertTrue($this->instance->file_exists('target/test2.txt'));
		$this->assertTrue($this->instance->file_exists('target/subfolder'));
		$this->assertTrue($this->instance->file_exists('target/subfolder/test.txt'));

		$this->assertEquals('foo', $this->instance->file_get_contents('target/test1.txt'));
		$this->assertEquals('qwerty', $this->instance->file_get_contents('target/test2.txt'));
		$this->assertEquals('bar', $this->instance->file_get_contents('target/subfolder/test.txt'));
		$targetId = $this->instance->getCache()->getId('target');
		$this->assertSame($sourceId, $targetId, 'fileid must be stable on move or shares will break');
	}

	public function testRenameOverWriteDirectory() {
		$this->instance->mkdir('source');
		$this->instance->file_put_contents('source/test1.txt', 'foo');
		$sourceId = $this->instance->getCache()->getId('source');
		$this->assertNotEquals(-1, $sourceId);

		$this->instance->mkdir('target');
		$this->instance->file_put_contents('target/test1.txt', 'bar');
		$this->instance->file_put_contents('target/test2.txt', 'bar');

		$this->instance->rename('source', 'target');

		$this->assertFalse($this->instance->file_exists('source'));
		$this->assertFalse($this->instance->file_exists('source/test1.txt'));
		$this->assertFalse($this->instance->file_exists('target/test2.txt'));
		$this->assertEquals('foo', $this->instance->file_get_contents('target/test1.txt'));
		$targetId = $this->instance->getCache()->getId('target');
		$this->assertSame($sourceId, $targetId, 'fileid must be stable on move or shares will break');
	}

	public function testRenameOverWriteDirectoryOverFile() {
		$this->instance->mkdir('source');
		$this->instance->file_put_contents('source/test1.txt', 'foo');
		$sourceId = $this->instance->getCache()->getId('source');
		$this->assertNotEquals(-1, $sourceId);

		$this->instance->file_put_contents('target', 'bar');

		$this->instance->rename('source', 'target');

		$this->assertFalse($this->instance->file_exists('source'));
		$this->assertFalse($this->instance->file_exists('source/test1.txt'));
		$this->assertEquals('foo', $this->instance->file_get_contents('target/test1.txt'));
		$targetId = $this->instance->getCache()->getId('target');
		$this->assertSame($sourceId, $targetId, 'fileid must be stable on move or shares will break');
	}

	public function testWriteObjectSilentFailure() {
		$objectStore = $this->instance->getObjectStore();
		$this->instance->setObjectStore(new FailWriteObjectStore($objectStore));

		try {
			$this->instance->file_put_contents('test.txt', 'foo');
			$this->fail('expected exception');
		} catch (\Exception $e) {
			$this->assertStringStartsWith('Object not found after writing', $e->getMessage());
		}
		$this->assertFalse($this->instance->file_exists('test.txt'));
	}

	public function testDeleteObjectFailureKeepCache() {
		$objectStore = $this->instance->getObjectStore();
		$this->instance->setObjectStore(new FailDeleteObjectStore($objectStore));
		$cache = $this->instance->getCache();

		$this->instance->file_put_contents('test.txt', 'foo');

		$this->assertTrue($cache->inCache('test.txt'));

		$this->assertFalse($this->instance->unlink('test.txt'));

		$this->assertTrue($cache->inCache('test.txt'));

		$this->instance->mkdir('foo');
		$this->instance->file_put_contents('foo/test.txt', 'foo');

		$this->assertTrue($cache->inCache('foo'));
		$this->assertTrue($cache->inCache('foo/test.txt'));

		$this->instance->rmdir('foo');

		$this->assertTrue($cache->inCache('foo'));
		$this->assertTrue($cache->inCache('foo/test.txt'));
	}
}
