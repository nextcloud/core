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
use OCP\DB\Exception;
use OCP\DB\IPreparedStatement;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Interface IDBConnection
 *
 * @since 6.0.0
 */
interface IDBConnection {
	/**
	 * @deprecated 22.0.0 this is an internal event
	 */
	public const ADD_MISSING_INDEXES_EVENT = self::class . '::ADD_MISSING_INDEXES';

	/**
	 * @deprecated 22.0.0 this is an internal event
	 */
	public const CHECK_MISSING_INDEXES_EVENT = self::class . '::CHECK_MISSING_INDEXES';

	/**
	 * @deprecated 22.0.0 this is an internal event
	 */
	public const ADD_MISSING_PRIMARY_KEYS_EVENT = self::class . '::ADD_MISSING_PRIMARY_KEYS';

	/**
	 * @deprecated 22.0.0 this is an internal event
	 */
	public const CHECK_MISSING_PRIMARY_KEYS_EVENT = self::class . '::CHECK_MISSING_PRIMARY_KEYS';

	/**
	 * @deprecated 22.0.0 this is an internal event
	 */
	public const ADD_MISSING_COLUMNS_EVENT = self::class . '::ADD_MISSING_COLUMNS';

	/**
	 * @deprecated 22.0.0 this is an internal event
	 */
	public const CHECK_MISSING_COLUMNS_EVENT = self::class . '::CHECK_MISSING_COLUMNS';

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
	 * @return IPreparedStatement The prepared statement.
	 * @since 6.0.0
	 * @throws Exception since 21.0.0
	 */
	public function prepare($sql, $limit = null, $offset = null): IPreparedStatement;

	/**
	 * Executes an, optionally parameterized, SQL query.
	 *
	 * If the query is parameterized, a prepared statement is used.
	 * If an SQLLogger is configured, the execution is logged.
	 *
	 * @param string $sql The SQL query to execute.
	 * @param string[] $params The parameters to bind to the query, if any.
	 * @param array $types The types the previous parameters are in.
	 * @return IResult The executed statement.
	 * @since 8.0.0
	 * @throws Exception since 21.0.0
	 */
	public function executeQuery(string $sql, array $params = [], $types = []): IResult;

	/**
	 * Executes an SQL INSERT/UPDATE/DELETE query with the given parameters
	 * and returns the number of affected rows.
	 *
	 * This method supports PDO binding types as well as DBAL mapping types.
	 *
	 * @param string $sql The SQL query.
	 * @param array $params The query parameters.
	 * @param array $types The parameter types.
	 * @return int The number of affected rows.
	 * @since 8.0.0
	 * @throws Exception since 21.0.0
	 *
	 * @deprecated 21.0.0 use executeStatement
	 */
	public function executeUpdate(string $sql, array $params = [], array $types = []): int;

	/**
	 * Executes an SQL INSERT/UPDATE/DELETE query with the given parameters
	 * and returns the number of affected rows.
	 *
	 * This method supports PDO binding types as well as DBAL mapping types.
	 *
	 * @param string $sql The SQL query.
	 * @param array $params The query parameters.
	 * @param array $types The parameter types.
	 * @return int The number of affected rows.
	 * @since 21.0.0
	 * @throws Exception since 21.0.0
	 */
	public function executeStatement($sql, array $params = [], array $types = []): int;

	/**
	 * Used to get the id of the just inserted element
	 * @param string $table the name of the table where we inserted the item
	 * @return int the id of the inserted element
	 * @since 6.0.0
	 * @throws Exception since 21.0.0
	 * @deprecated 21.0.0 use \OCP\DB\QueryBuilder\IQueryBuilder::getLastInsertId
	 */
	public function lastInsertId(string $table): int;

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
	 * @throws Exception used to be the removed dbal exception, since 21.0.0 it's \OCP\DB\Exception
	 * @since 6.0.0 - parameter $compare was added in 8.1.0, return type changed from boolean in 8.1.0
	 * @deprecated 15.0.0 - use unique index and "try { $db->insert() } catch (UniqueConstraintViolationException $e) {}" instead, because it is more reliable and does not have the risk for deadlocks - see https://github.com/nextcloud/server/pull/12371
	 */
	public function insertIfNotExist(string $table, array $input, array $compare = null);


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
	 * @throws Exception used to be the removed dbal exception, since 21.0.0 it's \OCP\DB\Exception
	 * @throws PreconditionNotMetException
	 * @since 9.0.0
	 */
	public function setValues($table, array $keys, array $values, array $updatePreconditionValues = []): int;

