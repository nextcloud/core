<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use Doctrine\DBAL\Query\Expression\ExpressionBuilder as DoctrineExpressionBuilder;
use OC\DB\QueryBuilder\CompositeExpression;
use OC\DB\QueryBuilder\FunctionBuilder\FunctionBuilder;
use OC\DB\QueryBuilder\Literal;
use OC\DB\QueryBuilder\QueryFunction;
use OC\DB\QueryBuilder\QuoteHelper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\ILiteral;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\IDBConnection;

class ExpressionBuilder implements IExpressionBuilder {
	/** @var \Doctrine\DBAL\Query\Expression\ExpressionBuilder */
	protected $expressionBuilder;

	/** @var QuoteHelper */
	protected $helper;

	/** @var IDBConnection */
	protected $connection;

	/** @var FunctionBuilder */
	protected $functionBuilder;

	/**
	 * Initializes a new <tt>ExpressionBuilder</tt>.
	 *
	 * @param IDBConnection $connection
	 * @param IQueryBuilder $queryBuilder
	 */
	public function __construct(IDBConnection $connection, IQueryBuilder $queryBuilder) {
		$this->connection = $connection;
		$this->helper = new QuoteHelper();
		$this->expressionBuilder = new DoctrineExpressionBuilder($connection);
		$this->functionBuilder = $queryBuilder->func();
	}

	/**
	 * Creates a conjunction of the given boolean expressions.
	 *
	 * Example:
	 *
	 *     [php]
	 *     // (u.type = ?) AND (u.role = ?)
	 *     $expr->andX('u.type = ?', 'u.role = ?'));
	 *
	 * @param mixed ...$x Optional clause. Defaults = null, but requires
	 *                 at least one defined when converting to string.
	 *
	 * @return \OCP\DB\QueryBuilder\ICompositeExpression
	 */
	public function andX(...$x) {
		$compositeExpression = call_user_func_array([$this->expressionBuilder, 'andX'], $x);
		return new CompositeExpression($compositeExpression);
	}

	/**
	 * Creates a disjunction of the given boolean expressions.
	 *
	 * Example:
	 *
	 *     [php]
	 *     // (u.type = ?) OR (u.role = ?)
	 *     $qb->where($qb->expr()->orX('u.type = ?', 'u.role = ?'));
	 *
	 * @param mixed ...$x Optional clause. Defaults = null, but requires
	 *                 at least one defined when converting to string.
	 *
	 * @return \OCP\DB\QueryBuilder\ICompositeExpression
	 */
	public function orX(...$x) {
		$compositeExpression = call_user_func_array([$this->expressionBuilder, 'orX'], $x);
		return new CompositeExpression($compositeExpression);
	}

	/**
	 * Creates a comparison expression.
	 *
	 * @param mixed $x The left expression.
	 * @param string $operator One of the IExpressionBuilder::* constants.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function comparison($x, $operator, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->comparison($x, $operator, $y);
	}

	/**
	 * Creates an equality comparison expression with the given arguments.
	 *
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> = <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id = ?
	 *     $expr->eq('u.id', '?');
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function eq($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->eq($x, $y);
	}

	/**
	 * Creates a non equality comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> <> <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id <> 1
	 *     $q->where($q->expr()->neq('u.id', '1'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function neq($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->neq($x, $y);
	}

	/**
	 * Creates a lower-than comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> < <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id < ?
	 *     $q->where($q->expr()->lt('u.id', '?'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function lt($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->lt($x, $y);
	}

	/**
	 * Creates a lower-than-equal comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> <= <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id <= ?
	 *     $q->where($q->expr()->lte('u.id', '?'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function lte($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->lte($x, $y);
	}

	/**
	 * Creates a greater-than comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> > <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id > ?
	 *     $q->where($q->expr()->gt('u.id', '?'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function gt($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->gt($x, $y);
	}

	/**
	 * Creates a greater-than-equal comparison expression with the given arguments.
	 * First argument is considered the left expression and the second is the right expression.
	 * When converted to string, it will generated a <left expr> >= <right expr>. Example:
	 *
	 *     [php]
	 *     // u.id >= ?
	 *     $q->where($q->expr()->gte('u.id', '?'));
	 *
	 * @param mixed $x The left expression.
	 * @param mixed $y The right expression.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function gte($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->gte($x, $y);
	}

	/**
	 * Creates an IS NULL expression with the given arguments.
	 *
	 * @param string $x The field in string format to be restricted by IS NULL.
	 *
	 * @return string
	 */
	public function isNull($x) {
		$x = $this->helper->quoteColumnName($x);
		return $this->expressionBuilder->isNull($x);
	}

