<?php

declare(strict_types=1);

/**
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\Dashboard;

use OCP\Dashboard\Exceptions\DashboardAppNotAvailableException;
use OCP\Dashboard\Model\IWidgetConfig;
use OCP\Dashboard\Service\IEventsService;
use OCP\Dashboard\Service\IWidgetsService;

/**
 * Interface IDashboardManager
 *
 * IDashboardManager should be used to manage widget from the backend.
 * The call can be done from any Service.
 *
 * @since 15.0.0
 * @deprecated 20.0.0
 *
 */
interface IDashboardManager {


	/**
	 * Register a IWidgetsService.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param IWidgetsService $widgetsService
	 */
	public function registerWidgetsService(IWidgetsService $widgetsService);


	/**
	 * Register a IEventsService.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param IEventsService $eventsService
	 */
	public function registerEventsService(IEventsService $eventsService);


	/**
	 * returns the OCP\Dashboard\Model\IWidgetConfig for a widgetId and userId.
	 *
	 * @see IWidgetConfig
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $widgetId
	 * @param string $userId
	 *
	 * @throws DashboardAppNotAvailableException
	 * @return IWidgetConfig
	 */
	public function getWidgetConfig(string $widgetId, string $userId): IWidgetConfig;


	/**
	 * Create push notifications for users.
	 * $payload is an array that will be send to the Javascript method
	 * called on push.
	 * $uniqueId needs to be used if you send the push to multiples users
	 * and multiples groups so that one user does not have duplicate
	 * notifications.
	 *
	 * Push notifications are created in database and broadcast to user
	 * that are running dashboard.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $widgetId
	 * @param array $users
	 * @param array $payload
	 * @param string $uniqueId
	 * @throws DashboardAppNotAvailableException
	 */
	public function createUsersEvent(string $widgetId, array $users, array $payload, string $uniqueId = '');


	/**
	 * Create push notifications for groups. (ie. createUsersEvent())
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $widgetId
	 * @param array $groups
	 * @param array $payload
	 * @param string $uniqueId
	 * @throws DashboardAppNotAvailableException
	 */
	public function createGroupsEvent(string $widgetId, array $groups, array $payload, string $uniqueId = '');


	/**
	 * Create push notifications for everyone. (ie. createUsersEvent())
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $widgetId
	 * @param array $payload
	 * @param string $uniqueId
	 * @throws DashboardAppNotAvailableException
	 */
	public function createGlobalEvent(string $widgetId, array $payload, string $uniqueId = '');
}
