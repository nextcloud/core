<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCP\Authentication\Events;

use OCP\EventDispatcher\Event;

/**
 * Emitted when the authentication fails, but only if the login name can be associated with an existing user.
 *
 * @since 19.0.0
 */
class LoginFailedEvent extends Event {

	/** @var string */
	private $uid;

	/**
	 * @since 19.0.0
	 */
	public function __construct(string $uid) {
		parent::__construct();

		$this->uid = $uid;
	}

	/**
	 * returns the uid of the user that was tried to login against
	 *
	 * @since 19.0.0
	 */
	public function getUid(): string {
		return $this->uid;
	}
}
