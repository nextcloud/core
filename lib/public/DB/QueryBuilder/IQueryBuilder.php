<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author J0WI <J0WI@users.noreply.github.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCP\DB\QueryBuilder;

use Doctrine\DBAL\Connection;
use OCP\DB\Exception;
use OCP\DB\IResult;

/**
 * This class provides a wrapper around Doctrine's QueryBuilder
 * @since 8.2.0
 */
interface IQueryBuilder {

	/**
	 * @since 9.0.0
	 */
	public const PARAM_NULL = \PDO::PARAM_NULL;
	/**
	 * @since 9.0.0
	 */
	public const PARAM_BOOL = \PDO::PARAM_BOOL;
	/**
	 * @since 9.0.0
	 */
	public const PARAM_INT = \PDO::PARAM_INT;
	/**
	 * @since 9.0.0
	 */
	public const PARAM_STR = \PDO::PARAM_STR;
	/**
	 * @since 9.0.0
	 */
	public const PARAM_LOB = \PDO::PARAM_LOB;
	/**
	 * @since 9.0.0
	 */
	public const PARAM_DATE = 'datetime';

	/**
	 * @since 9.0.0
	 */
	public const PARAM_INT_ARRAY = Connection::PARAM_INT_ARRAY;
	/**
	 * @since 9.0.0
	 */
	public const PARAM_STR_ARRAY = Connection::PARAM_STR_ARRAY;


	/**
	 * Enable/disable automatic prefixing of table names with the oc_ prefix
	 *
	 * @param bool $enabled If set to true table names will be prefixed with the
	 * owncloud database prefix automatically.
	 * @since 8.2.0
	 */
	public function automaticTablePrefix($enabled);

	/**
	 * Gets an ExpressionBuilder used for object-oriented construction of query expressions.
	 * This producer method is intended for convenient inline usage. Example:
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u')
	 *         ->from('users', 'u')
	 *         ->where($qb->expr()->eq('u.id', 1));
	 * </code>
	 *
	 * For more complex expression construction, consider storing the expression
	 * builder object in a local variable.
	 *
	 * @return \OCP\DB\QueryBuilder\IExpressionBuilder
	 * @since 8.2.0
	 */
	public function expr();

	/**
	 * Gets an FunctionBuilder used for object-oriented construction of query functions.
	 * This producer method is intended for convenient inline usage. Example:
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u')
	 *         ->from('users', 'u')
	 *         ->where($qb->fun()->md5('u.id'));
	 * </code>
	 *
	 * For more complex function construction, consider storing the function
	 * builder object in a local variable.
	 *
	 * @return \OCP\DB\QueryBuilder\IFunctionBuilder
	 * @since 12.0.0
	 */
	public function func();

	/**
	 * Gets the type of the currently built query.
	 *
	 * @return integer
	 * @since 8.2.0
	 */
	public function getType();

	/**
	 * Gets the associated DBAL Connection for this query builder.
	 *
	 * @return \OCP\IDBConnection
	 * @since 8.2.0
	 */
	public function getConnection();

	/**
	 * Gets the state of this query builder instance.
	 *
	 * @return integer Either QueryBuilder::STATE_DIRTY or QueryBuilder::STATE_CLEAN.
	 * @since 8.2.0
	 */
	public function getState();

	/**
	 * Executes this query using the bound parameters and their types.
	 *
	 * Uses {@see Connection::executeQuery} for select statements and {@see Connection::executeUpdate}
	 * for insert, update and delete statements.
	 *
	 * Warning: until Nextcloud 20, this method could return a \Doctrine\DBAL\Driver\Statement but since
	 *          that interface changed in a breaking way the adapter \OCP\DB\QueryBuilder\IStatement is returned
	 *          to bridge old code to the new API
	 *
	 * @return IResult|int
	 * @throws Exception since 21.0.0
	 * @since 8.2.0
	 */
	public function execute();

	/**
	 * Gets the complete SQL string formed by the current specifications of this QueryBuilder.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u')
	 *         ->from('User', 'u')
	 *     echo $qb->getSQL(); // SELECT u FROM User u
	 * </code>
	 *
	 * @return string The SQL query string.
	 * @since 8.2.0
	 */
	public function getSQL();

