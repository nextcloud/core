<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

class CalendarRoot extends \Sabre\CalDAV\CalendarRoot {

	function getChildForPrincipal(array $principal) {
		return new CalendarHome($this->caldavBackend, $principal);
	}

	function getName() {
		if ($this->principalPrefix === 'principals/calendar-resources' ||
			$this->principalPrefix === 'principals/calendar-rooms') {
			$parts = explode('/', $this->principalPrefix);

			return $parts[1];
		}

		return parent::getName();
	}
}
