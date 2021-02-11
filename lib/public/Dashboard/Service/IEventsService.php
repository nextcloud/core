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

namespace OCP\Dashboard\Service;

use OCP\Dashboard\IDashboardManager;

/**
 * Interface IEventsService
 *
 * The Service is provided by the Dashboard app. The method in this interface
 * are used by the IDashboardManager when creating push event.
 *
 * @since 15.0.0
 * @deprecated 20.0.0
 *
 */
interface IEventsService {


	/**
	 * Create an event for a widget and an array of users.
	 *
	 * @see IDashboardManager::createUsersEvent
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $widgetId
	 * @param array $users
	 * @param array $payload
	 * @param string $uniqueId
	 */
	public function createUsersEvent(string $widgetId, array $users, array $payload, string $uniqueId);


	/**
	 * Create an event for a widget and an array of groups.
	 *
	 * @see IDashboardManager::createGroupsEvent
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $widgetId
	 * @param array $groups
	 * @param array $payload
	 * @param string $uniqueId
	 */
	public function createGroupsEvent(string $widgetId, array $groups, array $payload, string $uniqueId);


	/**
	 * Create a global event for all users that use a specific widget.
	 *
	 * @see IDashboardManager::createGlobalEvent
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $widgetId
	 * @param array $payload
	 * @param string $uniqueId
	 */
	public function createGlobalEvent(string $widgetId, array $payload, string $uniqueId);
}