	/**
	 * Sets a query parameter for the query being constructed.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u')
	 *         ->from('users', 'u')
	 *         ->where('u.id = :user_id')
	 *         ->setParameter(':user_id', 1);
	 * </code>
	 *
	 * @param string|integer $key The parameter position or name.
	 * @param mixed $value The parameter value.
	 * @param string|null|int $type One of the IQueryBuilder::PARAM_* constants.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function setParameter($key, $value, $type = null);

	/**
	 * Sets a collection of query parameters for the query being constructed.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u')
	 *         ->from('users', 'u')
	 *         ->where('u.id = :user_id1 OR u.id = :user_id2')
	 *         ->setParameters(array(
	 *             ':user_id1' => 1,
	 *             ':user_id2' => 2
	 *         ));
	 * </code>
	 *
	 * @param array $params The query parameters to set.
	 * @param array $types The query parameters types to set.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function setParameters(array $params, array $types = []);

	/**
	 * Gets all defined query parameters for the query being constructed indexed by parameter index or name.
	 *
	 * @return array The currently defined query parameters indexed by parameter index or name.
	 * @since 8.2.0
	 */
	public function getParameters();

	/**
	 * Gets a (previously set) query parameter of the query being constructed.
	 *
	 * @param mixed $key The key (index or name) of the bound parameter.
	 *
	 * @return mixed The value of the bound parameter.
	 * @since 8.2.0
	 */
	public function getParameter($key);

	/**
	 * Gets all defined query parameter types for the query being constructed indexed by parameter index or name.
	 *
	 * @return array The currently defined query parameter types indexed by parameter index or name.
	 * @since 8.2.0
	 */
	public function getParameterTypes();

	/**
	 * Gets a (previously set) query parameter type of the query being constructed.
	 *
	 * @param mixed $key The key (index or name) of the bound parameter type.
	 *
	 * @return mixed The value of the bound parameter type.
	 * @since 8.2.0
	 */
	public function getParameterType($key);

	/**
	 * Sets the position of the first result to retrieve (the "offset").
	 *
	 * @param integer $firstResult The first result to return.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function setFirstResult($firstResult);

	/**
	 * Gets the position of the first result the query object was set to retrieve (the "offset").
	 * Returns NULL if {@link setFirstResult} was not applied to this QueryBuilder.
	 *
	 * @return integer The position of the first result.
	 * @since 8.2.0
	 */
	public function getFirstResult();

	/**
	 * Sets the maximum number of results to retrieve (the "limit").
	 *
	 * @param integer $maxResults The maximum number of results to retrieve.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function setMaxResults($maxResults);

	/**
	 * Gets the maximum number of results the query object was set to retrieve (the "limit").
	 * Returns NULL if {@link setMaxResults} was not applied to this query builder.
	 *
	 * @return int|null The maximum number of results.
	 * @since 8.2.0
	 */
	public function getMaxResults();

	/**
	 * Specifies an item that is to be returned in the query result.
	 * Replaces any previously specified selections, if any.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.id', 'p.id')
	 *         ->from('users', 'u')
	 *         ->leftJoin('u', 'phonenumbers', 'p', 'u.id = p.user_id');
	 * </code>
	 *
	 * @param mixed ...$selects The selection expressions.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function select(...$selects);

	/**
	 * Specifies an item that is to be returned with a different name in the query result.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->selectAlias('u.id', 'user_id')
	 *         ->from('users', 'u')
	 *         ->leftJoin('u', 'phonenumbers', 'p', 'u.id = p.user_id');
	 * </code>
	 *
	 * @param mixed $select The selection expressions.
	 * @param string $alias The column alias used in the constructed query.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.1
	 */
	public function selectAlias($select, $alias);

	/**
	 * Specifies an item that is to be returned uniquely in the query result.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->selectDistinct('type')
	 *         ->from('users');
	 * </code>
	 *
	 * @param mixed $select The selection expressions.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 9.0.0
	 */
	public function selectDistinct($select);

