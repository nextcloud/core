<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Wrapped;

use Icewind\SMB\ACL;
use Icewind\SMB\IFileInfo;

class FileInfo implements IFileInfo {
	/**
	 * @var string
	 */
	protected $path;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var int
	 */
	protected $size;

	/**
	 * @var int
	 */
	protected $time;

	/**
	 * @var int
	 */
	protected $mode;

	/**
	 * @var callable
	 */
	protected $aclCallback;

	/**
	 * @param string $path
	 * @param string $name
	 * @param int $size
	 * @param int $time
	 * @param int $mode
	 * @param callable $aclCallback
	 */
	public function __construct($path, $name, $size, $time, $mode, callable $aclCallback) {
		$this->path = $path;
		$this->name = $name;
		$this->size = $size;
		$this->time = $time;
		$this->mode = $mode;
		$this->aclCallback = $aclCallback;
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
	 * @return int
	 */
	public function getSize() {
		return $this->size;
	}

	/**
	 * @return int
	 */
	public function getMTime() {
		return $this->time;
	}

	/**
	 * @return bool
	 */
	public function isDirectory() {
		return (bool)($this->mode & IFileInfo::MODE_DIRECTORY);
	}

	/**
	 * @return bool
	 */
	public function isReadOnly() {
		return (bool)($this->mode & IFileInfo::MODE_READONLY);
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return (bool)($this->mode & IFileInfo::MODE_HIDDEN);
	}

	/**
	 * @return bool
	 */
	public function isSystem() {
		return (bool)($this->mode & IFileInfo::MODE_SYSTEM);
	}

	/**
	 * @return bool
	 */
	public function isArchived() {
		return (bool)($this->mode & IFileInfo::MODE_ARCHIVE);
	}

	/**
	 * @return ACL[]
	 */
	public function getAcls(): array {
		return ($this->aclCallback)();
	}
}
