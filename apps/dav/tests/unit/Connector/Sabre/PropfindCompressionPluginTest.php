<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OCA\DAV\Connector\Sabre\PropfindCompressionPlugin;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Test\TestCase;

class PropfindCompressionPluginTest extends TestCase {
	/** @var PropfindCompressionPlugin */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();

		$this->plugin = new PropfindCompressionPlugin();
	}

	public function testNoHeader() {
		$request = $this->createMock(Request::class);
		$response = $this->createMock(Response::class);

		$request->method('getHeader')
			->with('Accept-Encoding')
			->willReturn(null);

		$response->expects($this->never())
			->method($this->anything());

		$result = $this->plugin->compressResponse($request, $response);
		$this->assertSame($response, $result);
	}

	public function testHeaderButNoGzip() {
		$request = $this->createMock(Request::class);
		$response = $this->createMock(Response::class);

		$request->method('getHeader')
			->with('Accept-Encoding')
			->willReturn('deflate');

		$response->expects($this->never())
			->method($this->anything());

		$result = $this->plugin->compressResponse($request, $response);
		$this->assertSame($response, $result);
	}

	public function testHeaderGzipButNoStringBody() {
		$request = $this->createMock(Request::class);
		$response = $this->createMock(Response::class);

		$request->method('getHeader')
			->with('Accept-Encoding')
			->willReturn('deflate');

		$response->method('getBody')
			->willReturn(5);

		$result = $this->plugin->compressResponse($request, $response);
		$this->assertSame($response, $result);
	}


	public function testProperGzip() {
		$request = $this->createMock(Request::class);
		$response = $this->createMock(Response::class);

		$request->method('getHeader')
			->with('Accept-Encoding')
			->willReturn('gzip, deflate');

		$response->method('getBody')
			->willReturn('my gzip test');

		$response->expects($this->once())
			->method('setHeader')
			->with(
				$this->equalTo('Content-Encoding'),
				$this->equalTo('gzip')
			);
		$response->expects($this->once())
			->method('setBody')
			->with($this->callback(function ($data) {
				$orig = gzdecode($data);
				return $orig === 'my gzip test';
			}));

		$result = $this->plugin->compressResponse($request, $response);
		$this->assertSame($response, $result);
	}
}
