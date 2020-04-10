<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Ole Ostergaard <ole.c.ostergaard@gmail.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

/**
 * Public interface of ownCloud for apps to use.
 * DBConnection interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

use Doctrine\DBAL\Schema\Schema;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Interface IDBConnection
 *
 * @package OCP
 * @since 6.0.0
 */
interface IDBConnection {

	const ADD_MISSING_INDEXES_EVENT = self::class . '::ADD_MISSING_INDEXES';
	const CHECK_MISSING_INDEXES_EVENT = self::class . '::CHECK_MISSING_INDEXES';
	const ADD_MISSING_COLUMNS_EVENT = self::class . '::ADD_MISSING_COLUMNS';
	const CHECK_MISSING_COLUMNS_EVENT = self::class . '::CHECK_MISSING_COLUMNS';

	/**
	 * Gets the QueryBuilder for the connection.
	 *
	 * @return \OCP\DB\QueryBuilder\IQueryBuilder
	 * @since 8.2.0
	 */
	public function getQueryBuilder();

	/**
	 * Used to abstract the ownCloud database access away
	 * @param string $sql the sql query with ? placeholder for params
	 * @param int $limit the maximum number of rows
	 * @param int $offset from which row we want to start
	 * @return \Doctrine\DBAL\Driver\Statement The prepared statement.
	 * @since 6.0.0
	 */
	public function prepare($sql, $limit=null, $offset=null);

	/**
	 * Executes an, optionally parameterized, SQL query.
	 *
	 * If the query is parameterized, a prepared statement is used.
	 * If an SQLLogger is configured, the execution is logged.
	 *
	 * @param string $query The SQL query to execute.
	 * @param string[] $params The parameters to bind to the query, if any.
	 * @param array $types The types the previous parameters are in.
	 * @return \Doctrine\DBAL\Driver\Statement The executed statement.
	 * @since 8.0.0
	 */
	public function executeQuery($query, array $params = [], $types = []);

	/**
	 * Executes an SQL INSERT/UPDATE/DELETE query with the given parameters
	 * and returns the number of affected rows.
	 *
	 * This method supports PDO binding types as well as DBAL mapping types.
	 *
	 * @param string $query The SQL query.
	 * @param array $params The query parameters.
	 * @param array $types The parameter types.
	 * @return integer The number of affected rows.
	 * @since 8.0.0
	 */
	public function executeUpdate($query, array $params = [], array $types = []);

	/**
	 * Used to get the id of the just inserted element
	 * @param string $table the name of the table where we inserted the item
	 * @return int the id of the inserted element
	 * @since 6.0.0
	 */
	public function lastInsertId($table = null);

	/**
	 * Insert a row if the matching row does not exists. To accomplish proper race condition avoidance
	 * it is needed that there is also a unique constraint on the values. Then this method will
	 * catch the exception and return 0.
	 *
	 * @param string $table The table name (will replace *PREFIX* with the actual prefix)
	 * @param array $input data that should be inserted into the table  (column name => value)
	 * @param array|null $compare List of values that should be checked for "if not exists"
	 *				If this is null or an empty array, all keys of $input will be compared
	 *				Please note: text fields (clob) must not be used in the compare array
	 * @return int number of inserted rows
	 * @throws \Doctrine\DBAL\DBALException
	 * @since 6.0.0 - parameter $compare was added in 8.1.0, return type changed from boolean in 8.1.0
	 * @deprecated 15.0.0 - use unique index and "try { $db->insert() } catch (UniqueConstraintViolationException $e) {}" instead, because it is more reliable and does not have the risk for deadlocks - see https://github.com/nextcloud/server/pull/12371
	 */
	public function insertIfNotExist($table, $input, array $compare = null);


	/**
	 *
	 * Insert a row if the row does not exist. Eventual conflicts during insert will be ignored.
	 *
	 * Implementation is not fully finished and should not be used!
	 *
	 * @param string $table The table name (will replace *PREFIX* with the actual prefix)
	 * @param array $values data that should be inserted into the table  (column name => value)
	 * @return int number of inserted rows
	 * @since 16.0.0
	 */
	public function insertIgnoreConflict(string $table,array $values) : int;

