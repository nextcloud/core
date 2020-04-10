<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\File\SimpleFS;

use OC\Files\SimpleFS\SimpleFile;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;

class SimpleFileTest extends \Test\TestCase  {
	/** @var File|\PHPUnit_Framework_MockObject_MockObject */
	private $file;

	/** @var SimpleFile */
	private $simpleFile;

	protected function setUp(): void {
		parent::setUp();

		$this->file = $this->createMock(File::class);
		$this->simpleFile = new SimpleFile($this->file);
	}

	public function testGetName() {
		$this->file->expects($this->once())
			->method('getName')
			->willReturn('myname');

		$this->assertEquals('myname', $this->simpleFile->getName());
	}

	public function testGetSize() {
		$this->file->expects($this->once())
			->method('getSize')
			->willReturn(42);

		$this->assertEquals(42, $this->simpleFile->getSize());
	}

	public function testGetETag() {
		$this->file->expects($this->once())
			->method('getETag')
			->willReturn('etag');

		$this->assertEquals('etag', $this->simpleFile->getETag());
	}

	public function testGetMTime() {
		$this->file->expects($this->once())
			->method('getMTime')
			->willReturn(101);

		$this->assertEquals(101, $this->simpleFile->getMTime());
	}

	public function testGetContent() {
		$this->file->expects($this->once())
			->method('getContent')
			->willReturn('foo');

		$this->assertEquals('foo', $this->simpleFile->getContent());
	}

	public function testPutContent() {
		$this->file->expects($this->once())
			->method('putContent')
			->with($this->equalTo('bar'));

		$this->simpleFile->putContent('bar');
	}

	public function testDelete() {
		$this->file->expects($this->once())
			->method('delete');

		$this->simpleFile->delete();
	}

	public function testGetMimeType() {
		$this->file->expects($this->once())
			->method('getMimeType')
			->willReturn('app/awesome');

		$this->assertEquals('app/awesome', $this->simpleFile->getMimeType());
	}

	public function testGetContentInvalidAppData() {
		$this->file->method('getContent')
			->willReturn(false);
		$this->file->method('stat')->willReturn(false);

		$parent = $this->createMock(Folder::class);
		$parent->method('stat')->willReturn(false);

		$root = $this->createMock(Folder::class);
		$root->method('stat')->willReturn([]);

		$this->file->method('getParent')->willReturn($parent);
		$parent->method('getParent')->willReturn($root);

		$this->expectException(NotFoundException::class);

		$this->simpleFile->getContent();
	}

	public function testRead() {
		$this->file->expects($this->once())
			->method('fopen')
			->with('r');

		$this->simpleFile->read();
	}

	public function testWrite() {
		$this->file->expects($this->once())
			->method('fopen')
			->with('w');

		$this->simpleFile->write();
	}
}
