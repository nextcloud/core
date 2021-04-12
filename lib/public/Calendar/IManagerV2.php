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

use Closure;

/**
 * This class provides access to the Nextcloud CalDAV backend.
 * Use this class exclusively if you want to access and manage calendars.
 *
 * Events/Journals/Todos are expressed as instances of ICalendarObjectV2
 *
 * @since 21.0.0
 */
interface IManagerV2 {

	/**
	 * Check if calendars are available
	 *
	 * @return bool true if enabled, false if not
	 * @since 21.0.0
	 */
	public function isEnabled(): bool;

	/**
	 * Get all registered calendars
	 *
	 * @return ICalendarV2[]
	 * @since 21.0.0
	 */
	public function getCalendars(): array;

	/**
	 * Get a calendar by it's key
	 *
	 * @param string $key
	 * @return ICalendarV2|null
	 * @since 21.0.0
	 */
	public function getCalendar(string $key): ?ICalendarV2;

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
	 * @return ICalendarObjectV2[]
	 * @since 21.0.0
	 */
	public function search(string $pattern, array $searchProperties=[], array $options=[], int $limit=null, int $offset=null): array;

	/**
	 * Registers a calendar
	 *
	 * @param ICalendarV2 $calendar
	 * @return void
	 * @since 21.0.0
	 */
	public function registerCalendar(ICalendarV2 $calendar): void;

	/**
	 * Unregisters a calendar
	 *
	 * @param ICalendarV2 $calendar
	 * @return void
	 * @since 21.0.0
	 */
	public function unregisterCalendar(ICalendarV2 $calendar): void;

	/**
	 * In order to improve lazy loading a closure can be registered which will be called in case
	 * calendars are actually requested
	 *
	 * @param Closure $callable
	 * @return void
	 * @since 21.0.0
	 */
	public function register(Closure $callable): void;

	/**
	 * removes all registered calendar instances
	 * @return void
	 * @since 21.0.0
	 */
	public function clear(): void;
}
