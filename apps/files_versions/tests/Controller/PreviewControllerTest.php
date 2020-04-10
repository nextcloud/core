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

namespace OCA\Files_Versions\Tests\Controller;

use OCA\Files_Versions\Controller\PreviewController;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
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

	/** @var PreviewController|\PHPUnit_Framework_MockObject_MockObject */
	private $controller;

	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	private $userSession;

	/** @var IVersionManager|\PHPUnit_Framework_MockObject_MockObject */
	private $versionManager;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userId = 'user';
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn($this->userId);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->previewManager = $this->createMock(IPreview::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);
		$this->versionManager = $this->createMock(IVersionManager::class);

		$this->controller = new PreviewController(
			'files_versions',
			$this->createMock(IRequest::class),
			$this->rootFolder,
			$this->userSession,
			$this->mimeTypeDetector,
			$this->versionManager,
			$this->previewManager
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

	public function testInvalidVersion() {
		$res = $this->controller->getPreview('file', 10, 0);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $res);
	}

	public function testValidPreview() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->userId)
			->willReturn($userFolder);
		$userFolder->method('getParent')
			->willReturn($userRoot);

		$sourceFile = $this->createMock(File::class);
		$userFolder->method('get')
			->with('file')
			->willReturn($sourceFile);

		$file = $this->createMock(File::class);
		$file->method('getMimetype')
			->willReturn('myMime');

		$this->versionManager->method('getVersionFile')
			->willReturn($file);

		$preview = $this->createMock(ISimpleFile::class);
		$this->previewManager->method('getPreview')
			->with($this->equalTo($file), 10, 10, true, IPreview::MODE_FILL, 'myMime')
			->willReturn($preview);
		$preview->method('getMimeType')
			->willReturn('previewMime');

		$res = $this->controller->getPreview('file', 10, 10, '42');
		$expected = new FileDisplayResponse($preview, Http::STATUS_OK, ['Content-Type' => 'previewMime']);

		$this->assertEquals($expected, $res);
	}

	public function testVersionNotFound() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->userId)
			->willReturn($userFolder);
		$userFolder->method('getParent')
			->willReturn($userRoot);

		$sourceFile = $this->createMock(File::class);
		$userFolder->method('get')
			->with('file')
			->willReturn($sourceFile);

		$this->mimeTypeDetector->method('detectPath')
			->with($this->equalTo('file'))
			->willReturn('myMime');

		$this->versionManager->method('getVersionFile')
			->willThrowException(new NotFoundException());

		$res = $this->controller->getPreview('file', 10, 10, '42');
		$expected = new DataResponse([], Http::STATUS_NOT_FOUND);

		$this->assertEquals($expected, $res);
	}

}
