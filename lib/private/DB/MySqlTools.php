<?php
/**
 * @copyright Copyright (c) 2017, ownCloud GmbH
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

use OCP\IDBConnection;

/**
 * Various MySQL specific helper functions.
 */
class MySqlTools {

	/**
	 * @param IDBConnection $connection
	 * @return bool
	 */
	public function supports4ByteCharset(IDBConnection $connection) {
		$variables = ['innodb_file_per_table' => 'ON'];
		if (!$this->isMariaDBWithLargePrefix($connection)) {
			$variables['innodb_file_format'] = 'Barracuda';
			$variables['innodb_large_prefix'] = 'ON';
		}

		foreach ($variables as $var => $val) {
			$result = $connection->executeQuery("SHOW VARIABLES LIKE '$var'");
			$row = $result->fetch();
			$result->closeCursor();
			if ($row === false) {
				return false;
			}
			if (strcasecmp($row['Value'], $val) !== 0) {
				return false;
			}
		}
		return true;
	}

	protected function isMariaDBWithLargePrefix(IDBConnection $connection) {
		$result = $connection->executeQuery('SELECT VERSION()');
		$row = strtolower($result->fetchColumn());
		$result->closeCursor();

		if ($row === false) {
			return false;
		}

		return strpos($row, 'maria') && version_compare($row, '10.3', '>=') ||
			strpos($row, 'maria') === false && version_compare($row, '8.0', '>=');
	}
}
