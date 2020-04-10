<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
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

namespace Test\Share20;

use OCP\Files\IRootFolder;
use OCP\IUserManager;

/**
 * Class ShareTest
 *
 * @package Test\Share20
 */
class ShareTest extends \Test\TestCase {

	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	protected $rootFolder;
	/** @var \OCP\Share\IShare */
	protected $share;

	protected function setUp(): void {
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->share = new \OC\Share20\Share($this->rootFolder, $this->userManager);
	}

	
	public function testSetIdInvalid() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('String expected.');

		$this->share->setId(1.2);
	}

	public function testSetIdInt() {
		$this->share->setId(42);
		$this->assertEquals('42', $this->share->getId());
	}


	public function testSetIdString() {
		$this->share->setId('foo');
		$this->assertEquals('foo', $this->share->getId());
	}

	
	public function testSetIdOnce() {
		$this->expectException(\OCP\Share\Exceptions\IllegalIDChangeException::class);
		$this->expectExceptionMessage('Not allowed to assign a new internal id to a share');

		$this->share->setId('foo');
		$this->share->setId('bar');
	}

	
	public function testSetProviderIdInt() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('String expected.');

		$this->share->setProviderId(42);
	}


	public function testSetProviderIdString() {
		$this->share->setProviderId('foo');
		$this->share->setId('bar');
		$this->assertEquals('foo:bar', $this->share->getFullId());
	}

	
	public function testSetProviderIdOnce() {
		$this->expectException(\OCP\Share\Exceptions\IllegalIDChangeException::class);
		$this->expectExceptionMessage('Not allowed to assign a new provider id to a share');

		$this->share->setProviderId('foo');
		$this->share->setProviderId('bar');
	}
}
