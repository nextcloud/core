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

namespace OCA\DAV\CalDAV;

use OCP\Calendar\ICalendarObjectV2;
use OCP\Calendar\ICalendarV2;
use OCP\Constants;
use Sabre\DAV\Exception\BadRequest;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Sabre\VObject\UUIDUtil;

class CalendarImplV2 implements ICalendarV2 {

	/** @var CalDavBackend */
	private $backend;

	/** @var Calendar */
	private $calendar;

	/** @var array */
	private $calendarInfo;

	/**
	 * CalendarImpl constructor.
	 *
	 * @param Calendar $calendar
	 * @param array $calendarInfo
	 * @param CalDavBackend $backend
	 */
	public function __construct(Calendar $calendar, array $calendarInfo,
								CalDavBackend $backend) {
		$this->calendar = $calendar;
		$this->calendarInfo = $calendarInfo;
		$this->backend = $backend;
	}

	/**
	 * @return string defining the technical unique key
	 * @since 19.0.0
	 */
	public function getKey(): string {
		return (string) $this->calendarInfo['id'];
	}

	/**
	 * In comparison to getKey() this function returns a human readable (maybe translated) name
	 * @return null|string
	 * @since 19.0.0
	 */
	public function getDisplayName(): string {
		return $this->calendarInfo['{DAV:}displayname'];
	}

	/**
	 * Calendar color
	 * @return null|string
	 * @since 19.0.0
	 */
	public function getDisplayColor(): string {
		return $this->calendarInfo['{http://apple.com/ns/ical/}calendar-color'];
	}

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
	public function search($pattern, array $searchProperties=[], array $options=[], int $limit = null, int $offset = null): array {
		return $this->backend->search($this->calendarInfo, $pattern,
			$searchProperties, $options, $limit, $offset);
	}

	/**
	 * @return bool
	 * @since 19.0.0
	 */
	public function isWriteable(): bool {
		$permissions = $this->calendar->getACL();
		$result = 0;
		foreach ($permissions as $permission) {
			switch ($permission['privilege']) {
				case '{DAV:}read':
					$result |= Constants::PERMISSION_READ;
					break;
				case '{DAV:}write':
					$result |= Constants::PERMISSION_CREATE;
					$result |= Constants::PERMISSION_UPDATE;
					break;
				case '{DAV:}all':
					$result |= Constants::PERMISSION_ALL;
					break;
			}
		}

		return $result > Constants::PERMISSION_READ;
	}

	/**
	 * @param string $uri
	 * @return ICalendarObjectV2|null
	 * @since 19.0.0
	 */
	public function getByUri(string $uri): ?ICalendarObjectV2 {
		if ($calendarObjectData = $this->backend->getCalendarObject($this->getKey(), $uri)) {
			if (isset($calendarObjectData['calendardata'])) {
				$calendarData = Reader::read($calendarObjectData['calendardata']);
				if ($calendarData instanceof VCalendar) {
					return new CalendarObjectImplV2($calendarObjectData['calendarid'], $calendarObjectData['uri'], $calendarData, $this->backend);
				}
			}
		}
		return null;
	}

	/**
	 * @param VCalendar $vObject
	 * @return ICalendarObjectV2
	 * @throws BadRequest
	 * @since 19.0.0
	 */
	public function create(VCalendar $vObject): ICalendarObjectV2 {
		CalendarObjectImplV2::validateCalendarData($vObject);
		$uuid = UUIDUtil::getUUID() . '.ics';
		$this->backend->createCalendarObject($this->getKey(), $uuid, $vObject->serialize());
		return $this->getByUri($uuid);
	}
}
