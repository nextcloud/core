<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Storage\Wrapper;

//ensure the constants are loaded
use OC\Files\Cache\CacheEntry;
use OC\Files\Storage\Local;

\OC::$loader->load('\OC\Files\Filesystem');

/**
 * Class QuotaTest
 *
 * @group DB
 *
 * @package Test\Files\Storage\Wrapper
 */
class QuotaTest extends \Test\Files\Storage\Storage {
	/**
	 * @var string tmpDir
	 */
	private $tmpDir;

	protected function setUp(): void {
		parent::setUp();

		$this->tmpDir = \OC::$server->getTempManager()->getTemporaryFolder();
		$storage = new \OC\Files\Storage\Local(['datadir' => $this->tmpDir]);
		$this->instance = new \OC\Files\Storage\Wrapper\Quota(['storage' => $storage, 'quota' => 10000000]);
	}

	protected function tearDown(): void {
		\OC_Helper::rmdirr($this->tmpDir);
		parent::tearDown();
	}

	/**
	 * @param integer $limit
	 */
	protected function getLimitedStorage($limit) {
		$storage = new \OC\Files\Storage\Local(['datadir' => $this->tmpDir]);
		$storage->mkdir('files');
		$storage->getScanner()->scan('');
		return new \OC\Files\Storage\Wrapper\Quota(['storage' => $storage, 'quota' => $limit]);
	}

	public function testFilePutContentsNotEnoughSpace() {
		$instance = $this->getLimitedStorage(3);
		$this->assertFalse($instance->file_put_contents('files/foo', 'foobar'));
	}

	public function testCopyNotEnoughSpace() {
		$instance = $this->getLimitedStorage(9);
		$this->assertEquals(6, $instance->file_put_contents('files/foo', 'foobar'));
		$instance->getScanner()->scan('');
		$this->assertFalse($instance->copy('files/foo', 'files/bar'));
	}

	public function testFreeSpace() {
		$instance = $this->getLimitedStorage(9);
		$this->assertEquals(9, $instance->free_space(''));
	}

	public function testFreeSpaceWithUsedSpace() {
		$instance = $this->getLimitedStorage(9);
		$instance->getCache()->put(
			'', ['size' => 3]
		);
		$this->assertEquals(6, $instance->free_space(''));
	}

	public function testFreeSpaceWithUnknownDiskSpace() {
		$storage = $this->getMockBuilder(Local::class)
			->setMethods(['free_space'])
			->setConstructorArgs([['datadir' => $this->tmpDir]])
			->getMock();
		$storage->expects($this->any())
			->method('free_space')
			->willReturn(-2);
		$storage->getScanner()->scan('');

		$instance = new \OC\Files\Storage\Wrapper\Quota(['storage' => $storage, 'quota' => 9]);
		$instance->getCache()->put(
			'', ['size' => 3]
		);
		$this->assertEquals(6, $instance->free_space(''));
	}

	public function testFreeSpaceWithUsedSpaceAndEncryption() {
		$instance = $this->getLimitedStorage(9);
		$instance->getCache()->put(
			'', ['size' => 7]
		);
		$this->assertEquals(2, $instance->free_space(''));
	}

	public function testFWriteNotEnoughSpace() {
		$instance = $this->getLimitedStorage(9);
		$stream = $instance->fopen('files/foo', 'w+');
		$this->assertEquals(6, fwrite($stream, 'foobar'));
		$this->assertEquals(3, fwrite($stream, 'qwerty'));
		fclose($stream);
		$this->assertEquals('foobarqwe', $instance->file_get_contents('files/foo'));
	}

	public function testStreamCopyWithEnoughSpace() {
		$instance = $this->getLimitedStorage(16);
		$inputStream = fopen('data://text/plain,foobarqwerty', 'r');
		$outputStream = $instance->fopen('files/foo', 'w+');
		list($count, $result) = \OC_Helper::streamCopy($inputStream, $outputStream);
		$this->assertEquals(12, $count);
		$this->assertTrue($result);
		fclose($inputStream);
		fclose($outputStream);
	}

