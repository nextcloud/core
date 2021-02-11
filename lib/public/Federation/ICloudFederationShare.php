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
 * Interface ICloudFederationShare
 *
 *
 * @since 14.0.0
 */
interface ICloudFederationShare {

	/**
	 * set uid of the recipient
	 *
	 * @param string $user
	 *
	 * @since 14.0.0
	 */
	public function setShareWith($user);

	/**
	 * set resource name (e.g. file, calendar, contact,...)
	 *
	 * @param string $name
	 *
	 * @since 14.0.0
	 */
	public function setResourceName($name);

	/**
	 * set resource type (e.g. file, calendar, contact,...)
	 *
	 * @param string $resourceType
	 *
	 * @since 14.0.0
	 */
	public function setResourceType($resourceType);

	/**
	 * set resource description (optional)
	 *
	 * @param string $description
	 *
	 * @since 14.0.0
	 */
	public function setDescription($description);

	/**
	 * set provider ID (e.g. file ID)
	 *
	 * @param string $providerId
	 *
	 * @since 14.0.0
	 */
	public function setProviderId($providerId);

	/**
	 * set owner UID
	 *
	 * @param string $owner
	 *
	 * @since 14.0.0
	 */
	public function setOwner($owner);

	/**
	 * set owner display name
	 *
	 * @param string $ownerDisplayName
	 *
	 * @since 14.0.0
	 */
	public function setOwnerDisplayName($ownerDisplayName);

	/**
	 * set UID of the user who sends the share
	 *
	 * @param string $sharedBy
	 *
	 * @since 14.0.0
	 */
	public function setSharedBy($sharedBy);

	/**
	 * set display name of the user who sends the share
	 *
	 * @param $sharedByDisplayName
	 *
	 * @since 14.0.0
	 */
	public function setSharedByDisplayName($sharedByDisplayName);

	/**
	 * set protocol specification
	 *
	 * @param array $protocol
	 *
	 * @since 14.0.0
	 */
	public function setProtocol(array $protocol);

	/**
	 * share type (group or user)
	 *
	 * @param string $shareType
	 *
	 * @since 14.0.0
	 */
	public function setShareType($shareType);

	/**
	 * get the whole share, ready to send out
	 *
	 * @return array
	 *
	 * @since 14.0.0
	 */
	public function getShare();

	/**
	 * get uid of the recipient
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getShareWith();

	/**
	 * get resource name (e.g. file, calendar, contact,...)
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getResourceName();

	/**
	 * get resource type (e.g. file, calendar, contact,...)
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getResourceType();

	/**
	 * get resource description (optional)
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getDescription();

	/**
	 * get provider ID (e.g. file ID)
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getProviderId();

	/**
	 * get owner UID
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getOwner();

	/**
	 * get owner display name
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getOwnerDisplayName();

	/**
	 * get UID of the user who sends the share
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getSharedBy();

	/**
	 * get display name of the user who sends the share
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getSharedByDisplayName();

	/**
	 * get share type (group or user)
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getShareType();

	/**
	 * get share Secret
	 *
	 * @return string
	 *
	 * @since 14.0.0
	 */
	public function getShareSecret();


	/**
	 * get protocol specification
	 *
	 * @return array
	 *
	 * @since 14.0.0
	 */
	public function getProtocol();
}
