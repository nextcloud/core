<?php
/**
 * @copyright 2019, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCP\Calendar;

/**
 * Interface IMetadataProvider
 *
 * Provider for metadata of a resource or a room
 *
 * @since 17.0.0
 */
interface IMetadataProvider {

	/**
	 * Get a list of all metadata keys available for this room
	 *
	 * Room backends are allowed to return custom keys, beyond the ones
	 * defined in this class. If they do, they should make sure to use their
	 * own namespace.
	 *
	 * @return String[] - A list of available keys
	 * @since 17.0.0
	 */
	public function getAllAvailableMetadataKeys():array;

	/**
	 * Get whether or not a metadata key is set for this room
	 *
	 * @param string $key - The key to check for
	 * @return bool - Whether or not key is available
	 * @since 17.0.0
	 */
	public function hasMetadataForKey(string $key):bool;

	/**
	 * Get the value for a metadata key
	 *
	 * @param string $key - The key to check for
	 * @return string|null - The value stored for the key, null if no value stored
	 * @since 17.0.0
	 */
	public function getMetadataForKey(string $key):?string;
}
