<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Lib;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Auth\IUserProvided;
use OCA\Files_External\Lib\Backend\Backend;

/**
 * External storage configuration
 */
class StorageConfig implements \JsonSerializable {
	const MOUNT_TYPE_ADMIN = 1;
	const MOUNT_TYPE_PERSONAl = 2;

	/**
	 * Storage config id
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Backend
	 *
	 * @var Backend
	 */
	private $backend;

	/**
	 * Authentication mechanism
	 *
	 * @var AuthMechanism
	 */
	private $authMechanism;

	/**
	 * Backend options
	 *
	 * @var array
	 */
	private $backendOptions = [];

	/**
	 * Mount point path, relative to the user's "files" folder
	 *
	 * @var string
	 */
	private $mountPoint;

	/**
	 * Storage status
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Status message
	 *
	 * @var string
	 */
	private $statusMessage;

	/**
	 * Priority
	 *
	 * @var int
	 */
	private $priority;

	/**
	 * List of users who have access to this storage
	 *
	 * @var array
	 */
	private $applicableUsers = [];

	/**
	 * List of groups that have access to this storage
	 *
	 * @var array
	 */
	private $applicableGroups = [];

	/**
	 * Mount-specific options
	 *
	 * @var array
	 */
	private $mountOptions = [];

	/**
	 * Whether it's a personal or admin mount
	 *
	 * @var int
	 */
	private $type;

	/**
	 * Creates a storage config
	 *
	 * @param int|null $id config id or null for a new config
	 */
	public function __construct($id = null) {
		$this->id = $id;
		$this->mountOptions['enable_sharing'] = false;
	}

	/**
	 * Returns the configuration id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Sets the configuration id
	 *
	 * @param int $id configuration id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Returns mount point path relative to the user's
	 * "files" folder.
	 *
	 * @return string path
	 */
	public function getMountPoint() {
		return $this->mountPoint;
	}

	/**
	 * Sets mount point path relative to the user's
	 * "files" folder.
	 * The path will be normalized.
	 *
	 * @param string $mountPoint path
	 */
	public function setMountPoint($mountPoint) {
		$this->mountPoint = \OC\Files\Filesystem::normalizePath($mountPoint);
	}

	/**
	 * @return Backend
	 */
	public function getBackend() {
		return $this->backend;
	}

	/**
	 * @param Backend $backend
	 */
	public function setBackend(Backend $backend) {
		$this->backend= $backend;
	}

	/**
	 * @return AuthMechanism
	 */
	public function getAuthMechanism() {
		return $this->authMechanism;
	}

	/**
	 * @param AuthMechanism $authMechanism
	 */
	public function setAuthMechanism(AuthMechanism $authMechanism) {
		$this->authMechanism = $authMechanism;
	}

	/**
	 * Returns the external storage backend-specific options
	 *
	 * @return array backend options
	 */
	public function getBackendOptions() {
		return $this->backendOptions;
	}

	/**
	 * Sets the external storage backend-specific options
	 *
	 * @param array $backendOptions backend options
	 */
	public function setBackendOptions($backendOptions) {
		if($this->getBackend() instanceof  Backend) {
			$parameters = $this->getBackend()->getParameters();
			foreach($backendOptions as $key => $value) {
				if(isset($parameters[$key])) {
					switch ($parameters[$key]->getType()) {
						case \OCA\Files_External\Lib\DefinitionParameter::VALUE_BOOLEAN:
							$value = (bool)$value;
							break;
					}
					$backendOptions[$key] = $value;
				}
			}
		}

		$this->backendOptions = $backendOptions;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getBackendOption($key) {
		if (isset($this->backendOptions[$key])) {
			return $this->backendOptions[$key];
		}
		return null;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function setBackendOption($key, $value) {
		$this->backendOptions[$key] = $value;
	}

	/**
	 * Returns the mount priority
	 *
	 * @return int priority
	 */
	public function getPriority() {
		return $this->priority;
	}

	/**
	 * Sets the mount priotity
	 *
	 * @param int $priority priority
	 */
	public function setPriority($priority) {
		$this->priority = $priority;
	}

	/**
	 * Returns the users for which to mount this storage
	 *
	 * @return array applicable users
	 */
	public function getApplicableUsers() {
		return $this->applicableUsers;
	}

	/**
	 * Sets the users for which to mount this storage
	 *
	 * @param array|null $applicableUsers applicable users
	 */
	public function setApplicableUsers($applicableUsers) {
		if (is_null($applicableUsers)) {
			$applicableUsers = [];
		}
		$this->applicableUsers = $applicableUsers;
	}

	/**
	 * Returns the groups for which to mount this storage
	 *
	 * @return array applicable groups
	 */
	public function getApplicableGroups() {
		return $this->applicableGroups;
	}

	/**
	 * Sets the groups for which to mount this storage
	 *
	 * @param array|null $applicableGroups applicable groups
	 */
	public function setApplicableGroups($applicableGroups) {
		if (is_null($applicableGroups)) {
			$applicableGroups = [];
		}
		$this->applicableGroups = $applicableGroups;
	}

	/**
	 * Returns the mount-specific options
	 *
	 * @return array mount specific options
	 */
	public function getMountOptions() {
		return $this->mountOptions;
	}

	/**
	 * Sets the mount-specific options
	 *
	 * @param array $mountOptions applicable groups
	 */
	public function setMountOptions($mountOptions) {
		if (is_null($mountOptions)) {
			$mountOptions = [];
		}
		$this->mountOptions = $mountOptions;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getMountOption($key) {
		if (isset($this->mountOptions[$key])) {
			return $this->mountOptions[$key];
		}
		return null;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function setMountOption($key, $value) {
		$this->mountOptions[$key] = $value;
	}

	/**
	 * Gets the storage status, whether the config worked last time
	 *
	 * @return int $status status
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * Gets the message describing the storage status
	 *
	 * @return string|null
	 */
	public function getStatusMessage() {
		return $this->statusMessage;
	}

	/**
	 * Sets the storage status, whether the config worked last time
	 *
	 * @param int $status status
	 * @param string|null $message optional message
	 */
	public function setStatus($status, $message = null) {
		$this->status = $status;
		$this->statusMessage = $message;
	}

	/**
	 * @return int self::MOUNT_TYPE_ADMIN or self::MOUNT_TYPE_PERSONAl
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param int $type self::MOUNT_TYPE_ADMIN or self::MOUNT_TYPE_PERSONAl
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Serialize config to JSON
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		$result = [];
		if (!is_null($this->id)) {
			$result['id'] = $this->id;
		}
		$result['mountPoint'] = $this->mountPoint;
		$result['backend'] = $this->backend->getIdentifier();
		$result['authMechanism'] = $this->authMechanism->getIdentifier();
		$result['backendOptions'] = $this->backendOptions;
		if (!is_null($this->priority)) {
			$result['priority'] = $this->priority;
		}
		if (!empty($this->applicableUsers)) {
			$result['applicableUsers'] = $this->applicableUsers;
		}
		if (!empty($this->applicableGroups)) {
			$result['applicableGroups'] = $this->applicableGroups;
		}
		if (!empty($this->mountOptions)) {
			$result['mountOptions'] = $this->mountOptions;
		}
		if (!is_null($this->status)) {
			$result['status'] = $this->status;
		}
		if (!is_null($this->statusMessage)) {
			$result['statusMessage'] = $this->statusMessage;
		}
		$result['userProvided'] = $this->authMechanism instanceof IUserProvided;
		$result['type'] = ($this->getType() === self::MOUNT_TYPE_PERSONAl) ? 'personal': 'system';
		return $result;
	}
}
