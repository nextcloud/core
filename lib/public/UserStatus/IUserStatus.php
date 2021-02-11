<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCP\UserStatus;

use DateTimeImmutable;

/**
 * Interface IUserStatus
 *
 * @since 20.0.0
 */
interface IUserStatus {

	/**
	 * @var string
	 * @since 20.0.0
	 */
	public const ONLINE = 'online';

	/**
	 * @var string
	 * @since 20.0.0
	 */
	public const AWAY = 'away';

	/**
	 * @var string
	 * @since 20.0.0
	 */
	public const DND = 'dnd';

	/**
	 * @var string
	 * @since 20.0.0
	 */
	public const OFFLINE = 'offline';

	/**
	 * @var string
	 * @since 20.0.0
	 */
	public const INVISIBLE = 'invisible';

	/**
	 * Get the user this status is connected to
	 *
	 * @return string
	 * @since 20.0.0
	 */
	public function getUserId():string;

	/**
	 * Get the status
	 *
	 * It will return one of the constants defined above.
	 * It will never return invisible. In case a user marked
	 * themselves as invisible, it will return offline.
	 *
	 * @return string See IUserStatus constants
	 * @since 20.0.0
	 */
	public function getStatus():string;

	/**
	 * Get a custom message provided by the user
	 *
	 * @return string|null
	 * @since 20.0.0
	 */
	public function getMessage():?string;

	/**
	 * Get a custom icon provided by the user
	 *
	 * @return string|null
	 * @since 20.0.0
	 */
	public function getIcon():?string;

	/**
	 * Gets the time that the custom status will be cleared at
	 *
	 * @return DateTimeImmutable|null
	 * @since 20.0.0
	 */
	public function getClearAt():?DateTimeImmutable;
}