	/**
	 * Adds an item that is to be returned in the query result.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.id')
	 *         ->addSelect('p.id')
	 *         ->from('users', 'u')
	 *         ->leftJoin('u', 'phonenumbers', 'u.id = p.user_id');
	 * </code>
	 *
	 * @param mixed ...$select The selection expression.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function addSelect(...$select);

	/**
	 * Turns the query being built into a bulk delete query that ranges over
	 * a certain table.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->delete('users', 'u')
	 *         ->where('u.id = :user_id');
	 *         ->setParameter(':user_id', 1);
	 * </code>
	 *
	 * @param string $delete The table whose rows are subject to the deletion.
	 * @param string $alias The table alias used in the constructed query.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function delete($delete = null, $alias = null);

	/**
	 * Turns the query being built into a bulk update query that ranges over
	 * a certain table
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->update('users', 'u')
	 *         ->set('u.password', md5('password'))
	 *         ->where('u.id = ?');
	 * </code>
	 *
	 * @param string $update The table whose rows are subject to the update.
	 * @param string $alias The table alias used in the constructed query.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function update($update = null, $alias = null);

	/**
	 * Turns the query being built into an insert query that inserts into
	 * a certain table
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->insert('users')
	 *         ->values(
	 *             array(
	 *                 'name' => '?',
	 *                 'password' => '?'
	 *             )
	 *         );
	 * </code>
	 *
	 * @param string $insert The table into which the rows should be inserted.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function insert($insert = null);

	/**
	 * Creates and adds a query root corresponding to the table identified by the
	 * given alias, forming a cartesian product with any existing query roots.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.id')
	 *         ->from('users', 'u')
	 * </code>
	 *
	 * @param string $from The table.
	 * @param string|null $alias The alias of the table.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function from($from, $alias = null);

	/**
	 * Creates and adds a join to the query.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.name')
	 *         ->from('users', 'u')
	 *         ->join('u', 'phonenumbers', 'p', 'p.is_primary = 1');
	 * </code>
	 *
	 * @param string $fromAlias The alias that points to a from clause.
	 * @param string $join The table name to join.
	 * @param string $alias The alias of the join table.
	 * @param string $condition The condition for the join.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function join($fromAlias, $join, $alias, $condition = null);

	/**
	 * Creates and adds a join to the query.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.name')
	 *         ->from('users', 'u')
	 *         ->innerJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
	 * </code>
	 *
	 * @param string $fromAlias The alias that points to a from clause.
	 * @param string $join The table name to join.
	 * @param string $alias The alias of the join table.
	 * @param string $condition The condition for the join.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function innerJoin($fromAlias, $join, $alias, $condition = null);

	/**
	 * Creates and adds a left join to the query.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.name')
	 *         ->from('users', 'u')
	 *         ->leftJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
	 * </code>
	 *
	 * @param string $fromAlias The alias that points to a from clause.
	 * @param string $join The table name to join.
	 * @param string $alias The alias of the join table.
	 * @param string $condition The condition for the join.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function leftJoin($fromAlias, $join, $alias, $condition = null);

	/**
	 * Creates and adds a right join to the query.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.name')
	 *         ->from('users', 'u')
	 *         ->rightJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
	 * </code>
	 *
	 * @param string $fromAlias The alias that points to a from clause.
	 * @param string $join The table name to join.
	 * @param string $alias The alias of the join table.
	 * @param string $condition The condition for the join.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function rightJoin($fromAlias, $join, $alias, $condition = null);

	/**
	 * Sets a new value for a column in a bulk update query.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->update('users', 'u')
	 *         ->set('u.password', md5('password'))
	 *         ->where('u.id = ?');
	 * </code>
	 *
	 * @param string $key The column to set.
	 * @param ILiteral|IParameter|IQueryFunction|string $value The value, expression, placeholder, etc.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function set($key, $value);

	/**
	 * Specifies one or more restrictions to the query result.
	 * Replaces any previously specified restrictions, if any.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.name')
	 *         ->from('users', 'u')
	 *         ->where('u.id = ?');
	 *
	 *     // You can optionally programatically build and/or expressions
	 *     $qb = $conn->getQueryBuilder();
	 *
	 *     $or = $qb->expr()->orx();
	 *     $or->add($qb->expr()->eq('u.id', 1));
	 *     $or->add($qb->expr()->eq('u.id', 2));
	 *
	 *     $qb->update('users', 'u')
	 *         ->set('u.password', md5('password'))
	 *         ->where($or);
	 * </code>
	 *
	 * @param mixed $predicates The restriction predicates.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function where(...$predicates);

	/**
	 * Adds one or more restrictions to the query results, forming a logical
	 * conjunction with any previously specified restrictions.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u')
	 *         ->from('users', 'u')
	 *         ->where('u.username LIKE ?')
	 *         ->andWhere('u.is_active = 1');
	 * </code>
	 *
	 * @param mixed ...$where The query restrictions.
	 *
	 * @return $this This QueryBuilder instance.
	 *
	 * @see where()
	 * @since 8.2.0
	 */
	public function andWhere(...$where);

