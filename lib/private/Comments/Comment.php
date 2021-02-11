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

namespace OC\Comments;

use OCP\Comments\IComment;
use OCP\Comments\IllegalIDChangeException;
use OCP\Comments\MessageTooLongException;

class Comment implements IComment {
	protected $data = [
		'id' => '',
		'parentId' => '0',
		'topmostParentId' => '0',
		'childrenCount' => '0',
		'message' => '',
		'verb' => '',
		'actorType' => '',
		'actorId' => '',
		'objectType' => '',
		'objectId' => '',
		'referenceId' => null,
		'creationDT' => null,
		'latestChildDT' => null,
	];

	/**
	 * Comment constructor.
	 *
	 * @param array $data	optional, array with keys according to column names from
	 * 						the comments database scheme
	 */
	public function __construct(array $data = null) {
		if (is_array($data)) {
			$this->fromArray($data);
		}
	}

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
	public function getId() {
		return $this->data['id'];
	}

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
	public function setId($id) {
		if (!is_string($id)) {
			throw new \InvalidArgumentException('String expected.');
		}

		$id = trim($id);
		if ($this->data['id'] === '' || ($this->data['id'] !== '' && $id === '')) {
			$this->data['id'] = $id;
			return $this;
		}

		throw new IllegalIDChangeException('Not allowed to assign a new ID to an already saved comment.');
	}

	/**
	 * returns the parent ID of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getParentId() {
		return $this->data['parentId'];
	}

	/**
	 * sets the parent ID and returns itself
	 *
	 * @param string $parentId
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setParentId($parentId) {
		if (!is_string($parentId)) {
			throw new \InvalidArgumentException('String expected.');
		}
		$this->data['parentId'] = trim($parentId);
		return $this;
	}

	/**
	 * returns the topmost parent ID of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getTopmostParentId() {
		return $this->data['topmostParentId'];
	}


	/**
	 * sets the topmost parent ID and returns itself
	 *
	 * @param string $id
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setTopmostParentId($id) {
		if (!is_string($id)) {
			throw new \InvalidArgumentException('String expected.');
		}
		$this->data['topmostParentId'] = trim($id);
		return $this;
	}

	/**
	 * returns the number of children
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getChildrenCount() {
		return $this->data['childrenCount'];
	}

	/**
	 * sets the number of children
	 *
	 * @param int $count
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setChildrenCount($count) {
		if (!is_int($count)) {
			throw new \InvalidArgumentException('Integer expected.');
		}
		$this->data['childrenCount'] = $count;
		return $this;
	}

	/**
	 * returns the message of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getMessage() {
		return $this->data['message'];
	}

	/**
	 * sets the message of the comment and returns itself
	 *
	 * @param string $message
	 * @param int $maxLength
	 * @return IComment
	 * @throws MessageTooLongException
	 * @since 9.0.0
	 */
	public function setMessage($message, $maxLength = self::MAX_MESSAGE_LENGTH) {
		if (!is_string($message)) {
			throw new \InvalidArgumentException('String expected.');
		}
		$message = trim($message);
		if ($maxLength && mb_strlen($message, 'UTF-8') > $maxLength) {
			throw new MessageTooLongException('Comment message must not exceed ' . $maxLength. ' characters');
		}
		$this->data['message'] = $message;
		return $this;
	}

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
	public function getMentions() {
		$ok = preg_match_all("/\B(?<![^a-z0-9_\-@\.\'\s])@(\"guest\/[a-f0-9]+\"|\"[a-z0-9_\-@\.\' ]+\"|[a-z0-9_\-@\.\']+)/i", $this->getMessage(), $mentions);
		if (!$ok || !isset($mentions[0]) || !is_array($mentions[0])) {
			return [];
		}
		$uids = array_unique($mentions[0]);
		usort($uids, static function ($uid1, $uid2) {
			return mb_strlen($uid2) <=> mb_strlen($uid1);
		});
		$result = [];
		foreach ($uids as $uid) {
			$cleanUid = trim(substr($uid, 1), '"');
			if (strpos($cleanUid, 'guest/') === 0) {
				$result[] = ['type' => 'guest', 'id' => $cleanUid];
			} else {
				$result[] = ['type' => 'user', 'id' => $cleanUid];
			}
		}
		return $result;
	}

