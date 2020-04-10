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

use OC\Files\SimpleFS\SimpleFolder;
use OC\Files\Storage\Temporary;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use Test\Traits\MountProviderTrait;
use Test\Traits\UserTrait;

/**
 * @group DB
 */
class SimpleFolderTest extends \Test\TestCase {
	use MountProviderTrait;
	use UserTrait;

	/** @var Folder */
	private $folder;

	/** @var Folder */
	private $parentFolder;

	/** @var SimpleFolder */
	private $simpleFolder;

	private $storage;

	protected function setUp(): void {
		parent::setUp();

		$this->storage = new Temporary([]);
		$this->createUser('simple', 'simple');
		$this->registerMount('simple', $this->storage, '/simple/files');
		$this->loginAsUser('simple');

		$this->parentFolder = \OC::$server->getUserFolder('simple');

		$this->folder = $this->parentFolder->newFolder('test');
		$this->simpleFolder = new SimpleFolder($this->folder);
	}

	public function testGetName() {
		$this->assertEquals('test', $this->simpleFolder->getName());
	}

	public function testDelete() {
		$this->assertTrue($this->parentFolder->nodeExists('test'));
		$this->simpleFolder->delete();
		$this->assertFalse($this->parentFolder->nodeExists('test'));
	}

	public function testFileExists() {
		$this->folder->newFile('exists');

		$this->assertFalse($this->simpleFolder->fileExists('not-exists'));
		$this->assertTrue($this->simpleFolder->fileExists('exists'));
	}

	public function testGetFile() {
		$this->folder->newFile('exists');

		$result = $this->simpleFolder->getFile('exists');
		$this->assertInstanceOf(ISimpleFile::class, $result);

		$this->expectException(NotFoundException::class);
		$this->simpleFolder->getFile('not-exists');
	}

	public function testNewFile() {
		$result = $this->simpleFolder->newFile('file');
		$this->assertInstanceOf(ISimpleFile::class, $result);
		$this->assertFalse($this->folder->nodeExists('file'));
		$result->putContent('bar');

		$this->assertTrue($this->folder->nodeExists('file'));
		$this->assertEquals('bar', $result->getContent());
	}

	public function testGetDirectoryListing() {
		$this->folder->newFile('file1');
		$this->folder->newFile('file2');

		$result = $this->simpleFolder->getDirectoryListing();
		$this->assertCount(2, $result);
		$this->assertInstanceOf(ISimpleFile::class, $result[0]);
		$this->assertInstanceOf(ISimpleFile::class, $result[1]);
	}

}
