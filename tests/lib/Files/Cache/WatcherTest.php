<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Cache;

/**
 * Class WatcherTest
 *
 * @group DB
 *
 * @package Test\Files\Cache
 */
class WatcherTest extends \Test\TestCase {

	/**
	 * @var \OC\Files\Storage\Storage[] $storages
	 */
	private $storages = [];

	protected function setUp(): void {
		parent::setUp();

		$this->loginAsUser();
	}

	protected function tearDown(): void {
		foreach ($this->storages as $storage) {
			$cache = $storage->getCache();
			$ids = $cache->getAll();
			$cache->clear();
		}

		$this->logout();
		parent::tearDown();
	}

	/**
	 * @medium
	 */
	function testWatcher() {
		$storage = $this->getTestStorage();
		$cache = $storage->getCache();
		$updater = $storage->getWatcher();
		$updater->setPolicy(\OC\Files\Cache\Watcher::CHECK_ONCE);

		//set the mtime to the past so it can detect an mtime change
		$cache->put('', ['storage_mtime' => 10]);

		$this->assertTrue($cache->inCache('folder/bar.txt'));
		$this->assertTrue($cache->inCache('folder/bar2.txt'));

		$this->assertFalse($cache->inCache('bar.test'));
		$storage->file_put_contents('bar.test', 'foo');
		$updater->checkUpdate('');
		$this->assertTrue($cache->inCache('bar.test'));
		$cachedData = $cache->get('bar.test');
		$this->assertEquals(3, $cachedData['size']);

		$cache->put('bar.test', ['storage_mtime' => 10]);
		$storage->file_put_contents('bar.test', 'test data');

		// make sure that PHP can read the new size correctly
		clearstatcache();

		$updater->checkUpdate('bar.test');
		$cachedData = $cache->get('bar.test');
		$this->assertEquals(9, $cachedData['size']);

		$cache->put('folder', ['storage_mtime' => 10]);

		$storage->unlink('folder/bar2.txt');
		$updater->checkUpdate('folder');

		$this->assertTrue($cache->inCache('folder/bar.txt'));
		$this->assertFalse($cache->inCache('folder/bar2.txt'));
	}

	/**
	 * @medium
	 */
	public function testFileToFolder() {
		$storage = $this->getTestStorage();
		$cache = $storage->getCache();
		$updater = $storage->getWatcher();
		$updater->setPolicy(\OC\Files\Cache\Watcher::CHECK_ONCE);

		//set the mtime to the past so it can detect an mtime change
		$cache->put('', ['storage_mtime' => 10]);

		$storage->unlink('foo.txt');
		$storage->rename('folder', 'foo.txt');
		$updater->checkUpdate('');

		$entry = $cache->get('foo.txt');
		$this->assertEquals('httpd/unix-directory', $entry['mimetype']);
		$this->assertFalse($cache->inCache('folder'));
		$this->assertFalse($cache->inCache('folder/bar.txt'));

		$storage = $this->getTestStorage();
		$cache = $storage->getCache();
		$updater = $storage->getWatcher();
		$updater->setPolicy(\OC\Files\Cache\Watcher::CHECK_ONCE);

		//set the mtime to the past so it can detect an mtime change
		$cache->put('foo.txt', ['storage_mtime' => 10]);

		$storage->unlink('foo.txt');
		$storage->rename('folder', 'foo.txt');
		$updater->checkUpdate('foo.txt');

		$entry = $cache->get('foo.txt');
		$this->assertEquals('httpd/unix-directory', $entry['mimetype']);
		$this->assertTrue($cache->inCache('foo.txt/bar.txt'));
	}

	public function testPolicyNever() {
		$storage = $this->getTestStorage();
		$cache = $storage->getCache();
		$updater = $storage->getWatcher();

		//set the mtime to the past so it can detect an mtime change
		$cache->put('foo.txt', ['storage_mtime' => 10]);

		$updater->setPolicy(\OC\Files\Cache\Watcher::CHECK_NEVER);

		$storage->file_put_contents('foo.txt', 'q');
		$this->assertFalse($updater->checkUpdate('foo.txt'));

		$cache->put('foo.txt', ['storage_mtime' => 20]);
		$storage->file_put_contents('foo.txt', 'w');
		$this->assertFalse($updater->checkUpdate('foo.txt'));
	}

	public function testPolicyOnce() {
		$storage = $this->getTestStorage();
		$cache = $storage->getCache();
		$updater = $storage->getWatcher();

		//set the mtime to the past so it can detect an mtime change
		$cache->put('foo.txt', ['storage_mtime' => 10]);

		$updater->setPolicy(\OC\Files\Cache\Watcher::CHECK_ONCE);

		$storage->file_put_contents('foo.txt', 'q');
		$this->assertTrue($updater->checkUpdate('foo.txt'));

		$cache->put('foo.txt', ['storage_mtime' => 20]);
		$storage->file_put_contents('foo.txt', 'w');
		$this->assertFalse($updater->checkUpdate('foo.txt'));
	}

	public function testPolicyAlways() {
		$storage = $this->getTestStorage();
		$cache = $storage->getCache();
		$updater = $storage->getWatcher();

		//set the mtime to the past so it can detect an mtime change
		$cache->put('foo.txt', ['storage_mtime' => 10]);

		$updater->setPolicy(\OC\Files\Cache\Watcher::CHECK_ALWAYS);

		$storage->file_put_contents('foo.txt', 'q');
		$this->assertTrue($updater->checkUpdate('foo.txt'));

		$cache->put('foo.txt', ['storage_mtime' => 20]);
		$storage->file_put_contents('foo.txt', 'w');
		$this->assertTrue($updater->checkUpdate('foo.txt'));
	}

	/**
	 * @param bool $scan
	 * @return \OC\Files\Storage\Storage
	 */
	private function getTestStorage($scan = true) {
		$storage = new \OC\Files\Storage\Temporary([]);
		$textData = "dummy file data\n";
		$imgData = file_get_contents(\OC::$SERVERROOT . '/core/img/logo/logo.png');
		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', $textData);
		$storage->file_put_contents('foo.png', $imgData);
		$storage->file_put_contents('folder/bar.txt', $textData);
		$storage->file_put_contents('folder/bar2.txt', $textData);

		if ($scan) {
			$scanner = $storage->getScanner();
			$scanner->scan('');
		}
		$this->storages[] = $storage;
		return $storage;
	}
}
