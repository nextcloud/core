<?php

declare(strict_types=1);

/**
 * @copyright 2018 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use Sabre\DAV\Exception\MethodNotAllowed;

/**
 * Class CachedSubscriptionObject
 *
 * @package OCA\DAV\CalDAV
 * @property CalDavBackend $caldavBackend
 */
class CachedSubscriptionObject extends \Sabre\CalDAV\CalendarObject {

	/**
	 * @inheritdoc
	 */
	public function get() {
		// Pre-populating the 'calendardata' is optional, if we don't have it
		// already we fetch it from the backend.
		if (!isset($this->objectData['calendardata'])) {
			$this->objectData = $this->caldavBackend->getCalendarObject($this->calendarInfo['id'], $this->objectData['uri'], CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);
		}

		return $this->objectData['calendardata'];
	}

	/**
	 * @param resource|string $calendarData
	 * @return string|void
	 * @throws MethodNotAllowed
	 */
	public function put($calendarData) {
		throw new MethodNotAllowed('Creating objects in a cached subscription is not allowed');
	}

	/**
	 * @throws MethodNotAllowed
	 */
	public function delete() {
		throw new MethodNotAllowed('Deleting objects in a cached subscription is not allowed');
	}
}
