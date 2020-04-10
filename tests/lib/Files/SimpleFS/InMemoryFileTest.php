<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Michael Weimann <mail@michael-weimann.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace Test\File\SimpleFS;

use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\InMemoryFile;
use Test\TestCase;

/**
 * This class provide test casesf or the InMemoryFile.
 *
 * @package Test\File\SimpleFS
 */
class InMemoryFileTest extends TestCase {
	/**
	 * Holds a pdf file with know attributes for tests.
	 *
	 * @var InMemoryFile
	 */
	private $testPdf;

	/**
	 * Sets the test file from "./resources/test.pdf".
	 *
	 * @before
	 * @return void
	 */
	public function setupTestPdf() {
		$fileContents = file_get_contents(
			__DIR__ . '/../../../data/test.pdf'
		);
		$this->testPdf = new InMemoryFile('test.pdf', $fileContents);
	}

	/**
	 * Asserts that putContent replaces the file contents.
	 *
	 * @return void
	 */
	public function testPutContent() {
		$this->testPdf->putContent('test');
		self::assertEquals('test', $this->testPdf->getContent());
	}

	/**
	 * Asserts that delete() doesn't rise an exception.
	 *
	 * @return void
	 */
	public function testDelete() {
		$this->testPdf->delete();
		// assert true, otherwise phpunit complains about not doing any assert
		self::assertTrue(true);
	}

	/**
	 * Asserts that getName returns the name passed on file creation.
	 *
	 * @return void
	 */
	public function testGetName() {
		self::assertEquals('test.pdf', $this->testPdf->getName());
	}

	/**
	 * Asserts that the file size is the size of the test file.
	 *
	 * @return void
	 */
	public function testGetSize() {
		self::assertEquals(7083, $this->testPdf->getSize());
	}

	/**
	 * Asserts the file contents are the same than the original file contents.
	 *
	 * @return void
	 */
	public function testGetContent() {
		self::assertEquals(
			file_get_contents(__DIR__ . '/../../../data/test.pdf'),
			$this->testPdf->getContent()
		);
	}

	/**
	 * Asserts the test file modification time is an integer.
	 *
	 * @return void
	 */
	public function testGetMTime() {
		self::assertTrue(is_int($this->testPdf->getMTime()));
	}

	/**
	 * Asserts the test file mime type is "application/json".
	 *
	 * @return void
	 */
	public function testGetMimeType() {
		self::assertEquals('application/pdf', $this->testPdf->getMimeType());
	}


	/**
	 * Asserts that read() raises an NotPermittedException.
	 *
	 * @return void
	 */
	public function testRead() {
		self::expectException(NotPermittedException::class);
		$this->testPdf->read();
	}

	/**
	 * Asserts that write() raises an NotPermittedException.
	 *
	 * @return void
	 */
	public function testWrite() {
		self::expectException(NotPermittedException::class);
		$this->testPdf->write();
	}
}
