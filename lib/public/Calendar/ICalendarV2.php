<?php
/**
 * @copyright 2020, Thomas Citharel <nextcloud@tcit.fr>
 *
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

use Sabre\VObject\Component\VCalendar;

/**
 * Interface ICalendarV2
 *
 * @package OCP
 * @since 20.0.0
 */
interface ICalendarV2 {

	/**
	 * @return string defining the technical unique key
	 * @since 20.0.0
	 */
	public function getKey(): string;

	/**
	 * In comparison to getKey() this function returns a human readable (maybe translated) name
	 * @return null|string
	 * @since 20.0.0
	 */
	public function getDisplayName(): ?string;

	/**
	 * Calendar color
	 * @return null|string
	 * @since 20.0.0
	 */
	public function getDisplayColor(): ?string;

	/**
	 * Whether the calendar is writeable
	 *
	 * @return bool
	 * @since 20.0.0
	 */
	public function isWriteable(): bool;

	/**
	 * Get a calendar object by it's URI
	 *
	 * @param string $uri
	 * @return ICalendarObjectV2|null
	 * @since 20.0.0
	 */
	public function getByUri(string $uri): ?ICalendarObjectV2;

	/**
	 * @param string $pattern which should match within the $searchProperties
	 * @param array $searchProperties defines the properties within the query pattern should match
	 * @param array $options - optional parameters:
	 * 	['timerange' => ['start' => new DateTime(...), 'end' => new DateTime(...)]]
	 * @param integer|null $limit - limit number of search results
	 * @param integer|null $offset - offset for paging of search results
	 * @return array an array of events/journals/todos which are arrays of key-value-pairs
	 * @since 19.0.0
	 */
	public function search(string $pattern, array $searchProperties=[], array $options=[], int $limit=null, int $offset=null): array;

	/**
	 * Create a new calendar object into a calendar. Accepts a VCalendar object for calendar data.
	 *
	 * @param VCalendar $vObject
	 * @return ICalendarObjectV2|null
	 * @since 20.0.0
	 */
	public function create(VCalendar $vObject): ?ICalendarObjectV2;
}
