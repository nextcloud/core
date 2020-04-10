<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OC\DB;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

class MySQLMigrator extends Migrator {
	/**
	 * @param Schema $targetSchema
	 * @param \Doctrine\DBAL\Connection $connection
	 * @return \Doctrine\DBAL\Schema\SchemaDiff
	 */
	protected function getDiff(Schema $targetSchema, \Doctrine\DBAL\Connection $connection) {
		$platform = $connection->getDatabasePlatform();
		$platform->registerDoctrineTypeMapping('enum', 'string');
		$platform->registerDoctrineTypeMapping('bit', 'string');

		$schemaDiff = parent::getDiff($targetSchema, $connection);

		// identifiers need to be quoted for mysql
		foreach ($schemaDiff->changedTables as $tableDiff) {
			$tableDiff->name = $this->connection->quoteIdentifier($tableDiff->name);
			foreach ($tableDiff->changedColumns as $column) {
				$column->oldColumnName = $this->connection->quoteIdentifier($column->oldColumnName);
			}
		}

		return $schemaDiff;
	}

		/**
		 * Speed up migration test by disabling autocommit and unique indexes check
		 *
		 * @param \Doctrine\DBAL\Schema\Table $table
		 * @throws \OC\DB\MigrationException
		 */
		protected function checkTableMigrate(Table $table) {
				$this->connection->exec('SET autocommit=0');
				$this->connection->exec('SET unique_checks=0');

				try {
						parent::checkTableMigrate($table);
				} catch (\Exception $e) {
						$this->connection->exec('SET unique_checks=1');
						$this->connection->exec('SET autocommit=1');
						throw new MigrationException($table->getName(), $e->getMessage());
				}
				$this->connection->exec('SET unique_checks=1');
				$this->connection->exec('SET autocommit=1');
		}

}
