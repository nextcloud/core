<?php
/**
 *
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

use OCA\Files_Sharing\Controller\ShareInfoController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IRequest;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Test\TestCase;

class ShareInfoControllerTest extends TestCase {

	/** @var ShareInfoController */
	private $controller;

	/** @var ShareManager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;


	protected function setUp(): void {
		parent::setUp();

		$this->shareManager = $this->createMock(ShareManager::class);

		$this->controller = $this->getMockBuilder(ShareInfoController::class)
			->setConstructorArgs([
				'files_sharing',
				$this->createMock(IRequest::class),
				$this->shareManager
			])
			->setMethods(['addROWrapper'])
			->getMock();
	}

	public function testNoShare() {
		$this->shareManager->method('getShareByToken')
			->with('token')
			->willThrowException(new ShareNotFound());

		$expected = new JSONResponse([], Http::STATUS_NOT_FOUND);
		$this->assertEquals($expected, $this->controller->info('token'));
	}

	public function testWrongPassword() {
		$share = $this->createMock(IShare::class);
		$share->method('getPassword')
			->willReturn('sharePass');

		$this->shareManager->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->shareManager->method('checkPassword')
			->with($share, 'pass')
			->willReturn(false);

		$expected = new JSONResponse([], Http::STATUS_FORBIDDEN);
		$this->assertEquals($expected, $this->controller->info('token', 'pass'));
	}

	public function testNoReadPermissions() {
		$share = $this->createMock(IShare::class);
		$share->method('getPassword')
			->willReturn('sharePass');
		$share->method('getPermissions')
			->willReturn(Constants::PERMISSION_CREATE);

		$this->shareManager->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->shareManager->method('checkPassword')
			->with($share, 'pass')
			->willReturn(true);

		$expected = new JSONResponse([], Http::STATUS_FORBIDDEN);
		$this->assertEquals($expected, $this->controller->info('token', 'pass'));
	}

	private function prepareFile() {
		$file = $this->createMock(File::class);

		$file->method('getId')->willReturn(42);

		$parent = $this->createMock(Folder::class);
		$parent->method('getId')->willReturn(41);
		$file->method('getParent')->willReturn($parent);

		$file->method('getMTime')->willReturn(1337);
		$file->method('getName')->willReturn('file');
		$file->method('getPermissions')->willReturn(Constants::PERMISSION_READ);
		$file->method('getMimeType')->willReturn('mime/type');
		$file->method('getSize')->willReturn(1);
		$file->method('getType')->willReturn('file');
		$file->method('getEtag')->willReturn('etag');

		return $file;
	}

	public function testInfoFile() {
		$file = $this->prepareFile();

		$share = $this->createMock(IShare::class);
		$share->method('getPassword')
			->willReturn('sharePass');
		$share->method('getPermissions')
			->willReturn(Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE);
		$share->method('getNode')
			->willReturn($file);

		$this->shareManager->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->shareManager->method('checkPassword')
			->with($share, 'pass')
			->willReturn(true);

		$expected = new JSONResponse([
			'id' => 42,
			'parentId' => 41,
			'mtime' => 1337	,
			'name' => 'file',
			'permissions' => 1,
			'mimetype' => 'mime/type',
			'size' => 1,
			'type' => 'file',
			'etag' => 'etag',
		]);
		$this->assertEquals($expected, $this->controller->info('token', 'pass'));
	}

	public function testInfoFileRO() {
		$file = $this->prepareFile();

		$share = $this->createMock(IShare::class);
		$share->method('getPassword')
			->willReturn('sharePass');
		$share->method('getPermissions')
			->willReturn(Constants::PERMISSION_READ);
		$share->method('getNode')
			->willReturn($file);

		$this->shareManager->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->shareManager->method('checkPassword')
			->with($share, 'pass')
			->willReturn(true);

		$expected = new JSONResponse([
			'id' => 42,
			'parentId' => 41,
			'mtime' => 1337	,
			'name' => 'file',
			'permissions' => 1,
			'mimetype' => 'mime/type',
			'size' => 1,
			'type' => 'file',
			'etag' => 'etag',
		]);
		$this->assertEquals($expected, $this->controller->info('token', 'pass'));
	}

	private function prepareFolder() {
		$root = $this->createMock(Folder::class);

		$root->method('getId')->willReturn(42);

		$parent = $this->createMock(Folder::class);
		$parent->method('getId')->willReturn(41);
		$root->method('getParent')->willReturn($parent);

		$root->method('getMTime')->willReturn(1337);
		$root->method('getName')->willReturn('root');
		$root->method('getPermissions')->willReturn(Constants::PERMISSION_READ);
		$root->method('getMimeType')->willReturn('mime/type');
		$root->method('getSize')->willReturn(1);
		$root->method('getType')->willReturn('folder');
		$root->method('getEtag')->willReturn('etag');


		//Subfolder
		$sub = $this->createMock(Folder::class);

		$sub->method('getId')->willReturn(43);
		$sub->method('getParent')->willReturn($root);
		$sub->method('getMTime')->willReturn(1338);
		$sub->method('getName')->willReturn('sub');
		$sub->method('getPermissions')->willReturn(Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE);
		$sub->method('getMimeType')->willReturn('mime/type');
		$sub->method('getSize')->willReturn(2);
		$sub->method('getType')->willReturn('folder');
		$sub->method('getEtag')->willReturn('etag2');

		$root->method('getDirectoryListing')->willReturn([$sub]);

		//Subfile
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(88);
		$file->method('getParent')->willReturn($sub);
		$file->method('getMTime')->willReturn(1339);
		$file->method('getName')->willReturn('file');
		$file->method('getPermissions')->willReturn(Constants::PERMISSION_READ | Constants::PERMISSION_DELETE);
		$file->method('getMimeType')->willReturn('mime/type');
		$file->method('getSize')->willReturn(3);
		$file->method('getType')->willReturn('file');
		$file->method('getEtag')->willReturn('etag3');

		$sub->method('getDirectoryListing')->willReturn([$file]);

		return $root;
	}

	public function testInfoFolder() {
		$file = $this->prepareFolder();

		$share = $this->createMock(IShare::class);
		$share->method('getPassword')
			->willReturn('sharePass');
		$share->method('getPermissions')
			->willReturn(Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE);
		$share->method('getNode')
			->willReturn($file);

		$this->shareManager->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->shareManager->method('checkPassword')
			->with($share, 'pass')
			->willReturn(true);

		$expected = new JSONResponse([
			'id' => 42,
			'parentId' => 41,
			'mtime' => 1337,
			'name' => 'root',
			'permissions' => 1,
			'mimetype' => 'mime/type',
			'size' => 1,
			'type' => 'folder',
			'etag' => 'etag',
			'children' => [
				[
					'id' => 43,
					'parentId' => 42,
					'mtime' => 1338,
					'name' => 'sub',
					'permissions' => 3,
					'mimetype' => 'mime/type',
					'size' => 2,
					'type' => 'folder',
					'etag' => 'etag2',
					'children' => [
						[
							'id' => 88,
							'parentId' => 43,
							'mtime' => 1339,
							'name' => 'file',
							'permissions' => 1,
							'mimetype' => 'mime/type',
							'size' => 3,
							'type' => 'file',
							'etag' => 'etag3',
						]
					],
				]
			],
		]);
		$this->assertEquals($expected, $this->controller->info('token', 'pass'));
	}
}
