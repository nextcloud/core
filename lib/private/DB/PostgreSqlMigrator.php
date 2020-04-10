<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OC\DB;

use Doctrine\DBAL\Schema\Schema;

class PostgreSqlMigrator extends Migrator {
	/**
	 * @param Schema $targetSchema
	 * @param \Doctrine\DBAL\Connection $connection
	 * @return \Doctrine\DBAL\Schema\SchemaDiff
	 */
	protected function getDiff(Schema $targetSchema, \Doctrine\DBAL\Connection $connection) {
		$schemaDiff = parent::getDiff($targetSchema, $connection);

		foreach ($schemaDiff->changedTables as $tableDiff) {
			// fix default value in brackets - pg 9.4 is returning a negative default value in ()
			// see https://github.com/doctrine/dbal/issues/2427
			foreach ($tableDiff->changedColumns as $column) {
				$column->changedProperties = array_filter($column->changedProperties, function ($changedProperties) use ($column) {
					if ($changedProperties !== 'default') {
						return true;
					}
					$fromDefault = $column->fromColumn->getDefault();
					$toDefault = $column->column->getDefault();
					$fromDefault = trim($fromDefault, "()");

					// by intention usage of !=
					return $fromDefault != $toDefault;
				});
			}
		}

		return $schemaDiff;
	}
}
