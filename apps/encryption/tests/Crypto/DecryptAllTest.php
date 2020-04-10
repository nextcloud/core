<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Encryption\Tests\Crypto;

use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\Crypto\DecryptAll;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Session;
use OCA\Encryption\Util;
use Symfony\Component\Console\Helper\QuestionHelper;
use Test\TestCase;

class DecryptAllTest extends TestCase {

	/** @var  DecryptAll */
	protected $instance;

	/** @var Util | \PHPUnit_Framework_MockObject_MockObject  */
	protected $util;

	/** @var KeyManager | \PHPUnit_Framework_MockObject_MockObject  */
	protected $keyManager;

	/** @var  Crypt | \PHPUnit_Framework_MockObject_MockObject */
	protected $crypt;

	/** @var  Session | \PHPUnit_Framework_MockObject_MockObject */
	protected $session;

	/** @var QuestionHelper | \PHPUnit_Framework_MockObject_MockObject  */
	protected $questionHelper;

	protected function setUp(): void {
		parent::setUp();

		$this->util = $this->getMockBuilder(Util::class)
			->disableOriginalConstructor()->getMock();
		$this->keyManager = $this->getMockBuilder(KeyManager::class)
			->disableOriginalConstructor()->getMock();
		$this->crypt = $this->getMockBuilder(Crypt::class)
			->disableOriginalConstructor()->getMock();
		$this->session = $this->getMockBuilder(Session::class)
			->disableOriginalConstructor()->getMock();
		$this->questionHelper = $this->getMockBuilder(QuestionHelper::class)
			->disableOriginalConstructor()->getMock();

		$this->instance = new DecryptAll(
			$this->util,
			$this->keyManager,
			$this->crypt,
			$this->session,
			$this->questionHelper
		);
	}

	public function testUpdateSession() {
		$this->session->expects($this->once())->method('prepareDecryptAll')
			->with('user1', 'key1');

		$this->invokePrivate($this->instance, 'updateSession', ['user1', 'key1']);
	}

	/**
	 * @dataProvider dataTestGetPrivateKey
	 *
	 * @param string $user
	 * @param string $recoveryKeyId
	 */
	public function testGetPrivateKey($user, $recoveryKeyId, $masterKeyId) {
		$password = 'passwd';
		$recoveryKey = 'recoveryKey';
		$userKey = 'userKey';
		$unencryptedKey = 'unencryptedKey';

		$this->keyManager->expects($this->any())->method('getRecoveryKeyId')
			->willReturn($recoveryKeyId);

		if ($user === $recoveryKeyId) {
			$this->keyManager->expects($this->once())->method('getSystemPrivateKey')
				->with($recoveryKeyId)->willReturn($recoveryKey);
			$this->keyManager->expects($this->never())->method('getPrivateKey');
			$this->crypt->expects($this->once())->method('decryptPrivateKey')
				->with($recoveryKey, $password)->willReturn($unencryptedKey);
		} elseif ($user === $masterKeyId) {
			$this->keyManager->expects($this->once())->method('getSystemPrivateKey')
				->with($masterKeyId)->willReturn($masterKey);
			$this->keyManager->expects($this->never())->method('getPrivateKey');
			$this->crypt->expects($this->once())->method('decryptPrivateKey')
				->with($masterKey, $password, $masterKeyId)->willReturn($unencryptedKey);

		} else {
			$this->keyManager->expects($this->never())->method('getSystemPrivateKey');
			$this->keyManager->expects($this->once())->method('getPrivateKey')
				->with($user)->willReturn($userKey);
			$this->crypt->expects($this->once())->method('decryptPrivateKey')
				->with($userKey, $password, $user)->willReturn($unencryptedKey);
		}

		$this->assertSame($unencryptedKey,
			$this->invokePrivate($this->instance, 'getPrivateKey', [$user, $password])
		);
	}

	public function dataTestGetPrivateKey() {
		return [
			['user1', 'recoveryKey', 'masterKeyId'],
			['recoveryKeyId', 'recoveryKeyId', 'masterKeyId'],
			['masterKeyId', 'masterKeyId', 'masterKeyId']
		];
	}

}
