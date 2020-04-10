<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCP\SystemTag;

use OCP\IUser;

/**
 * Public interface to access and manage system-wide tags.
 *
 * @since 9.0.0
 */
interface ISystemTagManager {

	/**
	 * Returns the tag objects matching the given tag ids.
	 *
	 * @param array|string $tagIds id or array of unique ids of the tag to retrieve
	 *
	 * @return ISystemTag[] array of system tags with tag id as key
	 *
	 * @throws \InvalidArgumentException if at least one given tag ids is invalid (string instead of integer, etc.)
	 * @throws TagNotFoundException if at least one given tag ids did no exist
	 * 			The message contains a json_encoded array of the ids that could not be found
	 *
	 * @since 9.0.0
	 */
	public function getTagsByIds($tagIds): array;

	/**
	 * Returns the tag object matching the given attributes.
	 *
	 * @param string $tagName tag name
	 * @param bool $userVisible whether the tag is visible by users
	 * @param bool $userAssignable whether the tag is assignable by users
	 *
	 * @return ISystemTag system tag
	 *
	 * @throws TagNotFoundException if tag does not exist
	 *
	 * @since 9.0.0
	 */
	public function getTag(string $tagName, bool $userVisible, bool $userAssignable): ISystemTag;

	/**
	 * Creates the tag object using the given attributes.
	 *
	 * @param string $tagName tag name
	 * @param bool $userVisible whether the tag is visible by users
	 * @param bool $userAssignable whether the tag is assignable by users
	 *
	 * @return ISystemTag system tag
	 *
	 * @throws TagAlreadyExistsException if tag already exists
	 *
	 * @since 9.0.0
	 */
	public function createTag(string $tagName, bool $userVisible, bool $userAssignable): ISystemTag;

	/**
	 * Returns all known tags, optionally filtered by visibility.
	 *
	 * @param bool|null $visibilityFilter filter by visibility if non-null
	 * @param string $nameSearchPattern optional search pattern for the tag name
	 *
	 * @return ISystemTag[] array of system tags or empty array if none found
	 *
	 * @since 9.0.0
	 */
	public function getAllTags($visibilityFilter = null, $nameSearchPattern = null): array;

	/**
	 * Updates the given tag
	 *
	 * @param string $tagId tag id
	 * @param string $newName the new tag name
	 * @param bool $userVisible whether the tag is visible by users
	 * @param bool $userAssignable whether the tag is assignable by users
	 *
	 * @throws TagNotFoundException if tag with the given id does not exist
	 * @throws TagAlreadyExistsException if there is already another tag
	 * with the same attributes
	 *
	 * @since 9.0.0
	 */
	public function updateTag(string $tagId, string $newName, bool $userVisible, bool $userAssignable);

	/**
	 * Delete the given tags from the database and all their relationships.
	 *
	 * @param string|array $tagIds array of tag ids
	 *
	 * @throws TagNotFoundException if at least one tag did not exist
	 *
	 * @since 9.0.0
	 */
	public function deleteTags($tagIds);

	/**
	 * Checks whether the given user is allowed to assign/unassign the tag with the
	 * given id.
	 *
	 * @param ISystemTag $tag tag to check permission for
	 * @param IUser $user user to check permission for
	 *
	 * @return true if the user is allowed to assign/unassign the tag, false otherwise
	 *
	 * @since 9.1.0
	 */
	public function canUserAssignTag(ISystemTag $tag, IUser $user): bool;

	/**
	 * Checks whether the given user is allowed to see the tag with the given id.
	 *
	 * @param ISystemTag $tag tag to check permission for
	 * @param IUser $user user to check permission for
	 *
	 * @return true if the user can see the tag, false otherwise
	 *
	 * @since 9.1.0
	 */
	public function canUserSeeTag(ISystemTag $tag, IUser $user): bool;

	/**
	 * Set groups that can assign a given tag.
	 *
	 * @param ISystemTag $tag tag for group assignment
	 * @param string[] $groupIds group ids of groups that can assign/unassign the tag
	 *
	 * @since 9.1.0
	 */
	public function setTagGroups(ISystemTag $tag, array $groupIds);

	/**
	 * Get groups that can assign a given tag.
	 *
	 * @param ISystemTag $tag tag for group assignment
	 *
	 * @return string[] group ids of groups that can assign/unassign the tag
	 *
	 * @since 9.1.0
	 */
	public function getTagGroups(ISystemTag $tag): array;
}
