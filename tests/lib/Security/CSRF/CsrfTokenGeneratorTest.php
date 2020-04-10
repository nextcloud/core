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

class CsrfTokenGeneratorTest extends \Test\TestCase {
	/** @var \OCP\Security\ISecureRandom */
	private $random;
	/** @var \OC\Security\CSRF\CsrfTokenGenerator */
	private $csrfTokenGenerator;

	protected function setUp(): void {
		parent::setUp();
		$this->random = $this->getMockBuilder('\OCP\Security\ISecureRandom')
			->disableOriginalConstructor()->getMock();
		$this->csrfTokenGenerator = new \OC\Security\CSRF\CsrfTokenGenerator($this->random);

	}

	public function testGenerateTokenWithCustomNumber() {
		$this->random
			->expects($this->once())
			->method('generate')
			->with(3)
			->willReturn('abc');
		$this->assertSame('abc', $this->csrfTokenGenerator->generateToken(3));
	}

	public function testGenerateTokenWithDefault() {
		$this->random
			->expects($this->once())
			->method('generate')
			->with(32)
			->willReturn('12345678901234567890123456789012');
		$this->assertSame('12345678901234567890123456789012', $this->csrfTokenGenerator->generateToken(32));
	}
}
