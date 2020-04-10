<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\unit\Connector\Sabre\RequestTest;

use Sabre\HTTP\Request;
use Sabre\HTTP\Response;

class Sapi {
	/**
	 * @var \Sabre\HTTP\Request
	 */
	private $request;

	/**
	 * @var \Sabre\HTTP\Response
	 */
	private $response;

	/**
	 * This static method will create a new Request object, based on the
	 * current PHP request.
	 *
	 * @return \Sabre\HTTP\Request
	 */
	public function getRequest() {
		return $this->request;
	}

	public function __construct(Request $request) {
		$this->request = $request;
	}

	/**
	 * @param \Sabre\HTTP\Response $response
	 * @return void
	 */
	public function sendResponse(Response $response) {
		// we need to copy the body since we close the source stream
		$copyStream = fopen('php://temp', 'r+');
		if (is_string($response->getBody())) {
			fwrite($copyStream, $response->getBody());
		} else if (is_resource($response->getBody())) {
			stream_copy_to_stream($response->getBody(), $copyStream);
		}
		rewind($copyStream);
		$this->response = new Response($response->getStatus(), $response->getHeaders(), $copyStream);
	}

	/**
	 * @return \Sabre\HTTP\Response
	 */
	public function getResponse() {
		return $this->response;
	}
}
