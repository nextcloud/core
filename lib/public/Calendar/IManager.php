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
 * This class provides access to the Nextcloud CalDAV backend.
 * Use this class exclusively if you want to access calendars.
 *
 * Events/Journals/Todos in general will be expressed as an array of key-value-pairs.
 * The keys will match the property names defined in https://tools.ietf.org/html/rfc5545
 *
 * [
 *   'id' => 123,
 *   'type' => 'VEVENT',
 *   'calendar-key' => 42,
 *   'objects' => [
 *     [
 *       'SUMMARY' => ['FooBar', []],
 *       'DTSTART' => ['20171001T123456', ['TZID' => 'EUROPE/BERLIN']],
 *       'DURATION' => ['P1D', []],
 * 	     'ATTENDEE' => [
 *         ['mailto:bla@blub.com', ['CN' => 'Mr. Bla Blub']]
 *       ],
 *       'VALARM' => [
 * 	       [
 *           'TRIGGER' => ['19980101T050000Z', ['VALUE' => DATE-TIME]]
 *         ]
 *       ]
 *     ],
 *   ]
 * ]
 *
 * @since 13.0.0
 */
interface IManager {

	/**
	 * This function is used to search and find objects within the user's calendars.
	 * In case $pattern is empty all events/journals/todos will be returned.
	 *
	 * @param string $pattern which should match within the $searchProperties
	 * @param array $searchProperties defines the properties within the query pattern should match
	 * @param array $options - optional parameters:
	 * 	['timerange' => ['start' => new DateTime(...), 'end' => new DateTime(...)]]
	 * @param integer|null $limit - limit number of search results
	 * @param integer|null $offset - offset for paging of search results
	 * @return array an array of events/journals/todos which are arrays of arrays of key-value-pairs
	 * @since 13.0.0
	 */
	public function search($pattern, array $searchProperties=[], array $options=[], $limit=null, $offset=null);

	/**
	 * Check if calendars are available
	 *
	 * @return bool true if enabled, false if not
	 * @since 13.0.0
	 */
	public function isEnabled();

	/**
	 * Registers a calendar
	 *
	 * @param ICalendar $calendar
	 * @return void
	 * @since 13.0.0
	 */
	public function registerCalendar(ICalendar $calendar);

	/**
	 * Unregisters a calendar
	 *
	 * @param ICalendar $calendar
	 * @return void
	 * @since 13.0.0
	 */
	public function unregisterCalendar(ICalendar $calendar);

	/**
	 * In order to improve lazy loading a closure can be registered which will be called in case
	 * calendars are actually requested
	 *
	 * @param \Closure $callable
	 * @return void
	 * @since 13.0.0
	 */
	public function register(\Closure $callable);

	/**
	 * @return ICalendar[]
	 * @since 13.0.0
	 */
	public function getCalendars();

	/**
	 * removes all registered calendar instances
	 * @return void
	 * @since 13.0.0
	 */
	public function clear();
}
