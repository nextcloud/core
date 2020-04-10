<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Authentication\TwoFactorAuth;

/**
 * Interface for two-factor providers that provide dark and light provider
 * icons
 *
 * @since 15.0.0
 */
interface IProvidesIcons extends IProvider {

	/**
	 * Get the path to the light (white) icon of this provider
	 *
	 * @return String
	 *
	 * @since 15.0.0
	 */
	public function getLightIcon(): String;

	/**
	 * Get the path to the dark (black) icon of this provider
	 *
	 * @return String
	 *
	 * @since 15.0.0
	 */
	public function getDarkIcon(): String;

}
