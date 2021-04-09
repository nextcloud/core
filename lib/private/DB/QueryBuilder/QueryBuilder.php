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
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Query\QueryException;
use OC\DB\ConnectionAdapter;
use OC\DB\QueryBuilder\ExpressionBuilder\ExpressionBuilder;
use OC\DB\QueryBuilder\ExpressionBuilder\MySqlExpressionBuilder;
use OC\DB\QueryBuilder\ExpressionBuilder\OCIExpressionBuilder;
use OC\DB\QueryBuilder\ExpressionBuilder\PgSqlExpressionBuilder;
use OC\DB\QueryBuilder\ExpressionBuilder\SqliteExpressionBuilder;
use OC\DB\QueryBuilder\FunctionBuilder\FunctionBuilder;
use OC\DB\QueryBuilder\FunctionBuilder\OCIFunctionBuilder;
use OC\DB\QueryBuilder\FunctionBuilder\PgSqlFunctionBuilder;
use OC\DB\QueryBuilder\FunctionBuilder\SqliteFunctionBuilder;
use OC\DB\ResultAdapter;
use OC\SystemConfig;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\ILiteral;
use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\ILogger;
use Psr\Log\LoggerInterface;

class QueryBuilder implements IQueryBuilder {

	/** @var ConnectionAdapter */
	private $connection;

	/** @var SystemConfig */
	private $systemConfig;

	/** @var LoggerInterface */
	private $logger;

	/** @var \Doctrine\DBAL\Query\QueryBuilder */
	private $queryBuilder;

	/** @var QuoteHelper */
	private $helper;

	/** @var bool */
	private $automaticTablePrefix = true;

	/** @var string */
	protected $lastInsertedTable;

	/**
	 * Initializes a new QueryBuilder.
	 *
	 * @param ConnectionAdapter $connection
	 * @param SystemConfig $systemConfig
	 * @param LoggerInterface $logger
	 */
	public function __construct(ConnectionAdapter $connection, SystemConfig $systemConfig, LoggerInterface  $logger) {
		$this->connection = $connection;
		$this->systemConfig = $systemConfig;
		$this->logger = $logger;
		$this->queryBuilder = new \Doctrine\DBAL\Query\QueryBuilder($this->connection->getInner());
		$this->helper = new QuoteHelper();
	}

