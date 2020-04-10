<?php
/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\AppFramework\Controller;

use OCP\AppFramework\PublicShareController;
use OCP\IRequest;
use OCP\ISession;

class PublicShareControllerTest extends \Test\TestCase {

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var ISession|\PHPUnit_Framework_MockObject_MockObject */
	private $session;

	/** @var PublicShareController|\PHPUnit_Framework_MockObject_MockObject */
	private $controller;


	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->session = $this->createMock(ISession::class);

		$this->controller = $this->getMockBuilder(PublicShareController::class)
			->setConstructorArgs([
				'app',
				$this->request,
				$this->session
			])->getMock();
	}

	public function testGetToken() {
		$this->controller->setToken('test');
		$this->assertEquals('test', $this->controller->getToken());
	}

	public function dataIsAuthenticated() {
		return [
			[false, 'token1', 'token1', 'hash1', 'hash1',  true],
			[false, 'token1', 'token1', 'hash1', 'hash2',  true],
			[false, 'token1', 'token2', 'hash1', 'hash1',  true],
			[false, 'token1', 'token2', 'hash1', 'hash2',  true],
			[ true, 'token1', 'token1', 'hash1', 'hash1',  true],
			[ true, 'token1', 'token1', 'hash1', 'hash2', false],
			[ true, 'token1', 'token2', 'hash1', 'hash1', false],
			[ true, 'token1', 'token2', 'hash1', 'hash2', false],
		];
	}

	/**
	 * @dataProvider dataIsAuthenticated
	 */
	public function testIsAuthenticatedNotPasswordProtected(bool $protected, string $token1, string $token2, string $hash1, string $hash2, bool $expected) {
		$this->controller->method('isPasswordProtected')
			->willReturn($protected);

		$this->session->method('get')
			->willReturnMap([
				['public_link_authenticated_token', $token1],
				['public_link_authenticated_password_hash', $hash1],
			]);

		$this->controller->setToken($token2);
		$this->controller->method('getPasswordHash')
			->willReturn($hash2);

		$this->assertEquals($expected, $this->controller->isAuthenticated());
	}

}
