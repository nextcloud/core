<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\DAV;

use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class PublicAuth implements BackendInterface {

	/** @var string[] */
	private $publicURLs;

	public function __construct() {
		$this->publicURLs = [
			'public-calendars',
			'principals/system/public'
		];
	}

	/**
	 * When this method is called, the backend must check if authentication was
	 * successful.
	 *
	 * The returned value must be one of the following
	 *
	 * [true, "principals/username"]
	 * [false, "reason for failure"]
	 *
	 * If authentication was successful, it's expected that the authentication
	 * backend returns a so-called principal url.
	 *
	 * Examples of a principal url:
	 *
	 * principals/admin
	 * principals/user1
	 * principals/users/joe
	 * principals/uid/123457
	 *
	 * If you don't use WebDAV ACL (RFC3744) we recommend that you simply
	 * return a string such as:
	 *
	 * principals/users/[username]
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return array
	 */
	function check(RequestInterface $request, ResponseInterface $response) {

		if ($this->isRequestPublic($request)) {
			return [true, "principals/system/public"];
		}
		return [false, "No public access to this resource."];
	}

	/**
	 * @inheritdoc
	 */
	function challenge(RequestInterface $request, ResponseInterface $response) {
	}

	/**
	 * @param RequestInterface $request
	 * @return bool
	 */
	private function isRequestPublic(RequestInterface $request) {
		$url = $request->getPath();
		$matchingUrls = array_filter($this->publicURLs, function ($publicUrl) use ($url) {
			return strpos($url, $publicUrl, 0) === 0;
		});
		return !empty($matchingUrls);
	}
}
