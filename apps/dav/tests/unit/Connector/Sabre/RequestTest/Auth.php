<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre\RequestTest;

use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class Auth implements BackendInterface {
	/**
	 * @var string
	 */
	private $user;

	/**
	 * @var string
	 */
	private $password;

	/**
	 * Auth constructor.
	 *
	 * @param string $user
	 * @param string $password
	 */
	public function __construct($user, $password) {
		$this->user = $user;
		$this->password = $password;
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
		$userSession = \OC::$server->getUserSession();
		$result = $userSession->login($this->user, $this->password);
		if ($result) {
			//we need to pass the user name, which may differ from login name
			$user = $userSession->getUser()->getUID();
			\OC_Util::setupFS($user);
			//trigger creation of user home and /files folder
			\OC::$server->getUserFolder($user);
			return [true, "principals/$user"];
		}
		return [false, "login failed"];
	}

	/**
	 * This method is called when a user could not be authenticated, and
	 * authentication was required for the current request.
	 *
	 * This gives you the opportunity to set authentication headers. The 401
	 * status code will already be set.
	 *
	 * In this case of Basic Auth, this would for example mean that the
	 * following header needs to be set:
	 *
	 * $response->addHeader('WWW-Authenticate', 'Basic realm=SabreDAV');
	 *
	 * Keep in mind that in the case of multiple authentication backends, other
	 * WWW-Authenticate headers may already have been set, and you'll want to
	 * append your own WWW-Authenticate header instead of overwriting the
	 * existing one.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return void
	 */
	function challenge(RequestInterface $request, ResponseInterface $response) {
		// TODO: Implement challenge() method.
	}
}
