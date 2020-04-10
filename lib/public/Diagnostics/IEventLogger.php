<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrówczyński <mrow4a@yahoo.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\Diagnostics;

/**
 * Interface IEventLogger
 *
 * @package OCP\Diagnostics
 * @since 8.0.0
 */
interface IEventLogger {
	/**
	 * Mark the start of an event setting its ID $id and providing event description $description.
	 *
	 * @param string $id
	 * @param string $description
	 * @since 8.0.0
	 */
	public function start($id, $description);

	/**
	 * Mark the end of an event with specific ID $id, marked by start() method.
	 * Ending event should store \OCP\Diagnostics\IEvent to
	 * be returned with getEvents() method.
	 *
	 * @param string $id
	 * @since 8.0.0
	 */
	public function end($id);

	/**
	 * Mark the start and the end of an event with specific ID $id and description $description,
	 * explicitly marking start and end of the event, represented by $start and $end timestamps.
	 * Logging event should store \OCP\Diagnostics\IEvent to
	 * be returned with getEvents() method.
	 *
	 * @param string $id
	 * @param string $description
	 * @param float $start
	 * @param float $end
	 * @since 8.0.0
	 */
	public function log($id, $description, $start, $end);

	/**
	 * This method should return all \OCP\Diagnostics\IEvent objects stored using
	 * start()/end() or log() methods
	 *
	 * @return \OCP\Diagnostics\IEvent[]
	 * @since 8.0.0
	 */
	public function getEvents();

	/**
	 * Activate the module for the duration of the request. Deactivated module
	 * does not create and store \OCP\Diagnostics\IEvent objects.
	 * Only activated module should create and store objects to be
	 * returned with getEvents() call.
	 *
	 * @since 12.0.0
	 */
	public function activate();
}
