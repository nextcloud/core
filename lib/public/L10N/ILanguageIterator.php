<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCP\L10N;

/**
 * Interface ILanguageIterator
 *
 * iterator across language settings (if provided) in this order:
 * 1. returns the forced language or:
 * 2. if applicable, the trunk of 1 (e.g. "fu" instead of "fu_BAR"
 * 3. returns the user language or:
 * 4. if applicable, the trunk of 3
 * 5. returns the system default language or:
 * 6. if applicable, the trunk of 5
 * 7+∞. returns 'en'
 *
 * if settings are not present or truncating is not applicable, the iterator
 * skips to the next valid item itself
 *
 * @package OCP\L10N
 *
 * @since 14.0.0
 */
interface ILanguageIterator extends \Iterator {

	/**
	 * Return the current element
	 *
	 * @since 14.0.0
	 */
	public function current(): string;

	/**
	 * Move forward to next element
	 *
	 * @since 14.0.0
	 */
	public function next();

	/**
	 * Return the key of the current element
	 *
	 * @since 14.0.0
	 */
	public function key():int;

	/**
	 * Checks if current position is valid
	 *
	 * @since 14.0.0
	 */
	public function valid():bool;
}
