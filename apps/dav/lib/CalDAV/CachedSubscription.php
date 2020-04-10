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

use OCA\DAV\Exception\UnsupportedLimitOnInitialSyncException;
use Sabre\CalDAV\Backend\BackendInterface;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

/**
 * Class CachedSubscription
 *
 * @package OCA\DAV\CalDAV
 * @property BackendInterface|CalDavBackend $caldavBackend
 */
class CachedSubscription extends \Sabre\CalDAV\Calendar {

	/**
	 * @return string
	 */
	public function getPrincipalURI():string {
		return $this->calendarInfo['principaluri'];
	}

	/**
	 * @return array
	 */
	public function getACL():array {
		return [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-read',
				'protected' => true,
			],
			[
				'privilege' => '{' . Plugin::NS_CALDAV . '}read-free-busy',
				'principal' => '{DAV:}authenticated',
				'protected' => true,
			],
		];
	}

	/**
	 * @return array
	 */
	public function getChildACL():array {
		return [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			],

			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-read',
				'protected' => true,
			],

		];
	}

	/**
	 * @return null|string
	 */
	public function getOwner() {
		if (isset($this->calendarInfo['{http://owncloud.org/ns}owner-principal'])) {
			return $this->calendarInfo['{http://owncloud.org/ns}owner-principal'];
		}
		return parent::getOwner();
	}

	
	public function delete() {
		$this->caldavBackend->deleteSubscription($this->calendarInfo['id']);
	}

	/**
	 * @param PropPatch $propPatch
	 */
	public function propPatch(PropPatch $propPatch) {
		$this->caldavBackend->updateSubscription($this->calendarInfo['id'], $propPatch);
	}

	/**
	 * @param string $name
	 * @return CalendarObject|\Sabre\CalDAV\ICalendarObject
	 * @throws NotFound
	 */
	public function getChild($name) {
		$obj = $this->caldavBackend->getCalendarObject($this->calendarInfo['id'], $name, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);
		if (!$obj) {
			throw new NotFound('Calendar object not found');
		}

		$obj['acl'] = $this->getChildACL();
		return new CachedSubscriptionObject($this->caldavBackend, $this->calendarInfo, $obj);

	}

	/**
	 * @return array
	 */
	public function getChildren():array {
		$objs = $this->caldavBackend->getCalendarObjects($this->calendarInfo['id'], CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);

		$children = [];
		foreach($objs as $obj) {
			$children[] = new CachedSubscriptionObject($this->caldavBackend, $this->calendarInfo, $obj);
		}

		return $children;
	}

	/**
	 * @param array $paths
	 * @return array
	 */
	public function getMultipleChildren(array $paths):array {
		$objs = $this->caldavBackend->getMultipleCalendarObjects($this->calendarInfo['id'], $paths, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);

		$children = [];
		foreach($objs as $obj) {
			$children[] = new CachedSubscriptionObject($this->caldavBackend, $this->calendarInfo, $obj);
		}

		return $children;
	}

	/**
	 * @param string $name
	 * @param null $calendarData
	 * @return null|string|void
	 * @throws MethodNotAllowed
	 */
	public function createFile($name, $calendarData = null) {
		throw new MethodNotAllowed('Creating objects in cached subscription is not allowed');
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function childExists($name):bool {
		$obj = $this->caldavBackend->getCalendarObject($this->calendarInfo['id'], $name, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);
		if (!$obj) {
			return false;
		}

		return true;
	}

	/**
	 * @param array $filters
	 * @return array
	 */
	public function calendarQuery(array $filters):array {
		return $this->caldavBackend->calendarQuery($this->calendarInfo['id'], $filters, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);
	}

	/**
	 * @inheritDoc
	 */
	public function getChanges($syncToken, $syncLevel, $limit = null) {
		if (!$syncToken && $limit) {
			throw new UnsupportedLimitOnInitialSyncException();
		}

		return parent::getChanges($syncToken, $syncLevel, $limit);
	}
}
