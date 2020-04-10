<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCP;

/**
 * Interface IUser
 *
 * @package OCP
 * @since 8.0.0
 */
interface IUser {

	/**
	 * get the user id
	 *
	 * @return string
	 * @since 8.0.0
	 */
	public function getUID();

	/**
	 * get the display name for the user, if no specific display name is set it will fallback to the user id
	 *
	 * @return string
	 * @since 8.0.0
	 */
	public function getDisplayName();

	/**
	 * set the display name for the user
	 *
	 * @param string $displayName
	 * @return bool
	 * @since 8.0.0
	 */
	public function setDisplayName($displayName);

	/**
	 * returns the timestamp of the user's last login or 0 if the user did never
	 * login
	 *
	 * @return int
	 * @since 8.0.0
	 */
	public function getLastLogin();

	/**
	 * updates the timestamp of the most recent login of this user
	 * @since 8.0.0
	 */
	public function updateLastLoginTimestamp();

	/**
	 * Delete the user
	 *
	 * @return bool
	 * @since 8.0.0
	 */
	public function delete();

	/**
	 * Set the password of the user
	 *
	 * @param string $password
	 * @param string $recoveryPassword for the encryption app to reset encryption keys
	 * @return bool
	 * @since 8.0.0
	 */
	public function setPassword($password, $recoveryPassword = null);

	/**
	 * get the users home folder to mount
	 *
	 * @return string
	 * @since 8.0.0
	 */
	public function getHome();

	/**
	 * Get the name of the backend class the user is connected with
	 *
	 * @return string
	 * @since 8.0.0
	 */
	public function getBackendClassName();

	/**
	 * Get the backend for the current user object
	 *
	 * @return UserInterface
	 * @since 15.0.0
	 */
	public function getBackend();

	/**
	 * check if the backend allows the user to change his avatar on Personal page
	 *
	 * @return bool
	 * @since 8.0.0
	 */
	public function canChangeAvatar();

	/**
	 * check if the backend supports changing passwords
	 *
	 * @return bool
	 * @since 8.0.0
	 */
	public function canChangePassword();

	/**
	 * check if the backend supports changing display names
	 *
	 * @return bool
	 * @since 8.0.0
	 */
	public function canChangeDisplayName();

	/**
	 * check if the user is enabled
	 *
	 * @return bool
	 * @since 8.0.0
	 */
	public function isEnabled();

	/**
	 * set the enabled status for the user
	 *
	 * @param bool $enabled
	 * @since 8.0.0
	 */
	public function setEnabled(bool $enabled = true);

	/**
	 * get the users email address
	 *
	 * @return string|null
	 * @since 9.0.0
	 */
	public function getEMailAddress();

	/**
	 * get the avatar image if it exists
	 *
	 * @param int $size
	 * @return IImage|null
	 * @since 9.0.0
	 */
	public function getAvatarImage($size);

	/**
	 * get the federation cloud id
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getCloudId();

	/**
	 * set the email address of the user
	 *
	 * @param string|null $mailAddress
	 * @return void
	 * @since 9.0.0
	 */
	public function setEMailAddress($mailAddress);

	/**
	 * get the users' quota in human readable form. If a specific quota is not
	 * set for the user, the default value is returned. If a default setting
	 * was not set otherwise, it is return as 'none', i.e. quota is not limited.
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getQuota();

	/**
	 * set the users' quota
	 *
	 * @param string $quota
	 * @return void
	 * @since 9.0.0
	 */
	public function setQuota($quota);
}
