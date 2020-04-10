<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP;

/**
 * Interface ITempManager
 *
 * @package OCP
 * @since 8.0.0
 */
interface ITempManager {
	/**
	 * Create a temporary file and return the path
	 *
	 * @param string $postFix
	 * @return string
	 * @since 8.0.0
	 */
	public function getTemporaryFile($postFix = '');

	/**
	 * Create a temporary folder and return the path
	 *
	 * @param string $postFix
	 * @return string
	 * @since 8.0.0
	 */
	public function getTemporaryFolder($postFix = '');

	/**
	 * Remove the temporary files and folders generated during this request
	 * @since 8.0.0
	 */
	public function clean();

	/**
	 * Remove old temporary files and folders that were failed to be cleaned
	 * @since 8.0.0
	 */
	public function cleanOld();

	/**
	 * Get the temporary base directory
	 *
	 * @return string
	 * @since 8.2.0
	 */
	public function getTempBaseDir();
}
