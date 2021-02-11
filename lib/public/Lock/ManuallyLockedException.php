<?php

declare(strict_types=1);

/**
 * @copyright 2019, Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
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


namespace OCP\Lock;

/**
 * Class ManuallyLockedException
 *
 * @since 18.0.0
 */
class ManuallyLockedException extends LockedException {


	/**
	 * owner of the lock
	 *
	 * @var string|null
	 */
	private $owner = null;

	/**
	 * estimated timeout for the lock
	 *
	 * @var int
	 * @since 18.0.0
	 */
	private $timeout = -1;


	/**
	 * ManuallyLockedException constructor.
	 *
	 * @param string $path locked path
	 * @param \Exception|null $previous previous exception for cascading
	 * @param string $existingLock
	 * @param string|null $owner
	 * @param int $timeout
	 *
	 * @since 18.0.0
	 */
	public function __construct(string $path, \Exception $previous = null, ?string $existingLock = null, ?string $owner = null, int $timeout = -1) {
		parent::__construct($path, $previous, $existingLock);
		$this->owner = $owner;
		$this->timeout = $timeout;
	}


	/**
	 * @return int
	 * @since 18.0.0
	 */
	public function getTimeout(): int {
		return $this->timeout;
	}

	/**
	 * @return string|null
	 * @since 18.0.0
	 */
	public function getOwner(): ?string {
		return $this->owner;
	}
}
