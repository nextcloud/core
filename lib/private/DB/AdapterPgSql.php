<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Ole Ostergaard <ole.c.ostergaard@gmail.com>
 * @author Ole Ostergaard <ole.ostergaard@knime.com>
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

class AdapterPgSql extends Adapter {
	protected $compatModePre9_5 = null;

	public function lastInsertId($table) {
		$result = $this->conn->executeQuery('SELECT lastval()');
		$val = $result->fetchOne();
		$result->free();
		return (int)$val;
	}

	public const UNIX_TIMESTAMP_REPLACEMENT = 'cast(extract(epoch from current_timestamp) as integer)';
	public function fixupStatement($statement) {
		$statement = str_replace('`', '"', $statement);
		$statement = str_ireplace('UNIX_TIMESTAMP()', self::UNIX_TIMESTAMP_REPLACEMENT, $statement);
		return $statement;
	}

	public function insertIgnoreConflict(string $table,array $values) : int {
		if ($this->isPre9_5CompatMode() === true) {
			return parent::insertIgnoreConflict($table, $values);
		}

		// "upsert" is only available since PgSQL 9.5, but the generic way
		// would leave error logs in the DB.
		$builder = $this->conn->getQueryBuilder();
		$builder->insert($table);
		foreach ($values as $key => $value) {
			$builder->setValue($key, $builder->createNamedParameter($value));
		}
		$queryString = $builder->getSQL() . ' ON CONFLICT DO NOTHING';
		return $this->conn->executeUpdate($queryString, $builder->getParameters(), $builder->getParameterTypes());
	}

	protected function isPre9_5CompatMode(): bool {
		if ($this->compatModePre9_5 !== null) {
			return $this->compatModePre9_5;
		}

		$result = $this->conn->executeQuery('SHOW SERVER_VERSION');
		$version = $result->fetchOne();
		$result->free();
		$this->compatModePre9_5 = version_compare($version, '9.5', '<');

		return $this->compatModePre9_5;
	}
}
