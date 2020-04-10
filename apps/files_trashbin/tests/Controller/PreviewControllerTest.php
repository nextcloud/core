<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Trashbin\Tests\Controller;

use OCA\Files_Trashbin\Controller\PreviewController;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use Test\TestCase;

class PreviewControllerTest extends TestCase {
	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var string */
	private $userId;

	/** @var IMimeTypeDetector|\PHPUnit_Framework_MockObject_MockObject */
	private $mimeTypeDetector;

	/** @var IPreview|\PHPUnit_Framework_MockObject_MockObject */
	private $previewManager;

	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $time;

	/** @var PreviewController */
	private $controller;

	/** @var ITrashManager|\PHPUnit_Framework_MockObject_MockObject */
	private $trashManager;

	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	private $userSession;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userId = 'user';
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->previewManager = $this->createMock(IPreview::class);
		$this->time = $this->createMock(ITimeFactory::class);
		$this->trashManager = $this->createMock(ITrashManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn($this->userId);

		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);

		$this->controller = new PreviewController(
			'files_versions',
			$this->createMock(IRequest::class),
			$this->rootFolder,
			$this->trashManager,
			$this->userSession,
			$this->mimeTypeDetector,
			$this->previewManager,
			$this->time
		);
	}

	public function testInvalidWidth() {
		$res = $this->controller->getPreview(42, 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testInvalidHeight() {
		$res = $this->controller->getPreview(42, 10, 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testValidPreview() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);
		$trash = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->userId)
			->willReturn($userFolder);
		$userFolder->method('getParent')
			->willReturn($userRoot);
		$userRoot->method('get')
			->with('files_trashbin/files')
			->willReturn($trash);

		$this->mimeTypeDetector->method('detectPath')
			->with($this->equalTo('file'))
			->willReturn('myMime');

		$file = $this->createMock(File::class);
		$trash->method('getById')
			->with($this->equalTo(42))
			->willReturn([$file]);
		$file->method('getName')
			->willReturn('file.d1234');

		$file->method('getParent')
			->willReturn($trash);

		$this->trashManager->expects($this->any())
			->method('getTrashNodeById')
			->willReturn($file);

		$preview = $this->createMock(ISimpleFile::class);
		$this->previewManager->method('getPreview')
			->with($this->equalTo($file), 10, 10, true, IPreview::MODE_FILL, 'myMime')
			->willReturn($preview);
		$preview->method('getMimeType')
			->willReturn('previewMime');

		$this->time->method('getTime')
			->willReturn(1337);

		$this->overwriteService(ITimeFactory::class, $this->time);

		$res = $this->controller->getPreview(42, 10, 10);
		$expected = new FileDisplayResponse($preview, Http::STATUS_OK, ['Content-Type' => 'previewMime']);
		$expected->cacheFor(3600 * 24);

		$this->assertEquals($expected, $res);
	}

	public function testTrashFileNotFound() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);
		$trash = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->userId)
			->willReturn($userFolder);
		$userFolder->method('getParent')
			->willReturn($userRoot);
		$userRoot->method('get')
			->with('files_trashbin/files')
			->willReturn($trash);

		$trash->method('getById')
			->with($this->equalTo(42))
			->willReturn([]);

		$res = $this->controller->getPreview(42, 10, 10);
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $res);
	}

	public function testTrashFolder() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);
		$trash = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->userId)
			->willReturn($userFolder);
		$userFolder->method('getParent')
			->willReturn($userRoot);
		$userRoot->method('get')
			->with('files_trashbin/files')
			->willReturn($trash);

		$folder = $this->createMock(Folder::class);
		$this->trashManager->expects($this->any())
			->method('getTrashNodeById')
			->willReturn($folder);
		$trash->method('getById')
			->with($this->equalTo(43))
			->willReturn([$folder]);

		$res = $this->controller->getPreview(43, 10, 10);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}
}
