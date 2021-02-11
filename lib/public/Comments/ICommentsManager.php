<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCP\Comments;

use OCP\IUser;

/**
 * Interface ICommentsManager
 *
 * This class manages the access to comments
 *
 * @since 9.0.0
 */
interface ICommentsManager {

	/**
	 * @const DELETED_USER type and id for a user that has been deleted
	 * @see deleteReferencesOfActor
	 * @since 9.0.0
	 *
	 * To be used as replacement for user type actors in deleteReferencesOfActor().
	 *
	 * User interfaces shall show "Deleted user" as display name, if needed.
	 */
	public const DELETED_USER = 'deleted_users';

	/**
	 * returns a comment instance
	 *
	 * @param string $id the ID of the comment
	 * @return IComment
	 * @throws NotFoundException
	 * @since 9.0.0
	 */
	public function get($id);

	/**
	 * returns the comment specified by the id and all it's child comments
	 *
	 * @param string $id
	 * @param int $limit max number of entries to return, 0 returns all
	 * @param int $offset the start entry
	 * @return array
	 * @since 9.0.0
	 *
	 * The return array looks like this
	 * [
	 * 	 'comment' => IComment, // root comment
	 *   'replies' =>
	 *   [
	 *     0 =>
	 *     [
	 *       'comment' => IComment,
	 *       'replies' =>
	 *       [
	 *         0 =>
	 *         [
	 *           'comment' => IComment,
	 *           'replies' => [ … ]
	 *         ],
	 *         …
	 *       ]
	 *     ]
	 *     1 =>
	 *     [
	 *       'comment' => IComment,
	 *       'replies'=> [ … ]
	 *     ],
	 *     …
	 *   ]
	 * ]
	 */
	public function getTree($id, $limit = 0, $offset = 0);

	/**
	 * returns comments for a specific object (e.g. a file).
	 *
	 * The sort order is always newest to oldest.
	 *
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param int $limit optional, number of maximum comments to be returned. if
	 * not specified, all comments are returned.
	 * @param int $offset optional, starting point
	 * @param \DateTime|null $notOlderThan optional, timestamp of the oldest comments
	 * that may be returned
	 * @return IComment[]
	 * @since 9.0.0
	 */
	public function getForObject(
			$objectType,
			$objectId,
			$limit = 0,
			$offset = 0,
			\DateTime $notOlderThan = null
	);

	/**
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string $objectId the id of the object
	 * @param int $lastKnownCommentId the last known comment (will be used as offset)
	 * @param string $sortDirection direction of the comments (`asc` or `desc`)
	 * @param int $limit optional, number of maximum comments to be returned. if
	 * set to 0, all comments are returned.
	 * @param bool $includeLastKnown
	 * @return IComment[]
	 * @since 14.0.0
	 */
	public function getForObjectSince(
		string $objectType,
		string $objectId,
		int $lastKnownCommentId,
		string $sortDirection = 'asc',
		int $limit = 30,
		bool $includeLastKnown = false
	): array;

	/**
	 * Search for comments with a given content
	 *
	 * @param string $search content to search for
	 * @param string $objectType Limit the search by object type
	 * @param string $objectId Limit the search by object id
	 * @param string $verb Limit the verb of the comment
	 * @param int $offset
	 * @param int $limit
	 * @return IComment[]
	 * @since 14.0.0
	 */
	public function search(string $search, string $objectType, string $objectId, string $verb, int $offset, int $limit = 50): array;

	/**
	 * Search for comments on one or more objects with a given content
	 *
	 * @param string $search content to search for
	 * @param string $objectType Limit the search by object type
	 * @param array $objectIds Limit the search by object ids
	 * @param string $verb Limit the verb of the comment
	 * @param int $offset
	 * @param int $limit
	 * @return IComment[]
	 * @since 21.0.0
	 */
	public function searchForObjects(string $search, string $objectType, array $objectIds, string $verb, int $offset, int $limit = 50): array;

	/**
	 * @param $objectType string the object type, e.g. 'files'
	 * @param $objectId string the id of the object
	 * @param \DateTime|null $notOlderThan optional, timestamp of the oldest comments
	 * that may be returned
	 * @param string $verb Limit the verb of the comment - Added in 14.0.0
	 * @return Int
	 * @since 9.0.0
	 */
	public function getNumberOfCommentsForObject($objectType, $objectId, \DateTime $notOlderThan = null, $verb = '');

	/**
	 * @param string $objectType the object type, e.g. 'files'
	 * @param string[] $objectIds the id of the object
	 * @param IUser $user
	 * @param string $verb Limit the verb of the comment - Added in 14.0.0
	 * @return array Map with object id => # of unread comments
	 * @psalm-return array<string, int>
	 * @since 21.0.0
	 */
	public function getNumberOfUnreadCommentsForObjects(string $objectType, array $objectIds, IUser $user, $verb = ''): array;

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param int $lastRead
	 * @param string $verb
	 * @return int
	 * @since 21.0.0
	 */
	public function getNumberOfCommentsForObjectSinceComment(string $objectType, string $objectId, int $lastRead, string $verb = ''): int;

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param \DateTime $beforeDate
	 * @param string $verb
	 * @return int
	 * @since 21.0.0
	 */
	public function getLastCommentBeforeDate(string $objectType, string $objectId, \DateTime $beforeDate, string $verb = ''): int;

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @param string $verb
	 * @param string $actorType
	 * @param string[] $actors
	 * @return \DateTime[] Map of "string actor" => "\DateTime most recent comment date"
	 * @psalm-return array<string, \DateTime>
	 * @since 21.0.0
	 */
	public function getLastCommentDateByActor(
		string $objectType,
		string $objectId,
		string $verb,
		string $actorType,
		array $actors
	): array;

