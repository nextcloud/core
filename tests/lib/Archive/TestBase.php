<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Archive;

abstract class TestBase extends \Test\TestCase {
	/**
	 * @var \OC\Archive\Archive
	 */
	protected $instance;

	/**
	 * get the existing test archive
	 * @return \OC\Archive\Archive
	 */
	abstract protected function getExisting();
	/**
	 * get a new archive for write testing
	 * @return \OC\Archive\Archive
	 */
	abstract protected function getNew();

	public function testGetFiles() {
		$this->instance = $this->getExisting();
		$allFiles = $this->instance->getFiles();
		$expected = ['lorem.txt','logo-wide.png','dir/', 'dir/lorem.txt'];
		$this->assertEquals(4, count($allFiles), 'only found '.count($allFiles).' out of 4 expected files');
		foreach ($expected as $file) {
			$this->assertContains($file, $allFiles, 'cant find '.  $file . ' in archive');
			$this->assertTrue($this->instance->fileExists($file), 'file '.$file.' does not exist in archive');
		}
		$this->assertFalse($this->instance->fileExists('non/existing/file'));

		$rootContent = $this->instance->getFolder('');
		$expected = ['lorem.txt','logo-wide.png', 'dir/'];
		$this->assertEquals(3, count($rootContent));
		foreach ($expected as $file) {
			$this->assertContains($file, $rootContent, 'cant find '.  $file . ' in archive');
		}

		$dirContent = $this->instance->getFolder('dir/');
		$expected = ['lorem.txt'];
		$this->assertEquals(1, count($dirContent));
		foreach ($expected as $file) {
			$this->assertContains($file, $dirContent, 'cant find '.  $file . ' in archive');
		}
	}

	public function testContent() {
		$this->instance = $this->getExisting();
		$dir = \OC::$SERVERROOT.'/tests/data';
		$textFile = $dir.'/lorem.txt';
		$this->assertEquals(file_get_contents($textFile), $this->instance->getFile('lorem.txt'));

		$tmpFile = \OC::$server->getTempManager()->getTemporaryFile('.txt');
		$this->instance->extractFile('lorem.txt', $tmpFile);
		$this->assertEquals(file_get_contents($textFile), file_get_contents($tmpFile));
	}

	public function testWrite() {
		$dir = \OC::$SERVERROOT.'/tests/data';
		$textFile = $dir.'/lorem.txt';
		$this->instance = $this->getNew();
		$this->assertEquals(0, count($this->instance->getFiles()));
		$this->instance->addFile('lorem.txt', $textFile);
		$this->assertEquals(1, count($this->instance->getFiles()));
		$this->assertTrue($this->instance->fileExists('lorem.txt'));
		$this->assertFalse($this->instance->fileExists('lorem.txt/'));

		$this->assertEquals(file_get_contents($textFile), $this->instance->getFile('lorem.txt'));
		$this->instance->addFile('lorem.txt', 'foobar');
		$this->assertEquals('foobar', $this->instance->getFile('lorem.txt'));
	}

	public function testReadStream() {
		$dir = \OC::$SERVERROOT.'/tests/data';
		$this->instance = $this->getExisting();
		$fh = $this->instance->getStream('lorem.txt', 'r');
		$this->assertTrue((bool)$fh);
		$content = fread($fh, $this->instance->filesize('lorem.txt'));
		fclose($fh);
		$this->assertEquals(file_get_contents($dir.'/lorem.txt'), $content);
	}
	public function testWriteStream() {
		$dir = \OC::$SERVERROOT.'/tests/data';
		$this->instance = $this->getNew();
		$fh = $this->instance->getStream('lorem.txt', 'w');
		$source = fopen($dir.'/lorem.txt', 'r');
		\OCP\Files::streamCopy($source, $fh);
		fclose($source);
		fclose($fh);
		$this->assertTrue($this->instance->fileExists('lorem.txt'));
		$this->assertEquals(file_get_contents($dir.'/lorem.txt'), $this->instance->getFile('lorem.txt'));
	}
	public function testFolder() {
		$this->instance = $this->getNew();
		$this->assertFalse($this->instance->fileExists('/test'));
		$this->assertFalse($this->instance->fileExists('/test/'));
		$this->instance->addFolder('/test');
		$this->assertTrue($this->instance->fileExists('/test'));
		$this->assertTrue($this->instance->fileExists('/test/'));
		$this->instance->remove('/test');
		$this->assertFalse($this->instance->fileExists('/test'));
		$this->assertFalse($this->instance->fileExists('/test/'));
	}
	public function testExtract() {
		$dir = \OC::$SERVERROOT.'/tests/data';
		$this->instance = $this->getExisting();
		$tmpDir = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->instance->extract($tmpDir);
		$this->assertEquals(true, file_exists($tmpDir.'lorem.txt'));
		$this->assertEquals(true, file_exists($tmpDir.'dir/lorem.txt'));
		$this->assertEquals(true, file_exists($tmpDir.'logo-wide.png'));
		$this->assertEquals(file_get_contents($dir.'/lorem.txt'), file_get_contents($tmpDir.'lorem.txt'));
		\OCP\Files::rmdirr($tmpDir);
	}
	public function testMoveRemove() {
		$dir = \OC::$SERVERROOT.'/tests/data';
		$textFile = $dir.'/lorem.txt';
		$this->instance = $this->getNew();
		$this->instance->addFile('lorem.txt', $textFile);
		$this->assertFalse($this->instance->fileExists('target.txt'));
		$this->instance->rename('lorem.txt', 'target.txt');
		$this->assertTrue($this->instance->fileExists('target.txt'));
		$this->assertFalse($this->instance->fileExists('lorem.txt'));
		$this->assertEquals(file_get_contents($textFile), $this->instance->getFile('target.txt'));
		$this->instance->remove('target.txt');
		$this->assertFalse($this->instance->fileExists('target.txt'));
	}
	public function testRecursive() {
		$dir = \OC::$SERVERROOT.'/tests/data';
		$this->instance = $this->getNew();
		$this->instance->addRecursive('/dir', $dir);
		$this->assertTrue($this->instance->fileExists('/dir/lorem.txt'));
		$this->assertTrue($this->instance->fileExists('/dir/data.zip'));
		$this->assertTrue($this->instance->fileExists('/dir/data.tar.gz'));
	}
}
