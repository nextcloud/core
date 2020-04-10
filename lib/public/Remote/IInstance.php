<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\Remote;

/**
 * Provides some basic info about a remote Nextcloud instance
 *
 * @since 13.0.0
 */
interface IInstance {
	/**
	 * @return string The url of the remote server without protocol
	 *
	 * @since 13.0.0
	 */
	public function getUrl();

	/**
	 * @return string The of of the remote server with protocol
	 *
	 * @since 13.0.0
	 */
	public function getFullUrl();

	/**
	 * @return string The full version string in '13.1.2.3' format
	 *
	 * @since 13.0.0
	 */
	public function getVersion();

	/**
	 * @return string 'http' or 'https'
	 *
	 * @since 13.0.0
	 */
	public function getProtocol();

	/**
	 * Check that the remote server is installed and not in maintenance mode
	 *
	 * @since 13.0.0
	 *
	 * @return bool
	 */
	public function isActive();
}
