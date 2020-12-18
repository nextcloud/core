<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * This class provides avatar functionality
 * @since 6.0.0
 */

interface IAvatarManager {

	/**
	 * return a user specific instance of \OCP\IAvatar
	 * @see IAvatar
	 * @param string $user the ownCloud user id
	 * @return IAvatar
	 * @throws \Exception In case the username is potentially dangerous
	 * @throws \OCP\Files\NotFoundException In case there is no user folder yet
	 * @since 6.0.0
	 */
	public function getAvatar(string $user) : IAvatar;

	/**
	 * Returns a guest user avatar instance.
	 *
	 * @param string $name The guest name, e.g. "Albert".
	 * @return IAvatar
	 * @since 16.0.0
	 */
	public function getGuestAvatar(string $name): IAvatar;

	/**
	 * Returns an avatar provider instance of the given type
	 *
	 * @param string $type the type of the avatar
	 * @return IAvatarProvider
	 * @throws \InvalidArgumentException if the type is not known
	 * @since 21.0.0
	 */
	public function getAvatarProvider(string $type): IAvatarProvider;
}
