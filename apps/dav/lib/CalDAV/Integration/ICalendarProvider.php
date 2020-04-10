<?php
/**
 * @copyright 2020, Georg Ehrke <oc.list@georgehrke.com>
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
namespace OCA\DAV\CalDAV\Integration;

/**
 * Interface ICalendarProvider
 *
 * @package OCA\DAV\CalDAV\Integration
 * @since 19.0.0
 */
interface ICalendarProvider {

	/**
	 * Provides the appId of the plugin
	 *
	 * @since 19.0.0
	 * @return string AppId
	 */
	public function getAppId(): string;

	/**
	 * Fetches all calendars for a given principal uri
	 *
	 * @since 19.0.0
	 * @param string $principalUri E.g. principals/users/user1
	 * @return ExternalCalendar[] Array of all calendars
	 */
	public function fetchAllForCalendarHome(string $principalUri): array;

	/**
	 * Checks whether plugin has a calendar for a given principalUri and calendarUri
	 *
	 * @since 19.0.0
	 * @param string $principalUri E.g. principals/users/user1
	 * @param string $calendarUri E.g. personal
	 * @return bool True if calendar for principalUri and calendarUri exists, false otherwise
	 */
	public function hasCalendarInCalendarHome(string $principalUri, string $calendarUri): bool;

	/**
	 * Fetches a calendar for a given principalUri and calendarUri
	 * Returns null if calendar does not exist
	 *
	 * @since 19.0.0
	 * @param string $principalUri E.g. principals/users/user1
	 * @param string $calendarUri E.g. personal
	 * @return ExternalCalendar|null Calendar if it exists, null otherwise
	 */
	public function getCalendarInCalendarHome(string $principalUri, string $calendarUri): ?ExternalCalendar;

}
