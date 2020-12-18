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

namespace OC\Avatar;

use OCP\IAvatar;
use OCP\IAvatarProvider;
use Psr\Log\LoggerInterface;

class GuestAvatarProvider implements IAvatarProvider {

	/** @var LoggerInterface */
	private $logger;

	public function __construct(
			LoggerInterface $logger) {
		$this->logger = $logger;
	}

	/**
	 * Returns a GuestAvatar instance for the given guest name
	 *
	 * @param string $id the guest name, e.g. "Albert"
	 * @returns IAvatar
	 */
	public function getAvatar(string $id): IAvatar {
		return new GuestAvatar($id, $this->logger);
	}

	/**
	 * Returns whether the current user can access the given avatar or not
	 *
	 * @param IAvatar $avatar ignored
	 * @return bool true, as all users can access guest avatars
	 */
	public function canBeAccessedByCurrentUser(IAvatar $avatar): bool {
		return true;
	}

	/**
	 * Returns whether the current user can modify the given avatar or not
	 *
	 * @param IAvatar $avatar ignored
	 * @return bool false, as guest avatars can not be modified even by the
	 *         guest of the avatar
	 */
	public function canBeModifiedByCurrentUser(IAvatar $avatar): bool {
		return false;
	}

	/**
	 * Returns the latest value of the avatar version
	 *
	 * @param IAvatar $avatar ignored
	 * @return int 0, as versions are not supported by guest avatars
	 */
	public function getVersion(IAvatar $avatar): int {
		return 0;
	}

	/**
	 * Returns the cache duration for guest avatars in seconds
	 *
	 * @param IAvatar $avatar ignored, same duration for all guest avatars
	 * @return int|null the cache duration
	 */
	public function getCacheTimeToLive(IAvatar $avatar): ?int {
		// Cache for 30 minutes
		return 60 * 30;
	}
}