	public function testStreamCopyNotEnoughSpace() {
		$instance = $this->getLimitedStorage(9);
		$inputStream = fopen('data://text/plain,foobarqwerty', 'r');
		$outputStream = $instance->fopen('files/foo', 'w+');
		list($count, $result) = \OC_Helper::streamCopy($inputStream, $outputStream);
		$this->assertEquals(9, $count);
		$this->assertFalse($result);
		fclose($inputStream);
		fclose($outputStream);
	}

	public function testReturnFalseWhenFopenFailed() {
		$failStorage = $this->getMockBuilder(Local::class)
			->setMethods(['fopen'])
			->setConstructorArgs([['datadir' => $this->tmpDir]])
			->getMock();
		$failStorage->expects($this->any())
			->method('fopen')
			->willReturn(false);

		$instance = new \OC\Files\Storage\Wrapper\Quota(['storage' => $failStorage, 'quota' => 1000]);

		$this->assertFalse($instance->fopen('failedfopen', 'r'));
	}

	public function testReturnRegularStreamOnRead() {
		$instance = $this->getLimitedStorage(9);

		// create test file first
		$stream = $instance->fopen('files/foo', 'w+');
		fwrite($stream, 'blablacontent');
		fclose($stream);

		$stream = $instance->fopen('files/foo', 'r');
		$meta = stream_get_meta_data($stream);
		$this->assertEquals('plainfile', $meta['wrapper_type']);
		fclose($stream);

		$stream = $instance->fopen('files/foo', 'rb');
		$meta = stream_get_meta_data($stream);
		$this->assertEquals('plainfile', $meta['wrapper_type']);
		fclose($stream);
	}

	public function testReturnRegularStreamWhenOutsideFiles() {
		$instance = $this->getLimitedStorage(9);
		$instance->mkdir('files_other');

		// create test file first
		$stream = $instance->fopen('files_other/foo', 'w+');
		$meta = stream_get_meta_data($stream);
		$this->assertEquals('plainfile', $meta['wrapper_type']);
		fclose($stream);
	}

	public function testReturnQuotaStreamOnWrite() {
		$instance = $this->getLimitedStorage(9);
		$stream = $instance->fopen('files/foo', 'w+');
		$meta = stream_get_meta_data($stream);
		$expected_type = 'user-space';
		$this->assertEquals($expected_type, $meta['wrapper_type']);
		fclose($stream);
	}

	public function testSpaceRoot() {
		$storage = $this->getMockBuilder(Local::class)->disableOriginalConstructor()->getMock();
		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')->disableOriginalConstructor()->getMock();
		$storage->expects($this->once())
			->method('getCache')
			->willReturn($cache);
		$storage->expects($this->once())
			->method('free_space')
			->willReturn(2048);
		$cache->expects($this->once())
			->method('get')
			->with('files')
			->willReturn(new CacheEntry(['size' => 50]));

		$instance = new \OC\Files\Storage\Wrapper\Quota(['storage' => $storage, 'quota' => 1024, 'root' => 'files']);

		$this->assertEquals(1024 - 50, $instance->free_space(''));
	}

	public function testInstanceOfStorageWrapper() {
		$this->assertTrue($this->instance->instanceOfStorage('\OC\Files\Storage\Local'));
		$this->assertTrue($this->instance->instanceOfStorage('\OC\Files\Storage\Wrapper\Wrapper'));
		$this->assertTrue($this->instance->instanceOfStorage('\OC\Files\Storage\Wrapper\Quota'));
	}

	public function testNoMkdirQuotaZero() {
		$instance = $this->getLimitedStorage(0.0);
		$this->assertFalse($instance->mkdir('foobar'));
	}

	public function testNoTouchQuotaZero() {
		$instance = $this->getLimitedStorage(0.0);
		$this->assertFalse($instance->touch('foobar'));
	}
}
