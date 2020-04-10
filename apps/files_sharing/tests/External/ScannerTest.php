<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_Sharing\Tests\External;

use OCA\Files_Sharing\External\Scanner;
use Test\TestCase;

class ScannerTest extends TestCase {
	/** @var \OCA\Files_Sharing\External\Scanner */
	protected $scanner;
	/** @var \OCA\Files_Sharing\External\Storage|\PHPUnit_Framework_MockObject_MockObject */
	protected $storage;
	/** @var \OC\Files\Cache\Cache|\PHPUnit_Framework_MockObject_MockObject */
	protected $cache;

	protected function setUp(): void {
		parent::setUp();

		$this->storage = $this->getMockBuilder('\OCA\Files_Sharing\External\Storage')
			->disableOriginalConstructor()
			->getMock();
		$this->cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$this->storage->expects($this->any())
			->method('getCache')
			->willReturn($this->cache);

		$this->scanner = new Scanner($this->storage);
	}

	public function testScanAll() {
		$this->storage->expects($this->any())
			->method('getShareInfo')
			->willReturn(['status' => 'success', 'data' => []]);

		// FIXME add real tests, we are currently only checking for
		// Declaration of OCA\Files_Sharing\External\Scanner::*() should be
		// compatible with OC\Files\Cache\Scanner::*()
		$this->scanner->scanAll();
		$this->addToAssertionCount(1);
	}

	public function testScan() {
		$this->storage->expects($this->any())
			->method('getShareInfo')
			->willReturn(['status' => 'success', 'data' => []]);

		// FIXME add real tests, we are currently only checking for
		// Declaration of OCA\Files_Sharing\External\Scanner::*() should be
		// compatible with OC\Files\Cache\Scanner::*()
		$this->scanner->scan('test', Scanner::SCAN_RECURSIVE);
		$this->addToAssertionCount(1);
	}

	public function testScanFile() {
		// FIXME add real tests, we are currently only checking for
		// Declaration of OCA\Files_Sharing\External\Scanner::*() should be
		// compatible with OC\Files\Cache\Scanner::*()
		$this->scanner->scanFile('test', Scanner::SCAN_RECURSIVE);
		$this->addToAssertionCount(1);
	}
}
