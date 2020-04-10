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

use OC\Authentication\Login\UserDisabledCheckCommand;
use OC\Core\Controller\LoginController;
use OCP\ILogger;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

class UserDisabledCheckCommandTest extends ALoginCommandTest {

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var ILogger|MockObject */
	private $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->cmd = new UserDisabledCheckCommand(
			$this->userManager,
			$this->logger
		);
	}

	public function testProcessNonExistingUser() {
		$data = $this->getBasicLoginData();
		$this->userManager->expects($this->once())
			->method('get')
			->with($this->username)
			->willReturn(null);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
	}

	public function testProcessDisabledUser() {
		$data = $this->getBasicLoginData();
		$this->userManager->expects($this->once())
			->method('get')
			->with($this->username)
			->willReturn($this->user);
		$this->user->expects($this->once())
			->method('isEnabled')
			->willReturn(false);

		$result = $this->cmd->process($data);

		$this->assertFalse($result->isSuccess());
		$this->assertSame(LoginController::LOGIN_MSG_USERDISABLED, $result->getErrorMessage());
	}

	public function testProcess() {
		$data = $this->getBasicLoginData();
		$this->userManager->expects($this->once())
			->method('get')
			->with($this->username)
			->willReturn($this->user);
		$this->user->expects($this->once())
			->method('isEnabled')
			->willReturn(true);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
	}

}
