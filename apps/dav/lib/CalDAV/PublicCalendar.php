<?php
/**
 * @copyright Copyright (c) 2017, Georg Ehrke
 *
 * @author Gary Kim <gary@garykim.dev>
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

use Sabre\DAV\Exception\NotFound;

class PublicCalendar extends Calendar {

	/**
	 * @param string $name
	 * @throws NotFound
	 * @return PublicCalendarObject
	 */
	public function getChild($name) {
		$obj = $this->caldavBackend->getCalendarObject($this->calendarInfo['id'], $name);

		if (!$obj) {
			throw new NotFound('Calendar object not found');
		}
		if ($obj['classification'] === CalDavBackend::CLASSIFICATION_PRIVATE) {
			throw new NotFound('Calendar object not found');
		}
		$obj['acl'] = $this->getChildACL();

		return new PublicCalendarObject($this->caldavBackend, $this->l10n, $this->calendarInfo, $obj);
	}

	/**
	 * @return PublicCalendarObject[]
	 */
	public function getChildren() {
		$objs = $this->caldavBackend->getCalendarObjects($this->calendarInfo['id']);
		$children = [];
		foreach ($objs as $obj) {
			if ($obj['classification'] === CalDavBackend::CLASSIFICATION_PRIVATE) {
				continue;
			}
			$obj['acl'] = $this->getChildACL();
			$children[] = new PublicCalendarObject($this->caldavBackend, $this->l10n, $this->calendarInfo, $obj);
		}
		return $children;
	}

	/**
	 * @param string[] $paths
	 * @return PublicCalendarObject[]
	 */
	public function getMultipleChildren(array $paths) {
		$objs = $this->caldavBackend->getMultipleCalendarObjects($this->calendarInfo['id'], $paths);
		$children = [];
		foreach ($objs as $obj) {
			if ($obj['classification'] === CalDavBackend::CLASSIFICATION_PRIVATE) {
				continue;
			}
			$obj['acl'] = $this->getChildACL();
			$children[] = new PublicCalendarObject($this->caldavBackend, $this->l10n, $this->calendarInfo, $obj);
		}
		return $children;
	}

	/**
	 * public calendars are always shared
	 * @return bool
	 */
	protected function isShared() {
		return true;
	}
}
