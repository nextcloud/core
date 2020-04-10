<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

namespace Test\Security;

use OC\Security\Certificate;

class CertificateTest extends \Test\TestCase {

	/** @var Certificate That contains a valid certificate */
	protected $goodCertificate;
	/** @var Certificate That contains an invalid certificate */
	protected $invalidCertificate;
	/** @var Certificate That contains an expired certificate */
	protected $expiredCertificate;

	protected function setUp(): void {
		parent::setUp();

		$goodCertificate = file_get_contents(__DIR__ . '/../../data/certificates/goodCertificate.crt');
		$this->goodCertificate = new Certificate($goodCertificate, 'GoodCertificate');
		$badCertificate = file_get_contents(__DIR__ . '/../../data/certificates/badCertificate.crt');
		$this->invalidCertificate = new Certificate($badCertificate, 'BadCertificate');
		$expiredCertificate = file_get_contents(__DIR__ . '/../../data/certificates/expiredCertificate.crt');
		$this->expiredCertificate = new Certificate($expiredCertificate, 'ExpiredCertificate');
	}

	
	public function testBogusData() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Certificate could not get parsed.');

		$certificate = new Certificate('foo', 'bar');
		$certificate->getIssueDate();
	}

	
	function testCertificateStartingWithFileReference() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Certificate could not get parsed.');

		new Certificate('file://'.__DIR__ . '/../../data/certificates/goodCertificate.crt', 'bar');
	}

	public function testGetName() {
		$this->assertSame('GoodCertificate', $this->goodCertificate->getName());
		$this->assertSame('BadCertificate', $this->invalidCertificate->getName());
	}

	public function testGetCommonName() {
		$this->assertSame('security.owncloud.com', $this->goodCertificate->getCommonName());
		$this->assertSame(null, $this->invalidCertificate->getCommonName());
	}

	public function testGetOrganization() {
		$this->assertSame('ownCloud Security', $this->goodCertificate->getOrganization());
		$this->assertSame('Internet Widgits Pty Ltd', $this->invalidCertificate->getOrganization());
	}

	public function testGetIssueDate() {
		$expected = new \DateTime('2015-08-27 20:03:42 GMT');
		$this->assertEquals($expected->getTimestamp(), $this->goodCertificate->getIssueDate()->getTimestamp());
		$expected = new \DateTime('2015-08-27 20:19:13 GMT');
		$this->assertEquals($expected->getTimestamp(), $this->invalidCertificate->getIssueDate()->getTimestamp());
	}

	public function testGetExpireDate() {
		$expected = new \DateTime('2025-08-24 20:03:42 GMT');
		$this->assertEquals($expected->getTimestamp(), $this->goodCertificate->getExpireDate()->getTimestamp());
		$expected = new \DateTime('2025-08-24 20:19:13 GMT');
		$this->assertEquals($expected->getTimestamp(), $this->invalidCertificate->getExpireDate()->getTimestamp());
		$expected = new \DateTime('2014-08-28 09:12:43 GMT');
		$this->assertEquals($expected->getTimestamp(), $this->expiredCertificate->getExpireDate()->getTimestamp());
	}

	public function testIsExpired() {
		$this->assertSame(false, $this->goodCertificate->isExpired());
		$this->assertSame(false, $this->invalidCertificate->isExpired());
		$this->assertSame(true, $this->expiredCertificate->isExpired());
	}

	public function testGetIssuerName() {
		$this->assertSame('security.owncloud.com', $this->goodCertificate->getIssuerName());
		$this->assertSame(null, $this->invalidCertificate->getIssuerName());
		$this->assertSame(null, $this->expiredCertificate->getIssuerName());
	}

	public function testGetIssuerOrganization() {
		$this->assertSame('ownCloud Security', $this->goodCertificate->getIssuerOrganization());
		$this->assertSame('Internet Widgits Pty Ltd', $this->invalidCertificate->getIssuerOrganization());
		$this->assertSame('Internet Widgits Pty Ltd', $this->expiredCertificate->getIssuerOrganization());
	}
}
