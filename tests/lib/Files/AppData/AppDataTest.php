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

namespace Test\Files\AppData;

use OC\Files\AppData\AppData;
use OC\SystemConfig;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\SimpleFS\ISimpleFolder;

class AppDataTest extends \Test\TestCase {
	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var SystemConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $systemConfig;

	/** @var IAppData */
	private $appData;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->systemConfig = $this->createMock(SystemConfig::class);
		$this->appData = new AppData($this->rootFolder, $this->systemConfig, 'myApp');

		$this->systemConfig->expects($this->any())
			->method('getValue')
			->with('instanceid', null)
			->willReturn('iid');
	}

	private function setupAppFolder() {
		$appFolder = $this->createMock(Folder::class);

		$this->rootFolder->expects($this->any())
			->method('get')
			->with($this->equalTo('appdata_iid/myApp'))
			->willReturn($appFolder);

		return $appFolder;
	}

	public function testGetFolder() {
		$folder = $this->createMock(Folder::class);

		$this->rootFolder->expects($this->once())
			->method('get')
			->with($this->equalTo('appdata_iid/myApp/folder'))
			->willReturn($folder);

		$result = $this->appData->getFolder('folder');
		$this->assertInstanceOf(ISimpleFolder::class, $result);
	}

	public function testNewFolder() {
		$appFolder = $this->setupAppFolder();

		$folder = $this->createMock(Folder::class);

		$appFolder->expects($this->once())
			->method('newFolder')
			->with($this->equalTo('folder'))
			->willReturn($folder);

		$result = $this->appData->newFolder('folder');
		$this->assertInstanceOf(ISimpleFolder::class, $result);
	}

	public function testGetDirectoryListing() {
		$appFolder = $this->setupAppFolder();

		$file = $this->createMock(File::class);
		$folder = $this->createMock(Folder::class);
		$node = $this->createMock(Node::class);

		$appFolder->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$file, $folder, $node]);

		$result = $this->appData->getDirectoryListing();

		$this->assertCount(1, $result);
		$this->assertInstanceOf(ISimpleFolder::class, $result[0]);
	}

}
