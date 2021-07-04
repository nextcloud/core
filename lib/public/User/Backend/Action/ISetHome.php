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
use OCP\User\Backend\Exception\UserDoesNotExistInBackEndException;


/**
 * @since 21.0.0
 */
interface ISetHome {
	/**
	 * @return bool whether the implementing user back end has the ability to set a home folder at this time
	 * or with the current configuration
	 */
	public function canSetHome() : bool;

	/**
	 * Sets a new home folder for a user.
	 *
	 * Before using this method, you must call `can...Currently()` of this interface to ensure that the user
	 * backend can perform this action at his time or with the current configuration, since this can change
	 * during runtime. The implementing user back end will throw an ActionNotAvailableException if it doesn't.
	 *
	 * @param string $username the username to set the home folder for
	 * @param string $newHome the new home folder of the user
	 * @throws ActionNotAvailableException if this action is not supported at this time
	 * @throws UserDoesNotExistInBackEndException if specified username doesn't exist in the user back end
	 */
	public function setHome(string $username, string $newHome): void;
}
