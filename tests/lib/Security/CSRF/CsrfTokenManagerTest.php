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

namespace Test\Security\CSRF;

class CsrfTokenManagerTest extends \Test\TestCase {
	/** @var \OC\Security\CSRF\CsrfTokenManager */
	private $csrfTokenManager;
	/** @var \OC\Security\CSRF\CsrfTokenGenerator */
	private $tokenGenerator;
	/** @var \OC\Security\CSRF\TokenStorage\SessionStorage */
	private $storageInterface;

	protected function setUp(): void {
		parent::setUp();
		$this->tokenGenerator = $this->getMockBuilder('\OC\Security\CSRF\CsrfTokenGenerator')
			->disableOriginalConstructor()->getMock();
		$this->storageInterface = $this->getMockBuilder('\OC\Security\CSRF\TokenStorage\SessionStorage')
			->disableOriginalConstructor()->getMock();

		$this->csrfTokenManager = new \OC\Security\CSRF\CsrfTokenManager(
			$this->tokenGenerator,
			$this->storageInterface
		);
	}

	public function testGetTokenWithExistingToken() {
		$this->storageInterface
			->expects($this->once())
			->method('hasToken')
			->willReturn(true);
		$this->storageInterface
			->expects($this->once())
			->method('getToken')
			->willReturn('MyExistingToken');

		$expected = new \OC\Security\CSRF\CsrfToken('MyExistingToken');
		$this->assertEquals($expected, $this->csrfTokenManager->getToken());
	}

	public function testGetTokenWithExistingTokenKeepsOnSecondRequest() {
		$this->storageInterface
			->expects($this->once())
			->method('hasToken')
			->willReturn(true);
		$this->storageInterface
			->expects($this->once())
			->method('getToken')
			->willReturn('MyExistingToken');

		$expected = new \OC\Security\CSRF\CsrfToken('MyExistingToken');
		$token = $this->csrfTokenManager->getToken();
		$this->assertSame($token, $this->csrfTokenManager->getToken());
		$this->assertSame($token, $this->csrfTokenManager->getToken());
	}

	public function testGetTokenWithoutExistingToken() {
		$this->storageInterface
			->expects($this->once())
			->method('hasToken')
			->willReturn(false);
		$this->tokenGenerator
			->expects($this->once())
			->method('generateToken')
			->willReturn('MyNewToken');
		$this->storageInterface
			->expects($this->once())
			->method('setToken')
			->with('MyNewToken');

		$expected = new \OC\Security\CSRF\CsrfToken('MyNewToken');
		$this->assertEquals($expected, $this->csrfTokenManager->getToken());
	}

	public function testRefreshToken() {
		$this->tokenGenerator
			->expects($this->once())
			->method('generateToken')
			->willReturn('MyNewToken');
		$this->storageInterface
			->expects($this->once())
			->method('setToken')
			->with('MyNewToken');

		$expected = new \OC\Security\CSRF\CsrfToken('MyNewToken');
		$this->assertEquals($expected, $this->csrfTokenManager->refreshToken());
	}

	public function testRemoveToken() {
		$this->storageInterface
			->expects($this->once())
			->method('removeToken');

		$this->csrfTokenManager->removeToken();
	}

	public function testIsTokenValidWithoutToken() {
		$this->storageInterface
			->expects($this->once())
			->method('hasToken')
			->willReturn(false);
		$token = new \OC\Security\CSRF\CsrfToken('Token');

		$this->assertSame(false, $this->csrfTokenManager->isTokenValid($token));
	}

	public function testIsTokenValidWithWrongToken() {
		$this->storageInterface
			->expects($this->once())
			->method('hasToken')
			->willReturn(true);
		$token = new \OC\Security\CSRF\CsrfToken('Token');
		$this->storageInterface
			->expects($this->once())
			->method('getToken')
			->willReturn('MyToken');

		$this->assertSame(false, $this->csrfTokenManager->isTokenValid($token));
	}

	public function testIsTokenValidWithValidToken() {
		$a = 'abc';
		$b = 'def';
		$xorB64 = 'BQcF';
		$tokenVal = sprintf('%s:%s', $xorB64, base64_encode($a));
		$this->storageInterface
				->expects($this->once())
				->method('hasToken')
				->willReturn(true);
		$token = new \OC\Security\CSRF\CsrfToken($tokenVal);
		$this->storageInterface
				->expects($this->once())
				->method('getToken')
				->willReturn($b);

		$this->assertSame(true, $this->csrfTokenManager->isTokenValid($token));
	}
}
