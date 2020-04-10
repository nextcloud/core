<?php
/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Preview;

use OC\Files\AppData\Factory;
use OC\Preview\BackgroundCleanupJob;
use OC\PreviewManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use Test\Traits\MountProviderTrait;
use Test\Traits\UserTrait;

/**
 * Class BackgroundCleanupJobTest
 *
 * @group DB
 *
 * @package Test\Preview
 */
class BackgroundCleanupJobTest extends \Test\TestCase {

	use MountProviderTrait;
	use UserTrait;

	/** @var string */
	private $userId;

	/** @var bool */
	private $trashEnabled;

	/** @var Factory */
	private $appDataFactory;

	/** @var IDBConnection */
	private $connection;

	/** @var PreviewManager */
	private $previewManager;

	/** @var IRootFolder */
	private $rootFolder;

	protected function setUp(): void {
		parent::setUp();

		$this->userId = $this->getUniqueID();
		$this->createUser($this->userId, $this->userId);

		$storage = new \OC\Files\Storage\Temporary([]);
		$this->registerMount($this->userId, $storage, '');

		$this->loginAsUser($this->userId);
		$this->logout();
		$this->loginAsUser($this->userId);

		$appManager = \OC::$server->getAppManager();
		$this->trashEnabled = $appManager->isEnabledForUser('files_trashbin', $this->userId);
		$appManager->disableApp('files_trashbin');

		$this->appDataFactory = new Factory(
			\OC::$server->getRootFolder(),
			\OC::$server->getSystemConfig()
		);
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->previewManager = \OC::$server->getPreviewManager();
		$this->rootFolder = \OC::$server->getRootFolder();
	}

	protected function tearDown(): void {
		if ($this->trashEnabled) {
			$appManager = \OC::$server->getAppManager();
			$appManager->enableApp('files_trashbin');
		}

		$this->logout();

		parent::tearDown();
	}

	private function setup11Previews(): array {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);

		$files = [];
		for ($i = 0; $i < 11; $i++) {
			$file = $userFolder->newFile($i.'.txt');
			$file->putContent('hello world!');
			$this->previewManager->getPreview($file);
			$files[] = $file;
		}

		return $files;
	}

	public function testCleanupSystemCron() {
		$files = $this->setup11Previews();

		$preview = $this->appDataFactory->get('preview');

		$previews = $preview->getDirectoryListing();
		$this->assertCount(11, $previews);

		$job = new BackgroundCleanupJob($this->connection, $this->appDataFactory, true);
		$job->run([]);

		foreach ($files as $file) {
			$file->delete();
		}

		$this->assertCount(11, $previews);
		$job->run([]);

		$previews = $preview->getDirectoryListing();
		$this->assertCount(0, $previews);
	}

	public function testCleanupAjax() {
		$files = $this->setup11Previews();

		$preview = $this->appDataFactory->get('preview');

		$previews = $preview->getDirectoryListing();
		$this->assertCount(11, $previews);

		$job = new BackgroundCleanupJob($this->connection, $this->appDataFactory, false);
		$job->run([]);

		foreach ($files as $file) {
			$file->delete();
		}

		$this->assertCount(11, $previews);
		$job->run([]);

		$previews = $preview->getDirectoryListing();
		$this->assertCount(1, $previews);

		$job->run([]);

		$previews = $preview->getDirectoryListing();
		$this->assertCount(0, $previews);
	}
}
