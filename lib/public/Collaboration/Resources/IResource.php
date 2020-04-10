<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\Collaboration\Resources;

use OCP\IUser;

/**
 * @since 16.0.0
 */
interface IResource {

	/**
	 * @return string
	 * @since 16.0.0
	 */
	public function getType(): string;

	/**
	 * @return string
	 * @since 16.0.0
	 */
	public function getId(): string;

	/**
	 * @return array
	 * @since 16.0.0
	 */
	public function getRichObject(): array;

	/**
	 * Can a user/guest access the resource
	 *
	 * @param IUser|null $user
	 * @return bool
	 * @since 16.0.0
	 */
	public function canAccess(?IUser $user): bool;

	/**
	 * @return ICollection[]
	 * @since 16.0.0
	 */
	public function getCollections(): array;
}
