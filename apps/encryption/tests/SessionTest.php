<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Encryption\Tests;

use OCA\Encryption\Session;
use OCP\ISession;
use Test\TestCase;

class SessionTest extends TestCase {
	private static $tempStorage = [];
	/**
	 * @var Session
	 */
	private $instance;
	/** @var \OCP\ISession|\PHPUnit\Framework\MockObject\MockObject */
	private $sessionMock;


	public function testThatGetPrivateKeyThrowsExceptionWhenNotSet() {
		$this->expectException(\OCA\Encryption\Exceptions\PrivateKeyMissingException::class);
		$this->expectExceptionMessage('Private Key missing for user: please try to log-out and log-in again');

		$this->instance->getPrivateKey();
	}

	/**
	 * @depends testThatGetPrivateKeyThrowsExceptionWhenNotSet
	 */
	public function testSetAndGetPrivateKey() {
		$this->instance->setPrivateKey('dummyPrivateKey');
		$this->assertEquals('dummyPrivateKey', $this->instance->getPrivateKey());
	}

	/**
	 * @depends testSetAndGetPrivateKey
	 */
	public function testIsPrivateKeySet() {
		$this->instance->setPrivateKey('dummyPrivateKey');
		$this->assertTrue($this->instance->isPrivateKeySet());

		unset(self::$tempStorage['privateKey']);
		$this->assertFalse($this->instance->isPrivateKeySet());

		// Set private key back so we can test clear method
		self::$tempStorage['privateKey'] = 'dummyPrivateKey';
	}

	public function testDecryptAllModeActivated() {
		$this->instance->prepareDecryptAll('user1', 'usersKey');
		$this->assertTrue($this->instance->decryptAllModeActivated());
		$this->assertSame('user1', $this->instance->getDecryptAllUid());
		$this->assertSame('usersKey', $this->instance->getDecryptAllKey());
	}

	public function testDecryptAllModeDeactivated() {
		$this->assertFalse($this->instance->decryptAllModeActivated());
	}

	/**
	 * @expectExceptionMessage 'Please activate decrypt all mode first'
	 */
	public function testGetDecryptAllUidException() {
		$this->expectException(\Exception::class);

		$this->instance->getDecryptAllUid();
	}

	/**
	 * @expectExceptionMessage 'No uid found while in decrypt all mode'
	 */
	public function testGetDecryptAllUidException2() {
		$this->expectException(\Exception::class);

		$this->instance->prepareDecryptAll(null, 'key');
		$this->instance->getDecryptAllUid();
	}

	/**
	 * @expectExceptionMessage 'Please activate decrypt all mode first'
	 */
	public function testGetDecryptAllKeyException() {
		$this->expectException(\OCA\Encryption\Exceptions\PrivateKeyMissingException::class);

		$this->instance->getDecryptAllKey();
	}

	/**
	 * @expectExceptionMessage 'No key found while in decrypt all mode'
	 */
	public function testGetDecryptAllKeyException2() {
		$this->expectException(\OCA\Encryption\Exceptions\PrivateKeyMissingException::class);

		$this->instance->prepareDecryptAll('user', null);
		$this->instance->getDecryptAllKey();
	}


	public function testSetAndGetStatusWillSetAndReturn() {
		// Check if get status will return 0 if it has not been set before
		$this->assertEquals(0, $this->instance->getStatus());

		$this->instance->setStatus(Session::NOT_INITIALIZED);
		$this->assertEquals(0, $this->instance->getStatus());

		$this->instance->setStatus(Session::INIT_EXECUTED);
		$this->assertEquals(1, $this->instance->getStatus());

		$this->instance->setStatus(Session::INIT_SUCCESSFUL);
		$this->assertEquals(2, $this->instance->getStatus());
	}

	/**
	 * @dataProvider dataTestIsReady
	 *
	 * @param int $status
	 * @param bool $expected
	 */
	public function testIsReady($status, $expected) {
		/** @var Session | \PHPUnit\Framework\MockObject\MockObject $instance */
		$instance = $this->getMockBuilder(Session::class)
			->setConstructorArgs([$this->sessionMock])
			->setMethods(['getStatus'])->getMock();

		$instance->expects($this->once())->method('getStatus')
			->willReturn($status);

		$this->assertSame($expected, $instance->isReady());
	}

	public function dataTestIsReady() {
		return [
			[Session::INIT_SUCCESSFUL, true],
			[Session::INIT_EXECUTED, false],
			[Session::NOT_INITIALIZED, false],
		];
	}

	/**
	 * @param $key
	 * @param $value
	 */
	public function setValueTester($key, $value) {
		self::$tempStorage[$key] = $value;
	}

	/**
	 * @param $key
	 */
	public function removeValueTester($key) {
		unset(self::$tempStorage[$key]);
	}

	/**
	 * @param $key
	 * @return mixed
	 */
	public function getValueTester($key) {
		if (!empty(self::$tempStorage[$key])) {
			return self::$tempStorage[$key];
		}
		return null;
	}


	public function testClearWillRemoveValues() {
		$this->instance->setPrivateKey('privateKey');
		$this->instance->setStatus('initStatus');
		$this->instance->prepareDecryptAll('user', 'key');
		$this->assertNotEmpty(self::$tempStorage);
		$this->instance->clear();
		$this->assertEmpty(self::$tempStorage);
	}


	protected function setUp(): void {
		parent::setUp();
		$this->sessionMock = $this->createMock(ISession::class);

		$this->sessionMock->expects($this->any())
			->method('set')
			->willReturnCallback([$this, "setValueTester"]);

		$this->sessionMock->expects($this->any())
			->method('get')
			->willReturnCallback([$this, "getValueTester"]);

		$this->sessionMock->expects($this->any())
			->method('remove')
			->willReturnCallback([$this, "removeValueTester"]);


		$this->instance = new Session($this->sessionMock);
	}

	protected function tearDown(): void {
		self::$tempStorage = [];
		parent::tearDown();
	}
}
