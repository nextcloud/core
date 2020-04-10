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

use OCP\AppFramework\Http\DownloadResponse;

class ChildDownloadResponse extends DownloadResponse {

};


class DownloadResponseTest extends \Test\TestCase {

	/**
	 * @var ChildDownloadResponse
	 */
	protected $response;

	protected function setUp(): void {
		parent::setUp();
		$this->response = new ChildDownloadResponse('file', 'content');
	}


	public function testHeaders() {
		$headers = $this->response->getHeaders();

		$this->assertContains('attachment; filename="file"', $headers['Content-Disposition']);
		$this->assertContains('content', $headers['Content-Type']);
	}


}
