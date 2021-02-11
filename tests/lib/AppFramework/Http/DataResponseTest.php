<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2014 Bernhard Posselt <dev@bernhard-posselt.com>
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
use OCP\AppFramework\Http\DataResponse;

class DataResponseTest extends \Test\TestCase {

	/**
	 * @var DataResponse
	 */
	private $response;

	protected function setUp(): void {
		parent::setUp();
		$this->response = new DataResponse();
	}


	public function testSetData() {
		$params = ['hi', 'yo'];
		$this->response->setData($params);

		$this->assertEquals(['hi', 'yo'], $this->response->getData());
	}


	public function testConstructorAllowsToSetData() {
		$data = ['hi'];
		$code = 300;
		$response = new DataResponse($data, $code);

		$this->assertEquals($data, $response->getData());
		$this->assertEquals($code, $response->getStatus());
	}


	public function testConstructorAllowsToSetHeaders() {
		$data = ['hi'];
		$code = 300;
		$headers = ['test' => 'something'];
		$response = new DataResponse($data, $code, $headers);

		$expectedHeaders = [
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Content-Security-Policy' => "default-src 'none';base-uri 'none';manifest-src 'self';frame-ancestors 'none'",
			'Feature-Policy' => "autoplay 'none';camera 'none';fullscreen 'none';geolocation 'none';microphone 'none';payment 'none'",
			'X-Robots-Tag' => 'none',
		];
		$expectedHeaders = array_merge($expectedHeaders, $headers);

		$this->assertEquals($data, $response->getData());
		$this->assertEquals($code, $response->getStatus());
		$this->assertEquals($expectedHeaders, $response->getHeaders());
	}


	public function testChainability() {
		$params = ['hi', 'yo'];
		$this->response->setData($params)
			->setStatus(Http::STATUS_NOT_FOUND);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $this->response->getStatus());
		$this->assertEquals(['hi', 'yo'], $this->response->getData());
	}
}
