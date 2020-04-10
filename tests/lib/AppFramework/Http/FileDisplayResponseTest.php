<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\AppFramework\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\File;

class FileDisplayResponseTest extends \Test\TestCase {
	/** @var File|\PHPUnit_Framework_MockObject_MockObject */
	private $file;

	/** @var FileDisplayResponse */
	private $response;

	protected function setUp(): void {
		$this->file = $this->getMockBuilder('OCP\Files\File')
			->getMock();

		$this->file->expects($this->once())
			->method('getETag')
			->willReturn('myETag');
		$this->file->expects($this->once())
			->method('getName')
			->willReturn('myFileName');
		$this->file->expects($this->once())
			->method('getMTime')
			->willReturn(1464825600);

		$this->response = new FileDisplayResponse($this->file);
	}

	public function testHeader() {
		$headers = $this->response->getHeaders();
		$this->assertArrayHasKey('Content-Disposition', $headers);
		$this->assertSame('inline; filename="myFileName"', $headers['Content-Disposition']);
	}

	public function testETag() {
		$this->assertSame('myETag', $this->response->getETag());
	}

	public function testLastModified() {
		$lastModified = $this->response->getLastModified();
		$this->assertNotNull($lastModified);
		$this->assertSame(1464825600, $lastModified->getTimestamp());
	}

	public function test304() {
		$output = $this->getMockBuilder('OCP\AppFramework\Http\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$output->expects($this->any())
			->method('getHttpResponseCode')
			->willReturn(Http::STATUS_NOT_MODIFIED);
		$output->expects($this->never())
			->method('setOutput');
		$this->file->expects($this->never())
			->method('getContent');

		$this->response->callback($output);
	}


	public function testNon304() {
		$output = $this->getMockBuilder('OCP\AppFramework\Http\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$output->expects($this->any())
			->method('getHttpResponseCode')
			->willReturn(Http::STATUS_OK);
		$output->expects($this->once())
			->method('setOutput')
			->with($this->equalTo('my data'));
		$output->expects($this->once())
			->method('setHeader')
			->with($this->equalTo('Content-Length: 42'));
		$this->file->expects($this->once())
			->method('getContent')
			->willReturn('my data');
		$this->file->expects($this->any())
			->method('getSize')
			->willReturn(42);

		$this->response->callback($output);
	}
}
