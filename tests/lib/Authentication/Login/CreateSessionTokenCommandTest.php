<?php

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

declare(strict_types=1);

namespace lib\Authentication\Login;

use OC\Authentication\Login\CreateSessionTokenCommand;
use OC\Authentication\Token\IToken;
use OC\User\Session;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;

class CreateSessionTokenCommandTest extends ALoginCommandTest {

	/** @var IConfig|MockObject */
	private $config;

	/** @var Session|MockObject */
	private $userSession;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->userSession = $this->createMock(Session::class);

		$this->cmd = new CreateSessionTokenCommand(
			$this->config,
			$this->userSession
		);
	}

	public function testProcess() {
		$data = $this->getLoggedInLoginData();
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with(
				'remember_login_cookie_lifetime',
				60 * 60 * 24 * 15
			)
			->willReturn(100);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn($this->username);
		$this->userSession->expects($this->once())
			->method('createSessionToken')
			->with(
				$this->request,
				$this->username,
				$this->username,
				$this->password,
				IToken::REMEMBER
			);
		$this->userSession->expects($this->once())
			->method('updateTokens')
			->with(
				$this->username,
				$this->password
			);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
	}

	public function testProcessDoNotRemember() {
		$data = $this->getLoggedInLoginData();
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with(
				'remember_login_cookie_lifetime',
				60 * 60 * 24 * 15
			)
			->willReturn(0);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn($this->username);
		$this->userSession->expects($this->once())
			->method('createSessionToken')
			->with(
				$this->request,
				$this->username,
				$this->username,
				$this->password,
				IToken::DO_NOT_REMEMBER
			);
		$this->userSession->expects($this->once())
			->method('updateTokens')
			->with(
				$this->username,
				$this->password
			);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertFalse($data->isRememberLogin());
	}

}
