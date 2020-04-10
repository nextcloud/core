<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Native;

use Icewind\SMB\ACL;
use Icewind\SMB\IFileInfo;

class NativeFileInfo implements IFileInfo {
	/**
	 * @var string
	 */
	protected $path;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var NativeShare
	 */
	protected $share;

	/**
	 * @var array|null
	 */
	protected $attributeCache = null;

	/**
	 * @var int
	 */
	protected $modeCache;

	/**
	 * @param NativeShare $share
	 * @param string $path
	 * @param string $name
	 */
	public function __construct($share, $path, $name) {
		$this->share = $share;
		$this->path = $path;
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return array
	 */
	protected function stat() {
		if (is_null($this->attributeCache)) {
			$rawAttributes = explode(',', $this->share->getAttribute($this->path, 'system.dos_attr.*'));
			$this->attributeCache = [];
			foreach ($rawAttributes as $rawAttribute) {
				[$name, $value] = explode(':', $rawAttribute);
				$name = strtolower($name);
				if ($name == 'mode') {
					$this->attributeCache[$name] = (int)hexdec(substr($value, 2));
				} else {
					$this->attributeCache[$name] = (int)$value;
				}
			}
		}
		return $this->attributeCache;
	}

	/**
	 * @return int
	 */
	public function getSize() {
		$stat = $this->stat();
		return $stat['size'];
	}

	/**
	 * @return int
	 */
	public function getMTime() {
		$stat = $this->stat();
		return $stat['change_time'];
	}

	/**
	 * @return int
	 */
	protected function getMode() {
		return $this->stat()['mode'];
	}

	/**
	 * @return bool
	 */
	public function isDirectory() {
		$mode = $this->getMode();
		return (bool)($mode & IFileInfo::MODE_DIRECTORY);
	}

	/**
	 * @return bool
	 */
	public function isReadOnly() {
		$mode = $this->getMode();
		return (bool)($mode & IFileInfo::MODE_READONLY);
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		$mode = $this->getMode();
		return (bool)($mode & IFileInfo::MODE_HIDDEN);
	}

	/**
	 * @return bool
	 */
	public function isSystem() {
		$mode = $this->getMode();
		return (bool)($mode & IFileInfo::MODE_SYSTEM);
	}

	/**
	 * @return bool
	 */
	public function isArchived() {
		$mode = $this->getMode();
		return (bool)($mode & IFileInfo::MODE_ARCHIVE);
	}

	/**
	 * @return ACL[]
	 */
	public function getAcls(): array {
		$acls = [];
		$attribute = $this->share->getAttribute($this->path, 'system.nt_sec_desc.acl.*+');

		foreach (explode(',', $attribute) as $acl) {
			[$user, $permissions] = explode(':', $acl, 2);
			[$type, $flags, $mask] = explode('/', $permissions);
			$mask = hexdec($mask);

			$acls[$user] = new ACL($type, $flags, $mask);
		}

		return $acls;
	}
}
