<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use OCA\DAV\Connector\Sabre\BearerAuth;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Test\TestCase;

/**
 * @group DB
 */
class BearerAuthTest extends TestCase {
	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	private $userSession;
	/** @var ISession|\PHPUnit_Framework_MockObject_MockObject */
	private $session;
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var BearerAuth */
	private $bearerAuth;

	protected function setUp(): void {
		parent::setUp();

		$this->userSession = $this->createMock(\OC\User\Session::class);
		$this->session = $this->createMock(ISession::class);
		$this->request = $this->createMock(IRequest::class);

		$this->bearerAuth = new BearerAuth(
			$this->userSession,
			$this->session,
			$this->request
		);
	}

	public function testValidateBearerTokenNotLoggedIn() {
		$this->assertFalse($this->bearerAuth->validateBearerToken('Token'));
	}

	public function testValidateBearerToken() {
		$this->userSession
			->expects($this->at(0))
			->method('isLoggedIn')
			->willReturn(false);
		$this->userSession
			->expects($this->at(2))
			->method('isLoggedIn')
			->willReturn(true);
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->assertSame('principals/users/admin', $this->bearerAuth->validateBearerToken('Token'));
	}

	public function testChallenge() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|RequestInterface $request */
		$request = $this->createMock(RequestInterface::class);
		/** @var \PHPUnit_Framework_MockObject_MockObject|ResponseInterface $response */
		$response = $this->createMock(ResponseInterface::class);
		$result = $this->bearerAuth->challenge($request, $response);
		$this->assertEmpty($result);
	}
}
