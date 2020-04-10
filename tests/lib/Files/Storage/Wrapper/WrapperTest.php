<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Storage\Wrapper;

class WrapperTest extends \Test\Files\Storage\Storage {
	/**
	 * @var string tmpDir
	 */
	private $tmpDir;

	protected function setUp(): void {
		parent::setUp();

		$this->tmpDir = \OC::$server->getTempManager()->getTemporaryFolder();
		$storage = new \OC\Files\Storage\Local(['datadir' => $this->tmpDir]);
		$this->instance = new \OC\Files\Storage\Wrapper\Wrapper(['storage' => $storage]);
	}

	protected function tearDown(): void {
		\OC_Helper::rmdirr($this->tmpDir);
		parent::tearDown();
	}

	public function testInstanceOfStorageWrapper() {
		$this->assertTrue($this->instance->instanceOfStorage('\OC\Files\Storage\Local'));
		$this->assertTrue($this->instance->instanceOfStorage('\OC\Files\Storage\Wrapper\Wrapper'));
	}
}
