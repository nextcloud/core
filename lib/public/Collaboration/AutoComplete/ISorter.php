<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCP\Collaboration\AutoComplete;

/**
 * Interface ISorter
 *
 * Sorts the list of .e.g users for auto completion
 *
 * @package OCP\Collaboration\AutoComplete
 * @since 13.0.0
 */
interface ISorter {

	/**
	 * @return string The ID of the sorter, e.g. commenters
	 * @since 13.0.0
	 */
	public function getId();

	/**
	 * executes the sort action
	 *
	 * @param array $sortArray the array to be sorted, provided as reference
	 * @param array $context carries key 'itemType' and 'itemId' of the source object (e.g. a file)
	 * @since 13.0.0
	 */
	public function sort(array &$sortArray, array $context);
}
