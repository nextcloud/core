<?php
/**
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Storage\Wrapper;

class EncodingTest extends \Test\Files\Storage\Storage {

	const NFD_NAME = 'ümlaut';
	const NFC_NAME = 'ümlaut';

	/**
	 * @var \OC\Files\Storage\Temporary
	 */
	private $sourceStorage;

	protected function setUp(): void {
		parent::setUp();
		$this->sourceStorage = new \OC\Files\Storage\Temporary([]);
		$this->instance = new \OC\Files\Storage\Wrapper\Encoding([
			'storage' => $this->sourceStorage
		]);
	}

	protected function tearDown(): void {
		$this->sourceStorage->cleanUp();
		parent::tearDown();
	}

	public function directoryProvider() {
		$a = parent::directoryProvider();
		$a[] = [self::NFD_NAME];
		return $a;
	}

	public function fileNameProvider() {
		$a = parent::fileNameProvider();
		$a[] = [self::NFD_NAME . '.txt'];
		return $a;
	}

	public function copyAndMoveProvider() {
		$a = parent::copyAndMoveProvider();
		$a[] = [self::NFD_NAME . '.txt', self::NFC_NAME . '-renamed.txt'];
		return $a;
	}

	public function accessNameProvider() {
		return [
			[self::NFD_NAME],
			[self::NFC_NAME],
		];
	}

	/**
	 * @dataProvider accessNameProvider
	 */
	public function testFputEncoding($accessName) {
		$this->sourceStorage->file_put_contents(self::NFD_NAME, 'bar');
		$this->assertEquals('bar', $this->instance->file_get_contents($accessName));
	}

	/**
	 * @dataProvider accessNameProvider
	 */
	public function testFopenReadEncoding($accessName) {
		$this->sourceStorage->file_put_contents(self::NFD_NAME, 'bar');
		$fh = $this->instance->fopen($accessName, 'r');
		$data = fgets($fh);
		fclose($fh);
		$this->assertEquals('bar', $data);
	}

	/**
	 * @dataProvider accessNameProvider
	 */
	public function testFopenOverwriteEncoding($accessName) {
		$this->sourceStorage->file_put_contents(self::NFD_NAME, 'bar');
		$fh = $this->instance->fopen($accessName, 'w');
		$data = fputs($fh, 'test');
		fclose($fh);
		$data = $this->sourceStorage->file_get_contents(self::NFD_NAME);
		$this->assertEquals('test', $data);
		$this->assertFalse($this->sourceStorage->file_exists(self::NFC_NAME));
	}

	/**
	 * @dataProvider accessNameProvider
	 */
	public function testFileExistsEncoding($accessName) {
		$this->sourceStorage->file_put_contents(self::NFD_NAME, 'bar');
		$this->assertTrue($this->instance->file_exists($accessName));
	}

	/**
	 * @dataProvider accessNameProvider
	 */
	public function testUnlinkEncoding($accessName) {
		$this->sourceStorage->file_put_contents(self::NFD_NAME, 'bar');
		$this->assertTrue($this->instance->unlink($accessName));
		$this->assertFalse($this->sourceStorage->file_exists(self::NFC_NAME));
		$this->assertFalse($this->sourceStorage->file_exists(self::NFD_NAME));
	}

	public function testNfcHigherPriority() {
		$this->sourceStorage->file_put_contents(self::NFC_NAME, 'nfc');
		$this->sourceStorage->file_put_contents(self::NFD_NAME, 'nfd');
		$this->assertEquals('nfc', $this->instance->file_get_contents(self::NFC_NAME));
	}

	public function encodedDirectoriesProvider() {
		return [
			[self::NFD_NAME, self::NFC_NAME],
			[self::NFD_NAME . '/' . self::NFD_NAME, self::NFC_NAME . '/' . self::NFC_NAME],
			[self::NFD_NAME . '/' . self::NFC_NAME . '/' .self::NFD_NAME, self::NFC_NAME . '/' . self::NFC_NAME . '/' . self::NFC_NAME],
		];
	}

	/**
	 * @dataProvider encodedDirectoriesProvider
	 */
	public function testOperationInsideDirectory($sourceDir, $accessDir) {
		$this->sourceStorage->mkdir($sourceDir);
		$this->instance->file_put_contents($accessDir . '/test.txt', 'bar');
		$this->assertTrue($this->instance->file_exists($accessDir . '/test.txt'));
		$this->assertEquals('bar', $this->instance->file_get_contents($accessDir . '/test.txt'));

		$this->sourceStorage->file_put_contents($sourceDir . '/' . self::NFD_NAME, 'foo');
		$this->assertTrue($this->instance->file_exists($accessDir . '/' . self::NFC_NAME));
		$this->assertEquals('foo', $this->instance->file_get_contents($accessDir . '/' . self::NFC_NAME));

		// try again to make it use cached path
		$this->assertEquals('bar', $this->instance->file_get_contents($accessDir . '/test.txt'));
		$this->assertTrue($this->instance->file_exists($accessDir . '/test.txt'));
		$this->assertEquals('foo', $this->instance->file_get_contents($accessDir . '/' . self::NFC_NAME));
		$this->assertTrue($this->instance->file_exists($accessDir . '/' . self::NFC_NAME));
	}

	public function testCacheExtraSlash() {
		$this->sourceStorage->file_put_contents(self::NFD_NAME, 'foo');
		$this->assertEquals(3, $this->instance->file_put_contents(self::NFC_NAME, 'bar'));
		$this->assertEquals('bar', $this->instance->file_get_contents(self::NFC_NAME));
		clearstatcache();
		$this->assertEquals(5, $this->instance->file_put_contents('/' . self::NFC_NAME, 'baric'));
		$this->assertEquals('baric', $this->instance->file_get_contents(self::NFC_NAME));
		clearstatcache();
		$this->assertEquals(8, $this->instance->file_put_contents('/' . self::NFC_NAME, 'barbaric'));
		$this->assertEquals('barbaric', $this->instance->file_get_contents('//' . self::NFC_NAME));
	}

	public function sourceAndTargetDirectoryProvider() {
		return [
			[self::NFC_NAME . '1', self::NFC_NAME . '2'],
			[self::NFD_NAME . '1', self::NFC_NAME . '2'],
			[self::NFC_NAME . '1', self::NFD_NAME . '2'],
			[self::NFD_NAME . '1', self::NFD_NAME . '2'],
		];
	}

	/**
	 * @dataProvider sourceAndTargetDirectoryProvider
	 */
	public function testCopyAndMoveEncodedFolder($sourceDir, $targetDir) {
		$this->sourceStorage->mkdir($sourceDir);
		$this->sourceStorage->mkdir($targetDir);
		$this->sourceStorage->file_put_contents($sourceDir . '/test.txt', 'bar');
		$this->assertTrue($this->instance->copy(self::NFC_NAME . '1/test.txt', self::NFC_NAME . '2/test.txt'));

		$this->assertTrue($this->instance->file_exists(self::NFC_NAME . '1/test.txt'));
		$this->assertTrue($this->instance->file_exists(self::NFC_NAME . '2/test.txt'));
		$this->assertEquals('bar', $this->instance->file_get_contents(self::NFC_NAME . '2/test.txt'));

		$this->assertTrue($this->instance->rename(self::NFC_NAME . '1/test.txt', self::NFC_NAME . '2/test2.txt'));
		$this->assertFalse($this->instance->file_exists(self::NFC_NAME . '1/test.txt'));
		$this->assertTrue($this->instance->file_exists(self::NFC_NAME . '2/test2.txt'));

		$this->assertEquals('bar', $this->instance->file_get_contents(self::NFC_NAME . '2/test2.txt'));
	}

	/**
	 * @dataProvider sourceAndTargetDirectoryProvider
	 */
	public function testCopyAndMoveFromStorageEncodedFolder($sourceDir, $targetDir) {
		$this->sourceStorage->mkdir($sourceDir);
		$this->sourceStorage->mkdir($targetDir);
		$this->sourceStorage->file_put_contents($sourceDir . '/test.txt', 'bar');
		$this->assertTrue($this->instance->copyFromStorage($this->instance, self::NFC_NAME . '1/test.txt', self::NFC_NAME . '2/test.txt'));

		$this->assertTrue($this->instance->file_exists(self::NFC_NAME . '1/test.txt'));
		$this->assertTrue($this->instance->file_exists(self::NFC_NAME . '2/test.txt'));
		$this->assertEquals('bar', $this->instance->file_get_contents(self::NFC_NAME . '2/test.txt'));

		$this->assertTrue($this->instance->moveFromStorage($this->instance, self::NFC_NAME . '1/test.txt', self::NFC_NAME . '2/test2.txt'));
		$this->assertFalse($this->instance->file_exists(self::NFC_NAME . '1/test.txt'));
		$this->assertTrue($this->instance->file_exists(self::NFC_NAME . '2/test2.txt'));

		$this->assertEquals('bar', $this->instance->file_get_contents(self::NFC_NAME . '2/test2.txt'));
	}
}
