<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

interface IFileInfo {
	/*
	 * Mappings of the DOS mode bits, as returned by smbc_getxattr() when the
	 * attribute name "system.dos_attr.mode" (or "system.dos_attr.*" or
	 * "system.*") is specified.
	 */
	const MODE_READONLY = 0x01;
	const MODE_HIDDEN = 0x02;
	const MODE_SYSTEM = 0x04;
	const MODE_VOLUME_ID = 0x08;
	const MODE_DIRECTORY = 0x10;
	const MODE_ARCHIVE = 0x20;
	const MODE_NORMAL = 0x80;

	/**
	 * @return string
	 */
	public function getPath();

	/**
	 * @return string
	 */
	public function getName();

	/**
	 * @return int
	 */
	public function getSize();

	/**
	 * @return int
	 */
	public function getMTime();

	/**
	 * @return bool
	 */
	public function isDirectory();

	/**
	 * @return bool
	 */
	public function isReadOnly();

	/**
	 * @return bool
	 */
	public function isHidden();

	/**
	 * @return bool
	 */
	public function isSystem();

	/**
	 * @return bool
	 */
	public function isArchived();

	/**
	 * @return ACL[]
	 */
	public function getAcls(): array;
}