	/**
	 * Adds one or more restrictions to the query results, forming a logical
	 * disjunction with any previously specified restrictions.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.name')
	 *         ->from('users', 'u')
	 *         ->where('u.id = 1')
	 *         ->orWhere('u.id = 2');
	 * </code>
	 *
	 * @param mixed ...$where The WHERE statement.
	 *
	 * @return $this This QueryBuilder instance.
	 *
	 * @see where()
	 * @since 8.2.0
	 */
	public function orWhere(...$where);

	/**
	 * Specifies a grouping over the results of the query.
	 * Replaces any previously specified groupings, if any.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.name')
	 *         ->from('users', 'u')
	 *         ->groupBy('u.id');
	 * </code>
	 *
	 * @param mixed ...$groupBys The grouping expression.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function groupBy(...$groupBys);

	/**
	 * Adds a grouping expression to the query.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->select('u.name')
	 *         ->from('users', 'u')
	 *         ->groupBy('u.lastLogin');
	 *         ->addGroupBy('u.createdAt')
	 * </code>
	 *
	 * @param mixed ...$groupBy The grouping expression.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function addGroupBy(...$groupBy);

	/**
	 * Sets a value for a column in an insert query.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->insert('users')
	 *         ->values(
	 *             array(
	 *                 'name' => '?'
	 *             )
	 *         )
	 *         ->setValue('password', '?');
	 * </code>
	 *
	 * @param string $column The column into which the value should be inserted.
	 * @param IParameter|string $value The value that should be inserted into the column.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function setValue($column, $value);

	/**
	 * Specifies values for an insert query indexed by column names.
	 * Replaces any previous values, if any.
	 *
	 * <code>
	 *     $qb = $conn->getQueryBuilder()
	 *         ->insert('users')
	 *         ->values(
	 *             array(
	 *                 'name' => '?',
	 *                 'password' => '?'
	 *             )
	 *         );
	 * </code>
	 *
	 * @param array $values The values to specify for the insert query indexed by column names.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function values(array $values);

	/**
	 * Specifies a restriction over the groups of the query.
	 * Replaces any previous having restrictions, if any.
	 *
	 * @param mixed ...$having The restriction over the groups.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function having(...$having);

	/**
	 * Adds a restriction over the groups of the query, forming a logical
	 * conjunction with any existing having restrictions.
	 *
	 * @param mixed ...$having The restriction to append.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function andHaving(...$having);

	/**
	 * Adds a restriction over the groups of the query, forming a logical
	 * disjunction with any existing having restrictions.
	 *
	 * @param mixed ...$having The restriction to add.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function orHaving(...$having);

	/**
	 * Specifies an ordering for the query results.
	 * Replaces any previously specified orderings, if any.
	 *
	 * @param string $sort The ordering expression.
	 * @param string $order The ordering direction.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function orderBy($sort, $order = null);

	/**
	 * Adds an ordering to the query results.
	 *
	 * @param string $sort The ordering expression.
	 * @param string $order The ordering direction.
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function addOrderBy($sort, $order = null);

	/**
	 * Gets a query part by its name.
	 *
	 * @param string $queryPartName
	 *
	 * @return mixed
	 * @since 8.2.0
	 */
	public function getQueryPart($queryPartName);

	/**
	 * Gets all query parts.
	 *
	 * @return array
	 * @since 8.2.0
	 */
	public function getQueryParts();

	/**
	 * Resets SQL parts.
	 *
	 * @param array|null $queryPartNames
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function resetQueryParts($queryPartNames = null);

	/**
	 * Resets a single SQL part.
	 *
	 * @param string $queryPartName
	 *
	 * @return $this This QueryBuilder instance.
	 * @since 8.2.0
	 */
	public function resetQueryPart($queryPartName);

