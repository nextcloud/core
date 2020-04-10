<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\DB\QueryBuilder;

use OCP\DB\QueryBuilder\ILiteral;
use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryFunction;

class QuoteHelper {
	/**
	 * @param array|string|ILiteral|IParameter|IQueryFunction $strings string, Literal or Parameter
	 * @return array|string
	 */
	public function quoteColumnNames($strings) {
		if (!is_array($strings)) {
			return $this->quoteColumnName($strings);
		}

		$return = [];
		foreach ($strings as $string) {
			$return[] = $this->quoteColumnName($string);
		}

		return $return;
	}

	/**
	 * @param string|ILiteral|IParameter|IQueryFunction $string string, Literal or Parameter
	 * @return string
	 */
	public function quoteColumnName($string) {
		if ($string instanceof IParameter || $string instanceof ILiteral || $string instanceof IQueryFunction) {
			return (string) $string;
		}

		if ($string === null || $string === 'null' || $string === '*') {
			return $string;
		}

		if (!is_string($string)) {
			throw new \InvalidArgumentException('Only strings, Literals and Parameters are allowed');
		}

		$string = str_replace(' AS ', ' as ', $string);
		if (substr_count($string, ' as ')) {
			return implode(' as ', array_map([$this, 'quoteColumnName'], explode(' as ', $string, 2)));
		}

		if (substr_count($string, '.')) {
			list($alias, $columnName) = explode('.', $string, 2);

			if ($columnName === '*') {
				return '`' . $alias . '`.*';
			}

			return '`' . $alias . '`.`' . $columnName . '`';
		}

		return '`' . $string . '`';
	}
}
