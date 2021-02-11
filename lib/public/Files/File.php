<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
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

/**
 * Public interface of ownCloud for apps to use.
 * Files/File interface
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP\Files;

use OCP\Lock\LockedException;

/**
 * Interface File
 *
 * @since 6.0.0
 */
interface File extends Node {
	/**
	 * Get the content of the file as string
	 *
	 * @return string
	 * @throws NotPermittedException
	 * @throws LockedException
	 * @since 6.0.0
	 */
	public function getContent();

	/**
	 * Write to the file from string data
	 *
	 * @param string|resource $data
	 * @throws NotPermittedException
	 * @throws GenericFileException
	 * @throws LockedException
	 * @since 6.0.0
	 */
	public function putContent($data);

	/**
	 * Get the mimetype of the file
	 *
	 * @return string
	 * @since 6.0.0
	 */
	public function getMimeType();

	/**
	 * Open the file as stream, resulting resource can be operated as stream like the result from php's own fopen
	 *
	 * @param string $mode
	 * @return resource
	 * @throws NotPermittedException
	 * @throws LockedException
	 * @since 6.0.0
	 */
	public function fopen($mode);

	/**
	 * Compute the hash of the file
	 * Type of hash is set with $type and can be anything supported by php's hash_file
	 *
	 * @param string $type
	 * @param bool $raw
	 * @return string
	 * @since 6.0.0
	 */
	public function hash($type, $raw = false);

	/**
	 * Get the stored checksum for this file
	 *
	 * @return string
	 * @since 9.0.0
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getChecksum();

	/**
	 * Get the extension of this file
	 *
	 * @return string
	 * @since 15.0.0
	 */
	public function getExtension(): string;
}
