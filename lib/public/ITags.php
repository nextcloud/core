<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Reiter <ockham@raz.or.at>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <vincent@nextcloud.com>
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

/**
 * Public interface of ownCloud for apps to use.
 * Tags interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

use OC\Tags;

// FIXME: Where should I put this? Or should it be implemented as a Listener?
\OC_Hook::connect('OC_User', 'post_deleteUser', Tags::class, 'post_deleteUser');

/**
 * Class for easily tagging objects by their id
 *
 * A tag can be e.g. 'Family', 'Work', 'Chore', 'Special Occation' or
 * anything else that is either parsed from a vobject or that the user chooses
 * to add.
 * Tag names are not case-sensitive, but will be saved with the case they
 * are entered in. If a user already has a tag 'family' for a type, and
 * tries to add a tag named 'Family' it will be silently ignored.
 * @since 6.0.0
 */

interface ITags {
	/**
	 * @since 19.0.0
	 */
	public const TAG_FAVORITE = '_$!<Favorite>!$_';

	/**
	 * Check if any tags are saved for this type and user.
	 *
	 * @return boolean
	 * @since 6.0.0
	 */
	public function isEmpty();

	/**
	 * Returns an array mapping a given tag's properties to its values:
	 * ['id' => 0, 'name' = 'Tag', 'owner' = 'User', 'type' => 'tagtype']
	 *
	 * @param string $id The ID of the tag that is going to be mapped
	 * @return array|false
	 * @since 8.0.0
	 */
	public function getTag($id);

	/**
	 * Get the tags for a specific user.
	 *
	 * This returns an array with id/name maps:
	 * [
	 * 	['id' => 0, 'name' = 'First tag'],
	 * 	['id' => 1, 'name' = 'Second tag'],
	 * ]
	 *
	 * @return array
	 * @since 6.0.0
	 */
	public function getTags();

	/**
	 * Get a list of tags for the given item ids.
	 *
	 * This returns an array with object id / tag names:
	 * [
	 *   1 => array('First tag', 'Second tag'),
	 *   2 => array('Second tag'),
	 *   3 => array('Second tag', 'Third tag'),
	 * ]
	 *
	 * @param array $objIds item ids
	 * @return array|boolean with object id as key and an array
	 * of tag names as value or false if an error occurred
	 * @since 8.0.0
	 */
	public function getTagsForObjects(array $objIds);

	/**
	 * Get a list of items tagged with $tag.
	 *
	 * Throws an exception if the tag could not be found.
	 *
	 * @param string|integer $tag Tag id or name.
	 * @return array|false An array of object ids or false on error.
	 * @since 6.0.0
	 */
	public function getIdsForTag($tag);

	/**
	 * Checks whether a tag is already saved.
	 *
	 * @param string $name The name to check for.
	 * @return bool
	 * @since 6.0.0
	 */
	public function hasTag($name);

	/**
	 * Checks whether a tag is saved for the given user,
	 * disregarding the ones shared with him or her.
	 *
	 * @param string $name The tag name to check for.
	 * @param string $user The user whose tags are to be checked.
	 * @return bool
	 * @since 8.0.0
	 */
	public function userHasTag($name, $user);

	/**
	 * Add a new tag.
	 *
	 * @param string $name A string with a name of the tag
	 * @return int|false the id of the added tag or false if it already exists.
	 * @since 6.0.0
	 */
	public function add($name);

	/**
	 * Rename tag.
	 *
	 * @param string|integer $from The name or ID of the existing tag
	 * @param string $to The new name of the tag.
	 * @return bool
	 * @since 6.0.0
	 */
	public function rename($from, $to);

	/**
	 * Add a list of new tags.
	 *
	 * @param string[] $names A string with a name or an array of strings containing
	 * the name(s) of the to add.
	 * @param bool $sync When true, save the tags
	 * @param int|null $id int Optional object id to add to this|these tag(s)
	 * @return bool Returns false on error.
	 * @since 6.0.0
	 */
	public function addMultiple($names, $sync = false, $id = null);

	/**
	 * Delete tag/object relations from the db
	 *
	 * @param array $ids The ids of the objects
	 * @return boolean Returns false on error.
	 * @since 6.0.0
	 */
	public function purgeObjects(array $ids);

	/**
	 * Get favorites for an object type
	 *
	 * @return array|false An array of object ids.
	 * @since 6.0.0
	 */
	public function getFavorites();

	/**
	 * Add an object to favorites
	 *
	 * @param int $objid The id of the object
	 * @return boolean
	 * @since 6.0.0
	 */
	public function addToFavorites($objid);

	/**
	 * Remove an object from favorites
	 *
	 * @param int $objid The id of the object
	 * @return boolean
	 * @since 6.0.0
	 */
	public function removeFromFavorites($objid);

	/**
	 * Creates a tag/object relation.
	 *
	 * @param int $objid The id of the object
	 * @param string $tag The id or name of the tag
	 * @return boolean Returns false on database error.
	 * @since 6.0.0
	 */
	public function tagAs($objid, $tag);

	/**
	 * Delete single tag/object relation from the db
	 *
	 * @param int $objid The id of the object
	 * @param string $tag The id or name of the tag
	 * @return boolean
	 * @since 6.0.0
	 */
	public function unTag($objid, $tag);

	/**
	 * Delete tags from the database
	 *
	 * @param string[]|integer[] $names An array of tags (names or IDs) to delete
	 * @return bool Returns false on error
	 * @since 6.0.0
	 */
	public function delete($names);
}
