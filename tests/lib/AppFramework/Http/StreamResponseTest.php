<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2015 Bernhard Posselt <dev@bernhard-posselt.com>
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

namespace Test\AppFramework\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\Http\StreamResponse;

class StreamResponseTest extends \Test\TestCase {

	/** @var IOutput */
	private $output;

	protected function setUp(): void {
		parent::setUp();
		$this->output = $this->getMockBuilder('OCP\\AppFramework\\Http\\IOutput')
			->disableOriginalConstructor()
			->getMock();
	}

	public function testOutputNotModified() {
		$path = __FILE__;
		$this->output->expects($this->once())
			->method('getHttpResponseCode')
			->willReturn(Http::STATUS_NOT_MODIFIED);
		$this->output->expects($this->never())
			->method('setReadfile');
		$response = new StreamResponse($path);

		$response->callback($this->output);
	}

	public function testOutputOk() {
		$path = __FILE__;
		$this->output->expects($this->once())
			->method('getHttpResponseCode')
			->willReturn(Http::STATUS_OK);
		$this->output->expects($this->once())
			->method('setReadfile')
			->with($this->equalTo($path))
			->willReturn(true);
		$response = new StreamResponse($path);

		$response->callback($this->output);
	}

	public function testOutputNotFound() {
		$path = __FILE__ . 'test';
		$this->output->expects($this->once())
			->method('getHttpResponseCode')
			->willReturn(Http::STATUS_OK);
		$this->output->expects($this->never())
			->method('setReadfile');
		$this->output->expects($this->once())
			->method('setHttpResponseCode')
			->with($this->equalTo(Http::STATUS_NOT_FOUND));
		$response = new StreamResponse($path);

		$response->callback($this->output);
	}

	public function testOutputReadFileError() {
		$path = __FILE__;
		$this->output->expects($this->once())
			->method('getHttpResponseCode')
			->willReturn(Http::STATUS_OK);
		$this->output->expects($this->once())
			->method('setReadfile')
			->willReturn(false);
		$this->output->expects($this->once())
			->method('setHttpResponseCode')
			->with($this->equalTo(Http::STATUS_BAD_REQUEST));
		$response = new StreamResponse($path);

		$response->callback($this->output);
	}

}
