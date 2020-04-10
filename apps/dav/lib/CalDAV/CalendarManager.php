<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

use OCP\Calendar\IManager;
use OCP\IConfig;
use OCP\IL10N;

class CalendarManager {

	/** @var CalDavBackend */
	private $backend;

	/** @var IL10N */
	private $l10n;

	/** @var IConfig */
	private $config;

	/**
	 * CalendarManager constructor.
	 *
	 * @param CalDavBackend $backend
	 * @param IL10N $l10n
	 * @param IConfig $config
	 */
	public function __construct(CalDavBackend $backend, IL10N $l10n, IConfig $config) {
		$this->backend = $backend;
		$this->l10n = $l10n;
		$this->config = $config;
	}

	/**
	 * @param IManager $cm
	 * @param string $userId
	 */
	public function setupCalendarProvider(IManager $cm, $userId) {
		$calendars = $this->backend->getCalendarsForUser("principals/users/$userId");
		$this->register($cm, $calendars);
	}

	/**
	 * @param IManager $cm
	 * @param array $calendars
	 */
	private function register(IManager $cm, array $calendars) {
		foreach($calendars as $calendarInfo) {
			$calendar = new Calendar($this->backend, $calendarInfo, $this->l10n, $this->config);
			$cm->registerCalendar(new CalendarImpl(
				$calendar,
				$calendarInfo,
				$this->backend
			));
		}
	}
}
