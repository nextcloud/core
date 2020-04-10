<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Security\CSRF\TokenStorage;

use OCP\ISession;

class SessionStorageTest extends \Test\TestCase {
	/** @var \OCP\ISession */
	private $session;
	/** @var \OC\Security\CSRF\TokenStorage\SessionStorage */
	private $sessionStorage;

	protected function setUp(): void {
		parent::setUp();
		$this->session = $this->getMockBuilder(ISession::class)
			->disableOriginalConstructor()->getMock();
		$this->sessionStorage = new \OC\Security\CSRF\TokenStorage\SessionStorage($this->session);
	}

	/**
	 * @return array
	 */
	public function getTokenDataProvider() {
		return [
			[
				'',
			],
			[
				null,
			],
		];
	}

	/**
	 * @param string $token
	 * @dataProvider getTokenDataProvider
	 *
	 */
	public function testGetTokenWithEmptyToken($token) {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Session does not contain a requesttoken');

		$this->session
			->expects($this->once())
			->method('get')
			->with('requesttoken')
			->willReturn($token);
		$this->sessionStorage->getToken();
	}

	public function testGetTokenWithValidToken() {
		$this->session
			->expects($this->once())
			->method('get')
			->with('requesttoken')
			->willReturn('MyFancyCsrfToken');
		$this->assertSame('MyFancyCsrfToken', $this->sessionStorage->getToken());
	}

	public function testSetToken() {
		$this->session
			->expects($this->once())
			->method('set')
			->with('requesttoken', 'TokenToSet');
		$this->sessionStorage->setToken('TokenToSet');
	}

	public function testRemoveToken() {
		$this->session
			->expects($this->once())
			->method('remove')
			->with('requesttoken');
		$this->sessionStorage->removeToken();
	}

	public function testHasTokenWithExistingToken() {
		$this->session
			->expects($this->once())
			->method('exists')
			->with('requesttoken')
			->willReturn(true);
		$this->assertSame(true, $this->sessionStorage->hasToken());
	}

	public function testHasTokenWithoutExistingToken() {
		$this->session
			->expects($this->once())
			->method('exists')
			->with('requesttoken')
			->willReturn(false);
		$this->assertSame(false, $this->sessionStorage->hasToken());
	}

	public function testSetSession() {
		$session = $this->createMock(ISession::class);
		$session
			->expects($this->once())
			->method('get')
			->with('requesttoken')
			->willReturn('MyToken');
		$this->sessionStorage->setSession($session);
		$this->assertSame('MyToken', $this->sessionStorage->getToken());
	}
}
