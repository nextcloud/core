<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External\Lib\Auth;

use OCP\IUser;

/**
 * For auth mechanisms where the user needs to provide credentials
 */
interface IUserProvided {
	/**
	 * @param IUser $user the user for which to save the user provided options
	 * @param int $mountId the mount id to save the options for
	 * @param array $options the user provided options
	 */
	public function saveBackendOptions(IUser $user, $mountId, array $options);
}
