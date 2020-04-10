<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
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

namespace Test\Session;

use OC\Session\CryptoSessionData;
use OCP\Security\ICrypto;

class CryptoSessionDataTest extends Session {
	/** @var \PHPUnit_Framework_MockObject_MockObject|\OCP\Security\ICrypto */
	protected $crypto;

	/** @var \OCP\ISession */
	protected $wrappedSession;

	protected function setUp(): void {
		parent::setUp();

		$this->wrappedSession = new \OC\Session\Memory($this->getUniqueID());
		$this->crypto = $this->createMock(ICrypto::class);
		$this->crypto->expects($this->any())
			->method('encrypt')
			->willReturnCallback(function ($input) {
				return '#' . $input . '#';
			});
		$this->crypto->expects($this->any())
			->method('decrypt')
			->willReturnCallback(function ($input) {
				if ($input === '') {
					return '';
				}
				return substr($input, 1, -1);
			});

		$this->instance = new CryptoSessionData($this->wrappedSession, $this->crypto, 'PASS');
	}
}
