<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCP\DB;

/**
 * Interface ISchemaWrapper
 *
 * @since 13.0.0
 */
interface ISchemaWrapper {

	/**
	 * @param string $tableName
	 *
	 * @return \Doctrine\DBAL\Schema\Table
	 * @throws \Doctrine\DBAL\Schema\SchemaException
	 * @since 13.0.0
	 */
	public function getTable($tableName);

	/**
	 * Does this schema have a table with the given name?
	 *
	 * @param string $tableName Prefix is automatically prepended
	 *
	 * @return boolean
	 * @since 13.0.0
	 */
	public function hasTable($tableName);

	/**
	 * Creates a new table.
	 *
	 * @param string $tableName Prefix is automatically prepended
	 * @return \Doctrine\DBAL\Schema\Table
	 * @since 13.0.0
	 */
	public function createTable($tableName);

	/**
	 * Drops a table from the schema.
	 *
	 * @param string $tableName Prefix is automatically prepended
	 * @return \Doctrine\DBAL\Schema\Schema
	 * @since 13.0.0
	 */
	public function dropTable($tableName);

	/**
	 * Gets all tables of this schema.
	 *
	 * @return \Doctrine\DBAL\Schema\Table[]
	 * @since 13.0.0
	 */
	public function getTables();

	/**
	 * Gets all table names, prefixed with table prefix
	 *
	 * @return array
	 * @since 13.0.0
	 */
	public function getTableNames();
	
	/**
	 * Gets all table names
	 *
	 * @return array
	 * @since 13.0.0
	 */
	public function getTableNamesWithoutPrefix();
}
