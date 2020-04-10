<?php
/**
 * ownCloud
 *
 * @author Joas Schilling
 * @copyright 2015 Joas Schilling nickvergessen@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\ObjectStore;

class NoopScannerTest extends \Test\TestCase {
	/** @var \OC\Files\Storage\Storage $storage */
	private $storage;

	/** @var \OC\Files\ObjectStore\NoopScanner $scanner */
	private $scanner;

	protected function setUp(): void {
		parent::setUp();

		$this->storage = new \OC\Files\Storage\Temporary([]);
		$this->scanner = new \OC\Files\ObjectStore\NoopScanner($this->storage);
	}

	function testFile() {
		$data = "dummy file data\n";
		$this->storage->file_put_contents('foo.txt', $data);

		$this->assertEquals(
			[],
			$this->scanner->scanFile('foo.txt'),
			'Asserting that no error occurred while scanFile()'
		);
	}

	private function fillTestFolders() {
		$textData = "dummy file data\n";
		$imgData = file_get_contents(\OC::$SERVERROOT . '/core/img/logo/logo.png');
		$this->storage->mkdir('folder');
		$this->storage->file_put_contents('foo.txt', $textData);
		$this->storage->file_put_contents('foo.png', $imgData);
		$this->storage->file_put_contents('folder/bar.txt', $textData);
	}

	function testFolder() {
		$this->fillTestFolders();

		$this->assertEquals(
			[],
			$this->scanner->scan(''),
			'Asserting that no error occurred while scan()'
		);
	}

	function testBackgroundScan() {
		$this->fillTestFolders();
		$this->storage->mkdir('folder2');
		$this->storage->file_put_contents('folder2/bar.txt', 'foobar');

		$this->assertEquals(
			[],
			$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW),
			'Asserting that no error occurred while scan(SCAN_SHALLOW)'
		);

		$this->scanner->backgroundScan();

		$this->assertTrue(
			true,
			'Asserting that no error occurred while backgroundScan()'
		);
	}
}