	/**
	 * returns the verb of the comment
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getVerb() {
		return $this->data['verb'];
	}

	/**
	 * sets the verb of the comment, e.g. 'comment' or 'like'
	 *
	 * @param string $verb
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setVerb($verb) {
		if (!is_string($verb) || !trim($verb)) {
			throw new \InvalidArgumentException('Non-empty String expected.');
		}
		$this->data['verb'] = trim($verb);
		return $this;
	}

	/**
	 * returns the actor type
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getActorType() {
		return $this->data['actorType'];
	}

	/**
	 * returns the actor ID
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getActorId() {
		return $this->data['actorId'];
	}

	/**
	 * sets (overwrites) the actor type and id
	 *
	 * @param string $actorType e.g. 'users'
	 * @param string $actorId e.g. 'zombie234'
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setActor($actorType, $actorId) {
		if (
			   !is_string($actorType) || !trim($actorType)
			|| !is_string($actorId) || $actorId === ''
		) {
			throw new \InvalidArgumentException('String expected.');
		}
		$this->data['actorType'] = trim($actorType);
		$this->data['actorId'] = $actorId;
		return $this;
	}

	/**
	 * returns the creation date of the comment.
	 *
	 * If not explicitly set, it shall default to the time of initialization.
	 *
	 * @return \DateTime
	 * @since 9.0.0
	 */
	public function getCreationDateTime() {
		return $this->data['creationDT'];
	}

	/**
	 * sets the creation date of the comment and returns itself
	 *
	 * @param \DateTime $timestamp
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setCreationDateTime(\DateTime $timestamp) {
		$this->data['creationDT'] = $timestamp;
		return $this;
	}

	/**
	 * returns the DateTime of the most recent child, if set, otherwise null
	 *
	 * @return \DateTime|null
	 * @since 9.0.0
	 */
	public function getLatestChildDateTime() {
		return $this->data['latestChildDT'];
	}

	/**
	 * sets the date of the most recent child
	 *
	 * @param \DateTime $dateTime
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setLatestChildDateTime(\DateTime $dateTime = null) {
		$this->data['latestChildDT'] = $dateTime;
		return $this;
	}

	/**
	 * returns the object type the comment is attached to
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectType() {
		return $this->data['objectType'];
	}

	/**
	 * returns the object id the comment is attached to
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectId() {
		return $this->data['objectId'];
	}

	/**
	 * sets (overwrites) the object of the comment
	 *
	 * @param string $objectType e.g. 'files'
	 * @param string $objectId e.g. '16435'
	 * @return IComment
	 * @since 9.0.0
	 */
	public function setObject($objectType, $objectId) {
		if (
			   !is_string($objectType) || !trim($objectType)
			|| !is_string($objectId) || trim($objectId) === ''
		) {
			throw new \InvalidArgumentException('String expected.');
		}
		$this->data['objectType'] = trim($objectType);
		$this->data['objectId'] = trim($objectId);
		return $this;
	}

	/**
	 * returns the reference id of the comment
	 *
	 * @return string|null
	 * @since 19.0.0
	 */
	public function getReferenceId(): ?string {
		return $this->data['referenceId'];
	}

	/**
	 * sets (overwrites) the reference id of the comment
	 *
	 * @param string $referenceId e.g. sha256 hash sum
	 * @return IComment
	 * @since 19.0.0
	 */
	public function setReferenceId(?string $referenceId): IComment {
		if ($referenceId === null) {
			$this->data['referenceId'] = $referenceId;
		} else {
			$referenceId = trim($referenceId);
			if ($referenceId === '') {
				throw new \InvalidArgumentException('Non empty string expected.');
			}
			$this->data['referenceId'] = $referenceId;
		}
		return $this;
	}

	/**
	 * sets the comment data based on an array with keys as taken from the
	 * database.
	 *
	 * @param array $data
	 * @return IComment
	 */
	protected function fromArray($data) {
		foreach (array_keys($data) as $key) {
			// translate DB keys to internal setter names
			$setter = 'set' . implode('', array_map('ucfirst', explode('_', $key)));
			$setter = str_replace('Timestamp', 'DateTime', $setter);

			if (method_exists($this, $setter)) {
				$this->$setter($data[$key]);
			}
		}

		foreach (['actor', 'object'] as $role) {
			if (isset($data[$role . '_type']) && isset($data[$role . '_id'])) {
				$setter = 'set' . ucfirst($role);
				$this->$setter($data[$role . '_type'], $data[$role . '_id']);
			}
		}

		return $this;
	}
}
