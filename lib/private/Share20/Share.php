<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
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

namespace OC\Share20;

use OCP\Files\Cache\ICacheEntry;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Share\Exceptions\IllegalIDChangeException;
use OCP\Share\IShare;

class Share implements \OCP\Share\IShare {

	/** @var string */
	private $id;
	/** @var string */
	private $providerId;
	/** @var Node */
	private $node;
	/** @var int */
	private $fileId;
	/** @var string */
	private $nodeType;
	/** @var int */
	private $shareType;
	/** @var string */
	private $sharedWith;
	/** @var string */
	private $sharedWithDisplayName;
	/** @var string */
	private $sharedWithAvatar;
	/** @var string */
	private $sharedBy;
	/** @var string */
	private $shareOwner;
	/** @var int */
	private $permissions;
	/** @var int */
	private $status;
	/** @var string */
	private $note = '';
	/** @var \DateTime */
	private $expireDate;
	/** @var string */
	private $password;
	/** @var bool */
	private $sendPasswordByTalk = false;
	/** @var string */
	private $token;
	/** @var int */
	private $parent;
	/** @var string */
	private $target;
	/** @var \DateTime */
	private $shareTime;
	/** @var bool */
	private $mailSend;
	/** @var string */
	private $label = '';

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var ICacheEntry|null */
	private $nodeCacheEntry;

	/** @var bool */
	private $hideDownload = false;

