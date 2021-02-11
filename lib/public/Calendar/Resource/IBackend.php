<?php
/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OCP\Calendar\BackendTemporarilyUnavailableException;

/**
 * Interface IBackend
 *
 * @since 14.0.0
 */
interface IBackend {

	/**
	 * get a list of all resources in this backend
	 *
	 * @throws BackendTemporarilyUnavailableException
	 * @return IResource[]
	 * @since 14.0.0
	 */
	public function getAllResources():array;

	/**
	 * get a list of all resource identifiers in this backend
	 *
	 * @throws BackendTemporarilyUnavailableException
	 * @return string[]
	 * @since 14.0.0
	 */
	public function listAllResources():array;

	/**
	 * get a resource by it's id
	 *
	 * @param string $id
	 * @throws BackendTemporarilyUnavailableException
	 * @return IResource|null
	 * @since 14.0.0
	 */
	public function getResource($id);

	/**
	 * Get unique identifier of the backend
	 *
	 * @return string
	 * @since 14.0.0
	 */
	public function getBackendIdentifier():string;
}
