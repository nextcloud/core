<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
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
 * Interface ICalendar
 *
 * @package OCP
 * @since 13.0.0
 */
interface ICalendar {

	/**
	 * @return string defining the technical unique key
	 * @since 13.0.0
	 */
	public function getKey();

	/**
	 * In comparison to getKey() this function returns a human readable (maybe translated) name
	 * @return null|string
	 * @since 13.0.0
	 */
	public function getDisplayName();

	/**
	 * Calendar color
	 * @return null|string
	 * @since 13.0.0
	 */
	public function getDisplayColor();

	/**
	 * @param string $pattern which should match within the $searchProperties
	 * @param array $searchProperties defines the properties within the query pattern should match
	 * @param array $options - optional parameters:
	 * 	['timerange' => ['start' => new DateTime(...), 'end' => new DateTime(...)]]
	 * @param integer|null $limit - limit number of search results
	 * @param integer|null $offset - offset for paging of search results
	 * @return array an array of events/journals/todos which are arrays of key-value-pairs
	 * @since 13.0.0
	 */
	public function search($pattern, array $searchProperties=[], array $options=[], $limit=null, $offset=null);

	/**
	 * @return integer build up using \OCP\Constants
	 * @since 13.0.0
	 */
	public function getPermissions();
}