	/**
	 * Creates an IS NOT NULL expression with the given arguments.
	 *
	 * @param string $x The field in string format to be restricted by IS NOT NULL.
	 *
	 * @return string
	 */
	public function isNotNull($x) {
		$x = $this->helper->quoteColumnName($x);
		return $this->expressionBuilder->isNotNull($x);
	}

	/**
	 * Creates a LIKE() comparison expression with the given arguments.
	 *
	 * @param string $x Field in string format to be inspected by LIKE() comparison.
	 * @param mixed $y Argument to be used in LIKE() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function like($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->like($x, $y);
	}

	/**
	 * Creates a ILIKE() comparison expression with the given arguments.
	 *
	 * @param string $x Field in string format to be inspected by ILIKE() comparison.
	 * @param mixed $y Argument to be used in ILIKE() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function iLike($x, $y, $type = null) {
		return $this->expressionBuilder->like($this->functionBuilder->lower($x), $this->functionBuilder->lower($y));
	}

	/**
	 * Creates a NOT LIKE() comparison expression with the given arguments.
	 *
	 * @param string $x Field in string format to be inspected by NOT LIKE() comparison.
	 * @param mixed $y Argument to be used in NOT LIKE() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function notLike($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnName($y);
		return $this->expressionBuilder->notLike($x, $y);
	}

	/**
	 * Creates a IN () comparison expression with the given arguments.
	 *
	 * @param string $x The field in string format to be inspected by IN() comparison.
	 * @param string|array $y The placeholder or the array of values to be used by IN() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function in($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnNames($y);
		return $this->expressionBuilder->in($x, $y);
	}

	/**
	 * Creates a NOT IN () comparison expression with the given arguments.
	 *
	 * @param string $x The field in string format to be inspected by NOT IN() comparison.
	 * @param string|array $y The placeholder or the array of values to be used by NOT IN() comparison.
	 * @param mixed|null $type one of the IQueryBuilder::PARAM_* constants
	 *                  required when comparing text fields for oci compatibility
	 *
	 * @return string
	 */
	public function notIn($x, $y, $type = null) {
		$x = $this->helper->quoteColumnName($x);
		$y = $this->helper->quoteColumnNames($y);
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
		return $this->eq($x, $this->literal('', IQueryBuilder::PARAM_STR));
	}

	/**
	 * Creates a `$x <> ''` statement, because Oracle needs a different check
	 *
	 * @param string $x The field in string format to be inspected by the comparison.
	 * @return string
	 * @since 13.0.0
	 */
	public function nonEmptyString($x) {
		return $this->neq($x, $this->literal('', IQueryBuilder::PARAM_STR));
	}

	/**
	 * Binary AND Operator copies a bit to the result if it exists in both operands.
	 *
	 * @param string|ILiteral $x The field or value to check
	 * @param int $y Bitmap that must be set
	 * @return IQueryFunction
	 * @since 12.0.0
	 */
	public function bitwiseAnd($x, $y) {
		return new QueryFunction($this->connection->getDatabasePlatform()->getBitAndComparisonExpression(
			$this->helper->quoteColumnName($x),
			$y
		));
	}

	/**
	 * Binary OR Operator copies a bit if it exists in either operand.
	 *
	 * @param string|ILiteral $x The field or value to check
	 * @param int $y Bitmap that must be set
	 * @return IQueryFunction
	 * @since 12.0.0
	 */
	public function bitwiseOr($x, $y) {
		return new QueryFunction($this->connection->getDatabasePlatform()->getBitOrComparisonExpression(
			$this->helper->quoteColumnName($x),
			$y
		));
	}

	/**
	 * Quotes a given input parameter.
	 *
	 * @param mixed $input The parameter to be quoted.
	 * @param mixed|null $type One of the IQueryBuilder::PARAM_* constants
	 *
	 * @return ILiteral
	 */
	public function literal($input, $type = null) {
		return new Literal($this->expressionBuilder->literal($input, $type));
	}

	/**
	 * Returns a IQueryFunction that casts the column to the given type
	 *
	 * @param string $column
	 * @param mixed $type One of IQueryBuilder::PARAM_*
	 * @return string
	 */
	public function castColumn($column, $type) {
		return new QueryFunction(
			$this->helper->quoteColumnName($column)
		);
	}
}
