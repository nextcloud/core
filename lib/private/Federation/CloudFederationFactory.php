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

namespace OC\Federation;

use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationShare;

class CloudFederationFactory implements ICloudFederationFactory {

	/**
	 * get a CloudFederationShare Object to prepare a share you want to send
	 *
	 * @param string $shareWith
	 * @param string $name resource name (e.g. document.odt)
	 * @param string $description share description (optional)
	 * @param string $providerId resource UID on the provider side
	 * @param string $owner provider specific UID of the user who owns the resource
	 * @param string $ownerDisplayName display name of the user who shared the item
	 * @param string $sharedBy provider specific UID of the user who shared the resource
	 * @param string $sharedByDisplayName display name of the user who shared the resource
	 * @param string $sharedSecret used to authenticate requests across servers
	 * @param string $shareType ('group' or 'user' share)
	 * @param $resourceType ('file', 'calendar',...)
	 * @return ICloudFederationShare
	 *
	 * @since 14.0.0
	 */
	public function getCloudFederationShare($shareWith, $name, $description, $providerId, $owner, $ownerDisplayName, $sharedBy, $sharedByDisplayName, $sharedSecret, $shareType, $resourceType) {
		return new CloudFederationShare($shareWith, $name, $description, $providerId, $owner, $ownerDisplayName, $sharedBy, $sharedByDisplayName, $shareType, $resourceType, $sharedSecret);
	}

	/**
	 * get a Cloud FederationNotification object to prepare a notification you
	 * want to send
	 *
	 * @return ICloudFederationNotification
	 *
	 * @since 14.0.0
	 */
	public function getCloudFederationNotification() {
		return new CloudFederationNotification();
	}
}
