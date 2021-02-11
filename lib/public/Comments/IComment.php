<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

/**
 * Interface IComment
 *
 * This class represents a comment
 *
 * @since 9.0.0
 */
interface IComment {
	public const MAX_MESSAGE_LENGTH = 1000;

	/**
	 * returns the ID of the comment
	 *
	 * It may return an empty string, if the comment was not stored.
	 * It is expected that the concrete Comment implementation gives an ID
	 * by itself (e.g. after saving).
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getId();

	/**
	 * sets the ID of the comment and returns itself
	 *
	 * It is only allowed to set the ID only, if the current id is an empty
	 * string (which means it is not stored in a database, storage or whatever
	 * the concrete implementation does), or vice versa. Changing a given ID is
	 * not permitted and must result in an IllegalIDChangeException.
	 *
	 * @param string $id
	 * @return IComment
	 * @throws IllegalIDChangeException
	 * @since 9.0.0
	 */
	public function setId($id);

	/**
	 * returns the parent ID of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getParentId();

	/**
	 * sets the parent ID and returns itself
	 * @param string $parentId
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setParentId($parentId);

	/**
	 * returns the topmost parent ID of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getTopmostParentId();


	/**
	 * sets the topmost parent ID and returns itself
	 *
	 * @param string $id
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setTopmostParentId($id);

	/**
	 * returns the number of children
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getChildrenCount();

	/**
	 * sets the number of children
	 *
	 * @param int $count
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setChildrenCount($count);

	/**
	 * returns the message of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getMessage();

	/**
	 * sets the message of the comment and returns itself
	 *
	 * When the given message length exceeds MAX_MESSAGE_LENGTH an
	 * MessageTooLongException shall be thrown.
	 *
	 * @param string $message
	 * @param int $maxLength
	 * @return IComment
	 * @throws MessageTooLongException
	 * @since 9.0.0 - $maxLength added in 16.0.2
	 */
	public function setMessage($message, $maxLength = self::MAX_MESSAGE_LENGTH);

	/**
	 * returns an array containing mentions that are included in the comment
	 *
	 * @return array each mention provides a 'type' and an 'id', see example below
	 * @since 11.0.0
	 *
	 * The return array looks like:
	 * [
	 *   [
	 *     'type' => 'user',
	 *     'id' => 'citizen4'
	 *   ],
	 *   [
	 *     'type' => 'group',
	 *     'id' => 'media'
	 *   ],
	 *   …
	 * ]
	 *
	 */
	public function getMentions();

	/**
	 * returns the verb of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getVerb();

	/**
	 * sets the verb of the comment, e.g. 'comment' or 'like'
	 *
	 * @param string $verb
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setVerb($verb);

	/**
	 * returns the actor type
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getActorType();

	/**
	 * returns the actor ID
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getActorId();

	/**
	 * sets (overwrites) the actor type and id
	 *
	 * @param string $actorType e.g. 'users'
	 * @param string $actorId e.g. 'zombie234'
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setActor($actorType, $actorId);

	/**
	 * returns the creation date of the comment.
	 *
	 * If not explicitly set, it shall default to the time of initialization.
	 *
	 * @return \DateTime
	 * @since 9.0.0
	 */
	public function getCreationDateTime();

	/**
	 * sets the creation date of the comment and returns itself
	 *
	 * @param \DateTime $dateTime
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setCreationDateTime(\DateTime $dateTime);

	/**
	 * returns the date of the most recent child
	 *
	 * @return \DateTime
	 * @since 9.0.0
	 */
	public function getLatestChildDateTime();

	/**
	 * sets the date of the most recent child
	 *
	 * @param \DateTime $dateTime
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setLatestChildDateTime(\DateTime $dateTime);

	/**
	 * returns the object type the comment is attached to
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectType();

	/**
	 * returns the object id the comment is attached to
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectId();

	/**
	 * sets (overwrites) the object of the comment
	 *
	 * @param string $objectType e.g. 'files'
	 * @param string $objectId e.g. '16435'
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setObject($objectType, $objectId);

	/**
	 * returns the reference id of the comment
	 *
	 * @return string|null
	 * @since 19.0.0
	 */
	public function getReferenceId(): ?string;

	/**
	 * sets (overwrites) the reference id of the comment
	 *
	 * @param string|null $referenceId e.g. sha256 hash sum
	 * @return IComment
	 * @since 19.0.0
	 */
	public function setReferenceId(?string $referenceId): IComment;
}
