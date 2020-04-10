<?php
/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

declare(strict_types=1);

namespace OC\Authentication\Login;

use OCP\IRequest;
use OCP\IUser;

class LoginData {

	/** @var IRequest */
	private $request;

	/** @var string */
	private $username;

	/** @var string */
	private $password;

	/** @var string */
	private $redirectUrl;

	/** @var string */
	private $timeZone;

	/** @var string */
	private $timeZoneOffset;

	/** @var IUser|false|null */
	private $user = null;

	/** @var bool */
	private $rememberLogin = true;

	public function __construct(IRequest $request,
								string $username,
								?string $password,
								string $redirectUrl = null,
								string $timeZone = '',
								string $timeZoneOffset = '') {
		$this->request = $request;
		$this->username = $username;
		$this->password = $password;
		$this->redirectUrl = $redirectUrl;
		$this->timeZone = $timeZone;
		$this->timeZoneOffset = $timeZoneOffset;
	}

	public function getRequest(): IRequest {
		return $this->request;
	}

	public function setUsername(string $username): void {
		$this->username = $username;
	}

	public function getUsername(): string {
		return $this->username;
	}

	public function getPassword(): ?string {
		return $this->password;
	}

	public function getRedirectUrl(): ?string {
		return $this->redirectUrl;
	}

	public function getTimeZone(): string {
		return $this->timeZone;
	}

	public function getTimeZoneOffset(): string {
		return $this->timeZoneOffset;
	}

	/**
	 * @param IUser|false|null $user
	 */
	public function setUser($user) {
		$this->user = $user;
	}

	/**
	 * @return false|IUser|null
	 */
	public function getUser() {
		return $this->user;
	}

	public function setRememberLogin(bool $rememberLogin): void {
		$this->rememberLogin = $rememberLogin;
	}

	public function isRememberLogin(): bool {
		return $this->rememberLogin;
	}

}
