<?php
/**
 * @copyright Copyright (c) 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\Files\SimpleFS;

use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

/**
 * Interface ISimpleFile
 *
 * @package OCP\Files\SimpleFS
 * @since 11.0.0
 */
interface ISimpleFile {

	/**
	 * Get the name
	 *
	 * @return string
	 * @since 11.0.0
	 */
	public function getName();

	/**
	 * Get the size in bytes
	 *
	 * @return int
	 * @since 11.0.0
	 */
	public function getSize();

	/**
	 * Get the ETag
	 *
	 * @return string
	 * @since 11.0.0
	 */
	public function getETag();

	/**
	 * Get the last modification time
	 *
	 * @return int
	 * @since 11.0.0
	 */
	public function getMTime();

	/**
	 * Get the content
	 *
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @return string
	 * @since 11.0.0
	 */
	public function getContent();

	/**
	 * Overwrite the file
	 *
	 * @param string|resource $data
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @since 11.0.0
	 */
	public function putContent($data);

	/**
	 * Delete the file
	 *
	 * @throws NotPermittedException
	 * @since 11.0.0
	 */
	public function delete();

	/**
	 * Get the MimeType
	 *
	 * @return string
	 * @since 11.0.0
	 */
	public function getMimeType();

	/**
	 * Open the file as stream for reading, resulting resource can be operated as stream like the result from php's own fopen
	 *
	 * @return resource
	 * @throws \OCP\Files\NotPermittedException
	 * @since 14.0.0
	 */
	public function read();

	/**
	 * Open the file as stream for writing, resulting resource can be operated as stream like the result from php's own fopen
	 *
	 * @return resource
	 * @throws \OCP\Files\NotPermittedException
	 * @since 14.0.0
	 */
	public function write();
}
