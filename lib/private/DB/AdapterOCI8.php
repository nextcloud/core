<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

class AdapterOCI8 extends Adapter {
	public function lastInsertId($table) {
		if (is_null($table)) {
			throw new \InvalidArgumentException('Oracle requires a table name to be passed into lastInsertId()');
		}
		if ($table !== null) {
			$suffix = '_SEQ';
			$table = '"' . $table . $suffix . '"';
		}
		return $this->conn->realLastInsertId($table);
	}

	const UNIX_TIMESTAMP_REPLACEMENT = "(cast(sys_extract_utc(systimestamp) as date) - date'1970-01-01') * 86400";

	public function fixupStatement($statement) {
		$statement = preg_replace('/`(\w+)` ILIKE \?/', 'REGEXP_LIKE(`$1`, \'^\' || REPLACE(?, \'%\', \'.*\') || \'$\', \'i\')', $statement);
		$statement = str_replace('`', '"', $statement);
		$statement = str_ireplace('NOW()', 'CURRENT_TIMESTAMP', $statement);
		$statement = str_ireplace('UNIX_TIMESTAMP()', self::UNIX_TIMESTAMP_REPLACEMENT, $statement);
		return $statement;
	}
}
