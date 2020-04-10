<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Maxence Lange <maxence@nextcloud.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCP\Share;

use OCP\Files\Cache\ICacheEntry;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Share\Exceptions\IllegalIDChangeException;

/**
 * Interface IShare
 *
 * @package OCP\Share
 * @since 9.0.0
 */
interface IShare {

	/**
	 * @since 17.0.0
	 */
	public const TYPE_USER = 0;

	/**
	 * @since 17.0.0
	 */
	public const TYPE_GROUP = 1;

	/**
	 * @internal
	 * @since 18.0.0
	 */
	public const TYPE_USERGROUP = 2;

	/**
	 * @since 17.0.0
	 */
	public const TYPE_LINK = 3;

	/**
	 * @since 17.0.0
	 */
	public const TYPE_EMAIL = 4;

	/**
	 * ToDo Check if it is still in use otherwise remove it
	 * @since 17.0.0
	 */
	// public const TYPE_CONTACT = 5;

	/**
	 * @since 17.0.0
	 */
	public const TYPE_REMOTE = 6;

	/**
	 * @since 17.0.0
	 */
	public const TYPE_CIRCLE = 7;

	/**
	 * @since 17.0.0
	 */
	public const TYPE_GUEST = 8;

	/**
	 * @since 17.0.0
	 */
	public const TYPE_REMOTE_GROUP = 9;

	/**
	 * @since 17.0.0
	 */
	public const TYPE_ROOM = 10;

	/**
	 * Internal type used by RoomShareProvider
	 * @since 17.0.0
	 */
	// const TYPE_USERROOM = 11;

	/**
	 * @since 18.0.0
	 */
	public const STATUS_PENDING = 0;

	/**
	 * @since 18.0.0
	 */
	public const STATUS_ACCEPTED = 1;

	/**
	 * @since 18.0.0
	 */
	public const STATUS_REJECTED = 2;

	/**
	 * Set the internal id of the share
	 * It is only allowed to set the internal id of a share once.
	 * Attempts to override the internal id will result in an IllegalIDChangeException
	 *
	 * @param string $id
	 * @return \OCP\Share\IShare
	 * @throws IllegalIDChangeException
	 * @throws \InvalidArgumentException
	 * @since 9.1.0
	 */
	public function setId($id);

	/**
	 * Get the internal id of the share.
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getId();

	/**
	 * Get the full share id. This is the <providerid>:<internalid>.
	 * The full id is unique in the system.
	 *
	 * @return string
	 * @since 9.0.0
	 * @throws \UnexpectedValueException If the fullId could not be constructed
	 */
	public function getFullId();

	/**
	 * Set the provider id of the share
	 * It is only allowed to set the provider id of a share once.
	 * Attempts to override the provider id will result in an IllegalIDChangeException
	 *
	 * @param string $id
	 * @return \OCP\Share\IShare
	 * @throws IllegalIDChangeException
	 * @throws \InvalidArgumentException
	 * @since 9.1.0
	 */
	public function setProviderId($id);

	/**
	 * Set the node of the file/folder that is shared
	 *
	 * @param Node $node
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setNode(Node $node);

	/**
	 * Get the node of the file/folder that is shared
	 *
	 * @return File|Folder
	 * @since 9.0.0
	 * @throws NotFoundException
	 */
	public function getNode();

	/**
	 * Set file id for lazy evaluation of the node
	 * @param int $fileId
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setNodeId($fileId);

	/**
	 * Get the fileid of the node of this share
	 * @return int
	 * @since 9.0.0
	 * @throws NotFoundException
	 */
	public function getNodeId();

	/**
	 * Set the type of node (file/folder)
	 *
	 * @param string $type
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setNodeType($type);

	/**
	 * Get the type of node (file/folder)
	 *
	 * @return string
	 * @since 9.0.0
	 * @throws NotFoundException
	 */
	public function getNodeType();

	/**
	 * Set the shareType
	 *
	 * @param int $shareType
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setShareType($shareType);

	/**
	 * Get the shareType
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getShareType();

	/**
	 * Set the receiver of this share.
	 *
	 * @param string $sharedWith
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setSharedWith($sharedWith);

	/**
	 * Get the receiver of this share.
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getSharedWith();

	/**
	 * Set the display name of the receiver of this share.
	 *
	 * @param string $displayName
	 * @return \OCP\Share\IShare The modified object
	 * @since 14.0.0
	 */
	public function setSharedWithDisplayName($displayName);

	/**
	 * Get the display name of the receiver of this share.
	 *
	 * @return string
	 * @since 14.0.0
	 */
	public function getSharedWithDisplayName();

	/**
	 * Set the avatar of the receiver of this share.
	 *
	 * @param string $src
	 * @return \OCP\Share\IShare The modified object
	 * @since 14.0.0
	 */
	public function setSharedWithAvatar($src);

	/**
	 * Get the avatar of the receiver of this share.
	 *
	 * @return string
	 * @since 14.0.0
	 */
	public function getSharedWithAvatar();

