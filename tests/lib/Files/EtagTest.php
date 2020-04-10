<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files;

use OC\Files\Filesystem;
use OCP\EventDispatcher\IEventDispatcher;
use OCA\Files_Sharing\AppInfo\Application;

/**
 * Class EtagTest
 *
 * @group DB
 *
 * @package Test\Files
 */
class EtagTest extends \Test\TestCase {
	private $datadir;

	private $tmpDir;

	/**
	 * @var \Test\Util\User\Dummy $userBackend
	 */
	private $userBackend;

	protected function setUp(): void {
		parent::setUp();

		\OC_Hook::clear('OC_Filesystem', 'setup');
		// init files sharing
		new Application();

		\OC\Share\Share::registerBackend('file', 'OCA\Files_Sharing\ShareBackend\File');
		\OC\Share\Share::registerBackend('folder', 'OCA\Files_Sharing\ShareBackend\Folder', 'file');

		$config = \OC::$server->getConfig();
		$this->datadir = $config->getSystemValue('datadirectory');
		$this->tmpDir = \OC::$server->getTempManager()->getTemporaryFolder();
		$config->setSystemValue('datadirectory', $this->tmpDir);

		$this->userBackend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($this->userBackend);
	}

	protected function tearDown(): void {
		\OC::$server->getConfig()->setSystemValue('datadirectory', $this->datadir);

		$this->logout();
		parent::tearDown();
	}

	public function testNewUser() {
		$user1 = $this->getUniqueID('user_');
		$this->userBackend->createUser($user1, '');

		$this->loginAsUser($user1);
		Filesystem::mkdir('/folder');
		Filesystem::mkdir('/folder/subfolder');
		Filesystem::file_put_contents('/foo.txt', 'asd');
		Filesystem::file_put_contents('/folder/bar.txt', 'fgh');
		Filesystem::file_put_contents('/folder/subfolder/qwerty.txt', 'jkl');

		$files = ['/foo.txt', '/folder/bar.txt', '/folder/subfolder', '/folder/subfolder/qwerty.txt'];
		$originalEtags = $this->getEtags($files);

		$scanner = new \OC\Files\Utils\Scanner($user1, \OC::$server->getDatabaseConnection(), \OC::$server->query(IEventDispatcher::class), \OC::$server->getLogger());
		$scanner->backgroundScan('/');

		$newEtags = $this->getEtags($files);
		// loop over array and use assertSame over assertEquals to prevent false positives
		foreach ($originalEtags as $file => $originalEtag) {
			$this->assertSame($originalEtag, $newEtags[$file]);
		}
	}

	/**
	 * @param string[] $files
	 */
	private function getEtags($files) {
		$etags = [];
		foreach ($files as $file) {
			$info = Filesystem::getFileInfo($file);
			$etags[$file] = $info['etag'];
		}
		return $etags;
	}
}
