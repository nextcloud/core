<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Robin Appelman <robin@icewind.nl>
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

namespace OCA\files_versions\tests;

use OCA\Files_Versions\Expiration;
use OCA\Files_Versions\Hooks;
use OCA\Files_Versions\Storage;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * @group DB
 */
class StorageTest extends TestCase {
	use UserTrait;

	private $versionsRoot;
	private $userFolder;
	private $expireTimestamp = 10;

	protected function setUp(): void {
		parent::setUp();

		$expiration = $this->createMock(Expiration::class);
		$expiration->method('getMaxAgeAsTimestamp')
			->willReturnCallback(function () {
				return $this->expireTimestamp;
			});
		$this->overwriteService(Expiration::class, $expiration);

		Hooks::connectHooks();

		$this->createUser('version_test', '');
		$this->loginAsUser('version_test');
		/** @var IRootFolder $root */
		$root = \OC::$server->get(IRootFolder::class);
		$this->userFolder = $root->getUserFolder('version_test');
	}


	protected function createPastFile(string $path, int $mtime) {
		try {
			$file = $this->userFolder->get($path);
		} catch (NotFoundException $e) {
			$file = $this->userFolder->newFile($path);
		}
		$file->putContent((string)$mtime);
		$file->touch($mtime);
	}

	public function testExpireMaxAge() {
		$this->userFolder->newFolder('folder1');
		$this->userFolder->newFolder('folder1/sub1');
		$this->userFolder->newFolder('folder2');

		$this->createPastFile('file1', 100);
		$this->createPastFile('file1', 500);
		$this->createPastFile('file1', 900);

		$this->createPastFile('folder1/file2', 100);
		$this->createPastFile('folder1/file2', 200);
		$this->createPastFile('folder1/file2', 300);

		$this->createPastFile('folder1/sub1/file3', 400);
		$this->createPastFile('folder1/sub1/file3', 500);
		$this->createPastFile('folder1/sub1/file3', 600);

		$this->createPastFile('folder2/file4', 100);
		$this->createPastFile('folder2/file4', 600);
		$this->createPastFile('folder2/file4', 800);

		$this->assertCount(2, Storage::getVersions('version_test', 'file1'));
		$this->assertCount(2, Storage::getVersions('version_test', 'folder1/file2'));
		$this->assertCount(2, Storage::getVersions('version_test', 'folder1/sub1/file3'));
		$this->assertCount(2, Storage::getVersions('version_test', 'folder2/file4'));

		$this->expireTimestamp = 150;
		Storage::expireOlderThanMaxForUser('version_test');

		$this->assertCount(1, Storage::getVersions('version_test', 'file1'));
		$this->assertCount(1, Storage::getVersions('version_test', 'folder1/file2'));
		$this->assertCount(2, Storage::getVersions('version_test', 'folder1/sub1/file3'));
		$this->assertCount(1, Storage::getVersions('version_test', 'folder2/file4'));

		$this->expireTimestamp = 550;
		Storage::expireOlderThanMaxForUser('version_test');

		$this->assertCount(0, Storage::getVersions('version_test', 'file1'));
		$this->assertCount(0, Storage::getVersions('version_test', 'folder1/file2'));
		$this->assertCount(0, Storage::getVersions('version_test', 'folder1/sub1/file3'));
		$this->assertCount(1, Storage::getVersions('version_test', 'folder2/file4'));
	}
}
