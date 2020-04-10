<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\unit\Upload;

use OCA\DAV\Connector\Sabre\Directory;

class FutureFileTest extends \Test\TestCase {

	public function testGetContentType() {
		$f = $this->mockFutureFile();
		$this->assertEquals('application/octet-stream', $f->getContentType());
	}

	public function testGetETag() {
		$f = $this->mockFutureFile();
		$this->assertEquals('1234567890', $f->getETag());
	}

	public function testGetName() {
		$f = $this->mockFutureFile();
		$this->assertEquals('foo.txt', $f->getName());
	}

	public function testGetLastModified() {
		$f = $this->mockFutureFile();
		$this->assertEquals(12121212, $f->getLastModified());
	}

	public function testGetSize() {
		$f = $this->mockFutureFile();
		$this->assertEquals(0, $f->getSize());
	}

	public function testGet() {
		$f = $this->mockFutureFile();
		$stream = $f->get();
		$this->assertTrue(is_resource($stream));
	}

	public function testDelete() {
		$d = $this->getMockBuilder(Directory::class)
			->disableOriginalConstructor()
			->setMethods(['delete'])
			->getMock();

		$d->expects($this->once())
			->method('delete');

		$f = new \OCA\DAV\Upload\FutureFile($d, 'foo.txt');
		$f->delete();
	}

	
	public function testPut() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$f = $this->mockFutureFile();
		$f->put('');
	}

	
	public function testSetName() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$f = $this->mockFutureFile();
		$f->setName('');
	}

	/**
	 * @return \OCA\DAV\Upload\FutureFile
	 */
	private function mockFutureFile() {
		$d = $this->getMockBuilder(Directory::class)
			->disableOriginalConstructor()
			->setMethods(['getETag', 'getLastModified', 'getChildren'])
			->getMock();

		$d->expects($this->any())
			->method('getETag')
			->willReturn('1234567890');

		$d->expects($this->any())
			->method('getLastModified')
			->willReturn(12121212);

		$d->expects($this->any())
			->method('getChildren')
			->willReturn([]);

		return new \OCA\DAV\Upload\FutureFile($d, 'foo.txt');
	}
}
