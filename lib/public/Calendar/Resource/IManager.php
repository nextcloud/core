<?php
/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCP\Calendar\Resource;

/**
 * Interface IManager
 *
 * @since 14.0.0
 */
interface IManager {

	/**
	 * Registers a resource backend
	 *
	 * @param string $backendClass
	 * @return void
	 * @since 14.0.0
	 */
	public function registerBackend(string $backendClass);

	/**
	 * Unregisters a resource backend
	 *
	 * @param string $backendClass
	 * @return void
	 * @since 14.0.0
	 */
	public function unregisterBackend(string $backendClass);

	/**
	 * @return IBackend[]
	 * @since 14.0.0
	 */
	public function getBackends():array;

	/**
	 * @param string $backendId
	 * @return IBackend|null
	 * @since 14.0.0
	 */
	public function getBackend($backendId);

	/**
	 * removes all registered backend instances
	 * @return void
	 * @since 14.0.0
	 */
	public function clear();
}
