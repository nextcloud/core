<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Nina Pypchenko <22447785+nina-py@users.noreply.github.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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
namespace OCA\Files\Controller;

use OCA\Files\Service\TagService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\StorageNotAvailableException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IManager;
use Test\TestCase;

/**
 * Class ApiController
 *
 * @package OCA\Files\Controller
 */
class ApiControllerTest extends TestCase {
	/** @var string */
	private $appName = 'files';
	/** @var \OCP\IUser */
	private $user;
	/** @var IRequest */
	private $request;
	/** @var TagService */
	private $tagService;
	/** @var IPreview|\PHPUnit\Framework\MockObject\MockObject */
	private $preview;
	/** @var ApiController */
	private $apiController;
	/** @var \OCP\Share\IManager */
	private $shareManager;
	/** @var \OCP\IConfig */
	private $config;
	/** @var Folder|\PHPUnit\Framework\MockObject\MockObject */
	private $userFolder;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();
		$this->user = $this->createMock(IUser::class);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('user1');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->any())
			->method('getUser')
			->willReturn($this->user);
		$this->tagService = $this->getMockBuilder(TagService::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager = $this->getMockBuilder(IManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->preview = $this->getMockBuilder(IPreview::class)
			->disableOriginalConstructor()
			->getMock();
		$this->config = $this->createMock(IConfig::class);
		$this->userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();

		$this->apiController = new ApiController(
			$this->appName,
			$this->request,
			$userSession,
			$this->tagService,
			$this->preview,
			$this->shareManager,
			$this->config,
			$this->userFolder
		);
	}

	public function testUpdateFileTagsEmpty() {
		$expected = new DataResponse([]);
		$this->assertEquals($expected, $this->apiController->updateFileTags('/path.txt'));
	}

	public function testUpdateFileTagsWorking() {
		$this->tagService->expects($this->once())
			->method('updateFileTags')
			->with('/path.txt', ['Tag1', 'Tag2']);

		$expected = new DataResponse([
			'tags' => [
				'Tag1',
				'Tag2'
			],
		]);
		$this->assertEquals($expected, $this->apiController->updateFileTags('/path.txt', ['Tag1', 'Tag2']));
	}

	public function testUpdateFileTagsNotFoundException() {
		$this->tagService->expects($this->once())
			->method('updateFileTags')
			->with('/path.txt', ['Tag1', 'Tag2'])
			->will($this->throwException(new NotFoundException('My error message')));

		$expected = new DataResponse(['message' => 'My error message'], Http::STATUS_NOT_FOUND);
		$this->assertEquals($expected, $this->apiController->updateFileTags('/path.txt', ['Tag1', 'Tag2']));
	}

	public function testUpdateFileTagsStorageNotAvailableException() {
		$this->tagService->expects($this->once())
			->method('updateFileTags')
			->with('/path.txt', ['Tag1', 'Tag2'])
			->will($this->throwException(new StorageNotAvailableException('My error message')));

		$expected = new DataResponse(['message' => 'My error message'], Http::STATUS_SERVICE_UNAVAILABLE);
		$this->assertEquals($expected, $this->apiController->updateFileTags('/path.txt', ['Tag1', 'Tag2']));
	}

	public function testUpdateFileTagsStorageGenericException() {
		$this->tagService->expects($this->once())
			->method('updateFileTags')
			->with('/path.txt', ['Tag1', 'Tag2'])
			->will($this->throwException(new \Exception('My error message')));

		$expected = new DataResponse(['message' => 'My error message'], Http::STATUS_NOT_FOUND);
		$this->assertEquals($expected, $this->apiController->updateFileTags('/path.txt', ['Tag1', 'Tag2']));
	}

	public function testGetThumbnailInvalidSize() {
		$this->userFolder->method('get')
			->with($this->equalTo(''))
			->willThrowException(new NotFoundException());
		$expected = new DataResponse(['message' => 'Requested size must be numeric and a positive value.'], Http::STATUS_BAD_REQUEST);
		$this->assertEquals($expected, $this->apiController->getThumbnail(0, 0, ''));
	}

	public function testGetThumbnailInvaidImage() {
		$file = $this->createMock(File::class);
		$this->userFolder->method('get')
			->with($this->equalTo('unknown.jpg'))
			->willReturn($file);
		$this->preview->expects($this->once())
			->method('getPreview')
			->with($file, 10, 10, true)
			->willThrowException(new NotFoundException());
		$expected = new DataResponse(['message' => 'File not found.'], Http::STATUS_NOT_FOUND);
		$this->assertEquals($expected, $this->apiController->getThumbnail(10, 10, 'unknown.jpg'));
	}

	public function testGetThumbnail() {
		$file = $this->createMock(File::class);
		$this->userFolder->method('get')
			->with($this->equalTo('known.jpg'))
			->willReturn($file);
		$preview = $this->createMock(ISimpleFile::class);
		$this->preview->expects($this->once())
			->method('getPreview')
			->with($this->equalTo($file), 10, 10, true)
			->willReturn($preview);

		$ret = $this->apiController->getThumbnail(10, 10, 'known.jpg');

		$this->assertEquals(Http::STATUS_OK, $ret->getStatus());
		$this->assertInstanceOf(Http\FileDisplayResponse::class, $ret);
	}

	public function testUpdateFileSorting() {
		$mode = 'mtime';
		$direction = 'desc';

		$this->config->expects($this->at(0))
			->method('setUserValue')
			->with($this->user->getUID(), 'files', 'file_sorting', $mode);
		$this->config->expects($this->at(1))
			->method('setUserValue')
			->with($this->user->getUID(), 'files', 'file_sorting_direction', $direction);

		$expected = new HTTP\Response();
		$actual = $this->apiController->updateFileSorting($mode, $direction);
		$this->assertEquals($expected, $actual);
	}

	public function invalidSortingModeData() {
		return [
			['color', 'asc'],
			['name', 'size'],
			['foo', 'bar']
		];
	}

	/**
	 * @dataProvider invalidSortingModeData
	 */
	public function testUpdateInvalidFileSorting($mode, $direction) {
		$this->config->expects($this->never())
			->method('setUserValue');

		$expected = new Http\Response(null);
		$expected->setStatus(Http::STATUS_UNPROCESSABLE_ENTITY);

		$result = $this->apiController->updateFileSorting($mode, $direction);

		$this->assertEquals($expected, $result);
	}

	public function testShowHiddenFiles() {
		$show = false;

		$this->config->expects($this->once())
			->method('setUserValue')
			->with($this->user->getUID(), 'files', 'show_hidden', '0');

		$expected = new Http\Response();
		$actual = $this->apiController->showHiddenFiles($show);

		$this->assertEquals($expected, $actual);
	}

	public function testCropImagePreviews() {
		$crop = true;

		$this->config->expects($this->once())
			->method('setUserValue')
			->with($this->user->getUID(), 'files', 'crop_image_previews', '1');

		$expected = new Http\Response();
		$actual = $this->apiController->cropImagePreviews($crop);

		$this->assertEquals($expected, $actual);
	}
}
