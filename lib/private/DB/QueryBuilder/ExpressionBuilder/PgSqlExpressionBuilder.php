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
use OCP\DB\QueryBuilder\IQueryBuilder;

class PgSqlExpressionBuilder extends ExpressionBuilder {

	/**
	 * Returns a IQueryFunction that casts the column to the given type
	 *
	 * @param string $column
	 * @param mixed $type One of IQueryBuilder::PARAM_*
	 * @return string
	 */
	public function castColumn($column, $type) {
		switch ($type) {
			case IQueryBuilder::PARAM_INT:
				return new QueryFunction('CAST(' . $this->helper->quoteColumnName($column) . ' AS INT)');
			case IQueryBuilder::PARAM_STR:
				return new QueryFunction('CAST(' . $this->helper->quoteColumnName($column) . ' AS TEXT)');
			default:
				return parent::castColumn($column, $type);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function iLike($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->comparison($x, 'ILIKE', $y);
	}

}
