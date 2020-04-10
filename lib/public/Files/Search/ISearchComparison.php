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

/**
 * @since 12.0.0
 */
interface ISearchComparison extends ISearchOperator {
	const COMPARE_EQUAL = 'eq';
	const COMPARE_GREATER_THAN = 'gt';
	const COMPARE_GREATER_THAN_EQUAL = 'gte';
	const COMPARE_LESS_THAN = 'lt';
	const COMPARE_LESS_THAN_EQUAL = 'lte';
	const COMPARE_LIKE = 'like';

	/**
	 * Get the type of comparison, one of the ISearchComparison::COMPARE_* constants
	 *
	 * @return string
	 * @since 12.0.0
	 */
	public function getType();

	/**
	 * Get the name of the field to compare with
	 *
	 * i.e. 'size', 'name' or 'mimetype'
	 *
	 * @return string
	 * @since 12.0.0
	 */
	public function getField();

	/**
	 * Get the value to compare the field with
	 *
	 * @return string|integer|\DateTime
	 * @since 12.0.0
	 */
	public function getValue();
}
