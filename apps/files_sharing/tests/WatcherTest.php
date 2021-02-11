<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Files_Sharing\Tests;

use OCP\Share\IShare;

/**
 * Class WatcherTest
 *
 * @group DB
 */
class WatcherTest extends TestCase {

	/** @var \OC\Files\Storage\Storage */
	private $ownerStorage;

	/** @var \OC\Files\Cache\Cache */
	private $ownerCache;

	/** @var \OC\Files\Storage\Storage */
	private $sharedStorage;

	/** @var \OC\Files\Cache\Cache */
	private $sharedCache;

	/** @var \OCP\Share\IShare */
	private $_share;

	protected function setUp(): void {
		parent::setUp();

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		// prepare user1's dir structure
		$this->view->mkdir('container');
		$this->view->mkdir('container/shareddir');
		$this->view->mkdir('container/shareddir/subdir');

		list($this->ownerStorage, $internalPath) = $this->view->resolvePath('');
		$this->ownerCache = $this->ownerStorage->getCache();
		$this->ownerStorage->getScanner()->scan('');

		// share "shareddir" with user2
		$this->_share = $this->share(
			IShare::TYPE_USER,
			'container/shareddir',
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);

		$this->_share->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($this->_share);

		// login as user2
		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		// retrieve the shared storage
		$secondView = new \OC\Files\View('/' . self::TEST_FILES_SHARING_API_USER2);
		list($this->sharedStorage, $internalPath) = $secondView->resolvePath('files/shareddir');
		$this->sharedCache = $this->sharedStorage->getCache();
	}

	protected function tearDown(): void {
		if ($this->sharedCache) {
			$this->sharedCache->clear();
		}

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		if ($this->view) {
			$this->shareManager->deleteShare($this->_share);

			$this->view->deleteAll('container');

			$this->ownerCache->clear();
		}

		parent::tearDown();
	}

	/**
	 * Tests that writing a file using the shared storage will propagate the file
	 * size to the owner's parent folders.
	 */
	public function testFolderSizePropagationToOwnerStorage() {
		$initialSizes = self::getOwnerDirSizes('files/container/shareddir');

		$textData = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$dataLen = strlen($textData);
		$this->sharedCache->put('bar.txt', ['mtime' => 10, 'storage_mtime' => 10, 'size' => $dataLen, 'mimetype' => 'text/plain']);
		$this->sharedStorage->file_put_contents('bar.txt', $textData);
		$this->sharedCache->put('', ['mtime' => 10, 'storage_mtime' => 10, 'size' => '-1', 'mimetype' => 'httpd/unix-directory']);

		// run the propagation code
		$this->sharedStorage->getWatcher()->checkUpdate('');
		$this->sharedStorage->getCache()->correctFolderSize('');

		// the owner's parent dirs must have increase size
		$newSizes = self::getOwnerDirSizes('files/container/shareddir');
		$this->assertEquals($initialSizes[''] + $dataLen, $newSizes['']);
		$this->assertEquals($initialSizes['files'] + $dataLen, $newSizes['files']);
		$this->assertEquals($initialSizes['files/container'] + $dataLen, $newSizes['files/container']);
		$this->assertEquals($initialSizes['files/container/shareddir'] + $dataLen, $newSizes['files/container/shareddir']);

		// no more updates
		$result = $this->sharedStorage->getWatcher()->checkUpdate('');

		$this->assertFalse($result);
	}

	/**
	 * Tests that writing a file using the shared storage will propagate the file
	 * size to the owner's parent folders.
	 */
	public function testSubFolderSizePropagationToOwnerStorage() {
		$initialSizes = self::getOwnerDirSizes('files/container/shareddir/subdir');

		$textData = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$dataLen = strlen($textData);
		$this->sharedCache->put('subdir/bar.txt', ['mtime' => 10, 'storage_mtime' => 10, 'size' => $dataLen, 'mimetype' => 'text/plain']);
		$this->sharedStorage->file_put_contents('subdir/bar.txt', $textData);
		$this->sharedCache->put('subdir', ['mtime' => 10, 'storage_mtime' => 10, 'size' => $dataLen, 'mimetype' => 'text/plain']);

		// run the propagation code
		$this->sharedStorage->getWatcher()->checkUpdate('subdir');
		$this->sharedStorage->getCache()->correctFolderSize('subdir');

		// the owner's parent dirs must have increase size
		$newSizes = self::getOwnerDirSizes('files/container/shareddir/subdir');
		$this->assertEquals($initialSizes[''] + $dataLen, $newSizes['']);
		$this->assertEquals($initialSizes['files'] + $dataLen, $newSizes['files']);
		$this->assertEquals($initialSizes['files/container'] + $dataLen, $newSizes['files/container']);
		$this->assertEquals($initialSizes['files/container/shareddir'] + $dataLen, $newSizes['files/container/shareddir']);
		$this->assertEquals($initialSizes['files/container/shareddir/subdir'] + $dataLen, $newSizes['files/container/shareddir/subdir']);

		// no more updates
		$result = $this->sharedStorage->getWatcher()->checkUpdate('subdir');

		$this->assertFalse($result);
	}

	/**
	 * Returns the sizes of the path and its parent dirs in a hash
	 * where the key is the path and the value is the size.
	 * @param string $path
	 */
	public function getOwnerDirSizes($path) {
		$result = [];

		while ($path != '' && $path != '' && $path != '.') {
			$cachedData = $this->ownerCache->get($path);
			$result[$path] = $cachedData['size'];
			$path = dirname($path);
		}
		$cachedData = $this->ownerCache->get('');
		$result[''] = $cachedData['size'];
		return $result;
	}
}
