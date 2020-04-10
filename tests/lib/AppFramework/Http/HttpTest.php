<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt <dev@bernhard-posselt.com>
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

use OC\AppFramework\Http;

class HttpTest extends \Test\TestCase {

	private $server;

	/**
	 * @var Http
	 */
	private $http;

	protected function setUp(): void {
		parent::setUp();

		$this->server = [];
		$this->http = new Http($this->server);
	}


	public function testProtocol() {
		$header = $this->http->getStatusHeader(Http::STATUS_TEMPORARY_REDIRECT);
		$this->assertEquals('HTTP/1.1 307 Temporary Redirect', $header);
	}


	public function testProtocol10() {
		$this->http = new Http($this->server, 'HTTP/1.0');
		$header = $this->http->getStatusHeader(Http::STATUS_OK);
		$this->assertEquals('HTTP/1.0 200 OK', $header);
	}


	public function testEtagMatchReturnsNotModified() {
		$http = new Http(['HTTP_IF_NONE_MATCH' => 'hi']);

		$header = $http->getStatusHeader(Http::STATUS_OK, null, 'hi');
		$this->assertEquals('HTTP/1.1 304 Not Modified', $header);
	}


	public function testQuotedEtagMatchReturnsNotModified() {
		$http = new Http(['HTTP_IF_NONE_MATCH' => '"hi"']);

		$header = $http->getStatusHeader(Http::STATUS_OK, null, 'hi');
		$this->assertEquals('HTTP/1.1 304 Not Modified', $header);
	}


	public function testLastModifiedMatchReturnsNotModified() {
		$dateTime = new \DateTime(null, new \DateTimeZone('GMT'));
		$dateTime->setTimestamp('12');

		$http = new Http(
			[
				'HTTP_IF_MODIFIED_SINCE' => 'Thu, 01 Jan 1970 00:00:12 +0000']
			);

		$header = $http->getStatusHeader(Http::STATUS_OK, $dateTime);
		$this->assertEquals('HTTP/1.1 304 Not Modified', $header);
	}



	public function testTempRedirectBecomesFoundInHttp10() {
		$http = new Http([], 'HTTP/1.0');

		$header = $http->getStatusHeader(Http::STATUS_TEMPORARY_REDIRECT);
		$this->assertEquals('HTTP/1.0 302 Found', $header);
	}
	// TODO: write unittests for http codes

}