	/**
	 * Get the number of unread comments for all files in a folder
	 *
	 * @param int $folderId
	 * @param IUser $user
	 * @return array [$fileId => $unreadCount]
	 * @since 12.0.0
	 */
	public function getNumberOfUnreadCommentsForFolder($folderId, IUser $user);

	/**
	 * creates a new comment and returns it. At this point of time, it is not
	 * saved in the used data storage. Use save() after setting other fields
	 * of the comment (e.g. message or verb).
	 *
	 * @param string $actorType the actor type (e.g. 'users')
	 * @param string $actorId a user id
	 * @param string $objectType the object type the comment is attached to
	 * @param string $objectId the object id the comment is attached to
	 * @return IComment
	 * @since 9.0.0
	 */
	public function create($actorType, $actorId, $objectType, $objectId);

	/**
	 * permanently deletes the comment specified by the ID
	 *
	 * When the comment has child comments, their parent ID will be changed to
	 * the parent ID of the item that is to be deleted.
	 *
	 * @param string $id
	 * @return bool
	 * @since 9.0.0
	 */
	public function delete($id);

	/**
	 * saves the comment permanently
	 *
	 * if the supplied comment has an empty ID, a new entry comment will be
	 * saved and the instance updated with the new ID.
	 *
	 * Otherwise, an existing comment will be updated.
	 *
	 * Throws NotFoundException when a comment that is to be updated does not
	 * exist anymore at this point of time.
	 *
	 * @param IComment $comment
	 * @return bool
	 * @throws NotFoundException
	 * @since 9.0.0
	 */
	public function save(IComment $comment);

	/**
	 * removes references to specific actor (e.g. on user delete) of a comment.
	 * The comment itself must not get lost/deleted.
	 *
	 * A 'users' type actor (type and id) should get replaced by the
	 * value of the DELETED_USER constant of this interface.
	 *
	 * @param string $actorType the actor type (e.g. 'users')
	 * @param string $actorId a user id
	 * @return boolean
	 * @since 9.0.0
	 */
	public function deleteReferencesOfActor($actorType, $actorId);

	/**
	 * deletes all comments made of a specific object (e.g. on file delete)
	 *
	 * @param string $objectType the object type (e.g. 'files')
	 * @param string $objectId e.g. the file id
	 * @return boolean
	 * @since 9.0.0
	 */
	public function deleteCommentsAtObject($objectType, $objectId);

	/**
	 * sets the read marker for a given file to the specified date for the
	 * provided user
	 *
	 * @param string $objectType
	 * @param string $objectId
	 * @param \DateTime $dateTime
	 * @param \OCP\IUser $user
	 * @since 9.0.0
	 */
	public function setReadMark($objectType, $objectId, \DateTime $dateTime, \OCP\IUser $user);

	/**
	 * returns the read marker for a given file to the specified date for the
	 * provided user. It returns null, when the marker is not present, i.e.
	 * no comments were marked as read.
	 *
	 * @param string $objectType
	 * @param string $objectId
	 * @param \OCP\IUser $user
	 * @return \DateTime|null
	 * @since 9.0.0
	 */
	public function getReadMark($objectType, $objectId, \OCP\IUser $user);

	/**
	 * deletes the read markers for the specified user
	 *
	 * @param \OCP\IUser $user
	 * @return bool
	 * @since 9.0.0
	 */
	public function deleteReadMarksFromUser(\OCP\IUser $user);

	/**
	 * deletes the read markers on the specified object
	 *
	 * @param string $objectType
	 * @param string $objectId
	 * @return bool
	 * @since 9.0.0
	 */
	public function deleteReadMarksOnObject($objectType, $objectId);

	/**
	 * registers an Entity to the manager, so event notifications can be send
	 * to consumers of the comments infrastructure
	 *
	 * @param \Closure $closure
	 * @since 11.0.0
	 */
	public function registerEventHandler(\Closure $closure);

	/**
	 * registers a method that resolves an ID to a display name for a given type
	 *
	 * @param string $type
	 * @param \Closure $closure
	 * @throws \OutOfBoundsException
	 * @since 11.0.0
	 *
	 * Only one resolver shall be registered per type. Otherwise a
	 * \OutOfBoundsException has to thrown.
	 */
	public function registerDisplayNameResolver($type, \Closure $closure);

	/**
	 * resolves a given ID of a given Type to a display name.
	 *
	 * @param string $type
	 * @param string $id
	 * @return string
	 * @throws \OutOfBoundsException
	 * @since 11.0.0
	 *
	 * If a provided type was not registered, an \OutOfBoundsException shall
	 * be thrown. It is upon the resolver discretion what to return of the
	 * provided ID is unknown. It must be ensured that a string is returned.
	 */
	public function resolveDisplayName($type, $id);

	/**
	 * Load the Comments app into the page
	 *
	 * @since 21.0.0
	 */
	public function load(): void;
}
