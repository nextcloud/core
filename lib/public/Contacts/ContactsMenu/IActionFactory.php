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

/**
 * @since 12.0
 */
interface IActionFactory {

	/**
	 * Construct and return a new link action for the contacts menu
	 *
	 * @since 12.0
	 *
	 * @param string $icon full path to the action's icon
	 * @param string $name localized name of the action
	 * @param string $href target URL
	 * @return ILinkAction
	 */
	public function newLinkAction($icon, $name, $href);

	/**
	 * Construct and return a new email action for the contacts menu
	 *
	 * @since 12.0
	 *
	 * @param string $icon full path to the action's icon
	 * @param string $name localized name of the action
	 * @param string $email target e-mail address
	 * @return ILinkAction
	 */
	public function newEMailAction($icon, $name, $email);
}