	/**
	 * Creates a new named parameter and bind the value $value to it.
	 *
	 * This method provides a shortcut for PDOStatement::bindValue
	 * when using prepared statements.
	 *
	 * The parameter $value specifies the value that you want to bind. If
	 * $placeholder is not provided bindValue() will automatically create a
	 * placeholder for you. An automatic placeholder will be of the name
	 * ':dcValue1', ':dcValue2' etc.
	 *
	 * For more information see {@link https://www.php.net/pdostatement-bindparam}
	 *
	 * Example:
	 * <code>
	 * $value = 2;
	 * $q->eq( 'id', $q->bindValue( $value ) );
	 * $stmt = $q->executeQuery(); // executed with 'id = 2'
	 * </code>
	 *
	 * @license New BSD License
	 * @link http://www.zetacomponents.org
	 *
	 * @param mixed $value
	 * @param mixed $type
	 * @param string $placeHolder The name to bind with. The string must start with a colon ':'.
	 *
	 * @return IParameter
	 * @since 8.2.0
	 */
	public function createNamedParameter($value, $type = self::PARAM_STR, $placeHolder = null);

	/**
	 * Creates a new positional parameter and bind the given value to it.
	 *
	 * Attention: If you are using positional parameters with the query builder you have
	 * to be very careful to bind all parameters in the order they appear in the SQL
	 * statement , otherwise they get bound in the wrong order which can lead to serious
	 * bugs in your code.
	 *
	 * Example:
	 * <code>
	 *  $qb = $conn->getQueryBuilder();
	 *  $qb->select('u.*')
	 *     ->from('users', 'u')
	 *     ->where('u.username = ' . $qb->createPositionalParameter('Foo', IQueryBuilder::PARAM_STR))
	 *     ->orWhere('u.username = ' . $qb->createPositionalParameter('Bar', IQueryBuilder::PARAM_STR))
	 * </code>
	 *
	 * @param mixed $value
	 * @param integer $type
	 *
	 * @return IParameter
	 * @since 8.2.0
	 */
	public function createPositionalParameter($value, $type = self::PARAM_STR);

	/**
	 * Creates a new parameter
	 *
	 * Example:
	 * <code>
	 *  $qb = $conn->getQueryBuilder();
	 *  $qb->select('u.*')
	 *     ->from('users', 'u')
	 *     ->where('u.username = ' . $qb->createParameter('name'))
	 *     ->setParameter('name', 'Bar', IQueryBuilder::PARAM_STR))
	 * </code>
	 *
	 * @param string $name
	 *
	 * @return IParameter
	 * @since 8.2.0
	 */
	public function createParameter($name);

	/**
	 * Creates a new function
	 *
	 * Attention: Column names inside the call have to be quoted before hand
	 *
	 * Example:
	 * <code>
	 *  $qb = $conn->getQueryBuilder();
	 *  $qb->select($qb->createFunction('COUNT(*)'))
	 *     ->from('users', 'u')
	 *  echo $qb->getSQL(); // SELECT COUNT(*) FROM `users` u
	 * </code>
	 * <code>
	 *  $qb = $conn->getQueryBuilder();
	 *  $qb->select($qb->createFunction('COUNT(`column`)'))
	 *     ->from('users', 'u')
	 *  echo $qb->getSQL(); // SELECT COUNT(`column`) FROM `users` u
	 * </code>
	 *
	 * @param string $call
	 *
	 * @return IQueryFunction
	 * @since 8.2.0
	 */
	public function createFunction($call);

	/**
	 * Used to get the id of the last inserted element
	 * @return int
	 * @throws \BadMethodCallException When being called before an insert query has been run.
	 * @since 9.0.0
	 */
	public function getLastInsertId();

	/**
	 * Returns the table name quoted and with database prefix as needed by the implementation
	 *
	 * @param string $table
	 * @return string
	 * @since 9.0.0
	 */
	public function getTableName($table);

	/**
	 * Returns the column name quoted and with table alias prefix as needed by the implementation
	 *
	 * @param string $column
	 * @param string $tableAlias
	 * @return string
	 * @since 9.0.0
	 */
	public function getColumnName($column, $tableAlias = '');
}
