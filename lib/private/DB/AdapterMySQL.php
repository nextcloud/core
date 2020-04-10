<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

class AdapterMySQL extends Adapter {

	/** @var string */
	protected $charset;

	/**
	 * @param string $tableName
	 */
	public function lockTable($tableName) {
		$this->conn->executeUpdate('LOCK TABLES `' .$tableName . '` WRITE');
	}

	public function unlockTable() {
		$this->conn->executeUpdate('UNLOCK TABLES');
	}

	public function fixupStatement($statement) {
		$statement = str_replace(' ILIKE ', ' COLLATE ' . $this->getCharset() . '_general_ci LIKE ', $statement);
		return $statement;
	}

	protected function getCharset() {
		if (!$this->charset) {
			$params = $this->conn->getParams();
			$this->charset = isset($params['charset']) ? $params['charset'] : 'utf8';
		}

		return $this->charset;
	}
}
