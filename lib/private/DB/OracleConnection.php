<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

class OracleConnection extends Connection {
	/**
	 * Quote the keys of the array
	 */
	private function quoteKeys(array $data) {
		$return = [];
		$c = $this->getDatabasePlatform()->getIdentifierQuoteCharacter();
		foreach ($data as $key => $value) {
			if ($key[0] !== $c) {
				$return[$this->quoteIdentifier($key)] = $value;
			} else {
				$return[$key] = $value;
			}
		}
		return $return;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert($table, array $data, array $types = []) {
		if ($table[0] !== $this->getDatabasePlatform()->getIdentifierQuoteCharacter()) {
			$table = $this->quoteIdentifier($table);
		}
		$data = $this->quoteKeys($data);
		return parent::insert($table, $data, $types);
	}

	/**
	 * {@inheritDoc}
	 */
	public function update($table, array $data, array $criteria, array $types = []) {
		if ($table[0] !== $this->getDatabasePlatform()->getIdentifierQuoteCharacter()) {
			$table = $this->quoteIdentifier($table);
		}
		$data = $this->quoteKeys($data);
		$criteria = $this->quoteKeys($criteria);
		return parent::update($table, $data, $criteria, $types);
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete($table, array $criteria, array $types = []) {
		if ($table[0] !== $this->getDatabasePlatform()->getIdentifierQuoteCharacter()) {
			$table = $this->quoteIdentifier($table);
		}
		$criteria = $this->quoteKeys($criteria);
		return parent::delete($table, $criteria);
	}

	/**
	 * Drop a table from the database if it exists
	 *
	 * @param string $table table name without the prefix
	 */
	public function dropTable($table) {
		$table = $this->tablePrefix . trim($table);
		$table = $this->quoteIdentifier($table);
		$schema = $this->getSchemaManager();
		if ($schema->tablesExist([$table])) {
			$schema->dropTable($table);
		}
	}

	/**
	 * Check if a table exists
	 *
	 * @param string $table table name without the prefix
	 * @return bool
	 */
	public function tableExists($table) {
		$table = $this->tablePrefix . trim($table);
		$table = $this->quoteIdentifier($table);
		$schema = $this->getSchemaManager();
		return $schema->tablesExist([$table]);
	}
}
