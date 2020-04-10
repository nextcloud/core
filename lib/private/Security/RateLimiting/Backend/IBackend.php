<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\Security\RateLimiting\Backend;

/**
 * Interface IBackend defines a storage backend for the rate limiting data. It
 * should be noted that writing and reading rate limiting data is an expensive
 * operation and one should thus make sure to only use sufficient fast backends.
 *
 * @package OC\Security\RateLimiting\Backend
 */
interface IBackend {
	/**
	 * Gets the amount of attempts within the last specified seconds
	 *
	 * @param string $methodIdentifier Identifier for the method
	 * @param string $userIdentifier Identifier for the user
	 * @param int $seconds Seconds to look back at
	 * @return int
	 */
	public function getAttempts(string $methodIdentifier,
								string $userIdentifier,
								int $seconds): int;

	/**
	 * Registers an attempt
	 *
	 * @param string $methodIdentifier Identifier for the method
	 * @param string $userIdentifier Identifier for the user
	 * @param int $period Period in seconds how long this attempt should be stored
	 */
	public function registerAttempt(string $methodIdentifier,
									string $userIdentifier,
									int $period);
}
