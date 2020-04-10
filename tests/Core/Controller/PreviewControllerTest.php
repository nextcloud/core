<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace Tests\Core\Controller;

use OC\Core\Controller\PreviewController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IPreview;
use OCP\IRequest;

class PreviewControllerTest extends \Test\TestCase {

	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var string */
	private $userId;

	/** @var IPreview|\PHPUnit_Framework_MockObject_MockObject */
	private $previewManager;

	/** @var PreviewController|\PHPUnit_Framework_MockObject_MockObject */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userId = 'user';
		$this->previewManager = $this->createMock(IPreview::class);

		$this->controller = new PreviewController(
			'core',
			$this->createMock(IRequest::class),
			$this->previewManager,
			$this->rootFolder,
			$this->userId,
			$this->createMock(ITimeFactory::class)
		);
	}

	public function testInvalidFile() {
		$res = $this->controller->getPreview('');
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidWidth() {
		$res = $this->controller->getPreview('file', 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidHeight() {
		$res = $this->controller->getPreview('file', 10, 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testFileNotFound() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo($this->userId))
			->willReturn($userFolder);

		$userFolder->method('get')
			->with($this->equalTo('file'))
			->willThrowException(new NotFoundException());

		$res = $this->controller->getPreview('file');
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $res);
	}

	public function testNotAFile() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo($this->userId))
			->willReturn($userFolder);

		$folder = $this->createMock(Folder::class);
		$userFolder->method('get')
			->with($this->equalTo('file'))
			->willReturn($folder);

		$res = $this->controller->getPreview('file');
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $res);
	}

	public function testNoPreviewAndNoIcon() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo($this->userId))
			->willReturn($userFolder);

		$file = $this->createMock(File::class);
		$userFolder->method('get')
			->with($this->equalTo('file'))
			->willReturn($file);

		$this->previewManager->method('isAvailable')
			->with($this->equalTo($file))
			->willReturn(false);

		$res = $this->controller->getPreview('file', 10, 10, true, false);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $res);
	}

	public function testForbiddenFile() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo($this->userId))
			->willReturn($userFolder);

		$file = $this->createMock(File::class);
		$userFolder->method('get')
			->with($this->equalTo('file'))
			->willReturn($file);

		$this->previewManager->method('isAvailable')
			->with($this->equalTo($file))
			->willReturn(true);

		$file->method('isReadable')
			->willReturn(false);

		$res = $this->controller->getPreview('file', 10, 10, true, true);
		$expected = new DataResponse([], Http::STATUS_FORBIDDEN);

		$this->assertEquals($expected, $res);
	}

	public function testNoPreview() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo($this->userId))
			->willReturn($userFolder);

		$file = $this->createMock(File::class);
		$userFolder->method('get')
			->with($this->equalTo('file'))
			->willReturn($file);

		$this->previewManager->method('isAvailable')
			->with($this->equalTo($file))
			->willReturn(true);

		$file->method('isReadable')
			->willReturn(true);

		$this->previewManager->method('getPreview')
			->with($this->equalTo($file), 10, 10, false, $this->equalTo('myMode'))
			->willThrowException(new NotFoundException());

		$res = $this->controller->getPreview('file', 10, 10, true, true, 'myMode');
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $res);
	}

	public function testValidPreview() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo($this->userId))
			->willReturn($userFolder);

		$file = $this->createMock(File::class);
		$userFolder->method('get')
			->with($this->equalTo('file'))
			->willReturn($file);

		$this->previewManager->method('isAvailable')
			->with($this->equalTo($file))
			->willReturn(true);

		$file->method('isReadable')
			->willReturn(true);

		$preview = $this->createMock(ISimpleFile::class);
		$this->previewManager->method('getPreview')
			->with($this->equalTo($file), 10, 10, false, $this->equalTo('myMode'))
			->willReturn($preview);
		$preview->method('getMimeType')
			->willReturn('myMime');

		$res = $this->controller->getPreview('file', 10, 10, true, true, 'myMode');

		$this->assertEquals('myMime', $res->getHeaders()['Content-Type']);
		$this->assertEquals(Http::STATUS_OK, $res->getStatus());
		$this->assertEquals($preview, $this->invokePrivate($res, 'file'));
	}
}