	/**
	 * Set the permissions.
	 * See \OCP\Constants::PERMISSION_*
	 *
	 * @param int $permissions
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setPermissions($permissions);

	/**
	 * Get the share permissions
	 * See \OCP\Constants::PERMISSION_*
	 *
	 * @return int
	 * @since 9.0.0
	 */
	public function getPermissions();

	/**
	 * Set the accepted status
	 * See self::STATUS_*
	 *
	 * @param int $status
	 * @return IShare The modified object
	 * @since 18.0.0
	 */
	public function setStatus(int $status): IShare;

	/**
	 * Get the accepted status
	 * See self::STATUS_*
	 *
	 * @return int
	 * @since 18.0.0
	 */
	public function getStatus(): int;

	/**
	 * Attach a note to a share
	 *
	 * @param string $note
	 * @return \OCP\Share\IShare The modified object
	 * @since 14.0.0
	 */
	public function setNote($note);

	/**
	 * Get note attached to a share
	 *
	 * @return string
	 * @since 14.0.0
	 */
	public function getNote();


	/**
	 * Set the expiration date
	 *
	 * @param null|\DateTime $expireDate
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setExpirationDate($expireDate);

	/**
	 * Get the expiration date
	 *
	 * @return \DateTime
	 * @since 9.0.0
	 */
	public function getExpirationDate();

	/**
	 * Is the share expired ?
	 *
	 * @return boolean
	 * @since 18.0.0
	 */
	public function isExpired();

	/**
	 * set a label for a share, some shares, e.g. public links can have a label
	 *
	 * @param string $label
	 * @return \OCP\Share\IShare The modified object
	 * @since 15.0.0
	 */
	public function setLabel($label);

	/**
	 * get label for the share, some shares, e.g. public links can have a label
	 *
	 * @return string
	 * @since 15.0.0
	 */
	public function getLabel();

	/**
	 * Set the sharer of the path.
	 *
	 * @param string $sharedBy
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setSharedBy($sharedBy);

	/**
	 * Get share sharer
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getSharedBy();

	/**
	 * Set the original share owner (who owns the path that is shared)
	 *
	 * @param string $shareOwner
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setShareOwner($shareOwner);

	/**
	 * Get the original share owner (who owns the path that is shared)
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getShareOwner();

	/**
	 * Set the password for this share.
	 * When the share is passed to the share manager to be created
	 * or updated the password will be hashed.
	 *
	 * @param string $password
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setPassword($password);

	/**
	 * Get the password of this share.
	 * If this share is obtained via a shareprovider the password is
	 * hashed.
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getPassword();


	/**
	 * Set if the recipient can start a conversation with the owner to get the
	 * password using Nextcloud Talk.
	 *
	 * @param bool $sendPasswordByTalk
	 * @return \OCP\Share\IShare The modified object
	 * @since 14.0.0
	 */
	public function setSendPasswordByTalk(bool $sendPasswordByTalk);

	/**
	 * Get if the recipient can start a conversation with the owner to get the
	 * password using Nextcloud Talk.
	 * The returned value does not take into account other factors, like Talk
	 * being enabled for the owner of the share or not; it just cover whether
	 * the option is enabled for the share itself or not.
	 *
	 * @return bool
	 * @since 14.0.0
	 */
	public function getSendPasswordByTalk(): bool;

	/**
	 * Set the public link token.
	 *
	 * @param string $token
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setToken($token);

	/**
	 * Get the public link token.
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getToken();

	/**
	 * Set the target path of this share relative to the recipients user folder.
	 *
	 * @param string $target
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setTarget($target);

	/**
	 * Get the target path of this share relative to the recipients user folder.
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getTarget();

	/**
	 * Set the time this share was created
	 *
	 * @param \DateTime $shareTime
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setShareTime(\DateTime $shareTime);

	/**
	 * Get the timestamp this share was created
	 *
	 * @return \DateTime
	 * @since 9.0.0
	 */
	public function getShareTime();

	/**
	 * Set if the recipient is informed by mail about the share.
	 *
	 * @param bool $mailSend
	 * @return \OCP\Share\IShare The modified object
	 * @since 9.0.0
	 */
	public function setMailSend($mailSend);

	/**
	 * Get if the recipient informed by mail about the share.
	 *
	 * @return bool
	 * @since 9.0.0
	 */
	public function getMailSend();

	/**
	 * Set the cache entry for the shared node
	 *
	 * @param ICacheEntry $entry
	 * @since 11.0.0
	 */
	public function setNodeCacheEntry(ICacheEntry $entry);

	/**
	 * Get the cache entry for the shared node
	 *
	 * @return null|ICacheEntry
	 * @since 11.0.0
	 */
	public function getNodeCacheEntry();

	/**
	 * Sets a shares hide download state
	 * This is mainly for public shares. It will signal that the share page should
	 * hide download buttons etc.
	 *
	 * @param bool $ro
	 * @return IShare
	 * @since 15.0.0
	 */
	public function setHideDownload(bool $hide): IShare;

	/**
	 * Gets a shares hide download state
	 * This is mainly for public shares. It will signal that the share page should
	 * hide download buttons etc.
	 *
	 * @return bool
	 * @since 15.0.0
	 */
	public function getHideDownload(): bool;
}
