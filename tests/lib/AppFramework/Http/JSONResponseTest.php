<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @author Morris Jobke
 * @copyright 2012 Bernhard Posselt <dev@bernhard-posselt.com>
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
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
use OCP\AppFramework\Http\JSONResponse;

class JSONResponseTest extends \Test\TestCase {

	/**
	 * @var JSONResponse
	 */
	private $json;

	protected function setUp(): void {
		parent::setUp();
		$this->json = new JSONResponse();
	}


	public function testHeader() {
		$headers = $this->json->getHeaders();
		$this->assertEquals('application/json; charset=utf-8', $headers['Content-Type']);
	}


	public function testSetData() {
		$params = ['hi', 'yo'];
		$this->json->setData($params);

		$this->assertEquals(['hi', 'yo'], $this->json->getData());
	}


	public function testSetRender() {
		$params = ['test' => 'hi'];
		$this->json->setData($params);

		$expected = '{"test":"hi"}';

		$this->assertEquals($expected, $this->json->render());
	}

	/**
	 * @return array
	 */
	public function renderDataProvider() {
		return [
			[
				['test' => 'hi'], '{"test":"hi"}',
			],
			[
				['<h1>test' => '<h1>hi'], '{"\u003Ch1\u003Etest":"\u003Ch1\u003Ehi"}',
			],
		];
	}

	/**
	 * @dataProvider renderDataProvider
	 * @param array $input
	 * @param string $expected
	 */
	public function testRender(array $input, $expected) {
		$this->json->setData($input);
		$this->assertEquals($expected, $this->json->render());
	}

	
	public function testRenderWithNonUtf8Encoding() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Could not json_encode due to invalid non UTF-8 characters in the array: array (');

		$params = ['test' => hex2bin('e9')];
		$this->json->setData($params);
		$this->json->render();
	}

	public function testConstructorAllowsToSetData() {
		$data = ['hi'];
		$code = 300;
		$response = new JSONResponse($data, $code);

		$expected = '["hi"]';
		$this->assertEquals($expected, $response->render());
		$this->assertEquals($code, $response->getStatus());
	}

	public function testChainability() {
		$params = ['hi', 'yo'];
		$this->json->setData($params)
			->setStatus(Http::STATUS_NOT_FOUND);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $this->json->getStatus());
		$this->assertEquals(['hi', 'yo'], $this->json->getData());
	}

}
