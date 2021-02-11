<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

use OCP\ILogger;

/**
 * This class manages the access to the database. It basically is a wrapper for
 * Doctrine with some adaptions.
 */
class OC_DB {

	/**
	 * get MDB2 schema manager
	 *
	 * @return \OC\DB\MDB2SchemaManager
	 */
	private static function getMDB2SchemaManager() {
		return new \OC\DB\MDB2SchemaManager(\OC::$server->get(\OC\DB\Connection::class));
	}

	/**
	 * Prepare a SQL query
	 * @param string $query Query string
	 * @param int|null $limit
	 * @param int|null $offset
	 * @param bool|null $isManipulation
	 * @throws \OC\DatabaseException
	 * @return OC_DB_StatementWrapper prepared SQL query
	 * @deprecated 21.0.0 Please use \OCP\IDBConnection::getQueryBuilder() instead
	 *
	 * SQL query via Doctrine prepare(), needs to be execute()'d!
	 */
	public static function prepare($query , $limit = null, $offset = null, $isManipulation = null) {
		$connection = \OC::$server->getDatabaseConnection();

		if ($isManipulation === null) {
			//try to guess, so we return the number of rows on manipulations
			$isManipulation = self::isManipulation($query);
		}

		// return the result
		try {
			$result = $connection->prepare($query, $limit, $offset);
		} catch (\Doctrine\DBAL\Exception $e) {
			throw new \OC\DatabaseException($e->getMessage());
		}
		// differentiate between query and manipulation
		return new OC_DB_StatementWrapper($result, $isManipulation);
	}

	/**
	 * tries to guess the type of statement based on the first 10 characters
	 * the current check allows some whitespace but does not work with IF EXISTS or other more complex statements
	 *
	 * @param string $sql
	 * @return bool
	 */
	public static function isManipulation($sql) {
		$sql = trim($sql);
		$selectOccurrence = stripos($sql, 'SELECT');
		if ($selectOccurrence === 0) {
			return false;
		}
		$insertOccurrence = stripos($sql, 'INSERT');
		if ($insertOccurrence === 0) {
			return true;
		}
		$updateOccurrence = stripos($sql, 'UPDATE');
		if ($updateOccurrence === 0) {
			return true;
		}
		$deleteOccurrence = stripos($sql, 'DELETE');
		if ($deleteOccurrence === 0) {
			return true;
		}

		// This is triggered with "SHOW VERSION" and some more, so until we made a list, we keep this out.
		// \OC::$server->getLogger()->logException(new \Exception('Can not detect if query is manipulating: ' . $sql));

		return false;
	}

	/**
	 * execute a prepared statement, on error write log and throw exception
	 * @param mixed $stmt OC_DB_StatementWrapper,
	 *					  an array with 'sql' and optionally 'limit' and 'offset' keys
	 *					.. or a simple sql query string
	 * @param array $parameters
	 * @return OC_DB_StatementWrapper
	 * @throws \OC\DatabaseException
	 * @deprecated 21.0.0 Please use \OCP\IDBConnection::getQueryBuilder() instead
	 */
	public static function executeAudited($stmt, array $parameters = []) {
		if (is_string($stmt)) {
			// convert to an array with 'sql'
			if (stripos($stmt, 'LIMIT') !== false) { //OFFSET requires LIMIT, so we only need to check for LIMIT
				// TODO try to convert LIMIT OFFSET notation to parameters
				$message = 'LIMIT and OFFSET are forbidden for portability reasons,'
						 . ' pass an array with \'limit\' and \'offset\' instead';
				throw new \OC\DatabaseException($message);
			}
			$stmt = ['sql' => $stmt, 'limit' => null, 'offset' => null];
		}
		if (is_array($stmt)) {
			// convert to prepared statement
			if (! array_key_exists('sql', $stmt)) {
				$message = 'statement array must at least contain key \'sql\'';
				throw new \OC\DatabaseException($message);
			}
			if (! array_key_exists('limit', $stmt)) {
				$stmt['limit'] = null;
			}
			if (! array_key_exists('limit', $stmt)) {
				$stmt['offset'] = null;
			}
			$stmt = self::prepare($stmt['sql'], $stmt['limit'], $stmt['offset']);
		}
		self::raiseExceptionOnError($stmt, 'Could not prepare statement');
		if ($stmt instanceof OC_DB_StatementWrapper) {
			$result = $stmt->execute($parameters);
			self::raiseExceptionOnError($result, 'Could not execute statement');
		} else {
			if (is_object($stmt)) {
				$message = 'Expected a prepared statement or array got ' . get_class($stmt);
			} else {
				$message = 'Expected a prepared statement or array got ' . gettype($stmt);
			}
			throw new \OC\DatabaseException($message);
		}
		return $result;
	}

	/**
	 * Creates tables from XML file
	 * @param string $file file to read structure from
	 * @return bool
	 *
	 * TODO: write more documentation
	 */
	public static function createDbFromStructure($file) {
		$schemaManager = self::getMDB2SchemaManager();
		return $schemaManager->createDbFromStructure($file);
	}

	/**
	 * update the database schema
	 * @param string $file file to read structure from
	 * @throws Exception
	 * @return string|boolean
	 * @suppress PhanDeprecatedFunction
	 */
	public static function updateDbFromStructure($file) {
		$schemaManager = self::getMDB2SchemaManager();
		try {
			$result = $schemaManager->updateDbFromStructure($file);
		} catch (Exception $e) {
			\OCP\Util::writeLog('core', 'Failed to update database structure ('.$e.')', ILogger::FATAL);
			throw $e;
		}
		return $result;
	}

	/**
	 * remove all tables defined in a database structure xml file
	 * @param string $file the xml file describing the tables
	 */
	public static function removeDBStructure($file) {
		$schemaManager = self::getMDB2SchemaManager();
		$schemaManager->removeDBStructure($file);
	}

	/**
	 * check if a result is an error and throws an exception, works with \Doctrine\DBAL\Exception
	 * @param mixed $result
	 * @param string $message
	 * @return void
	 * @throws \OC\DatabaseException
	 */
	public static function raiseExceptionOnError($result, $message = null) {
		if ($result === false) {
			if ($message === null) {
				$message = self::getErrorMessage();
			} else {
				$message .= ', Root cause:' . self::getErrorMessage();
			}
			throw new \OC\DatabaseException($message);
		}
	}

	/**
	 * returns the error code and message as a string for logging
	 * works with DoctrineException
	 * @return string
	 */
	public static function getErrorMessage() {
		$connection = \OC::$server->getDatabaseConnection();
		return $connection->getError();
	}

	/**
	 * Checks if a table exists in the database - the database prefix will be prepended
	 *
	 * @param string $table
	 * @return bool
	 * @throws \OC\DatabaseException
	 */
	public static function tableExists($table) {
		$connection = \OC::$server->getDatabaseConnection();
		return $connection->tableExists($table);
	}
}
