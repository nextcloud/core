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
 * Interface IResource
 *
 * @since 14.0.0
 */
interface IResource {

	/**
	 * get the resource id
	 *
	 * This id has to be unique within the backend
	 *
	 * @return string
	 * @since 14.0.0
	 */
	public function getId():string;

	/**
	 * get the display name for a resource
	 *
	 * @return string
	 * @since 14.0.0
	 */
	public function getDisplayName():string;

	/**
	 * Get a list of groupIds that are allowed to access this resource
	 *
	 * If an empty array is returned, no group restrictions are
	 * applied.
	 *
	 * @return string[]
	 * @since 14.0.0
	 */
	public function getGroupRestrictions():array;

	/**
	 * get email-address for resource
	 *
	 * The email address has to be globally unique
	 *
	 * @return string
	 * @since 14.0.0
	 */
	public function getEMail():string;

	/**
	 * Get corresponding backend object
	 *
	 * @return IBackend
	 * @since 14.0.0
	 */
	public function getBackend():IBackend;
}
