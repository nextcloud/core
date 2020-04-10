<?php
/**
 * @copyright 2016, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace Test\Lockdown\Filesystem;

use Icewind\Streams\IteratorDirectory;
use OC\Files\FileInfo;
use OC\ForbiddenException;
use OC\Lockdown\Filesystem\NullCache;
use OC\Lockdown\Filesystem\NullStorage;
use OCP\Files\Storage;
use Test\TestCase;

class NullStorageTest extends TestCase  {

	/** @var NullStorage */
	private $storage;

	protected function setUp(): void {
		parent::setUp();

		$this->storage = new NullStorage([]);
	}

	public function testGetId() {
		$this->assertSame('null', $this->storage->getId());
	}

	public function testMkdir() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->mkdir('foo');
	}

	public function testRmdir() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->rmdir('foo');
	}

	public function testOpendir() {
		$this->assertInstanceOf(IteratorDirectory::class, $this->storage->opendir('foo'));
	}

	public function testIs_dir() {
		$this->assertTrue($this->storage->is_dir(''));
		$this->assertFalse($this->storage->is_dir('foo'));
	}

	public function testIs_file() {
		$this->assertFalse($this->storage->is_file('foo'));
	}

	public function testStat() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->stat('foo');
	}

	public function testFiletype() {
		$this->assertSame('dir', $this->storage->filetype(''));
		$this->assertFalse($this->storage->filetype('foo'));
	}

	public function testFilesize() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->filesize('foo');
	}

	public function testIsCreatable() {
		$this->assertFalse($this->storage->isCreatable('foo'));
	}

	public function testIsReadable() {
		$this->assertTrue($this->storage->isReadable(''));
		$this->assertFalse($this->storage->isReadable('foo'));
	}

	public function testIsUpdatable() {
		$this->assertFalse($this->storage->isUpdatable('foo'));
	}

	public function testIsDeletable() {
		$this->assertFalse($this->storage->isDeletable('foo'));
	}

	public function testIsSharable() {
		$this->assertFalse($this->storage->isSharable('foo'));
	}

	public function testGetPermissions() {
		$this->assertNull($this->storage->getPermissions('foo'));
	}

	public function testFile_exists() {
		$this->assertTrue($this->storage->file_exists(''));
		$this->assertFalse($this->storage->file_exists('foo'));
	}

	public function testFilemtime() {
		$this->assertFalse($this->storage->filemtime('foo'));
	}

	public function testFile_get_contents() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->file_get_contents('foo');
	}

	public function testFile_put_contents() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->file_put_contents('foo', 'bar');
	}

	public function testUnlink() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->unlink('foo');
	}

	public function testRename() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->rename('foo', 'bar');
	}

	public function testCopy() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->copy('foo', 'bar');
	}

	public function testFopen() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->fopen('foo', 'R');
	}

	public function testGetMimeType() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->getMimeType('foo');
	}

	public function testHash() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->hash('md5', 'foo', true);
	}

	public function testFree_space() {
		$this->assertSame(FileInfo::SPACE_UNKNOWN, $this->storage->free_space('foo'));
	}

	public function testTouch() {
		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->touch('foo');
	}

	public function testGetLocalFile() {
		$this->assertFalse($this->storage->getLocalFile('foo'));
	}

	public function testHasUpdated() {
		$this->assertFalse($this->storage->hasUpdated('foo', 42));
	}

	public function testGetETag() {
		$this->assertSame('', $this->storage->getETag('foo'));
	}

	public function testIsLocal() {
		$this->assertFalse($this->storage->isLocal());
	}

	public function testGetDirectDownload() {
		$this->assertFalse($this->storage->getDirectDownload('foo'));
	}

	public function testCopyFromStorage() {
		$sourceStorage = $this->createMock(Storage::class);

		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->copyFromStorage($sourceStorage, 'foo', 'bar');
	}

	public function testMoveFromStorage() {
		$sourceStorage = $this->createMock(Storage::class);

		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('This request is not allowed to access the filesystem');

		$this->storage->moveFromStorage($sourceStorage, 'foo', 'bar');
	}

	public function testTest() {
		$this->assertTrue($this->storage->test());
		return true;
	}

	public function testGetOwner() {
		$this->assertNull($this->storage->getOwner('foo'));
	}

	public function testGetCache() {
		$this->assertInstanceOf(NullCache::class, $this->storage->getCache('foo'));
	}
}
