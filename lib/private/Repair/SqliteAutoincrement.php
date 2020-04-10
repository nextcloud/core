<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OC\Repair;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\TableDiff;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Fixes Sqlite autoincrement by forcing the SQLite table schemas to be
 * altered in order to retrigger SQL schema generation through OCSqlitePlatform.
 */
class SqliteAutoincrement implements IRepairStep {
	/**
	 * @var \OC\DB\Connection
	 */
	protected $connection;

	/**
	 * @param \OC\DB\Connection $connection
	 */
	public function __construct($connection) {
		$this->connection = $connection;
	}

	public function getName() {
		return 'Repair SQLite autoincrement';
	}

	/**
	 * Fix mime types
	 */
	public function run(IOutput $out) {
		if (!$this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
			return;
		}

		$sourceSchema = $this->connection->getSchemaManager()->createSchema();

		$schemaDiff = new SchemaDiff();

		foreach ($sourceSchema->getTables() as $tableSchema) {
			$primaryKey = $tableSchema->getPrimaryKey();
			if (!$primaryKey) {
				continue;
			}

			$columnNames = $primaryKey->getColumns();

			// add a column diff for every primary key column,
			// but do not actually change anything, this will
			// force the generation of SQL statements to alter
			// those tables, which will then trigger the
			// specific SQL code from OCSqlitePlatform
			try {
				$tableDiff = new TableDiff($tableSchema->getName());
				$tableDiff->fromTable = $tableSchema;
				foreach ($columnNames as $columnName) {
					$columnSchema = $tableSchema->getColumn($columnName);
					$columnDiff = new ColumnDiff($columnSchema->getName(), $columnSchema);
					$tableDiff->changedColumns[] = $columnDiff;
					$schemaDiff->changedTables[] = $tableDiff;
				}
			} catch (SchemaException $e) {
				// ignore
			}
		}

		$this->connection->beginTransaction();
		foreach ($schemaDiff->toSql($this->connection->getDatabasePlatform()) as $sql) {
			$this->connection->query($sql);
		}
		$this->connection->commit();
	}
}
