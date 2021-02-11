<?php
/**
 * Copyright (c) 2014 Andreas Fischer <bantu@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use bantu\IniGetWrapper\IniGetWrapper;

/**
 * Tests whether LargeFileHelper is able to determine file size at all.
 * Large files are not considered yet.
 */
class LargeFileHelperGetFileSizeTest extends TestCase {
	/** @var string */
	protected $filename;
	/** @var int */
	protected $fileSize;
	/** @var \OC\LargeFileHelper */
	protected $helper;

	protected function setUp(): void {
		parent::setUp();
		$this->helper = new \OC\LargeFileHelper();
	}

	public function dataFileNameProvider() {
		$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;

		return [
			[ $path . 'lorem.txt', 446 ],
			[ $path . 'strängé filename (duplicate #2).txt', 446 ],
		];
	}

	/**
	 * @dataProvider dataFileNameProvider
	 */
	public function XtestGetFileSizeViaCurl($filename, $fileSize) {
		if (!extension_loaded('curl')) {
			$this->markTestSkipped(
				'The PHP curl extension is required for this test.'
			);
		}
		if (\OC::$server->get(IniGetWrapper::class)->getString('open_basedir') !== '') {
			$this->markTestSkipped(
				'The PHP curl extension does not work with the file:// protocol when open_basedir is enabled.'
			);
		}
		$this->assertSame(
			$fileSize,
			$this->helper->getFileSizeViaCurl($filename)
		);
	}

	/**
	 * @dataProvider dataFileNameProvider
	 */
	public function testGetFileSizeViaExec($filename, $fileSize) {
		if (escapeshellarg('strängé') !== '\'strängé\'') {
			$this->markTestSkipped('Your escapeshell args removes accents');
		}
		if (!\OC_Helper::is_function_enabled('exec')) {
			$this->markTestSkipped(
				'The exec() function needs to be enabled for this test.'
			);
		}
		$this->assertSame(
			$fileSize,
			$this->helper->getFileSizeViaExec($filename)
		);
	}

	/**
	 * @dataProvider dataFileNameProvider
	 */
	public function testGetFileSizeNative($filename, $fileSize) {
		$this->assertSame(
			$fileSize,
			$this->helper->getFileSizeNative($filename)
		);
	}
}