	/**
	 * Enable/disable automatic prefixing of table names with the oc_ prefix
	 *
	 * @param bool $enabled If set to true table names will be prefixed with the
	 * owncloud database prefix automatically.
	 * @since 8.2.0
	 */
	public function automaticTablePrefix($enabled) {
		$this->automaticTablePrefix = (bool) $enabled;
	}

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
	 */
	public function expr() {
		if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
			return new OCIExpressionBuilder($this->connection, $this);
		}
		if ($this->connection->getDatabasePlatform() instanceof PostgreSQL94Platform) {
			return new PgSqlExpressionBuilder($this->connection, $this);
		}
		if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
			return new MySqlExpressionBuilder($this->connection, $this);
		}
		if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
			return new SqliteExpressionBuilder($this->connection, $this);
		}

		return new ExpressionBuilder($this->connection, $this);
	}

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
	 */
	public function func() {
		if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
			return new OCIFunctionBuilder($this->helper);
		}
		if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
			return new SqliteFunctionBuilder($this->helper);
		}
		if ($this->connection->getDatabasePlatform() instanceof PostgreSQL94Platform) {
			return new PgSqlFunctionBuilder($this->helper);
		}

		return new FunctionBuilder($this->helper);
	}

	/**
	 * Gets the type of the currently built query.
	 *
	 * @return integer
	 */
	public function getType() {
		return $this->queryBuilder->getType();
	}

	/**
	 * Gets the associated DBAL Connection for this query builder.
	 *
	 * @return \OCP\IDBConnection
	 */
	public function getConnection() {
		return $this->connection;
	}

	/**
	 * Gets the state of this query builder instance.
	 *
	 * @return integer Either QueryBuilder::STATE_DIRTY or QueryBuilder::STATE_CLEAN.
	 */
	public function getState() {
		return $this->queryBuilder->getState();
	}

	/**
	 * Executes this query using the bound parameters and their types.
	 *
	 * Uses {@see Connection::executeQuery} for select statements and {@see Connection::executeUpdate}
	 * for insert, update and delete statements.
	 *
	 * @return IResult|int
	 */
	public function execute() {
		if ($this->systemConfig->getValue('log_query', false)) {
			try {
				$params = [];
				foreach ($this->getParameters() as $placeholder => $value) {
					if ($value instanceof \DateTime) {
						$params[] = $placeholder . ' => DateTime:\'' . $value->format('c') . '\'';
					} elseif (is_array($value)) {
						$params[] = $placeholder . ' => (\'' . implode('\', \'', $value) . '\')';
					} else {
						$params[] = $placeholder . ' => \'' . $value . '\'';
					}
				}
				if (empty($params)) {
					$this->logger->debug('DB QueryBuilder: \'{query}\'', [
						'query' => $this->getSQL(),
						'app' => 'core',
					]);
				} else {
					$this->logger->debug('DB QueryBuilder: \'{query}\' with parameters: {params}', [
						'query' => $this->getSQL(),
						'params' => implode(', ', $params),
						'app' => 'core',
					]);
				}
			} catch (\Error $e) {
				// likely an error during conversion of $value to string
				$this->logger->debug('DB QueryBuilder: error trying to log SQL query');
				$this->logger->logException($e);
			}
		}

		if (!empty($this->getQueryPart('select'))) {
			$select = $this->getQueryPart('select');
			$hasSelectAll = array_filter($select, static function ($s) {
				return $s === '*';
			});
			$hasSelectSpecific = array_filter($select, static function ($s) {
				return $s !== '*';
			});

			if (empty($hasSelectAll) === empty($hasSelectSpecific)) {
				$exception = new QueryException('Query is selecting * and specific values in the same query. This is not supported in Oracle.');
				$this->logger->logException($exception, [
					'message' => 'Query is selecting * and specific values in the same query. This is not supported in Oracle.',
					'query' => $this->getSQL(),
					'level' => ILogger::ERROR,
					'app' => 'core',
				]);
			}
		}

		$numberOfParameters = 0;
		$hasTooLargeArrayParameter = false;
		foreach ($this->getParameters() as $parameter) {
			if (is_array($parameter)) {
				$count = count($parameter);
				$numberOfParameters += $count;
				$hasTooLargeArrayParameter = $hasTooLargeArrayParameter || ($count > 1000);
			}
		}

		if ($hasTooLargeArrayParameter) {
			$exception = new QueryException('More than 1000 expressions in a list are not allowed on Oracle.');
			$this->logger->logException($exception, [
				'message' => 'More than 1000 expressions in a list are not allowed on Oracle.',
				'query' => $this->getSQL(),
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
		}

		if ($numberOfParameters > 65535) {
			$exception = new QueryException('The number of parameters must not exceed 65535. Restriction by PostgreSQL.');
			$this->logger->logException($exception, [
				'message' => 'The number of parameters must not exceed 65535. Restriction by PostgreSQL.',
				'query' => $this->getSQL(),
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
		}

		$result = $this->queryBuilder->execute();
		if (is_int($result)) {
			return $result;
		}
		return new ResultAdapter($result);
	}

	public function executeQuery(): IResult {
		if ($this->getType() !== \Doctrine\DBAL\Query\QueryBuilder::SELECT) {
			throw new \RuntimeException('Invalid query type, expected SELECT query');
		}

		try {
			$result = $this->execute();
		} catch (\Doctrine\DBAL\Exception $e) {
			throw \OC\DB\Exceptions\DbalException::wrap($e);
		}

		if ($result instanceof IResult) {
			return $result;
		}

		throw new \RuntimeException('Invalid return type for query');
	}

	public function executeUpdate(): int {
		if ($this->getType() === \Doctrine\DBAL\Query\QueryBuilder::SELECT) {
			throw new \RuntimeException('Invalid query type, expected INSERT, DELETE or UPDATE query');
		}

		try {
			$result = $this->execute();
		} catch (\Doctrine\DBAL\Exception $e) {
			throw \OC\DB\Exceptions\DbalException::wrap($e);
		}

		if (!is_int($result)) {
			throw new \RuntimeException('Invalid return type for query');
		}

		return $result;
	}


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
	 */
	public function getSQL() {
		return $this->queryBuilder->getSQL();
	}

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
	 */
	public function setParameter($key, $value, $type = null) {
		$this->queryBuilder->setParameter($key, $value, $type);

		return $this;
	}

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
	 */
	public function setParameters(array $params, array $types = []) {
		$this->queryBuilder->setParameters($params, $types);

		return $this;
	}

	/**
	 * Gets all defined query parameters for the query being constructed indexed by parameter index or name.
	 *
	 * @return array The currently defined query parameters indexed by parameter index or name.
	 */
	public function getParameters() {
		return $this->queryBuilder->getParameters();
	}

	/**
	 * Gets a (previously set) query parameter of the query being constructed.
	 *
	 * @param mixed $key The key (index or name) of the bound parameter.
	 *
	 * @return mixed The value of the bound parameter.
	 */
	public function getParameter($key) {
		return $this->queryBuilder->getParameter($key);
	}

	/**
	 * Gets all defined query parameter types for the query being constructed indexed by parameter index or name.
	 *
	 * @return array The currently defined query parameter types indexed by parameter index or name.
	 */
	public function getParameterTypes() {
		return $this->queryBuilder->getParameterTypes();
	}

	/**
	 * Gets a (previously set) query parameter type of the query being constructed.
	 *
	 * @param mixed $key The key (index or name) of the bound parameter type.
	 *
	 * @return mixed The value of the bound parameter type.
	 */
	public function getParameterType($key) {
		return $this->queryBuilder->getParameterType($key);
	}

	/**
	 * Sets the position of the first result to retrieve (the "offset").
	 *
	 * @param integer $firstResult The first result to return.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function setFirstResult($firstResult) {
		$this->queryBuilder->setFirstResult($firstResult);

		return $this;
	}

	/**
	 * Gets the position of the first result the query object was set to retrieve (the "offset").
	 * Returns NULL if {@link setFirstResult} was not applied to this QueryBuilder.
	 *
	 * @return integer The position of the first result.
	 */
	public function getFirstResult() {
		return $this->queryBuilder->getFirstResult();
	}

	/**
	 * Sets the maximum number of results to retrieve (the "limit").
	 *
	 * NOTE: Setting max results to "0" will cause mixed behaviour. While most
	 * of the databases will just return an empty result set, Oracle will return
	 * all entries.
	 *
	 * @param integer $maxResults The maximum number of results to retrieve.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function setMaxResults($maxResults) {
		$this->queryBuilder->setMaxResults($maxResults);

		return $this;
	}

	/**
	 * Gets the maximum number of results the query object was set to retrieve (the "limit").
	 * Returns NULL if {@link setMaxResults} was not applied to this query builder.
	 *
	 * @return int|null The maximum number of results.
	 */
	public function getMaxResults() {
		return $this->queryBuilder->getMaxResults();
	}

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
	 * '@return $this This QueryBuilder instance.
	 */
	public function select(...$selects) {
		if (count($selects) === 1 && is_array($selects[0])) {
			$selects = $selects[0];
		}

		$this->queryBuilder->select(
			$this->helper->quoteColumnNames($selects)
		);

		return $this;
	}

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
	 */
	public function selectAlias($select, $alias) {
		$this->queryBuilder->addSelect(
			$this->helper->quoteColumnName($select) . ' AS ' . $this->helper->quoteColumnName($alias)
		);

		return $this;
	}

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
	 */
	public function selectDistinct($select) {
		if (!is_array($select)) {
			$select = [$select];
		}

		$quotedSelect = $this->helper->quoteColumnNames($select);

		$this->queryBuilder->addSelect(
			'DISTINCT ' . implode(', ', $quotedSelect)
		);

		return $this;
	}

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
	 * @param mixed ...$selects The selection expression.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function addSelect(...$selects) {
		if (count($selects) === 1 && is_array($selects[0])) {
			$selects = $selects[0];
		}

		$this->queryBuilder->addSelect(
			$this->helper->quoteColumnNames($selects)
		);

		return $this;
	}

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
	 */
	public function delete($delete = null, $alias = null) {
		$this->queryBuilder->delete(
			$this->getTableName($delete),
			$alias
		);

		return $this;
	}

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
	 */
	public function update($update = null, $alias = null) {
		$this->queryBuilder->update(
			$this->getTableName($update),
			$alias
		);

		return $this;
	}

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
	 */
	public function insert($insert = null) {
		$this->queryBuilder->insert(
			$this->getTableName($insert)
		);

		$this->lastInsertedTable = $insert;

		return $this;
	}

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
	 */
	public function from($from, $alias = null) {
		$this->queryBuilder->from(
			$this->getTableName($from),
			$this->quoteAlias($alias)
		);

		return $this;
	}

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
	 * @param string|ICompositeExpression|null $condition The condition for the join.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function join($fromAlias, $join, $alias, $condition = null) {
		$this->queryBuilder->join(
			$this->quoteAlias($fromAlias),
			$this->getTableName($join),
			$this->quoteAlias($alias),
			$condition
		);

		return $this;
	}

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
	 * @param string|ICompositeExpression|null $condition The condition for the join.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function innerJoin($fromAlias, $join, $alias, $condition = null) {
		$this->queryBuilder->innerJoin(
			$this->quoteAlias($fromAlias),
			$this->getTableName($join),
			$this->quoteAlias($alias),
			$condition
		);

		return $this;
	}

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
	 * @param string|ICompositeExpression|null $condition The condition for the join.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function leftJoin($fromAlias, $join, $alias, $condition = null) {
		$this->queryBuilder->leftJoin(
			$this->quoteAlias($fromAlias),
			$this->getTableName($join),
			$this->quoteAlias($alias),
			$condition
		);

		return $this;
	}

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
	 * @param string|ICompositeExpression|null $condition The condition for the join.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function rightJoin($fromAlias, $join, $alias, $condition = null) {
		$this->queryBuilder->rightJoin(
			$this->quoteAlias($fromAlias),
			$this->getTableName($join),
			$this->quoteAlias($alias),
			$condition
		);

		return $this;
	}

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
	 */
	public function set($key, $value) {
		$this->queryBuilder->set(
			$this->helper->quoteColumnName($key),
			$this->helper->quoteColumnName($value)
		);

		return $this;
	}

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
	 * @param mixed ...$predicates The restriction predicates.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function where(...$predicates) {
		call_user_func_array(
			[$this->queryBuilder, 'where'],
			$predicates
		);

		return $this;
	}

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
	 */
	public function andWhere(...$where) {
		call_user_func_array(
			[$this->queryBuilder, 'andWhere'],
			$where
		);

		return $this;
	}

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
	 */
	public function orWhere(...$where) {
		call_user_func_array(
			[$this->queryBuilder, 'orWhere'],
			$where
		);

		return $this;
	}

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
	 */
	public function groupBy(...$groupBys) {
		if (count($groupBys) === 1 && is_array($groupBys[0])) {
			$groupBys = $groupBys[0];
		}

		call_user_func_array(
			[$this->queryBuilder, 'groupBy'],
			$this->helper->quoteColumnNames($groupBys)
		);

		return $this;
	}

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
	 */
	public function addGroupBy(...$groupBys) {
		if (count($groupBys) === 1 && is_array($groupBys[0])) {
			$$groupBys = $groupBys[0];
		}

		call_user_func_array(
			[$this->queryBuilder, 'addGroupBy'],
			$this->helper->quoteColumnNames($groupBys)
		);

		return $this;
	}

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
	 */
	public function setValue($column, $value) {
		$this->queryBuilder->setValue(
			$this->helper->quoteColumnName($column),
			(string) $value
		);

		return $this;
	}

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
	 */
	public function values(array $values) {
		$quotedValues = [];
		foreach ($values as $key => $value) {
			$quotedValues[$this->helper->quoteColumnName($key)] = $value;
		}

		$this->queryBuilder->values($quotedValues);

		return $this;
	}

	/**
	 * Specifies a restriction over the groups of the query.
	 * Replaces any previous having restrictions, if any.
	 *
	 * @param mixed ...$having The restriction over the groups.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function having(...$having) {
		call_user_func_array(
			[$this->queryBuilder, 'having'],
			$having
		);

		return $this;
	}

	/**
	 * Adds a restriction over the groups of the query, forming a logical
	 * conjunction with any existing having restrictions.
	 *
	 * @param mixed ...$having The restriction to append.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function andHaving(...$having) {
		call_user_func_array(
			[$this->queryBuilder, 'andHaving'],
			$having
		);

		return $this;
	}

	/**
	 * Adds a restriction over the groups of the query, forming a logical
	 * disjunction with any existing having restrictions.
	 *
	 * @param mixed ...$having The restriction to add.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function orHaving(...$having) {
		call_user_func_array(
			[$this->queryBuilder, 'orHaving'],
			$having
		);

		return $this;
	}

	/**
	 * Specifies an ordering for the query results.
	 * Replaces any previously specified orderings, if any.
	 *
	 * @param string $sort The ordering expression.
	 * @param string $order The ordering direction.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function orderBy($sort, $order = null) {
		$this->queryBuilder->orderBy(
			$this->helper->quoteColumnName($sort),
			$order
		);

		return $this;
	}

	/**
	 * Adds an ordering to the query results.
	 *
	 * @param string $sort The ordering expression.
	 * @param string $order The ordering direction.
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function addOrderBy($sort, $order = null) {
		$this->queryBuilder->addOrderBy(
			$this->helper->quoteColumnName($sort),
			$order
		);

		return $this;
	}

	/**
	 * Gets a query part by its name.
	 *
	 * @param string $queryPartName
	 *
	 * @return mixed
	 */
	public function getQueryPart($queryPartName) {
		return $this->queryBuilder->getQueryPart($queryPartName);
	}

	/**
	 * Gets all query parts.
	 *
	 * @return array
	 */
	public function getQueryParts() {
		return $this->queryBuilder->getQueryParts();
	}

	/**
	 * Resets SQL parts.
	 *
	 * @param array|null $queryPartNames
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function resetQueryParts($queryPartNames = null) {
		$this->queryBuilder->resetQueryParts($queryPartNames);

		return $this;
	}

	/**
	 * Resets a single SQL part.
	 *
	 * @param string $queryPartName
	 *
	 * @return $this This QueryBuilder instance.
	 */
	public function resetQueryPart($queryPartName) {
		$this->queryBuilder->resetQueryPart($queryPartName);

		return $this;
	}

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
	 * @return IParameter the placeholder name used.
	 */
	public function createNamedParameter($value, $type = IQueryBuilder::PARAM_STR, $placeHolder = null) {
		return new Parameter($this->queryBuilder->createNamedParameter($value, $type, $placeHolder));
	}

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
	 */
	public function createPositionalParameter($value, $type = IQueryBuilder::PARAM_STR) {
		return new Parameter($this->queryBuilder->createPositionalParameter($value, $type));
	}

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
	 */
	public function createParameter($name) {
		return new Parameter(':' . $name);
	}

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
	 */
	public function createFunction($call) {
		return new QueryFunction($call);
	}

	/**
	 * Used to get the id of the last inserted element
	 * @return int
	 * @throws \BadMethodCallException When being called before an insert query has been run.
	 */
	public function getLastInsertId(): int {
		if ($this->getType() === \Doctrine\DBAL\Query\QueryBuilder::INSERT && $this->lastInsertedTable) {
			// lastInsertId() needs the prefix but no quotes
			$table = $this->prefixTableName($this->lastInsertedTable);
			return $this->connection->lastInsertId($table);
		}

		throw new \BadMethodCallException('Invalid call to getLastInsertId without using insert() before.');
	}

	/**
	 * Returns the table name quoted and with database prefix as needed by the implementation
	 *
	 * @param string $table
	 * @return string
	 */
	public function getTableName($table) {
		if ($table instanceof IQueryFunction) {
			return (string) $table;
		}

		$table = $this->prefixTableName($table);
		return $this->helper->quoteColumnName($table);
	}

	/**
	 * Returns the table name with database prefix as needed by the implementation
	 *
	 * @param string $table
	 * @return string
	 */
	protected function prefixTableName($table) {
		if ($this->automaticTablePrefix === false || strpos($table, '*PREFIX*') === 0) {
			return $table;
		}

		return '*PREFIX*' . $table;
	}

	/**
	 * Returns the column name quoted and with table alias prefix as needed by the implementation
	 *
	 * @param string $column
	 * @param string $tableAlias
	 * @return string
	 */
	public function getColumnName($column, $tableAlias = '') {
		if ($tableAlias !== '') {
			$tableAlias .= '.';
		}

		return $this->helper->quoteColumnName($tableAlias . $column);
	}

	/**
	 * Returns the column name quoted and with table alias prefix as needed by the implementation
	 *
	 * @param string $alias
	 * @return string
	 */
	public function quoteAlias($alias) {
		if ($alias === '' || $alias === null) {
			return $alias;
		}

		return $this->helper->quoteColumnName($alias);
	}
}
