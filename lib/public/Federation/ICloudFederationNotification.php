<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

/**
 * Interface ICloudFederationNotification
 *
 *
 * @since 14.0.0
 */
interface ICloudFederationNotification {

	/**
	 * add a message to the notification
	 *
	 * @param string $notificationType (e.g. SHARE_ACCEPTED)
	 * @param string $resourceType (e.g. file, calendar, contact,...)
	 * @param $providerId id of the share
	 * @param array $notification , payload of the notification
	 *
	 * @since 14.0.0
	 */
	public function setMessage($notificationType, $resourceType, $providerId, array $notification);

	/**
	 * get message, ready to send out
	 *
	 * @return array
	 *
	 * @since 14.0.0
	 */
	public function getMessage();
}
