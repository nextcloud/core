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

/**
 * Public interface to access and manage system-wide tags.
 *
 * @since 9.0.0
 */
interface ISystemTagObjectMapper {

	/**
	 * Get a list of tag ids for the given object ids.
	 *
	 * This returns an array that maps object id to tag ids
	 * [
	 *   1 => array('id1', 'id2'),
	 *   2 => array('id3', 'id2'),
	 *   3 => array('id5'),
	 *   4 => array()
	 * ]
	 *
	 * Untagged objects will have an empty array associated.
	 *
	 * @param string|array $objIds object ids
	 * @param string $objectType object type
	 *
	 * @return array with object id as key and an array
	 * of tag ids as value
	 *
	 * @since 9.0.0
	 */
	public function getTagIdsForObjects($objIds, string $objectType): array;

	/**
	 * Get a list of objects tagged with $tagIds.
	 *
	 * @param string|array $tagIds Tag id or array of tag ids.
	 * @param string $objectType object type
	 * @param int $limit Count of object ids you want to get
	 * @param string $offset The last object id you already received
	 *
	 * @return string[] array of object ids or empty array if none found
	 *
	 * @throws TagNotFoundException if at least one of the
	 * given tags does not exist
	 * @throws \InvalidArgumentException When a limit is specified together with
	 * multiple tag ids
	 *
	 * @since 9.0.0
	 */
	public function getObjectIdsForTags($tagIds, string $objectType, int $limit = 0, string $offset = ''): array;

	/**
	 * Assign the given tags to the given object.
	 *
	 * If at least one of the given tag ids doesn't exist, none of the tags
	 * will be assigned.
	 *
	 * If the relationship already existed, fail silently.
	 *
	 * @param string $objId object id
	 * @param string $objectType object type
	 * @param string|array $tagIds tag id or array of tag ids to assign
	 *
	 * @throws TagNotFoundException if at least one of the
	 * given tags does not exist
	 *
	 * @since 9.0.0
	 */
	public function assignTags(string $objId, string $objectType, $tagIds);

	/**
	 * Unassign the given tags from the given object.
	 *
	 * If at least one of the given tag ids doesn't exist, none of the tags
	 * will be unassigned.
	 *
	 * If the relationship did not exist in the first place, fail silently.
	 *
	 * @param string $objId object id
	 * @param string $objectType object type
	 * @param string|array $tagIds tag id or array of tag ids to unassign
	 *
	 * @throws TagNotFoundException if at least one of the
	 * given tags does not exist
	 *
	 * @since 9.0.0
	 */
	public function unassignTags(string $objId, string $objectType, $tagIds);

	/**
	 * Checks whether the given objects have the given tag.
	 *
	 * @param string|array $objIds object ids
	 * @param string $objectType object type
	 * @param string $tagId tag id to check
	 * @param bool $all true to check that ALL objects have the tag assigned,
	 * false to check that at least ONE object has the tag.
	 *
	 * @return bool true if the condition set by $all is matched, false
	 * otherwise
	 *
	 * @throws TagNotFoundException if the tag does not exist
	 *
	 * @since 9.0.0
	 */
	public function haveTag($objIds, string $objectType, string $tagId, bool $all = true): bool;

}
