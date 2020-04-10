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

use OCP\IUser;

/**
 * Marks a 2FA provider as activale by the administrator. This means that an
 * admin can activate this provider without user interaction. The provider,
 * therefore, must not require any user-provided configuration.
 *
 * @since 15.0.0
 */
interface IDeactivatableByAdmin extends IProvider {

	/**
	 * Disable this provider for the given user.
	 *
	 * @param IUser $user the user to deactivate this provider for
	 *
	 * @return void
	 *
	 * @since 15.0.0
	 */
	public function disableFor(IUser $user);

}