	/**
	 * Create an exclusive read+write lock on a table
	 *
	 * Important Note: Due to the nature how locks work on different DBs, it is
	 * only possible to lock one table at a time. You should also NOT start a
	 * transaction while holding a lock.
	 *
	 * @param string $tableName
	 * @throws Exception since 21.0.0
	 * @since 9.1.0
	 */
	public function lockTable($tableName): void;

	/**
	 * Release a previous acquired lock again
	 *
	 * @throws Exception since 21.0.0
	 * @since 9.1.0
	 */
	public function unlockTable(): void;

	/**
	 * Start a transaction
	 * @since 6.0.0
	 * @throws Exception since 21.0.0
	 */
	public function beginTransaction(): void;

	/**
	 * Check if a transaction is active
	 *
	 * @return bool
	 * @since 8.2.0
	 */
	public function inTransaction(): bool;

	/**
	 * Commit the database changes done during a transaction that is in progress
	 * @since 6.0.0
	 * @throws Exception since 21.0.0
	 */
	public function commit(): void;

	/**
	 * Rollback the database changes done during a transaction that is in progress
	 * @since 6.0.0
	 * @throws Exception since 21.0.0
	 */
	public function rollBack(): void;

	/**
	 * Gets the error code and message as a string for logging
	 * @return string
	 * @since 6.0.0
	 * @deprecated 21.0.0 doesn't return anything meaningful
	 */
	public function getError(): string;

	/**
	 * Fetch the SQLSTATE associated with the last database operation.
	 *
	 * @return integer The last error code.
	 * @since 8.0.0
	 * @deprecated 21.0.0 doesn't return anything anymore
	 */
	public function errorCode();

	/**
	 * Fetch extended error information associated with the last database operation.
	 *
	 * @return array The last error information.
	 * @since 8.0.0
	 * @deprecated 21.0.0 doesn't return anything anymore
	 */
	public function errorInfo();

	/**
	 * Establishes the connection with the database.
	 *
	 * @return bool
	 * @throws Exception since 21.0.0
	 * @since 8.0.0
	 */
	public function connect(): bool;

	/**
	 * Close the database connection
	 * @since 8.0.0
	 */
	public function close(): void;

	/**
	 * Quotes a given input parameter.
	 *
	 * @param mixed $input Parameter to be quoted.
	 * @param int $type Type of the parameter.
	 * @return mixed The quoted parameter.
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
	 * @throws Exception since 21.0.0
	 * @since 8.0.0
	 */
	public function dropTable(string $table): void;

	/**
	 * Check if a table exists
	 *
	 * @param string $table table name without the prefix
	 * @return bool
	 * @throws Exception since 21.0.0
	 * @since 8.0.0
	 */
	public function tableExists(string $table): bool;

	/**
	 * Escape a parameter to be used in a LIKE query
	 *
	 * @param string $param
	 * @return string
	 * @since 9.0.0
	 */
	public function escapeLikeParameter(string $param): string;

	/**
	 * Check whether or not the current database support 4byte wide unicode
	 *
	 * @return bool
	 * @since 11.0.0
	 */
	public function supports4ByteText(): bool;

	/**
	 * Create the schema of the connected database
	 *
	 * @return Schema
	 * @throws Exception since 21.0.0
	 * @since 13.0.0
	 */
	public function createSchema(): Schema;

	/**
	 * Migrate the database to the given schema
	 *
	 * @param Schema $toSchema
	 * @throws Exception since 21.0.0
	 * @since 13.0.0
	 */
	public function migrateToSchema(Schema $toSchema): void;
}
