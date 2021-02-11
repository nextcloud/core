<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lionel Elie Mamane <lionel@mamane.lu>
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

namespace OCP\User\Events;

use OCP\EventDispatcher\Event;
use OCP\IUser;

/**
 * @since 18.0.0
 */
class PostLoginEvent extends Event {

	/** @var IUser */
	private $user;

	/**
	 * @since 20.0.0
	 * @var string
	 */
	private $loginName;

	/** @var string */
	private $password;

	/** @var bool */
	private $isTokenLogin;

	/**
	 * @since 18.0.0
	 */
	public function __construct(IUser $user, string $loginName, string $password, bool $isTokenLogin) {
		parent::__construct();
		$this->user = $user;
		$this->loginName = $loginName;
		$this->password = $password;
		$this->isTokenLogin = $isTokenLogin;
	}

	/**
	 * @since 18.0.0
	 */
	public function getUser(): IUser {
		return $this->user;
	}

	/**
	 * @since 20.0.0
	 */
	public function getLoginName(): string {
		return $this->loginName;
	}

	/**
	 * @since 18.0.0
	 */
	public function getPassword(): string {
		return $this->password;
	}

	/**
	 * @since 18.0.0
	 */
	public function isTokenLogin(): bool {
		return $this->isTokenLogin;
	}
}
