<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\User_LDAP\Tests;

use OCA\User_LDAP\LDAP;
use Test\TestCase;

class LDAPTest extends TestCase {
	/** @var LDAP|\PHPUnit\Framework\MockObject\MockObject */
	private $ldap;

	protected function setUp(): void {
		parent::setUp();
		$this->ldap = $this->getMockBuilder(LDAP::class)
			->setMethods(['invokeLDAPMethod'])
			->getMock();
	}

	public function errorProvider() {
		return [
			[
				'ldap_search(): Partial search results returned: Sizelimit exceeded at /srv/http/nextcloud/master/apps/user_ldap/lib/LDAP.php#292',
				false
			],
			[
				'Some other error', true
			]
		];
	}

	/**
	 * @param string $errorMessage
	 * @param bool $passThrough
	 * @dataProvider errorProvider
	 */
	public function testSearchWithErrorHandler(string $errorMessage, bool $passThrough) {
		$wasErrorHandlerCalled = false;
		$errorHandler = function ($number, $message, $file, $line) use (&$wasErrorHandlerCalled) {
			$wasErrorHandlerCalled = true;
		};

		set_error_handler($errorHandler);

		$this->ldap
			->expects($this->once())
			->method('invokeLDAPMethod')
			->with('search', $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything())
			->willReturnCallback(function () use ($errorMessage) {
				trigger_error($errorMessage);
			});

		$fakeResource = ldap_connect();
		$this->ldap->search($fakeResource, 'base', 'filter', []);
		$this->assertSame($wasErrorHandlerCalled, $passThrough);

		restore_error_handler();
	}

	public function testModReplace() {
		$link = $this->createMock(LDAP::class);
		$userDN = 'CN=user';
		$password = 'MyPassword';
		$this->ldap
			->expects($this->once())
			->method('invokeLDAPMethod')
			->with('mod_replace', $link, $userDN, ['userPassword' => $password])
			->willReturn(true);

		$this->assertTrue($this->ldap->modReplace($link, $userDN, $password));
	}
}
