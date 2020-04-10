<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OC\Files\Filesystem;
use OC\Files\View;
use OCP\Lock\ILockingProvider;

/**
 * Class LockingTest
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests
 */
class LockingTest extends TestCase {
	/**
	 * @var \Test\Util\User\Dummy
	 */
	private $userBackend;

	private $ownerUid;
	private $recipientUid;

	protected function setUp(): void {
		parent::setUp();

		$this->userBackend = new \Test\Util\User\Dummy();
		\OC::$server->getUserManager()->registerBackend($this->userBackend);

		$this->ownerUid = $this->getUniqueID('owner_');
		$this->recipientUid = $this->getUniqueID('recipient_');
		$this->userBackend->createUser($this->ownerUid, '');
		$this->userBackend->createUser($this->recipientUid, '');

		$this->loginAsUser($this->ownerUid);
		Filesystem::mkdir('/foo');
		Filesystem::file_put_contents('/foo/bar.txt', 'asd');
		$fileId = Filesystem::getFileInfo('/foo/bar.txt')->getId();

		$this->share(
			\OCP\Share::SHARE_TYPE_USER,
			'/foo/bar.txt',
			$this->ownerUid,
			$this->recipientUid,
			\OCP\Constants::PERMISSION_READ | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_SHARE
		);

		$this->loginAsUser($this->recipientUid);
		$this->assertTrue(Filesystem::file_exists('bar.txt'));
	}

	protected function tearDown(): void {
		\OC::$server->getUserManager()->removeBackend($this->userBackend);
		parent::tearDown();
	}

	
	public function testLockAsRecipient() {
		$this->expectException(\OCP\Lock\LockedException::class);

		$this->loginAsUser($this->ownerUid);

		Filesystem::initMountPoints($this->recipientUid);
		$recipientView = new View('/' . $this->recipientUid . '/files');
		$recipientView->lockFile('bar.txt', ILockingProvider::LOCK_EXCLUSIVE);

		Filesystem::rename('/foo', '/asd');
	}

	public function testUnLockAsRecipient() {
		$this->loginAsUser($this->ownerUid);

		Filesystem::initMountPoints($this->recipientUid);
		$recipientView = new View('/' . $this->recipientUid . '/files');
		$recipientView->lockFile('bar.txt', ILockingProvider::LOCK_EXCLUSIVE);
		$recipientView->unlockFile('bar.txt', ILockingProvider::LOCK_EXCLUSIVE);

		$this->assertTrue(Filesystem::rename('/foo', '/asd'));
	}

	public function testChangeLock() {

		Filesystem::initMountPoints($this->recipientUid);
		$recipientView = new View('/' . $this->recipientUid . '/files');
		$recipientView->lockFile('bar.txt', ILockingProvider::LOCK_SHARED);
		$recipientView->changeLock('bar.txt', ILockingProvider::LOCK_EXCLUSIVE);
		$recipientView->unlockFile('bar.txt', ILockingProvider::LOCK_EXCLUSIVE);

		$this->addToAssertionCount(1);
	}
}
