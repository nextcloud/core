<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
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

namespace Test\Security\CSP;

use OC\AppFramework\Http\Request;
use OC\Security\CSP\ContentSecurityPolicyNonceManager;
use OC\Security\CSRF\CsrfToken;
use OC\Security\CSRF\CsrfTokenManager;
use Test\TestCase;

class ContentSecurityPolicyNonceManagerTest extends TestCase  {
	/** @var CsrfTokenManager */
	private $csrfTokenManager;
	/** @var Request */
	private $request;
	/** @var ContentSecurityPolicyNonceManager */
	private $nonceManager;

	protected function setUp(): void {
		$this->csrfTokenManager = $this->createMock(CsrfTokenManager::class);
		$this->request = $this->createMock(Request::class);
		$this->nonceManager = new ContentSecurityPolicyNonceManager(
			$this->csrfTokenManager,
			$this->request
		);
	}

	public function testGetNonce() {
		$token = $this->createMock(CsrfToken::class);
		$token
			->expects($this->once())
			->method('getEncryptedValue')
			->willReturn('MyToken');

		$this->csrfTokenManager
			->expects($this->once())
			->method('getToken')
			->willReturn($token);

		$this->assertSame('TXlUb2tlbg==', $this->nonceManager->getNonce());
		$this->assertSame('TXlUb2tlbg==', $this->nonceManager->getNonce());
	}

	public function testGetNonceServerVar() {
		$token = 'SERVERNONCE';
		$this->request
			->method('__isset')
			->with('server')
			->willReturn(true);

		$this->request
			->method('__get')
			->with('server')
			->willReturn(['CSP_NONCE' => $token]);

		$this->assertSame($token, $this->nonceManager->getNonce());
		$this->assertSame($token, $this->nonceManager->getNonce());
	}
}
