<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Utils;

use OC\Files\Filesystem;
use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Temporary;
use OCA\Files_Sharing\SharedStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;

class TestScanner extends \OC\Files\Utils\Scanner {
	/**
	 * @var \OC\Files\Mount\MountPoint[] $mounts
	 */
	private $mounts = [];

	/**
	 * @param \OC\Files\Mount\MountPoint $mount
	 */
	public function addMount($mount) {
		$this->mounts[] = $mount;
	}

	protected function getMounts($dir) {
		return $this->mounts;
	}
}

/**
 * Class ScannerTest
 *
 * @group DB
 *
 * @package Test\Files\Utils
 */
class ScannerTest extends \Test\TestCase {
	/**
	 * @var \Test\Util\User\Dummy
	 */
	private $userBackend;

	protected function setUp(): void {
		parent::setUp();

		$this->userBackend = new \Test\Util\User\Dummy();
		\OC::$server->getUserManager()->registerBackend($this->userBackend);
		$this->loginAsUser();
	}

	protected function tearDown(): void {
		$this->logout();
		\OC::$server->getUserManager()->removeBackend($this->userBackend);
		parent::tearDown();
	}

	public function testReuseExistingRoot() {
		$storage = new Temporary([]);
		$mount = new MountPoint($storage, '');
		Filesystem::getMountManager()->addMount($mount);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', 'qwerty');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection(), $this->createMock(IEventDispatcher::class), \OC::$server->getLogger());
		$scanner->addMount($mount);

		$scanner->scan('');
		$this->assertTrue($cache->inCache('folder/bar.txt'));
		$oldRoot = $cache->get('');

		$scanner->scan('');
		$newRoot = $cache->get('');
		$this->assertEquals($oldRoot, $newRoot);
	}

	public function testReuseExistingFile() {
		$storage = new Temporary([]);
		$mount = new MountPoint($storage, '');
		Filesystem::getMountManager()->addMount($mount);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', 'qwerty');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection(), $this->createMock(IEventDispatcher::class), \OC::$server->getLogger());
		$scanner->addMount($mount);

		$scanner->scan('');
		$this->assertTrue($cache->inCache('folder/bar.txt'));
		$old = $cache->get('folder/bar.txt');

		$scanner->scan('');
		$new = $cache->get('folder/bar.txt');
		$this->assertEquals($old, $new);
	}

	public function testScanSubMount() {
		$uid = $this->getUniqueID();
		$this->userBackend->createUser($uid, 'test');

		$mountProvider = $this->createMock(IMountProvider::class);

		$storage = new Temporary([]);
		$mount = new MountPoint($storage, '/' . $uid . '/files/foo');

		$mountProvider->expects($this->any())
			->method('getMountsForUser')
			->willReturnCallback(function (IUser $user, IStorageFactory $storageFactory) use ($mount, $uid) {
				if ($user->getUID() === $uid) {
					return [$mount];
				} else {
					return [];
				}
			});

		\OC::$server->getMountProviderCollection()->registerProvider($mountProvider);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', 'qwerty');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');

		$scanner = new \OC\Files\Utils\Scanner($uid, \OC::$server->getDatabaseConnection(), \OC::$server->query(IEventDispatcher::class), \OC::$server->getLogger());

		$this->assertFalse($cache->inCache('folder/bar.txt'));
		$scanner->scan('/' . $uid . '/files/foo');
		$this->assertTrue($cache->inCache('folder/bar.txt'));
	}

	/**
	 * @return array
	 */
	public function invalidPathProvider() {
		return [
			[
				'../',
			],
			[
				'..\\',
			],
			[
				'../..\\../',
			],
		];
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @param string $invalidPath
	 */
	public function testInvalidPathScanning($invalidPath) {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid path to scan');

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection(), $this->createMock(IEventDispatcher::class), \OC::$server->getLogger());
		$scanner->scan($invalidPath);
	}

	public function testPropagateEtag() {
		$storage = new Temporary([]);
		$mount = new MountPoint($storage, '');
		Filesystem::getMountManager()->addMount($mount);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');
		$storage->touch('folder/bar.txt', time() - 200);

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection(), $this->createMock(IEventDispatcher::class), \OC::$server->getLogger());
		$scanner->addMount($mount);

		$scanner->scan('');
		$this->assertTrue($cache->inCache('folder/bar.txt'));
		$oldRoot = $cache->get('');

		$storage->file_put_contents('folder/bar.txt', 'qwerty');
		$scanner->scan('');
		$newRoot = $cache->get('');

		$this->assertNotEquals($oldRoot->getEtag(), $newRoot->getEtag());
	}

	public function testSkipLocalShares() {
		$sharedStorage = $this->createMock(SharedStorage::class);
		$sharedMount = new MountPoint($sharedStorage, '/share');
		Filesystem::getMountManager()->addMount($sharedMount);

		$sharedStorage->expects($this->any())
			->method('instanceOfStorage')
			->willReturnMap([
				[SharedStorage::class, true],
			]);
		$sharedStorage->expects($this->never())
			->method('getScanner');

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection(), $this->createMock(IEventDispatcher::class), \OC::$server->getLogger());
		$scanner->addMount($sharedMount);
		$scanner->scan('');

		$scanner->backgroundScan('');
	}

	public function testShallow() {
		$storage = new Temporary([]);
		$mount = new MountPoint($storage, '');
		Filesystem::getMountManager()->addMount($mount);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->mkdir('folder/subfolder');
		$storage->file_put_contents('foo.txt', 'qwerty');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');
		$storage->file_put_contents('folder/subfolder/foobar.txt', 'qwerty');

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection(), $this->createMock(IEventDispatcher::class), \OC::$server->getLogger());
		$scanner->addMount($mount);

		$scanner->scan('', $recusive = false);
		$this->assertTrue($cache->inCache('folder'));
		$this->assertFalse($cache->inCache('folder/subfolder'));
		$this->assertTrue($cache->inCache('foo.txt'));
		$this->assertFalse($cache->inCache('folder/bar.txt'));
		$this->assertFalse($cache->inCache('folder/subfolder/foobar.txt'));

		$scanner->scan('folder', $recusive = false);
		$this->assertTrue($cache->inCache('folder'));
		$this->assertTrue($cache->inCache('folder/subfolder'));
		$this->assertTrue($cache->inCache('foo.txt'));
		$this->assertTrue($cache->inCache('folder/bar.txt'));
		$this->assertFalse($cache->inCache('folder/subfolder/foobar.txt'));
	}

}
