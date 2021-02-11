<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrowczynski <mrow4a@yahoo.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

class OracleMigrator extends Migrator {

	/**
	 * Quote a column's name but changing the name requires recreating
	 * the column instance and copying over all properties.
	 *
	 * @param Column $column old column
	 * @return Column new column instance with new name
	 */
	protected function quoteColumn(Column $column) {
		$newColumn = new Column(
			$this->connection->quoteIdentifier($column->getName()),
			$column->getType()
		);
		$newColumn->setAutoincrement($column->getAutoincrement());
		$newColumn->setColumnDefinition($column->getColumnDefinition());
		$newColumn->setComment($column->getComment());
		$newColumn->setDefault($column->getDefault());
		$newColumn->setFixed($column->getFixed());
		$newColumn->setLength($column->getLength());
		$newColumn->setNotnull($column->getNotnull());
		$newColumn->setPrecision($column->getPrecision());
		$newColumn->setScale($column->getScale());
		$newColumn->setUnsigned($column->getUnsigned());
		$newColumn->setPlatformOptions($column->getPlatformOptions());
		$newColumn->setCustomSchemaOptions($column->getPlatformOptions());
		return $newColumn;
	}

	/**
	 * Quote an index's name but changing the name requires recreating
	 * the index instance and copying over all properties.
	 *
	 * @param Index $index old index
	 * @return Index new index instance with new name
	 */
	protected function quoteIndex($index) {
		return new Index(
		//TODO migrate existing uppercase indexes, then $this->connection->quoteIdentifier($index->getName()),
			$index->getName(),
			array_map(function ($columnName) {
				return $this->connection->quoteIdentifier($columnName);
			}, $index->getColumns()),
			$index->isUnique(),
			$index->isPrimary(),
			$index->getFlags(),
			$index->getOptions()
		);
	}

	/**
	 * Quote an ForeignKeyConstraint's name but changing the name requires recreating
	 * the ForeignKeyConstraint instance and copying over all properties.
	 *
	 * @param ForeignKeyConstraint $fkc old fkc
	 * @return ForeignKeyConstraint new fkc instance with new name
	 */
	protected function quoteForeignKeyConstraint($fkc) {
		return new ForeignKeyConstraint(
			array_map(function ($columnName) {
				return $this->connection->quoteIdentifier($columnName);
			}, $fkc->getLocalColumns()),
			$this->connection->quoteIdentifier($fkc->getForeignTableName()),
			array_map(function ($columnName) {
				return $this->connection->quoteIdentifier($columnName);
			}, $fkc->getForeignColumns()),
			$fkc->getName(),
			$fkc->getOptions()
		);
	}

	/**
	 * @param Schema $targetSchema
	 * @param \Doctrine\DBAL\Connection $connection
	 * @return \Doctrine\DBAL\Schema\SchemaDiff
	 * @throws Exception
	 */
	protected function getDiff(Schema $targetSchema, \Doctrine\DBAL\Connection $connection) {
		$schemaDiff = parent::getDiff($targetSchema, $connection);

		// oracle forces us to quote the identifiers
		$schemaDiff->newTables = array_map(function (Table $table) {
			return new Table(
				$this->connection->quoteIdentifier($table->getName()),
				array_map(function (Column $column) {
					return $this->quoteColumn($column);
				}, $table->getColumns()),
				array_map(function (Index $index) {
					return $this->quoteIndex($index);
				}, $table->getIndexes()),
				[],
				array_map(function (ForeignKeyConstraint $fck) {
					return $this->quoteForeignKeyConstraint($fck);
				}, $table->getForeignKeys()),
				$table->getOptions()
			);
		}, $schemaDiff->newTables);

		$schemaDiff->removedTables = array_map(function (Table $table) {
			return new Table(
				$this->connection->quoteIdentifier($table->getName()),
				$table->getColumns(),
				$table->getIndexes(),
				[],
				$table->getForeignKeys(),
				$table->getOptions()
			);
		}, $schemaDiff->removedTables);

		foreach ($schemaDiff->changedTables as $tableDiff) {
			$tableDiff->name = $this->connection->quoteIdentifier($tableDiff->name);

			$tableDiff->addedColumns = array_map(function (Column $column) {
				return $this->quoteColumn($column);
			}, $tableDiff->addedColumns);

			foreach ($tableDiff->changedColumns as $column) {
				$column->oldColumnName = $this->connection->quoteIdentifier($column->oldColumnName);
				// auto increment is not relevant for oracle and can anyhow not be applied on change
				$column->changedProperties = array_diff($column->changedProperties, ['autoincrement', 'unsigned']);
			}
			// remove columns that no longer have changed (because autoincrement and unsigned are not supported)
			$tableDiff->changedColumns = array_filter($tableDiff->changedColumns, function (ColumnDiff $column) {
				return count($column->changedProperties) > 0;
			});

			$tableDiff->removedColumns = array_map(function (Column $column) {
				return $this->quoteColumn($column);
			}, $tableDiff->removedColumns);

			$tableDiff->renamedColumns = array_map(function (Column $column) {
				return $this->quoteColumn($column);
			}, $tableDiff->renamedColumns);

			$tableDiff->addedIndexes = array_map(function (Index $index) {
				return $this->quoteIndex($index);
			}, $tableDiff->addedIndexes);

			$tableDiff->changedIndexes = array_map(function (Index $index) {
				return $this->quoteIndex($index);
			}, $tableDiff->changedIndexes);

			$tableDiff->removedIndexes = array_map(function (Index $index) {
				return $this->quoteIndex($index);
			}, $tableDiff->removedIndexes);

			$tableDiff->renamedIndexes = array_map(function (Index $index) {
				return $this->quoteIndex($index);
			}, $tableDiff->renamedIndexes);

			$tableDiff->addedForeignKeys = array_map(function (ForeignKeyConstraint $fkc) {
				return $this->quoteForeignKeyConstraint($fkc);
			}, $tableDiff->addedForeignKeys);

			$tableDiff->changedForeignKeys = array_map(function (ForeignKeyConstraint $fkc) {
				return $this->quoteForeignKeyConstraint($fkc);
			}, $tableDiff->changedForeignKeys);

			$tableDiff->removedForeignKeys = array_map(function (ForeignKeyConstraint $fkc) {
				return $this->quoteForeignKeyConstraint($fkc);
			}, $tableDiff->removedForeignKeys);
		}

		return $schemaDiff;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	protected function generateTemporaryTableName($name) {
		return 'oc_' . uniqid();
	}

	/**
	 * @param $statement
	 * @return string
	 */
	protected function convertStatementToScript($statement) {
		if (substr($statement, -1) === ';') {
			return $statement . PHP_EOL . '/' . PHP_EOL;
		}
		$script = $statement . ';';
		$script .= PHP_EOL;
		$script .= PHP_EOL;
		return $script;
	}

	protected function getFilterExpression() {
		return '/^"' . preg_quote($this->config->getSystemValue('dbtableprefix', 'oc_')) . '/';
	}
}
