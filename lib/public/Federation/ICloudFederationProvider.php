<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

namespace OCP\Federation;

use OCP\Federation\Exceptions\ActionNotSupportedException;
use OCP\Federation\Exceptions\AuthenticationFailedException;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Share\Exceptions\ShareNotFound;

/**
 * Interface ICloudFederationProvider
 *
 * Enable apps to create their own cloud federation provider
 *
 * @since 14.0.0
 *
 */

interface ICloudFederationProvider {

	/**
	 * get the name of the share type, handled by this provider
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getShareType();

	/**
	 * share received from another server
	 *
	 * @param ICloudFederationShare $share
	 * @return string provider specific unique ID of the share
	 *
	 * @throws ProviderCouldNotAddShareException
	 *
	 * @since 14.0.0
	 */
	public function shareReceived(ICloudFederationShare $share);

	/**
	 * notification received from another server
	 *
	 * @param string $notificationType (e.g SHARE_ACCEPTED)
	 * @param string $providerId share ID
	 * @param array $notification provider specific notification
	 * @return array $data send back to sender
	 *
	 * @throws ShareNotFound
	 * @throws ActionNotSupportedException
	 * @throws BadRequestException
	 * @throws AuthenticationFailedException
	 *
	 * @since 14.0.0
	 */
	public function notificationReceived($notificationType, $providerId, array $notification);

	/**
	 * get the supported share types, e.g. "user", "group", etc.
	 *
	 * @return array
	 *
	 * @since 14.0.0
	 */
	public function getSupportedShareTypes();
}
