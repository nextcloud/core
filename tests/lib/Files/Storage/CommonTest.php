<?php
/**
 * ownCloud
 *
 * @author Robin Appelman
 * @copyright 2012 Robin Appelman icewind@owncloud.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Files\Storage;

use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\Wrapper;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class CommonTest
 *
 * @group DB
 *
 * @package Test\Files\Storage
 */
class CommonTest extends Storage {
	/**
	 * @var string tmpDir
	 */
	private $tmpDir;

	protected function setUp(): void {
		parent::setUp();

		$this->tmpDir = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->instance = new \OC\Files\Storage\CommonTest(['datadir' => $this->tmpDir]);
	}

	protected function tearDown(): void {
		\OC_Helper::rmdirr($this->tmpDir);
		parent::tearDown();
	}

	public function testMoveFromStorageWrapped() {
		/** @var \OC\Files\Storage\CommonTest|MockObject $instance */
		$instance = $this->getMockBuilder(\OC\Files\Storage\CommonTest::class)
			->setMethods(['copyFromStorage', 'rmdir', 'unlink'])
			->setConstructorArgs([['datadir' => $this->tmpDir]])
			->getMock();
		$instance->method('copyFromStorage')
			->willThrowException(new \Exception('copy'));

		$source = new Wrapper([
			'storage' => $instance,
		]);

		$instance->file_put_contents('foo.txt', 'bar');
		$instance->moveFromStorage($source, 'foo.txt', 'bar.txt');
		$this->assertTrue($instance->file_exists('bar.txt'));
	}

	public function testMoveFromStorageJailed() {
		/** @var \OC\Files\Storage\CommonTest|MockObject $instance */
		$instance = $this->getMockBuilder(\OC\Files\Storage\CommonTest::class)
			->setMethods(['copyFromStorage', 'rmdir', 'unlink'])
			->setConstructorArgs([['datadir' => $this->tmpDir]])
			->getMock();
		$instance->method('copyFromStorage')
			->willThrowException(new \Exception('copy'));

		$source = new Jail([
			'storage' => $instance,
			'root' => 'foo'
		]);
		$source = new Wrapper([
			'storage' => $source
		]);

		$instance->mkdir('foo');
		$instance->file_put_contents('foo/foo.txt', 'bar');
		$instance->moveFromStorage($source, 'foo.txt', 'bar.txt');
		$this->assertTrue($instance->file_exists('bar.txt'));
	}

	public function testMoveFromStorageNestedJail() {
		/** @var \OC\Files\Storage\CommonTest|MockObject $instance */
		$instance = $this->getMockBuilder(\OC\Files\Storage\CommonTest::class)
			->setMethods(['copyFromStorage', 'rmdir', 'unlink'])
			->setConstructorArgs([['datadir' => $this->tmpDir]])
			->getMock();
		$instance->method('copyFromStorage')
			->willThrowException(new \Exception('copy'));

		$source = new Jail([
			'storage' => $instance,
			'root' => 'foo'
		]);
		$source = new Wrapper([
			'storage' => $source
		]);
		$source = new Jail([
			'storage' => $source,
			'root' => 'bar'
		]);
		$source = new Wrapper([
			'storage' => $source
		]);

		$instance->mkdir('foo');
		$instance->mkdir('foo/bar');
		$instance->file_put_contents('foo/bar/foo.txt', 'bar');
		$instance->moveFromStorage($source, 'foo.txt', 'bar.txt');
		$this->assertTrue($instance->file_exists('bar.txt'));
	}
}
