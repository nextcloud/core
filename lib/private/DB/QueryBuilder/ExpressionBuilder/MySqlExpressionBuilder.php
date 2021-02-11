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

use OC\DB\ConnectionAdapter;
use OCP\DB\QueryBuilder\IQueryBuilder;

class MySqlExpressionBuilder extends ExpressionBuilder {

	/** @var string */
	protected $charset;

	/**
	 * @param ConnectionAdapter $connection
	 * @param IQueryBuilder $queryBuilder
	 */
	public function __construct(ConnectionAdapter $connection, IQueryBuilder $queryBuilder) {
		parent::__construct($connection, $queryBuilder);

		$params = $connection->getInner()->getParams();
		$this->charset = isset($params['charset']) ? $params['charset'] : 'utf8';
	}

	/**
	 * @inheritdoc
	 */
	public function iLike($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->comparison($x, ' COLLATE ' . $this->charset . '_general_ci LIKE', $y);
	}
}
