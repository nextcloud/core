<?php
/**
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

namespace Test\IntegrityCheck\Iterator;

use OC\IntegrityCheck\Iterator\ExcludeFileByNameFilterIterator;
use Test\TestCase;

class ExcludeFileByNameFilterIteratorTest extends TestCase {
	/** @var ExcludeFileByNameFilterIterator|\PHPUnit\Framework\MockObject\MockObject */
	protected $filter;

	protected function setUp(): void {
		parent::setUp();
		$this->filter = $this->getMockBuilder(ExcludeFileByNameFilterIterator::class)
			->disableOriginalConstructor()
			->setMethods(['current'])
			->getMock();
	}

	public function fileNameProvider(): array {
		return [
			['a file', true],
			['Thumbs.db', false],
			['another file', true],
			['.directory', false],
			['.webapp-nextcloud-15.0.2', false],
			['.webapp-nextcloud-14.0.5-r3', false],
			['wx.webapp-nextcloud-obee', true],
			['.rnd', false],
		];
	}

	/**
	 * @dataProvider fileNameProvider
	 * @param string $fileName
	 * @param bool $expectedResult
	 */
	public function testAcceptForFiles($fileName, $expectedResult): void {
		$iteratorMock = $this->getMockBuilder(\RecursiveDirectoryIterator::class)
			->disableOriginalConstructor()
			->setMethods(['getFilename', 'isDir'])
			->getMock();

		$iteratorMock->method('getFilename')
			->willReturn($fileName);
		$iteratorMock->method('isDir')
			->willReturn(false);
		$this->filter->method('current')
			->willReturn($iteratorMock);

		$actualResult = $this->filter->accept();
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @dataProvider fileNameProvider
	 * @param string $fileName
	 * @param bool $expectedResult
	 */
	public function testAcceptForDirs($fileName, $expectedResult): void {
		$iteratorMock = $this->getMockBuilder(\RecursiveDirectoryIterator::class)
			->disableOriginalConstructor()
			->setMethods(['getFilename', 'isDir'])
			->getMock();

		$iteratorMock->method('getFilename')
			->willReturn($fileName);
		$iteratorMock->method('isDir')
			->willReturn(true);
		$this->filter->method('current')
			->willReturn($iteratorMock);

		$actualResult = $this->filter->accept();
		$this->assertTrue($actualResult);
	}
}