	/**
	 * Insert or update a row value
	 *
	 * @param string $table
	 * @param array $keys (column name => value)
	 * @param array $values (column name => value)
	 * @param array $updatePreconditionValues ensure values match preconditions (column name => value)
	 * @return int number of new rows
	 * @throws \Doctrine\DBAL\DBALException
	 * @throws PreconditionNotMetException
	 * @since 9.0.0
	 */
	public function setValues($table, array $keys, array $values, array $updatePreconditionValues = []);

	/**
	 * Create an exclusive read+write lock on a table
	 *
	 * Important Note: Due to the nature how locks work on different DBs, it is
	 * only possible to lock one table at a time. You should also NOT start a
	 * transaction while holding a lock.
	 *
	 * @param string $tableName
	 * @since 9.1.0
	 */
	public function lockTable($tableName);

	/**
	 * Release a previous acquired lock again
	 *
	 * @since 9.1.0
	 */
	public function unlockTable();

	/**
	 * Start a transaction
	 * @since 6.0.0
	 */
	public function beginTransaction();

	/**
	 * Check if a transaction is active
	 *
	 * @return bool
	 * @since 8.2.0
	 */
	public function inTransaction();

	/**
	 * Commit the database changes done during a transaction that is in progress
	 * @since 6.0.0
	 */
	public function commit();

	/**
	 * Rollback the database changes done during a transaction that is in progress
	 * @since 6.0.0
	 */
	public function rollBack();

	/**
	 * Gets the error code and message as a string for logging
	 * @return string
	 * @since 6.0.0
	 */
	public function getError();

	/**
	 * Fetch the SQLSTATE associated with the last database operation.
	 *
	 * @return integer The last error code.
	 * @since 8.0.0
	 */
	public function errorCode();

	/**
	 * Fetch extended error information associated with the last database operation.
	 *
	 * @return array The last error information.
	 * @since 8.0.0
	 */
	public function errorInfo();

	/**
	 * Establishes the connection with the database.
	 *
	 * @return bool
	 * @since 8.0.0
	 */
	public function connect();

	/**
	 * Close the database connection
	 * @since 8.0.0
	 */
	public function close();

	/**
	 * Quotes a given input parameter.
	 *
	 * @param mixed $input Parameter to be quoted.
	 * @param int $type Type of the parameter.
	 * @return string The quoted parameter.
	 * @since 8.0.0
	 */
	public function quote($input, $type = IQueryBuilder::PARAM_STR);

	/**
	 * Gets the DatabasePlatform instance that provides all the metadata about
	 * the platform this driver connects to.
	 *
	 * @return \Doctrine\DBAL\Platforms\AbstractPlatform The database platform.
	 * @since 8.0.0
	 */
	public function getDatabasePlatform();

	/**
	 * Drop a table from the database if it exists
	 *
	 * @param string $table table name without the prefix
	 * @since 8.0.0
	 */
	public function dropTable($table);

	/**
	 * Check if a table exists
	 *
	 * @param string $table table name without the prefix
	 * @return bool
	 * @since 8.0.0
	 */
	public function tableExists($table);

	/**
	 * Escape a parameter to be used in a LIKE query
	 *
	 * @param string $param
	 * @return string
	 * @since 9.0.0
	 */
	public function escapeLikeParameter($param);

	/**
	 * Check whether or not the current database support 4byte wide unicode
	 *
	 * @return bool
	 * @since 11.0.0
	 */
	public function supports4ByteText();

	/**
	 * Create the schema of the connected database
	 *
	 * @return Schema
	 * @since 13.0.0
	 */
	public function createSchema();

	/**
	 * Migrate the database to the given schema
	 *
	 * @param Schema $toSchema
	 * @since 13.0.0
	 */
	public function migrateToSchema(Schema $toSchema);
}
