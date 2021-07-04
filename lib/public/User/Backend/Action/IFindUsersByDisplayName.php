<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Alexey Abel <dev@abelonline.de>
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
 *
 */

namespace OCP\User\Backend\Action;

use OCP\User\Backend\Exception\ActionNotAvailableException;


/**
 * @since 21.0.0
 */
interface IFindUsersByDisplayName {
	/**
	 * @return bool whether the implementing user back end has the ability to search users at this time
	 * or with the current configuration
	 */
	public function canFindUsersByDisplayName() : bool;

	/**
	 * Finds users by display name and returns a list of matched users.
	 *
	 * Before using this method, you must call `can...Currently()` of this interface to ensure that the user
	 * backend can perform this action at his time or with the current configuration, since this can change
	 * during runtime. The implementing user back end will throw an ActionNotAvailableException if it doesn't.
	 *
	 * @param string $pattern the search pattern
	 * @param int $limit limit the returned users to only this many
	 * @param int $offset start the returned users at this position, 0-based
	 * @return iterable list of users that matched the pattern or empty iterable if none matched
	 * @throws ActionNotAvailableException if this action is not supported at this time
	 */
	public function findUsersByDisplayName(string $pattern, ?int $limit, ?int $offset): iterable;
}
