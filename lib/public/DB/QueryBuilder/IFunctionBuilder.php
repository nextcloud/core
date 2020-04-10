<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\DB\QueryBuilder;

/**
 * This class provides a builder for sql some functions
 *
 * @since 12.0.0
 */
interface IFunctionBuilder {
	/**
	 * Calculates the MD5 hash of a given input
	 *
	 * @param mixed $input The input to be hashed
	 *
	 * @return IQueryFunction
	 * @since 12.0.0
	 */
	public function md5($input);

	/**
	 * Combines two input strings
	 *
	 * @param mixed $x The first input string
	 * @param mixed $y The seccond input string
	 *
	 * @return IQueryFunction
	 * @since 12.0.0
	 */
	public function concat($x, $y);

	/**
	 * Takes a substring from the input string
	 *
	 * @param mixed $input The input string
	 * @param mixed $start The start of the substring, note that counting starts at 1
	 * @param mixed $length The length of the substring
	 *
	 * @return IQueryFunction
	 * @since 12.0.0
	 */
	public function substring($input, $start, $length = null);

	/**
	 * Takes the sum of all rows in a column
	 *
	 * @param mixed $field the column to sum
	 *
	 * @return IQueryFunction
	 * @since 12.0.0
	 */
	public function sum($field);

	/**
	 * Transforms a string field or value to lower case
	 *
	 * @param mixed $field
	 * @return IQueryFunction
	 * @since 14.0.0
	 */
	public function lower($field);

	/**
	 * @param mixed $x The first input field or number
	 * @param mixed $y The second input field or number
	 * @return IQueryFunction
	 * @since 14.0.0
	 */
	public function add($x, $y);

	/**
	 * @param mixed $x The first input field or number
	 * @param mixed $y The second input field or number
	 * @return IQueryFunction
	 * @since 14.0.0
	 */
	public function subtract($x, $y);

	/**
	 * @param mixed $count The input to be counted
	 * @param string $alias Alias for the counter
	 *
	 * @return IQueryFunction
	 * @since 14.0.0
	 */
	public function count($count = '', $alias = '');

	/**
	 * Takes the maximum of all rows in a column
	 *
	 * If you want to get the maximum value of multiple columns in the same row, use `greatest` instead
	 *
	 * @param mixed $field the column to maximum
	 *
	 * @return IQueryFunction
	 * @since 18.0.0
	 */
	public function max($field);

	/**
	 * Takes the minimum of all rows in a column
	 *
	 * If you want to get the minimum value of multiple columns in the same row, use `least` instead
	 *
	 * @param mixed $field the column to minimum
	 *
	 * @return IQueryFunction
	 * @since 18.0.0
	 */
	public function min($field);

	/**
	 * Takes the maximum of multiple values
	 *
	 * If you want to get the maximum value of all rows in a column, use `max` instead
	 *
	 * @param mixed $x the first input field or number
	 * @param mixed $y the first input field or number
	 *
	 * @return IQueryFunction
	 * @since 18.0.0
	 */
	public function greatest($x, $y);

	/**
	 * Takes the minimum of multiple values
	 *
	 * If you want to get the minimum value of all rows in a column, use `min` instead
	 *
	 * @param mixed $x the first input field or number
	 * @param mixed $y the first input field or number
	 *
	 * @return IQueryFunction
	 * @since 18.0.0
	 */
	public function least($x, $y);
}
