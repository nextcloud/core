<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * TODO actually this is a IUserBackend
 *
 * @since 4.5.0
 * @deprecated 21.0.0 use \OCP\User\Backend\AbstractUserBackEnd instead
 */
interface UserInterface {

	/**
	 * Check if backend implements actions
	 * @param int $actions bitwise-or'ed actions
	 * @return boolean
	 *
	 * Returns the supported actions as int to be
	 * compared with \OC\User\Backend::CREATE_USER etc.
	 * @since 4.5.0
	 * @deprecated 21.0.0 implement interfaces from \OCP\User\Backend\Action instead
	 */
	public function implementsActions($actions);

	/**
	 * delete a user
	 * @param string $uid The username of the user to delete
	 * @return bool
	 * @since 4.5.0
	 * @deprecated 21.0.0 implement interfaces from \OCP\User\Backend\Action instead
	 */
	public function deleteUser($uid);

	/**
	 * Get a list of all users
	 *
	 * @param string $search
	 * @param null|int $limit
	 * @param null|int $offset
	 * @return string[] an array of all uids
	 * @since 4.5.0
	 * @deprecated 21.0.0 implement interfaces from \OCP\User\Backend\Action instead
	 */
	public function getUsers($search = '', $limit = null, $offset = null);

	/**
	 * check if a user exists
	 * @param string $uid the username
	 * @return boolean
	 * @since 4.5.0
	 * @deprecated 21.0.0 implement interfaces from \OCP\User\Backend\Action instead
	 */
	public function userExists($uid);

	/**
	 * get display name of the user
	 * @param string $uid user ID of the user
	 * @return string display name
	 * @since 4.5.0
	 * @deprecated 21.0.0 implement interfaces from \OCP\User\Backend\Action instead
	 */
	public function getDisplayName($uid);

	/**
	 * Get a list of all display names and user ids.
	 *
	 * @param string $search
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array an array of all displayNames (value) and the corresponding uids (key)
	 * @since 4.5.0
	 * @deprecated 21.0.0 implement interfaces from \OCP\User\Backend\Action instead
	 */
	public function getDisplayNames($search = '', $limit = null, $offset = null);

	/**
	 * Check if a user list is available or not
	 * @return boolean if users can be listed or not
	 * @since 4.5.0
	 * @deprecated 21.0.0 implement interfaces from \OCP\User\Backend\Action instead
	 */
	public function hasUserListings();
}
