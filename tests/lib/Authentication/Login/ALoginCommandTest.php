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

use OC\Authentication\Login\ALoginCommand;
use OC\Authentication\Login\LoginData;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

abstract class ALoginCommandTest extends TestCase {

	/** @var IRequest|MockObject */
	protected $request;

	/** @var string */
	protected $username = 'user123';

	/** @var string */
	protected $password = '123456';

	/** @var string */
	protected $redirectUrl = '/apps/contacts';

	/** @var string */
	protected $timezone = 'Europe/Vienna';

	protected $timeZoneOffset = '2';

	/** @var IUser|MockObject */
	protected $user;

	/** @var ALoginCommand */
	protected $cmd;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->user = $this->createMock(IUser::class);
	}

	protected function getBasicLoginData(): LoginData {
		return new LoginData(
			$this->request,
			$this->username,
			$this->password
		);
	}

	protected function getInvalidLoginData(): LoginData {
		return new LoginData(
			$this->request,
			$this->username,
			$this->password
		);
	}

	protected function getFailedLoginData(): LoginData {
		$data = new LoginData(
			$this->request,
			$this->username,
			$this->password
		);
		$data->setUser(false);
		return $data;
	}

	protected function getLoggedInLoginData(): LoginData {
		$basic = $this->getBasicLoginData();
		$basic->setUser($this->user);
		return $basic;
	}

	protected function getLoggedInLoginDataWithRedirectUrl(): LoginData {
		$data = new LoginData(
			$this->request,
			$this->username,
			$this->password,
			$this->redirectUrl
		);
		$data->setUser($this->user);
		return $data;
	}

	protected function getLoggedInLoginDataWithTimezone(): LoginData {
		$data = new LoginData(
			$this->request,
			$this->username,
			$this->password,
			null,
			$this->timezone,
			$this->timeZoneOffset
		);
		$data->setUser($this->user);
		return $data;
	}

}
