<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCP\User\Events;

use OCP\EventDispatcher\Event;

/**
 * @since 18.0.0
 */
class BeforeUserLoggedInEvent extends Event {

	/** @var string */
	private $username;

	/** @var string */
	private $password;

	/**
	 * @since 18.0.0
	 */
	public function __construct(string $username, string $password) {
		parent::__construct();
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * returns the login name, which must not necessarily match to a user ID
	 *
	 * @since 18.0.0
	 */
	public function getUsername(): string {
		return $this->username;
	}

	/**
	 * @since 18.0.0
	 */
	public function getPassword(): string {
		return $this->password;
	}

}
