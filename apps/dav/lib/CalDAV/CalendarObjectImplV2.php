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

use InvalidArgumentException;
use OCP\Calendar\ICalendarObjectV2;
use Sabre\VObject\Component\VCalendar;

class CalendarObjectImplV2 implements ICalendarObjectV2 {

	/** @var int */
	private $calendarId;

	/** @var string */
	private $uri;

	/** @var VCalendar */
	private $data;

	/** @var CalDavBackend */
	private $backend;

	/**
	 * CalendarImpl constructor.
	 *
	 * @param int $calendarId
	 * @param string $uri
	 * @param VCalendar $data
	 * @param CalDavBackend $backend
	 */
	public function __construct(int $calendarId, string $uri, VCalendar $data, CalDavBackend $backend) {
		$this->calendarId = $calendarId;
		$this->uri = $uri;
		$this->data = $data;
		$this->backend = $backend;
	}

	public function getCalendarKey(): string {
		return (string) $this->calendarId;
	}

	public function getUri(): string {
		return $this->uri;
	}

	public function getVObject(): VCalendar {
		return $this->data;
	}

	public function update(VCalendar $data): void {
		self::validateCalendarData($data);
		$serializedData = $data->serialize();
		$this->backend->updateCalendarObject($this->getCalendarKey(), $this->getUri(), $serializedData);
		$this->data = $data;
	}

	public function delete(): void {
		$this->backend->deleteCalendarObject($this->getCalendarKey(), $this->getUri());
	}

	/**
	 * @param VCalendar $data
	 * @throws InvalidArgumentException
	 */
	public static function validateCalendarData(VCalendar $data): void {
		$result = $data->validate(VCalendar::PROFILE_CALDAV);
		foreach ($result as $warning) {
			if ($warning['level'] === 3) {
				throw new InvalidArgumentException($warning['message']);
			}
		}
	}
}
