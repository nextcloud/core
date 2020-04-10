<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\Files\Search;

use OCP\IUser;

/**
 * @since 12.0.0
 */
interface ISearchQuery {
	/**
	 * @return ISearchOperator
	 * @since 12.0.0
	 */
	public function getSearchOperation();

	/**
	 * Get the maximum number of results to return
	 *
	 * @return integer
	 * @since 12.0.0
	 */
	public function getLimit();

	/**
	 * Get the offset for returned results
	 *
	 * @return integer
	 * @since 12.0.0
	 */
	public function getOffset();

	/**
	 * The fields and directions to order by
	 *
	 * @return ISearchOrder[]
	 * @since 12.0.0
	 */
	public function getOrder();

	/**
	 * The user that issued the search
	 *
	 * @return IUser
	 * @since 12.0.0
	 */
	public function getUser();

	/**
	 * Whether or not the search should be limited to the users home storage
	 *
	 * @return bool
	 * @since 18.0.0
	 */
	public function limitToHome(): bool;
}
