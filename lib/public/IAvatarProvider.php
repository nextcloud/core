<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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

namespace OCP;

/**
 * This class acts as a factory for avatar instances
 *
 * @since 21.0.0
 */
interface IAvatarProvider {

	/**
	 * Returns an IAvatar instance for the given id
	 *
	 * @param string $id the identifier of the avatar
	 * @return IAvatar the avatar instance
	 * @throws AvatarProviderException if an error occurred while getting the
	 *         avatar
	 * @since 21.0.0
	 */
	public function getAvatar(string $id): IAvatar;

	/**
	 * Returns whether the current user can access the given avatar or not
	 *
	 * Clients of IAvatarProvider should not try to access the avatar if not
	 * allowed, but they can ignore it if it makes sense.
	 *
	 * Implementers of IAvatarProvider may not throw \InvalidArgumentException
	 * if the behaviour does not depend on specific avatar instances.
	 *
	 * @param IAvatar $avatar the avatar to check
	 * @return bool true if the current user can access the avatar, false
	 *         otherwise
	 * @throws \InvalidArgumentException if the given avatar is not supported by
	 *         this provider
	 * @since 21.0.0
	 */
	public function canBeAccessedByCurrentUser(IAvatar $avatar): bool;

	/**
	 * Returns whether the current user can modify the given avatar or not
	 *
	 * Clients of IAvatarProvider should not try to modify the avatar (including
	 * deletion) if not allowed, but they can ignore it if it makes sense.
	 *
	 * Implementers of IAvatarProvider may not throw \InvalidArgumentException
	 * if the behaviour does not depend on specific avatar instances.
	 *
	 * @param IAvatar $avatar the avatar to check
	 * @return bool true if the current user can modify the avatar, false
	 *         otherwise
	 * @throws \InvalidArgumentException if the given avatar is not supported by
	 *         this provider
	 * @since 21.0.0
	 */
	public function canBeModifiedByCurrentUser(IAvatar $avatar): bool;

	/**
	 * Returns the latest value of the avatar version
	 *
	 * Implementers of IAvatarProvider may not throw \InvalidArgumentException
	 * if the behaviour does not depend on specific avatar instances (for
	 * example, if versions are not supported and the same version is always
	 * returned).
	 *
	 * @param IAvatar $avatar the avatar to check
	 * @return int the latest value of the avatar version
	 * @throws \InvalidArgumentException if the given avatar is not supported by
	 *         this provider
	 * @since 21.0.0
	 */
	public function getVersion(IAvatar $avatar): int;

	/**
	 * Returns the cache duration in seconds
	 *
	 * Implementers of IAvatarProvider may not throw \InvalidArgumentException
	 * if the behaviour does not depend on specific avatar instances.
	 *
	 * @param IAvatar $avatar the specific avatar, returned by this provider, to
	 *        get the cache for
	 * @return int|null the cache duration, or null for no cache
	 * @throws \InvalidArgumentException if the given avatar is not supported by
	 *         this provider
	 * @since 21.0.0
	 */
	public function getCacheTimeToLive(IAvatar $avatar): ?int;
}
