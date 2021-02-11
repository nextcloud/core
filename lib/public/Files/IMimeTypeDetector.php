<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP\Files;

/**
 * Interface IMimeTypeDetector
 * @since 8.2.0
 *
 * Interface to handle mimetypes (detection and icon retrieval)
 **/
interface IMimeTypeDetector {

	/**
	 * detect mimetype only based on filename, content of file is not used
	 * @param string $path
	 * @return string
	 * @since 8.2.0
	 */
	public function detectPath($path);

	/**
	 * detect mimetype only based on the content of file
	 * @param string $path
	 * @return string
	 * @since 18.0.0
	 */
	public function detectContent(string $path): string;

	/**
	 * detect mimetype based on both filename and content
	 *
	 * @param string $path
	 * @return string
	 * @since 8.2.0
	 */
	public function detect($path);

	/**
	 * Get a secure mimetype that won't expose potential XSS.
	 *
	 * @param string $mimeType
	 * @return string
	 * @since 8.2.0
	 */
	public function getSecureMimeType($mimeType);

	/**
	 * detect mimetype based on the content of a string
	 *
	 * @param string $data
	 * @return string
	 * @since 8.2.0
	 */
	public function detectString($data);

	/**
	 * Get path to the icon of a file type
	 * @param string $mimeType the MIME type
	 * @return string the url
	 * @since 8.2.0
	 */
	public function mimeTypeIcon($mimeType);
}
