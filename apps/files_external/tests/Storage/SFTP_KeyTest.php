<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Ross Nicoll <jrn@jrn.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Viktor Szépe <viktor@szepe.net>
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

namespace OCA\Files_External\Tests\Storage;

use OCA\Files_External\Lib\Storage\SFTP_Key;

/**
 * Class SFTP_KeyTest
 *
 * @group DB
 *
 * @package OCA\Files_External\Tests\Storage
 */
class SFTP_KeyTest extends \Test\Files\Storage\Storage {
	private $config;

	protected function setUp(): void {
		parent::setUp();

		$id = $this->getUniqueID();
		$this->config = include('files_external/tests/config.php');
		if (! is_array($this->config) or ! isset($this->config['sftp_key']) or ! $this->config['sftp_key']['run']) {
			$this->markTestSkipped('SFTP with key backend not configured');
		}
		// Make sure we have an new empty folder to work in
		$this->config['sftp_key']['root'] .= '/' . $id;
		$this->instance = new SFTP_Key($this->config['sftp_key']);
		$this->instance->mkdir('/');
	}

	protected function tearDown(): void {
		if ($this->instance) {
			$this->instance->rmdir('/');
		}

		parent::tearDown();
	}

	
	public function testInvalidAddressShouldThrowException() {
		$this->expectException(\InvalidArgumentException::class);

		// I'd use example.com for this, but someone decided to break the spec and make it resolve
		$this->instance->assertHostAddressValid('notarealaddress...');
	}

	public function testValidAddressShouldPass() {
		$this->assertTrue($this->instance->assertHostAddressValid('localhost'));
	}

	
	public function testNegativePortNumberShouldThrowException() {
		$this->expectException(\InvalidArgumentException::class);

		$this->instance->assertPortNumberValid('-1');
	}

	
	public function testNonNumericalPortNumberShouldThrowException() {
		$this->expectException(\InvalidArgumentException::class);

		$this->instance->assertPortNumberValid('a');
	}

	
	public function testHighPortNumberShouldThrowException() {
		$this->expectException(\InvalidArgumentException::class);
 
		$this->instance->assertPortNumberValid('65536');
	}

	public function testValidPortNumberShouldPass() {
		$this->assertTrue($this->instance->assertPortNumberValid('22222'));
	}
}
