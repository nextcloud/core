<?php

declare(strict_types=1);

/**
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OC\Dashboard;

use OCP\Dashboard\Exceptions\DashboardAppNotAvailableException;
use OCP\Dashboard\IDashboardManager;
use OCP\Dashboard\Model\IWidgetConfig;
use OCP\Dashboard\Service\IEventsService;
use OCP\Dashboard\Service\IWidgetsService;

/**
 * Class DashboardManager
 *
 * @package OC\Dashboard
 */
class DashboardManager implements IDashboardManager {


	/** @var IWidgetsService */
	private $widgetsService;

	/** @var IEventsService */
	private $eventsService;


	/**
	 * @param IEventsService $eventsService
	 */
	public function registerEventsService(IEventsService $eventsService) {
		$this->eventsService = $eventsService;
	}


	/**
	 * @param IWidgetsService $widgetsService
	 */
	public function registerWidgetsService(IWidgetsService $widgetsService) {
		$this->widgetsService = $widgetsService;
	}


	/**
	 * @param string $widgetId
	 * @param string $userId
	 *
	 * @return IWidgetConfig
	 * @throws DashboardAppNotAvailableException
	 */
	public function getWidgetConfig(string $widgetId, string $userId): IWidgetConfig {
		return $this->getWidgetsService()->getWidgetConfig($widgetId, $userId);
	}


	/**
	 * @param string $widgetId
	 * @param array $users
	 * @param array $payload
	 * @param string $uniqueId
	 *
	 * @throws DashboardAppNotAvailableException
	 */
	public function createUsersEvent(string $widgetId, array $users, array $payload, string $uniqueId = '') {
		$this->getEventsService()->createUsersEvent($widgetId, $users, $payload, $uniqueId);
	}


	/**
	 * @param string $widgetId
	 * @param array $groups
	 * @param array $payload
	 * @param string $uniqueId
	 *
	 * @throws DashboardAppNotAvailableException
	 */
	public function createGroupsEvent(string $widgetId, array $groups, array $payload, string $uniqueId = '') {
		$this->getEventsService()->createGroupsEvent($widgetId, $groups, $payload, $uniqueId);
	}


	/**
	 * @param string $widgetId
	 * @param array $payload
	 * @param string $uniqueId
	 *
	 * @throws DashboardAppNotAvailableException
	 */
	public function createGlobalEvent(string $widgetId, array $payload, string $uniqueId = '') {
		$this->getEventsService()->createGlobalEvent($widgetId, $payload, $uniqueId);
	}


	/**
	 * @return IWidgetsService
	 * @throws DashboardAppNotAvailableException
	 */
	private function getWidgetsService() {
		if ($this->widgetsService === null) {
			throw new DashboardAppNotAvailableException('No IWidgetsService registered');
		}

		return $this->widgetsService;
	}


	/**
	 * @return IEventsService
	 * @throws DashboardAppNotAvailableException
	 */
	private function getEventsService() {
		if ($this->eventsService === null) {
			throw new DashboardAppNotAvailableException('No IEventsService registered');
		}

		return $this->eventsService;
	}

}
