<?php
/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Contacts\ContactsMenu;

use JsonSerializable;

/**
 * @since 12.0
 */
interface IEntry extends JsonSerializable {

	/**
	 * @since 12.0
	 * @return string
	 */
	public function getFullName();

	/**
	 * @since 12.0
	 * @return string[]
	 */
	public function getEMailAddresses();

	/**
	 * @since 12.0
	 * @return string|null image URI
	 */
	public function getAvatar();

	/**
	 * @since 12.0
	 * @param IAction $action an action to show in the contacts menu
	 */
	public function addAction(IAction $action);

	/**
	 * Get an arbitrary property from the contact
	 *
	 * @since 12.0
	 * @param string $key
	 * @return mixed the value of the property or null
	 */
	public function getProperty($key);
}
