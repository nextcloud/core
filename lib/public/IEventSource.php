<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP;

/**
 * wrapper for server side events (http://en.wikipedia.org/wiki/Server-sent_events)
 * includes a fallback for older browsers and IE
 *
 * use server side events with caution, to many open requests can hang the server
 *
 * The event source will initialize the connection to the client when the first data is sent
 * @since 8.0.0
 */
interface IEventSource {
	/**
	 * send a message to the client
	 *
	 * @param string $type
	 * @param mixed $data
	 *
	 * if only one parameter is given, a typeless message will be send with that parameter as data
	 * @since 8.0.0
	 */
	public function send($type, $data = null);

	/**
	 * close the connection of the event source
	 * @since 8.0.0
	 */
	public function close();
}
