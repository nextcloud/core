<?php
/**
 * Copyright (c) 2015 Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Http\Client;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use function GuzzleHttp\Psr7\stream_for;
use OC\Http\Client\Response;

/**
 * Class ResponseTest
 */
class ResponseTest extends \Test\TestCase {
	/** @var GuzzleResponse */
	private $guzzleResponse;

	protected function setUp(): void {
		parent::setUp();
		$this->guzzleResponse = new GuzzleResponse(418);
	}

	public function testGetBody() {
		$response = new Response($this->guzzleResponse->withBody(stream_for('MyResponse')));
		$this->assertSame('MyResponse', $response->getBody());
	}

	public function testGetStatusCode() {
		$response = new Response($this->guzzleResponse);
		$this->assertSame(418, $response->getStatusCode());
	}

	public function testGetHeader() {
		$response = new Response($this->guzzleResponse->withHeader('bar', 'foo'));
		$this->assertSame('foo', $response->getHeader('bar'));
	}

	public function testGetHeaders() {
		$response = new Response($this->guzzleResponse
			->withHeader('bar', 'foo')
			->withHeader('x-awesome', 'yes')
		);

		$expected = [
			'bar' => [
				0 => 'foo',
			],
			'x-awesome' => [
				0 => 'yes',
			],
		];
		$this->assertSame($expected, $response->getHeaders());
		$this->assertSame('yes', $response->getHeader('x-awesome'));
	}
}
