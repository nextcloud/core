<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Files_Sharing\Tests;

use OCA\Files_Sharing\DeleteOrphanedSharesJob;

/**
 * Class DeleteOrphanedSharesJobTest
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests
 */
class DeleteOrphanedSharesJobTest extends \Test\TestCase {

	/**
	 * @var bool
	 */
	private static $trashBinStatus;

	/**
	 * @var DeleteOrphanedSharesJob
	 */
	private $job;

	/**
	 * @var \OCP\IDBConnection
	 */
	private $connection;

	/**
	 * @var string
	 */
	private $user1;

	/**
	 * @var string
	 */
	private $user2;

	public static function setUpBeforeClass(): void {
		$appManager = \OC::$server->getAppManager();
		self::$trashBinStatus = $appManager->isEnabledForUser('files_trashbin');
		$appManager->disableApp('files_trashbin');

		// just in case...
		\OC\Files\Filesystem::getLoader()->removeStorageWrapper('oc_trashbin');
	}

	public static function tearDownAfterClass(): void {
		if (self::$trashBinStatus) {
			\OC::$server->getAppManager()->enableApp('files_trashbin');
		}
	}

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		// clear occasional leftover shares from other tests
		$this->connection->executeUpdate('DELETE FROM `*PREFIX*share`');

		$this->user1 = $this->getUniqueID('user1_');
		$this->user2 = $this->getUniqueID('user2_');

		$userManager = \OC::$server->getUserManager();
		$userManager->createUser($this->user1, 'pass');
		$userManager->createUser($this->user2, 'pass');

		\OC::registerShareHooks();

		$this->job = new DeleteOrphanedSharesJob();
	}

	protected function tearDown(): void {
		$this->connection->executeUpdate('DELETE FROM `*PREFIX*share`');

		$userManager = \OC::$server->getUserManager();
		$user1 = $userManager->get($this->user1);
		if($user1) {
			$user1->delete();
		}
		$user2 = $userManager->get($this->user2);
		if($user2) {
			$user2->delete();
		}

		$this->logout();

		parent::tearDown();
	}

	private function getShares() {
		$shares = [];
		$result = $this->connection->executeQuery('SELECT * FROM `*PREFIX*share`');
		while ($row = $result->fetch()) {
			$shares[] = $row;
		}
		$result->closeCursor();
		return $shares;
	}

	/**
	 * Test clearing orphaned shares
	 */
	public function testClearShares() {
		$this->loginAsUser($this->user1);

		$user1Folder = \OC::$server->getUserFolder($this->user1);
		$testFolder = $user1Folder->newFolder('test');
		$testSubFolder = $testFolder->newFolder('sub');

		$shareManager = \OC::$server->getShareManager();
		$share = $shareManager->newShare();

		$share->setNode($testSubFolder)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setSharedWith($this->user2)
			->setSharedBy($this->user1);

		$shareManager->createShare($share);

		$this->assertCount(1, $this->getShares());

		$this->job->run([]);

		$this->assertCount(1, $this->getShares(), 'Linked shares not deleted');

		$testFolder->delete();

		$this->job->run([]);

		$this->assertCount(0, $this->getShares(), 'Orphaned shares deleted');
	}
}
