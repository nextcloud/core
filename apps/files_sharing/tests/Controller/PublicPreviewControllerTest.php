<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_Sharing\Tests\Controller;

use OCA\Files_Sharing\Controller\PublicPreviewController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IPreview;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class PublicPreviewControllerTest extends TestCase {

	/** @var IPreview|\PHPUnit_Framework_MockObject_MockObject */
	private $previewManager;
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;
	/** @var ITimeFactory|MockObject */
	private $timeFactory;

	/** @var PublicPreviewController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->previewManager = $this->createMock(IPreview::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$this->timeFactory->method('getTime')
			->willReturn(1337);

		$this->overwriteService(ITimeFactory::class, $this->timeFactory);

		$this->controller = new PublicPreviewController(
			'files_sharing',
			$this->createMock(IRequest::class),
			$this->shareManager,
			$this->createMock(ISession::class),
			$this->previewManager
		);
	}

	public function testInvalidToken() {
		$res = $this->controller->getPreview('', 'file', 10, 10, '');
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidWidth() {
		$res = $this->controller->getPreview('token', 'file', 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidHeight() {
		$res = $this->controller->getPreview('token', 'file', 10, 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidShare() {
		$this->shareManager->method('getShareByToken')
			->with($this->equalTo('token'))
			->willThrowException(new ShareNotFound());

		$res = $this->controller->getPreview('token', 'file', 10, 10);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $res);
	}

	public function testShareNotAccessable() {
		$share = $this->createMock(IShare::class);
		$this->shareManager->method('getShareByToken')
			->with($this->equalTo('token'))
			->willReturn($share);

		$share->method('getPermissions')
			->willReturn(0);

		$res = $this->controller->getPreview('token', 'file', 10, 10);
		$expected = new DataResponse([], Http::STATUS_FORBIDDEN);

		$this->assertEquals($expected, $res);
	}

	public function testPreviewFile() {
		$share = $this->createMock(IShare::class);
		$this->shareManager->method('getShareByToken')
			->with($this->equalTo('token'))
			->willReturn($share);

		$share->method('getPermissions')
			->willReturn(Constants::PERMISSION_READ);

		$file = $this->createMock(File::class);
		$share->method('getNode')
			->willReturn($file);

		$preview = $this->createMock(ISimpleFile::class);
		$this->previewManager->method('getPreview')
			->with($this->equalTo($file), 10, 10, false)
			->willReturn($preview);

		$preview->method('getMimeType')
			->willReturn('myMime');

		$res = $this->controller->getPreview('token', 'file', 10, 10, true);
		$expected = new FileDisplayResponse($preview, Http::STATUS_OK, ['Content-Type' => 'myMime']);
		$expected->cacheFor(3600 * 24);
		$this->assertEquals($expected, $res);
	}

	public function testPreviewFolderInvalidFile() {
		$share = $this->createMock(IShare::class);
		$this->shareManager->method('getShareByToken')
			->with($this->equalTo('token'))
			->willReturn($share);

		$share->method('getPermissions')
			->willReturn(Constants::PERMISSION_READ);

		$folder = $this->createMock(Folder::class);
		$share->method('getNode')
			->willReturn($folder);

		$folder->method('get')
			->with($this->equalTo('file'))
			->willThrowException(new NotFoundException());

		$res = $this->controller->getPreview('token', 'file', 10, 10, true);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);
		$this->assertEquals($expected, $res);
	}


	public function testPreviewFolderValidFile() {
		$share = $this->createMock(IShare::class);
		$this->shareManager->method('getShareByToken')
			->with($this->equalTo('token'))
			->willReturn($share);

		$share->method('getPermissions')
			->willReturn(Constants::PERMISSION_READ);

		$folder = $this->createMock(Folder::class);
		$share->method('getNode')
			->willReturn($folder);

		$file = $this->createMock(File::class);
		$folder->method('get')
			->with($this->equalTo('file'))
			->willReturn($file);

		$preview = $this->createMock(ISimpleFile::class);
		$this->previewManager->method('getPreview')
			->with($this->equalTo($file), 10, 10, false)
			->willReturn($preview);

		$preview->method('getMimeType')
			->willReturn('myMime');

		$res = $this->controller->getPreview('token', 'file', 10, 10, true);
		$expected = new FileDisplayResponse($preview, Http::STATUS_OK, ['Content-Type' => 'myMime']);
		$expected->cacheFor(3600 * 24);
		$this->assertEquals($expected, $res);
	}
}
