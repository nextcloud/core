<?php

declare(strict_types=1);

/**
 * @copyright 2018
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OCP\FullTextSearch\Model;

/**
 * Interface ISearchRequestSimpleQuery
 *
 * Add a Query during a Search Request...
 * - on a specific field,
 * - using a specific value,
 * - with a specific comparison
 *
 * @since 17.0.0
 *
 */
interface ISearchRequestSimpleQuery {
	public const COMPARE_TYPE_TEXT = 1;
	public const COMPARE_TYPE_KEYWORD = 2;
	public const COMPARE_TYPE_INT_EQ = 3;
	public const COMPARE_TYPE_INT_GTE = 4;
	public const COMPARE_TYPE_INT_GT = 5;
	public const COMPARE_TYPE_INT_LTE = 6;
	public const COMPARE_TYPE_INT_LT = 7;
	public const COMPARE_TYPE_BOOL = 8;
	public const COMPARE_TYPE_ARRAY = 9;
	public const COMPARE_TYPE_REGEX = 10;
	public const COMPARE_TYPE_WILDCARD = 11;


	/**
	 * Get the compare type of the query
	 *
	 * @return int
	 * @since 17.0.0
	 */
	public function getType(): int;


	/**
	 * Get the field to apply query
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getField(): string;

	/**
	 * Set the field to apply query
	 *
	 * @param string $field
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function setField(string $field): ISearchRequestSimpleQuery;


	/**
	 * Get the all values to compare
	 *
	 * @return array
	 * @since 17.0.0
	 */
	public function getValues(): array;

	/**
	 * Add value to compare (string)
	 *
	 * @param string $value
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function addValue(string $value): ISearchRequestSimpleQuery;

	/**
	 * Add value to compare (int)
	 *
	 * @param int $value
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function addValueInt(int $value): ISearchRequestSimpleQuery;

	/**
	 * Add value to compare (array)
	 *
	 * @param array $value
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function addValueArray(array $value): ISearchRequestSimpleQuery;

	/**
	 * Add value to compare (bool)
	 *
	 * @param bool $value
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function addValueBool(bool $value): ISearchRequestSimpleQuery;
}
