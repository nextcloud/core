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

use OC\Authentication\Login\EmailLoginCommand;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

class EmailLoginCommandTest extends ALoginCommandTest {

	/** @var IUserManager|MockObject */
	private $userManager;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);

		$this->cmd = new EmailLoginCommand(
			$this->userManager
		);
	}

	public function testProcessAlreadyLoggedIn() {
		$data = $this->getLoggedInLoginData();

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
	}

	public function testProcessNotAnEmailLogin() {
		$data = $this->getFailedLoginData();
		$this->userManager->expects($this->once())
			->method('getByEmail')
			->with($this->username)
			->willReturn([]);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
	}

	public function testProcessDuplicateEmailLogin() {
		$data = $this->getFailedLoginData();
		$this->userManager->expects($this->once())
			->method('getByEmail')
			->with($this->username)
			->willReturn([
				$this->createMock(IUser::class),
				$this->createMock(IUser::class),
			]);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
	}

	public function testProcessUidIsEmail() {
		$email = 'user@domain.com';
		$data = $this->getFailedLoginData();
		$data->setUsername($email);
		$emailUser = $this->createMock(IUser::class);
		$emailUser->expects($this->any())
			->method('getUID')
			->willReturn($email);
		$this->userManager->expects($this->once())
			->method('getByEmail')
			->with($email)
			->willReturn([
				$emailUser,
			]);
		$this->userManager->expects($this->never())
			->method('checkPassword');

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertFalse($data->getUser());
		$this->assertEquals($email, $data->getUsername());
	}

	public function testProcessWrongPassword() {
		$email = 'user@domain.com';
		$data = $this->getFailedLoginData();
		$data->setUsername($email);
		$emailUser = $this->createMock(IUser::class);
		$emailUser->expects($this->any())
			->method('getUID')
			->willReturn('user2');
		$this->userManager->expects($this->once())
			->method('getByEmail')
			->with($email)
			->willReturn([
				$emailUser,
			]);
		$this->userManager->expects($this->once())
			->method('checkPassword')
			->with(
				'user2',
				$this->password
			)
			->willReturn(false);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertFalse($data->getUser());
		$this->assertEquals($email, $data->getUsername());
	}

	public function testProcess() {
		$email = 'user@domain.com';
		$data = $this->getFailedLoginData();
		$data->setUsername($email);
		$emailUser = $this->createMock(IUser::class);
		$emailUser->expects($this->any())
			->method('getUID')
			->willReturn('user2');
		$this->userManager->expects($this->once())
			->method('getByEmail')
			->with($email)
			->willReturn([
				$emailUser,
			]);
		$this->userManager->expects($this->once())
			->method('checkPassword')
			->with(
				'user2',
				$this->password
			)
			->willReturn($emailUser);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
		$this->assertEquals($emailUser, $data->getUser());
		$this->assertEquals('user2', $data->getUsername());
	}

}
