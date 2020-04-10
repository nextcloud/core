<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
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

namespace OCP\DirectEditing;

use OCP\Files\File;
use OCP\Files\NotFoundException;

/**
 * @since 18.0.0
 */
interface IToken {

	/**
	 * Extend the token validity time
	 *
	 * @since 18.0.0
	 */
	public function extend(): void;

	/**
	 * Invalidate the token
	 *
	 * @since 18.0.0
	 */
	public function invalidate(): void;

	/**
	 * Check if the token has already been used
	 *
	 * @since 18.0.0
	 * @return bool
	 */
	public function hasBeenAccessed(): bool;

	/**
	 * Change to the user scope of the token
	 *
	 * @since 18.0.0
	 */
	public function useTokenScope(): void;

	/**
	 * Get the file that is related to the token
	 *
	 * @since 18.0.0
	 * @return File
	 * @throws NotFoundException
	 */
	public function getFile(): File;

	/**
	 * @since 18.0.0
	 * @return string
	 */
	public function getEditor(): string;

	/**
	 * @since 18.0.0
	 * @return string
	 */
	public function getUser(): string;

}