	public function __construct(IRootFolder $rootFolder, IUserManager $userManager) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
	}

	/**
	 * @inheritdoc
	 */
	public function setId($id) {
		if (is_int($id)) {
			$id = (string)$id;
		}

		if(!is_string($id)) {
			throw new \InvalidArgumentException('String expected.');
		}

		if ($this->id !== null) {
			throw new IllegalIDChangeException('Not allowed to assign a new internal id to a share');
		}

		$this->id = trim($id);
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @inheritdoc
	 */
	public function getFullId() {
		if ($this->providerId === null || $this->id === null) {
			throw new \UnexpectedValueException;
		}
		return $this->providerId . ':' . $this->id;
	}

	/**
	 * @inheritdoc
	 */
	public function setProviderId($id) {
		if(!is_string($id)) {
			throw new \InvalidArgumentException('String expected.');
		}

		if ($this->providerId !== null) {
			throw new IllegalIDChangeException('Not allowed to assign a new provider id to a share');
		}

		$this->providerId = trim($id);
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function setNode(Node $node) {
		$this->fileId = null;
		$this->nodeType = null;
		$this->node = $node;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getNode() {
		if ($this->node === null) {

			if ($this->shareOwner === null || $this->fileId === null) {
				throw new NotFoundException();
			}

			// for federated shares the owner can be a remote user, in this
			// case we use the initiator
			if($this->userManager->userExists($this->shareOwner)) {
				$userFolder = $this->rootFolder->getUserFolder($this->shareOwner);
			} else {
				$userFolder = $this->rootFolder->getUserFolder($this->sharedBy);
			}

			$nodes = $userFolder->getById($this->fileId);
			if (empty($nodes)) {
				throw new NotFoundException('Node for share not found, fileid: ' . $this->fileId);
			}

			$this->node = $nodes[0];
		}

		return $this->node;
	}

	/**
	 * @inheritdoc
	 */
	public function setNodeId($fileId) {
		$this->node = null;
		$this->fileId = $fileId;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getNodeId() {
		if ($this->fileId === null) {
			$this->fileId = $this->getNode()->getId();
		}

		return $this->fileId;
	}

	/**
	 * @inheritdoc
	 */
	public function setNodeType($type) {
		if ($type !== 'file' && $type !== 'folder') {
			throw new \InvalidArgumentException();
		}

		$this->nodeType = $type;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getNodeType() {
		if ($this->nodeType === null) {
			$node = $this->getNode();
			$this->nodeType = $node instanceof File ? 'file' : 'folder';
		}

		return $this->nodeType;
	}

	/**
	 * @inheritdoc
	 */
	public function setShareType($shareType) {
		$this->shareType = $shareType;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getShareType() {
		return $this->shareType;
	}

	/**
	 * @inheritdoc
	 */
	public function setSharedWith($sharedWith) {
		if (!is_string($sharedWith)) {
			throw new \InvalidArgumentException();
		}
		$this->sharedWith = $sharedWith;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharedWith() {
		return $this->sharedWith;
	}

	/**
	 * @inheritdoc
	 */
	public function setSharedWithDisplayName($displayName) {
		if (!is_string($displayName)) {
			throw new \InvalidArgumentException();
		}
		$this->sharedWithDisplayName = $displayName;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharedWithDisplayName() {
		return $this->sharedWithDisplayName;
	}

	/**
	 * @inheritdoc
	 */
	public function setSharedWithAvatar($src) {
		if (!is_string($src)) {
			throw new \InvalidArgumentException();
		}
		$this->sharedWithAvatar = $src;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharedWithAvatar() {
		return $this->sharedWithAvatar;
	}

	/**
	 * @inheritdoc
	 */
	public function setPermissions($permissions) {
		//TODO checkes

		$this->permissions = $permissions;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getPermissions() {
		return $this->permissions;
	}

	/**
	 * @inheritdoc
	 */
	public function setStatus(int $status): IShare {
		$this->status = $status;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getStatus(): int {
		return $this->status;
	}

	/**
	 * @inheritdoc
	 */
	public function setNote($note) {
		$this->note = $note;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getNote() {
		if (is_string($this->note)) {
			return $this->note;
		}
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function setLabel($label) {
		$this->label = $label;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getLabel() {
		return $this->label;
	}

	/**
	 * @inheritdoc
	 */
	public function setExpirationDate($expireDate) {
		//TODO checks

		$this->expireDate = $expireDate;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getExpirationDate() {
		return $this->expireDate;
	}

	/**
	 * @inheritdoc
	 */
	public function isExpired() {
		return $this->getExpirationDate() !== null &&
			$this->getExpirationDate() <= new \DateTime();
	}

	/**
	 * @inheritdoc
	 */
	public function setSharedBy($sharedBy) {
		if (!is_string($sharedBy)) {
			throw new \InvalidArgumentException();
		}
		//TODO checks
		$this->sharedBy = $sharedBy;

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharedBy() {
		//TODO check if set
		return $this->sharedBy;
	}

	/**
	 * @inheritdoc
	 */
	public function setShareOwner($shareOwner) {
		if (!is_string($shareOwner)) {
			throw new \InvalidArgumentException();
		}
		//TODO checks

		$this->shareOwner = $shareOwner;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getShareOwner() {
		//TODO check if set
		return $this->shareOwner;
	}

	/**
	 * @inheritdoc
	 */
	public function setPassword($password) {
		$this->password = $password;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @inheritdoc
	 */
	public function setSendPasswordByTalk(bool $sendPasswordByTalk) {
		$this->sendPasswordByTalk = $sendPasswordByTalk;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getSendPasswordByTalk(): bool {
		return $this->sendPasswordByTalk;
	}

	/**
	 * @inheritdoc
	 */
	public function setToken($token) {
		$this->token = $token;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getToken() {
		return $this->token;
	}

	/**
	 * Set the parent of this share
	 *
	 * @param int parent
	 * @return \OCP\Share\IShare
	 * @deprecated The new shares do not have parents. This is just here for legacy reasons.
	 */
	public function setParent($parent) {
		$this->parent = $parent;
		return $this;
	}

	/**
	 * Get the parent of this share.
	 *
	 * @return int
	 * @deprecated The new shares do not have parents. This is just here for legacy reasons.
	 */
	public function getParent() {
		return $this->parent;
	}

	/**
	 * @inheritdoc
	 */
	public function setTarget($target) {
		$this->target = $target;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getTarget() {
		return $this->target;
	}

	/**
	 * @inheritdoc
	 */
	public function setShareTime(\DateTime $shareTime) {
		$this->shareTime = $shareTime;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getShareTime() {
		return $this->shareTime;
	}

	/**
	 * @inheritdoc
	 */
	public function setMailSend($mailSend) {
		$this->mailSend = $mailSend;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function getMailSend() {
		return $this->mailSend;
	}

	/**
	 * @inheritdoc
	 */
	public function setNodeCacheEntry(ICacheEntry $entry) {
		$this->nodeCacheEntry = $entry;
	}

	/**
	 * @inheritdoc
	 */
	public function getNodeCacheEntry() {
		return $this->nodeCacheEntry;
	}

	public function setHideDownload(bool $hide): IShare {
		$this->hideDownload = $hide;
		return $this;
	}

	public function getHideDownload(): bool {
		return $this->hideDownload;
	}
}
