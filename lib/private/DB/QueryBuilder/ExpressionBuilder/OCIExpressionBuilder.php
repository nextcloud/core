<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\DB\QueryBuilder\ExpressionBuilder;

use OC\DB\QueryBuilder\QueryFunction;
use OCP\DB\QueryBuilder\ILiteral;
use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;

class OCIExpressionBuilder extends ExpressionBuilder {

	/**
	 * @param mixed $column
	 * @param mixed|null $type
	 * @return array|IQueryFunction|string
	 */
	protected function prepareColumn($column, $type) {
		if ($type === IQueryBuilder::PARAM_STR && !is_array($column) && !($column instanceof IParameter) && !($column instanceof ILiteral)) {
			$column = $this->castColumn($column, $type);
		} else {
			$column = $this->helper->quoteColumnNames($column);
		}
		return $column;
	}

	/**
	 * @inheritdoc
	 */
	public function comparison($x, $operator, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->comparison($x, $operator, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function eq($x, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->eq($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function neq($x, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->neq($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function lt($x, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->lt($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function lte($x, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->lte($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function gt($x, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->gt($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function gte($x, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->gte($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function in($x, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->in($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function notIn($x, $y, $type = null) {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->notIn($x, $y);
	}

	/**
	 * Creates a $x = '' statement, because Oracle needs a different check
	 *
	 * @param string $x The field in string format to be inspected by the comparison.
	 * @return string
	 * @since 13.0.0
	 */
	public function emptyString($x) {
		return $this->isNull($x);
	}

	/**
	 * Creates a `$x <> ''` statement, because Oracle needs a different check
	 *
	 * @param string $x The field in string format to be inspected by the comparison.
	 * @return string
	 * @since 13.0.0
	 */
	public function nonEmptyString($x) {
		return $this->isNotNull($x);
	}

	/**
	 * Returns a IQueryFunction that casts the column to the given type
	 *
	 * @param string $column
	 * @param mixed $type One of IQueryBuilder::PARAM_*
	 * @return IQueryFunction
	 */
	public function castColumn($column, $type) {
		if ($type === IQueryBuilder::PARAM_STR) {
			$column = $this->helper->quoteColumnName($column);
			return new QueryFunction('to_char(' . $column . ')');
		}

		return parent::castColumn($column, $type);
	}

	/**
	 * @inheritdoc
	 */
	public function like($x, $y, $type = null) {
		return parent::like($x, $y, $type) . " ESCAPE '\\'";
	}

	/**
	 * @inheritdoc
	 */
	public function iLike($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return new QueryFunction('REGEXP_LIKE(' . $x . ', \'^\' || REPLACE(REPLACE(' . $y . ', \'%\', \'.*\'), \'_\', \'.\') || \'$\', \'i\')');
	}
}
