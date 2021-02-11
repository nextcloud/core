<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\User;

use OCP\EventDispatcher\Event;
use OCP\IUser;

/**
 * Event to allow apps to
 *
 * @since 20.0.0
 */
class GetQuotaEvent extends Event {
	/** @var IUser */
	private $user;
	/** @var string|null */
	private $quota = null;

	/**
	 * @since 20.0.0
	 */
	public function __construct(IUser $user) {
		parent::__construct();
		$this->user = $user;
	}

	/**
	 * @since 20.0.0
	 */
	public function getUser(): IUser {
		return $this->user;
	}

	/**
	 * Get the set quota as human readable string, or null if no overwrite is set
	 *
	 * @since 20.0.0
	 */
	public function getQuota(): ?string {
		return $this->quota;
	}

	/**
	 * Set the quota overwrite as human readable string
	 *
	 * @since 20.0.0
	 */
	public function setQuota(string $quota): void {
		$this->quota = $quota;
	}
}
