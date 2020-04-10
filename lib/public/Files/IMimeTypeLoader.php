<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCP\Files;

/**
 * Interface IMimeTypeLoader
 * @package OCP\Files
 * @since 8.2.0
 *
 * Interface to load mimetypes
 **/
interface IMimeTypeLoader {

	/**
	 * Get a mimetype from its ID
	 *
	 * @param int $id
	 * @return string|null
	 * @since 8.2.0
	 */
	public function getMimetypeById($id);

	/**
	 * Get a mimetype ID, adding the mimetype to the DB if it does not exist
	 *
	 * @param string $mimetype
	 * @return int
	 * @since 8.2.0
	 */
	public function getId($mimetype);

	/**
	 * Test if a mimetype exists in the database
	 *
	 * @param string $mimetype
	 * @return bool
	 * @since 8.2.0
	 */
	public function exists($mimetype);

	/**
	 * Clear all loaded mimetypes, allow for re-loading
	 *
	 * @since 8.2.0
	 */
	public function reset();
}
