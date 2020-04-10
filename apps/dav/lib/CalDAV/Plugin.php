<?php
/**
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\CalDAV;

class Plugin extends \Sabre\CalDAV\Plugin {

	const SYSTEM_CALENDAR_ROOT = 'system-calendars';

	/**
	 * Returns the path to a principal's calendar home.
	 *
	 * The return url must not end with a slash.
	 * This function should return null in case a principal did not have
	 * a calendar home.
	 *
	 * @param string $principalUrl
	 * @return string|null
	 */
	function getCalendarHomeForPrincipal($principalUrl) {
		if (strrpos($principalUrl, 'principals/users', -strlen($principalUrl)) !== false) {
			list(, $principalId) = \Sabre\Uri\split($principalUrl);
			return self::CALENDAR_ROOT . '/' . $principalId;
		}
		if (strrpos($principalUrl, 'principals/calendar-resources', -strlen($principalUrl)) !== false) {
			list(, $principalId) = \Sabre\Uri\split($principalUrl);
			return self::SYSTEM_CALENDAR_ROOT . '/calendar-resources/' . $principalId;
		}
		if (strrpos($principalUrl, 'principals/calendar-rooms', -strlen($principalUrl)) !== false) {
			list(, $principalId) = \Sabre\Uri\split($principalUrl);
			return self::SYSTEM_CALENDAR_ROOT . '/calendar-rooms/' . $principalId;
		}
	}

}
