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

namespace OC\DB\QueryBuilder\ExpressionBuilder;

use OC\DB\QueryBuilder\QueryFunction;
use OCP\DB\QueryBuilder\ILiteral;
use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;

class SqliteExpressionBuilder extends ExpressionBuilder {
	/**
	 * @inheritdoc
	 */
	public function like($x, $y, $type = null): string {
		return parent::like($x, $y, $type) . " ESCAPE '\\'";
	}

	public function iLike($x, $y, $type = null): string {
		return $this->like($this->functionBuilder->lower($x), $this->functionBuilder->lower($y), $type);
	}

	/**
	 * @inheritdoc
	 */
	public function comparison($x, string $operator, $y, $type = null): string {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->comparison($x, $operator, $y);
	}


	/**
	 * @inheritdoc
	 */
	public function eq($x, $y, $type = null): string {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->eq($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function neq($x, $y, $type = null): string {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->neq($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function lt($x, $y, $type = null): string {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->lt($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function lte($x, $y, $type = null): string {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->lte($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function gt($x, $y, $type = null): string {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->gt($x, $y);
	}

	/**
	 * @inheritdoc
	 */
	public function gte($x, $y, $type = null): string {
		$x = $this->prepareColumn($x, $type);
		$y = $this->prepareColumn($y, $type);

		return $this->expressionBuilder->gte($x, $y);
	}

	/**
	 * @param mixed $column
	 * @param mixed|null $type
	 * @return array|IQueryFunction|string
	 */
	protected function prepareColumn($column, $type) {
		if ($type === IQueryBuilder::PARAM_DATE && !is_array($column) && !($column instanceof IParameter) && !($column instanceof ILiteral)) {
			$column = $this->castColumn($column, $type);
		} else {
			$column = $this->helper->quoteColumnNames($column);
		}
		return $column;
	}

	/**
	 * Returns a IQueryFunction that casts the column to the given type
	 *
	 * @param string $column
	 * @param mixed $type One of IQueryBuilder::PARAM_*
	 * @return IQueryFunction
	 */
	public function castColumn(string $column, $type): IQueryFunction {
		if ($type === IQueryBuilder::PARAM_DATE) {
			$column = $this->helper->quoteColumnName($column);
			return new QueryFunction('DATETIME(' . $column . ')');
		}

		return parent::castColumn($column, $type);
	}
}
