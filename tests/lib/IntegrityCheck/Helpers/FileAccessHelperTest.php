<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 *
 */

namespace Test\IntegrityCheck\Helpers;

use OC\IntegrityCheck\Helpers\FileAccessHelper;
use Test\TestCase;

class FileAccessHelperTest extends TestCase {
	/** @var FileAccessHelper */
	private $fileAccessHelper;

	protected function setUp(): void {
		parent::setUp();
		$this->fileAccessHelper = new FileAccessHelper();
	}

	public function testReadAndWrite() {
		$tempManager = \OC::$server->getTempManager();
		$filePath = $tempManager->getTemporaryFile();
		$data = 'SomeDataGeneratedByIntegrityCheck';

		$this->fileAccessHelper->file_put_contents($filePath, $data);
		$this->assertSame($data, $this->fileAccessHelper->file_get_contents($filePath));
	}

	
	public function testFile_put_contentsWithException() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Failed to write into /anabsolutelynotexistingfolder/on/the/system.txt');

		$this->fileAccessHelper->file_put_contents('/anabsolutelynotexistingfolder/on/the/system.txt', 'MyFiles');
	}

	public function testIs_writable() {
		$this->assertFalse($this->fileAccessHelper->is_writable('/anabsolutelynotexistingfolder/on/the/system.txt'));
		$this->assertTrue($this->fileAccessHelper->is_writable(\OC::$server->getTempManager()->getTemporaryFile('MyFile')));
	}

	
	public function testAssertDirectoryExistsWithException() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Directory /anabsolutelynotexistingfolder/on/the/system does not exist.');

		$this->fileAccessHelper->assertDirectoryExists('/anabsolutelynotexistingfolder/on/the/system');
	}

	public function testAssertDirectoryExists() {
		$this->fileAccessHelper->assertDirectoryExists(\OC::$server->getTempManager()->getTemporaryFolder('/testfolder/'));
		$this->addToAssertionCount(1);
	}
}
