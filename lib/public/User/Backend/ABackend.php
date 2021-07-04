<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCP\User\Backend;

use OC\User\Backend;
use OCP\IUserBackend;
use OCP\UserInterface;

/**
 * @since 14.0.0
 * @deprecated 21.0.0 use \OCP\Public\User\Backend\AbstractUserBackEnd instead
 */
abstract class ABackend implements IUserBackend, UserInterface {

	/**
	 * @deprecated 14.0.0
	 *
	 * @param int $actions The action to check for
	 * @return bool
	 */
	public function implementsActions($actions): bool {
		$implements = 0;

		if ($this instanceof ICreateUserBackend) {
			$implements |= Backend::CREATE_USER;
		}
		if ($this instanceof ISetPasswordBackend) {
			$implements |= Backend::SET_PASSWORD;
		}
		if ($this instanceof ICheckPasswordBackend) {
			$implements |= Backend::CHECK_PASSWORD;
		}
		if ($this instanceof IGetHomeBackend) {
			$implements |= Backend::GET_HOME;
		}
		if ($this instanceof IGetDisplayNameBackend) {
			$implements |= Backend::GET_DISPLAYNAME;
		}
		if ($this instanceof ISetDisplayNameBackend) {
			$implements |= Backend::SET_DISPLAYNAME;
		}
		if ($this instanceof IProvideAvatarBackend) {
			$implements |= Backend::PROVIDE_AVATAR;
		}
		if ($this instanceof ICountUsersBackend) {
			$implements |= Backend::COUNT_USERS;
		}

		return (bool)($actions & $implements);
	}
}
