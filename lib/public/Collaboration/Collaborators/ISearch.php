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

namespace OCP\Collaboration\Collaborators;

/**
 * Interface ISearch
 *
 * @since 13.0.0
 */
interface ISearch {
	/**
	 * @param string $search
	 * @param array $shareTypes
	 * @param bool $lookup
	 * @param int $limit
	 * @param int $offset
	 * @return array with two elements, 1st ISearchResult as array, 2nd a bool indicating whether more result are available
	 * @since 13.0.0
	 */
	public function search($search, array $shareTypes, $lookup, $limit, $offset);

	/**
	 * @param array $pluginInfo with keys 'shareType' containing the name of a corresponding constant in \OCP\Share and
	 * 	'class' with the class name of the plugin
	 * @since 13.0.0
	 */
	public function registerPlugin(array $pluginInfo);
}
