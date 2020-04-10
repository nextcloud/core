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

use OC\Authentication\Login\CompleteLoginCommand;
use OC\User\Session;
use PHPUnit\Framework\MockObject\MockObject;

class CompleteLoginCommandTest extends ALoginCommandTest {

	/** @var Session|MockObject */
	private $session;

	protected function setUp(): void {
		parent::setUp();

		$this->session = $this->createMock(Session::class);

		$this->cmd = new CompleteLoginCommand(
			$this->session
		);
	}

	public function testProcess() {
		$data = $this->getLoggedInLoginData();
		$this->session->expects($this->once())
			->method('completeLogin')
			->with(
				$this->user,
				$this->equalTo(
					[
						'loginName' => $this->username,
						'password' => $this->password,
					]
				)
			);

		$result = $this->cmd->process($data);

		$this->assertTrue($result->isSuccess());
	}


}
