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
 * Interface ICalendarObjectV2
 *
 * @package OCP
 * @since 21.0.0
 */
interface ICalendarObjectV2 {

	/**
	 * @return string defining the technical unique key
	 * @since 21.0.0
	 */
	public function getCalendarKey(): string;

	/**
	 * @return string calendar object unique URI
	 * @since 21.0.0
	 */
	public function getUri(): string;

	/**
	 * @return VCalendar the calendar object data
	 * @since 21.0.0
	 */
	public function getVObject(): VCalendar;

	/**
	 * Update calendar object data
	 *
	 * @param VCalendar $data
	 * @since 21.0.0
	 */
	public function update(VCalendar $data): void;

	/**
	 * Delete calendar object
	 * @since 21.0.0
	 */
	public function delete(): void;
}
